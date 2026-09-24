<?php

declare(strict_types=1);

namespace Tests\Feature;

use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ExpertNetworkTest extends TestCase
{
    use RefreshDatabase;

    protected function acting(?User $user = null): User
    {
        $user ??= User::factory()->create();
        Sanctum::actingAs($user);

        return $user;
    }

    public function test_profile_question_answer_flow(): void
    {
        $tenant = Tenant::create(['name' => 'Net GmbH']);
        $user = $this->acting();

        $person = $this->postJson('/api/v1/persons', [
            'first_name' => 'Max', 'last_name' => 'Expert', 'type' => 'consultant',
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json('id');

        $this->postJson('/api/v1/expert-profiles', [
            'person_id' => $person,
            'headline' => 'Arbeitssicherheit',
            'skills' => ['siFaKo', 'iso45001'],
        ], ['X-Tenant' => $tenant->id])->assertCreated();

        $q = $this->postJson('/api/v1/questions', [
            'title' => 'Brauchen wir DGUV V3?',
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json('id');

        $a = $this->postJson("/api/v1/questions/{$q}/answers", [
            'body' => 'Ja, für elektrische Betriebsmittel.',
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json('id');

        $this->postJson("/api/v1/answers/{$a}/accept", [], ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonPath('is_accepted', true);

        $this->getJson("/api/v1/questions/{$q}", ['X-Tenant' => $tenant->id])
            ->assertOk()->assertJsonPath('status', 'answered');
    }

    public function test_tender_apply_and_award(): void
    {
        $tenant = Tenant::create(['name' => 'Tend GmbH']);
        $user = $this->acting();

        $person = $this->postJson('/api/v1/persons', [
            'first_name' => 'Eva', 'last_name' => 'Pro',
        ], ['X-Tenant' => $tenant->id])->json('id');

        $profile = $this->postJson('/api/v1/expert-profiles', [
            'person_id' => $person, 'skills' => ['audit'],
        ], ['X-Tenant' => $tenant->id])->json('id');

        $tender = $this->postJson('/api/v1/tenders', [
            'title' => 'ISO-Audit 2026', 'required_skills' => ['audit'], 'budget' => 5000,
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json('id');

        $app = $this->postJson("/api/v1/tenders/{$tender}/applications", [
            'expert_profile_id' => $profile, 'price' => 4500,
        ], ['X-Tenant' => $tenant->id])->assertCreated()->json('id');

        $this->patchJson("/api/v1/tender-applications/{$app}", [
            'status' => 'awarded',
        ], ['X-Tenant' => $tenant->id])->assertOk()->assertJsonPath('status', 'awarded');

        $this->getJson("/api/v1/tenders/{$tender}", ['X-Tenant' => $tenant->id])
            ->assertJsonPath('status', 'awarded');
    }

    public function test_match_endpoint_scores_skill_overlap(): void
    {
        $tenant = Tenant::create(['name' => 'Match GmbH']);
        $this->acting();

        $p1 = $this->postJson('/api/v1/persons', ['first_name' => 'A', 'last_name' => 'B'], ['X-Tenant' => $tenant->id])->json('id');
        $p2 = $this->postJson('/api/v1/persons', ['first_name' => 'C', 'last_name' => 'D'], ['X-Tenant' => $tenant->id])->json('id');

        $this->postJson('/api/v1/expert-profiles', ['person_id' => $p1, 'skills' => ['audit', 'iso45001']], ['X-Tenant' => $tenant->id]);
        $this->postJson('/api/v1/expert-profiles', ['person_id' => $p2, 'skills' => ['audit']], ['X-Tenant' => $tenant->id]);

        $res = $this->getJson('/api/v1/expert-profiles/match?skills[]=audit&skills[]=iso45001', ['X-Tenant' => $tenant->id])
            ->assertOk()->json();

        $this->assertSame(2, $res[0]['match_score']);
        $this->assertSame(1, $res[1]['match_score']);
    }

    public function test_expert_network_is_tenant_scoped_and_authed(): void
    {
        $tenantA = Tenant::create(['name' => 'X GmbH']);
        $tenantB = Tenant::create(['name' => 'Y GmbH']);
        $this->acting();

        $person = $this->postJson('/api/v1/persons', ['first_name' => 'Z', 'last_name' => 'W'], ['X-Tenant' => $tenantA->id])->json('id');
        $this->postJson('/api/v1/expert-profiles', ['person_id' => $person], ['X-Tenant' => $tenantA->id]);

        $this->getJson('/api/v1/expert-profiles', ['X-Tenant' => $tenantB->id])
            ->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_expert_network_requires_auth(): void
    {
        $tenant = Tenant::create(['name' => 'Auth3 GmbH']);

        $this->getJson('/api/v1/tenders', ['X-Tenant' => $tenant->id])->assertUnauthorized();
    }
}
