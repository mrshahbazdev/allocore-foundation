<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('projects', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('strategy_id')->nullable()->constrained('strategies')->nullOnDelete();
            $table->string('name');
            $table->text('description')->nullable();
            $table->string('status')->default('planned'); // planned|active|on_hold|done|cancelled
            $table->unsignedTinyInteger('progress')->default(0); // 0-100
            $table->unsignedBigInteger('owner_id')->nullable();
            $table->foreign('owner_id')->references('id')->on('users')->nullOnDelete();
            $table->date('starts_at')->nullable();
            $table->date('ends_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('projects');
    }
};
