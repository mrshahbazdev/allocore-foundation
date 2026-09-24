<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('inspections', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('title');
            $table->string('type')->nullable();
            $table->string('subject')->nullable();
            $table->foreignId('person_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->foreignId('responsible_id')->nullable()->constrained('users')->nullOnDelete();
            $table->string('status')->default('scheduled')->index();
            $table->string('result')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('scheduled_at')->nullable()->index();
            $table->timestamp('completed_at')->nullable();
            $table->timestamp('reminded_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('inspections');
    }
};
