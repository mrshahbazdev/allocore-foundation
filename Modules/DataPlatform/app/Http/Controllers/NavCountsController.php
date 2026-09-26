<?php

namespace Modules\DataPlatform\Http\Controllers;

use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

class NavCountsController extends Controller
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
        'audits' => 'audits',
        'audit-findings' => 'audit_findings',
    ];

    private const DUE_KEYS = ['due_at', 'deadline', 'deadline_at', 'ends_on', 'ends_at', 'due_date', 'end_date', 'next_due_at', 'review_at', 'scheduled_at'];

    private const OPEN = ['open', 'pending', 'in_progress', 'running', 'queued', 'scheduled', 'planned', 'active', 'submitted', 'shortlisted', 'draft', 'on_hold'];

    public function index()
    {
        $today = now()->toDateString();
        $tid = tenancy()->initialized ? tenant()->getTenantKey() : null;
        $out = [];

        foreach (self::TABLES as $key => $table) {
            if (! Schema::hasTable($table)) {
                continue;
            }
            $cols = Schema::getColumnListing($table);
            $due = collect(self::DUE_KEYS)->first(fn ($k) => in_array($k, $cols, true));
            if (! $due) {
                continue;
            }
            $base = fn () => DB::table($table)
                ->when($tid, fn ($q) => $q->where('tenant_id', $tid))
                ->when(in_array('status', $cols, true), fn ($q) => $q->where(
                    fn ($w) => $w->whereIn('status', self::OPEN)->orWhereNull('status')
                ));

            $out[$key] = [
                $base()->whereDate($due, '<', $today)->count(),
                $base()->whereDate($due, $today)->count(),
            ];
        }

        return $out;
    }
}
