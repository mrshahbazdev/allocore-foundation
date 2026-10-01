<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * Sweep: every model using Stancl\Tenancy's BelongsToTenant must never leak
 * rows across tenants — a missed scope would expose data of another Mandant.
 * New models get covered automatically as soon as they use the trait.
 */
class TenantIsolationTest extends TestCase
{
    use RefreshDatabase;

    public function test_tenant_scoped_models_never_leak_rows_across_tenants(): void
    {
        $models = $this->tenantScopedModels();
        $this->assertNotEmpty($models, 'no BelongsToTenant models discovered — sweep is blind');

        $tenantA = Tenant::create(['name' => 'Isolation A']);
        $tenantB = Tenant::create(['name' => 'Isolation B']);

        tenancy()->initialize($tenantA);
        $seeded = [];
        foreach ($models as $class) {
            $row = $this->seedRow($class, $tenantA);
            $this->assertNotNull($class::find($row->getKey()), "{$class} seed failed");
            $seeded[$class] = $row;
        }

        tenancy()->initialize($tenantB);
        foreach ($models as $class) {
            $this->assertSame(0, $class::count(), "{$class} leaks rows into foreign tenant");
            $this->assertNull($class::find($seeded[$class]->getKey()), "{$class} direct find() leaks across tenants");
        }

        tenancy()->end();
        foreach ($models as $class) {
            $this->assertNotNull(
                $class::withoutGlobalScopes()->find($seeded[$class]->getKey()),
                "{$class} row missing without scope"
            );
        }
    }

    /** @return list<class-string<Model>> */
    protected function tenantScopedModels(): array
    {
        $classes = [];
        foreach (File::glob(base_path('Modules/*/app/Models/*.php')) as $file) {
            $source = file_get_contents($file);
            if (! str_contains($source, 'BelongsToTenant')) {
                continue;
            }
            if (! preg_match('/namespace ([\w\\\\]+);/m', $source, $ns) || ! preg_match('/class (\w+)/m', $source, $cl)) {
                continue;
            }
            $class = $ns[1].'\\'.$cl[1];
            if (is_subclass_of($class, Model::class)) {
                $classes[] = $class;
            }
        }

        return $classes;
    }

    /** @var array<string, int|string> seeded ids per table for FK reuse */
    protected array $fkSeeds = [];

    /** Build a minimal row: dummy-fill required cols, seed referenced parents for FKs. */
    protected function seedRow(string $class, Tenant $tenant): Model
    {
        /** @var Model $model */
        $model = new $class;
        $attrs = [];

        foreach ($this->foreignKeys($model->getTable()) as $colName => $ref) {
            if (
                ! isset($this->fkSeeds[$ref['table']])
                || ! DB::table($ref['table'])->where($ref['column'], $this->fkSeeds[$ref['table']])->exists()
            ) {
                $this->fkSeeds[$ref['table']] = $this->seedReferenced($ref['table'], $tenant);
            }
            $attrs[$colName] = $this->fkSeeds[$ref['table']];
        }

        foreach (Schema::getColumns($model->getTable()) as $col) {
            $name = $col['name'];
            if ($name === 'tenant_id' || $col['auto_increment'] ?? false) {
                continue;
            }
            if (in_array($name, ['id', 'created_at', 'updated_at', 'deleted_at'], true)) {
                continue;
            }
            $required = ! ($col['nullable'] ?? true) && ! isset($col['default']);
            if (! $required) {
                continue;
            }
            $attrs[$name] = $this->dummyValue($col['type_name'] ?? $col['type'] ?? 'string', $name);
        }

        // uuid primary keys
        if ($model->getKeyType() === 'string' && $model->incrementing === false) {
            $attrs[$model->getKeyName()] = (string) Str::uuid();
        }

        $attrs['tenant_id'] = $tenant->id;

        $save = function () use ($class, $model, &$attrs) {
            return $class::withoutEvents(function () use ($model, $attrs) {
                $model->forceFill($attrs);
                $model->save();

                return $model;
            });
        };

        try {
            return $save();
        } catch (QueryException $e) {
            // A cached FK parent can still vanish before insert — re-seed all
            // referenced parents and retry once.
            foreach ($this->foreignKeys($model->getTable()) as $colName => $ref) {
                $this->fkSeeds[$ref['table']] = $this->seedReferenced($ref['table'], $tenant);
                $attrs[$colName] = $this->fkSeeds[$ref['table']];
            }

            return $save();
        }
    }

    /** @return array<string, array{table: string, column: string}> column => referenced */
    protected function foreignKeys(string $table): array
    {
        $rows = DB::table('information_schema.KEY_COLUMN_USAGE')
            ->where('TABLE_SCHEMA', DB::getDatabaseName())
            ->where('TABLE_NAME', $table)
            ->whereNotNull('REFERENCED_TABLE_NAME')
            ->get(['COLUMN_NAME', 'REFERENCED_TABLE_NAME', 'REFERENCED_COLUMN_NAME']);

        $map = [];
        foreach ($rows as $r) {
            $map[$r->COLUMN_NAME] = ['table' => $r->REFERENCED_TABLE_NAME, 'column' => $r->REFERENCED_COLUMN_NAME];
        }

        return $map;
    }

    /** Seed a row in a referenced table — via its model if tenant-scoped, else raw insert. */
    protected function seedReferenced(string $table, Tenant $tenant): int|string
    {
        $class = collect($this->tenantScopedModels())->first(
            fn ($c) => (new $c)->getTable() === $table
        );
        if ($class) {
            return $this->seedRow($class, $tenant)->getKey();
        }
        if ($table === 'users') {
            return User::factory()->create()->id;
        }
        if ($table === 'tenants') {
            return Tenant::create(['name' => 'Ref '.Str::random(6)])->id;
        }

        // last resort: raw insert filling required columns
        $attrs = [];
        foreach (Schema::getColumns($table) as $col) {
            if (($col['auto_increment'] ?? false) || ($col['nullable'] ?? true) || isset($col['default'])) {
                continue;
            }
            if (in_array($col['name'], ['id', 'created_at', 'updated_at'], true)) {
                continue;
            }
            $attrs[$col['name']] = $col['name'] === 'tenant_id'
                ? $tenant->id
                : $this->dummyValue($col['type_name'] ?? 'string', $col['name']);
        }
        if (in_array('id', array_column(Schema::getColumns($table), 'name'))) {
            $idType = collect(Schema::getColumns($table))->firstWhere('name', 'id')['type_name'] ?? 'int';
            if (str_contains($idType, 'int')) {
                return DB::table($table)->insertGetId($attrs);
            }
            $id = (string) Str::uuid();
            DB::table($table)->insert($attrs + ['id' => $id]);

            return $id;
        }

        throw new \RuntimeException("cannot seed referenced table {$table}");
    }

    protected function dummyValue(string $type, string $name): mixed
    {
        return match (true) {
            str_contains($type, 'int') => 1,
            in_array($type, ['decimal', 'float', 'double', 'real'], true) => 1.5,
            in_array($type, ['date'], true) => '2026-01-01',
            in_array($type, ['datetime', 'timestamp'], true) => '2026-01-01 00:00:00',
            in_array($type, ['json'], true) => '{}',
            in_array($type, ['bool', 'boolean', 'tinyint'], true) => 1,
            str_ends_with($name, '_id') => 'dummy-'.Str::random(8),
            default => 'x',
        };
    }
}
