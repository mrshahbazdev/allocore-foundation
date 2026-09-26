<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('instructions', function (Blueprint $table) {
            $table->timestamp('renewal_reminded_at')->nullable()->index()->after('reminded_at');
        });
    }

    public function down(): void
    {
        Schema::table('instructions', function (Blueprint $table) {
            $table->dropColumn('renewal_reminded_at');
        });
    }
};
