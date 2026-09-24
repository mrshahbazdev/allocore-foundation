<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('participations', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('company_id')->nullable()->constrained('companies')->nullOnDelete();
            $table->string('name'); // Beteiligungsgesellschaft
            $table->string('legal_form')->nullable(); // GmbH, AG, ...
            $table->decimal('stake_pct', 5, 2);      // Anteil in %
            $table->decimal('invested_amount', 20, 2)->default(0);
            $table->decimal('current_valuation', 20, 2)->nullable();
            $table->decimal('capital_need', 20, 2)->default(0); // Kapitalbedarf
            $table->string('status')->default('active'); // active|exited|candidate
            $table->date('acquired_at')->nullable();
            $table->date('exited_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('participations');
    }
};
