<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('exec_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('title')->default('Executive Report');
            $table->unsignedBigInteger('generated_by')->nullable();
            $table->json('payload');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('exec_reports');
    }
};
