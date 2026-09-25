<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('graph_entities', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('type')->index(); // company, person, document, machine, project, investment, other
            $table->string('name');
            $table->nullableMorphs('subject'); // optional link to a real domain row
            $table->json('properties')->nullable();
            $table->timestamps();
        });

        Schema::create('graph_edges', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->foreignId('from_entity_id')->constrained('graph_entities')->cascadeOnDelete();
            $table->foreignId('to_entity_id')->constrained('graph_entities')->cascadeOnDelete();
            $table->string('relation')->index(); // works_at, owns, audits, supplies, invested_in, ...
            $table->json('properties')->nullable();
            $table->timestamps();
            $table->unique(['from_entity_id', 'to_entity_id', 'relation'], 'graph_edges_unique');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('graph_edges');
        Schema::dropIfExists('graph_entities');
    }
};
