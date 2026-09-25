<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('operating_instructions', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')->references('id')->on('documents')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('operating_instructions', function (Blueprint $table) {
            $table->dropForeign(['document_id']);
            $table->foreign('document_id')->references('id')->on('persons')->nullOnDelete();
        });
    }
};
