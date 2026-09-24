<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('investments', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->foreignId('portfolio_id')->constrained('portfolios')->cascadeOnDelete();
            $table->string('name');
            $table->string('asset_class')->default('equity'); // equity|bond|fund|real_estate|participation|cash|other
            $table->decimal('quantity', 20, 6)->default(1);
            $table->decimal('cost_basis', 20, 2);      // Einstandswert
            $table->decimal('current_value', 20, 2)->nullable(); // letzter Bewertungsstand
            $table->date('valued_at')->nullable();
            $table->date('acquired_at')->nullable();
            $table->date('disposed_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('investments');
    }
};
