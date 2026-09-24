<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('production_orders', function (Blueprint $table) {
            $table->id();
            $table->uuid('tenant_id')->index();
            $table->string('order_no'); // Auftragsnummer (extern/zentral)
            $table->string('product');  // z.B. "Krone Zirkon", "Modellguss"
            $table->unsignedInteger('quantity')->default(1);
            $table->foreignId('machine_id')->nullable()->constrained('machines')->nullOnDelete();
            $table->foreignId('assigned_to')->nullable()->constrained('persons')->nullOnDelete();
            $table->string('status')->default('queued'); // queued|running|done|rejected|cancelled
            $table->unsignedInteger('scrap_qty')->default(0); // Ausschuss
            $table->date('due_at')->nullable();
            $table->timestamp('started_at')->nullable();
            $table->timestamp('finished_at')->nullable();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('production_orders');
    }
};
