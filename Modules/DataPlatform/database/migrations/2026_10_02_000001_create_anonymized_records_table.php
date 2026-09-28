<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('anonymized_records', function (Blueprint $table) {
            $table->id();
            $table->string('tenant_id')->index();
            $table->string('source_type');
            $table->string('pseudonym', 64);
            $table->json('payload');
            $table->timestamp('synced_at');
            $table->timestamps();

            $table->unique(['tenant_id', 'source_type', 'pseudonym']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('anonymized_records');
    }
};
