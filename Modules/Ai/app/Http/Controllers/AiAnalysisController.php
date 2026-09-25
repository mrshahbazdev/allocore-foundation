<?php

namespace Modules\Ai\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Modules\Ai\Models\AiAnalysis;
use Modules\Ai\Support\AiService;

class AiAnalysisController extends Controller
{
    public function index(Request $request)
    {
        return AiAnalysis::query()->latest('id')->paginate(min($request->integer('per_page', 50), 200), ['id', 'kind', 'provider', 'status', 'summary', 'created_at']);
    }

    public function store(AiService $service)
    {
        $result = $service->analyze();

        $analysis = AiAnalysis::create([
            'tenant_id' => tenant()->getTenantKey(),
            'kind' => 'analysis',
            'provider' => $result['provider'],
            'status' => 'completed',
            'summary' => $result['summary'],
            'findings' => $result['findings'],
        ]);

        return response()->json($analysis, 201);
    }

    public function show(AiAnalysis $ai_analysis)
    {
        return $ai_analysis;
    }
}
