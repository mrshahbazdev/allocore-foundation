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
        'data-objects' => 'data_objects',
        'graph-entities' => 'graph_entities',
        'graph-edges' => 'graph_edges',
        'ai-analyses' => 'ai_analyses',
        'events' => 'stored_events',
        'audits' => 'audits',
        'audit-findings' => 'audit_findings',
    ];

    private const TENANT_COLS = ['stored_events' => 'meta_data->tenant_id'];

    private const SEARCH_COLS = ['name', 'title', 'subject', 'question', 'reference', 'email', 'relation', 'event_type', 'event_class', 'summary'];

    private const LABEL_COLS = ['name', 'title', 'subject', 'question', 'reference', 'email', 'relation', 'event_type', 'event_class', 'summary'];

    public function index()
    {
        $q = trim((string) request('q', ''));
        if (strlen($q) < 2) {
            return [];
        }
        $tid = tenancy()->initialized ? tenant()->getTenantKey() : null;
        $like = '%'.str_replace(['%', '_'], ['\\%', '\\_'], $q).'%';
        $max = min(max((int) request('limit', 20), 1), 50);
        $sections = collect(explode(',', (string) request('sections', '')))
            ->map(fn ($s) => trim($s))->filter()->all();
        $tables = $sections ? array_intersect_key(self::TABLES, array_flip($sections)) : self::TABLES;
        $out = [];

        foreach ($tables as $key => $table) {
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
                ->when($tid, fn ($w) => $w->where(self::TENANT_COLS[$table] ?? 'tenant_id', $tid))
                ->where(function ($w) use ($searchCols, $like) {
                    foreach ($searchCols as $c) {
                        $w->orWhere($c, 'like', $like);
                    }
                })
                ->limit(5)
                ->get(['id', $label]);

            foreach ($hits as $h) {
                $out[] = ['section' => $key, 'id' => $h->id, 'label' => $h->{$label}];
                if (count($out) >= $max) {
                    return $out;
                }
            }
        }

        return $out;
    }
}
