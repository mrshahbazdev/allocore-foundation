<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tender_applications', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->foreignId('tender_id')->constrained()->cascadeOnDelete();
            $table->foreignId('expert_profile_id')->constrained()->cascadeOnDelete();
            $table->text('proposal')->nullable();
            $table->decimal('price', 12, 2)->nullable();
            $table->string('status')->default('submitted')->index();
            $table->timestamps();
            $table->unique(['tender_id', 'expert_profile_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('tender_applications');
    }
};
