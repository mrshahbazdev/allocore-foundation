<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('operating_instructions', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id', 36)->index();
            $table->string('title');
            $table->text('content')->nullable();
            $table->unsignedInteger('version')->default(1);
            $table->foreignId('document_id')->nullable()->constrained('persons')->nullOnDelete();
            $table->date('valid_from')->nullable();
            $table->string('status')->default('draft')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('operating_instructions');
    }
};
