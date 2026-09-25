<?php

namespace Modules\Ai\Contracts;

interface AnalysisProvider
{
    public function name(): string;

    /**
     * @param  array  $context  platform context snapshot (counts, metrics, events)
     * @return array ['summary' => string, 'findings' => array]
     */
    public function analyze(array $context): array;
}
