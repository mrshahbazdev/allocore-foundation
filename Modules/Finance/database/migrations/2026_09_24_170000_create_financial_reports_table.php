<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('financial_reports', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('company_id')->constrained('companies')->cascadeOnDelete();
            $table->char('period', 7); // YYYY-MM
            $table->decimal('revenue', 20, 2)->default(0);
            $table->decimal('cashflow', 20, 2)->default(0);
            $table->decimal('ebitda', 20, 2)->default(0);
            $table->decimal('liquidity', 20, 2)->default(0);
            $table->timestamps();
            $table->unique(['tenant_id', 'company_id', 'period']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('financial_reports');
    }
};
