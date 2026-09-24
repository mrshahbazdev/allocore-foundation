<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('risk_assessments', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('title');
            $table->string('area')->nullable();
            $table->text('hazard')->nullable();
            $table->string('risk_level')->default('medium')->index();
            $table->text('measures')->nullable();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->string('status')->default('open')->index();
            $table->timestamp('review_at')->nullable()->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('risk_assessments');
    }
};
