<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class SearchController extends Controller
{
    private const TABLES = [
        'companies' => 'companies',
        'persons' => 'persons',
        'documents' => 'documents',
        'tasks' => 'tasks',
        'instructions' => 'instructions',
        'inspections' => 'inspections',
        'deadlines' => 'deadlines',
        'risk-assessments' => 'risk_assessments',
        'operating-instructions' => 'operating_instructions',
        'expert-profiles' => 'expert_profiles',
        'questions' => 'questions',
        'tenders' => 'tenders',
        'strategies' => 'strategies',
        'projects' => 'projects',
        'measures' => 'measures',
        'portfolios' => 'portfolios',
        'investments' => 'investments',
        'participations' => 'participations',
        'machines' => 'machines',
        'production-orders' => 'production_orders',
        'leave-requests' => 'leave_requests',
        'financial-reports' => 'financial_reports',
    ];

    private const SEARCH_COLS = ['name', 'title', 'subject', 'question', 'reference', 'email'];

    private const LABEL_COLS = ['name', 'title', 'subject', 'question', 'reference', 'email'];

    public function index()
    {
        $q = trim((string) request('q', ''));
        if (strlen($q) < 2) {
            return [];
        }
        $tid = tenancy()->initialized ? tenant()->getTenantKey() : null;
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
        $out = [];

        foreach (self::TABLES as $key => $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $cols = Schema::getColumnListing($table);
            $searchCols = array_values(array_intersect(self::SEARCH_COLS, $cols));
            if (! $searchCols) {
                continue;
            }
            $label = collect(self::LABEL_COLS)->first(fn ($c) => in_array($c, $cols, true)) ?: $searchCols[0];

            $hits = DB::table($table)
                ->when($tid, fn ($w) => $w->where('tenant_id', $tid))
                ->where(function ($w) use ($searchCols, $like) {
                    foreach ($searchCols as $c) {
                        $w->orWhere($c, 'like', $like);
                    }
                })
                ->limit(5)
                ->get(['id', $label]);

            foreach ($hits as $h) {
                $out[] = ['section' => $key, 'id' => $h->id, 'label' => $h->{$label}];
                if (count($out) >= 20) {
                    return $out;
                }
            }
        }

        return $out;
    }
}
