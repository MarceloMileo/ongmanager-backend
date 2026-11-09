<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('expense_lines', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->uuid('project_id');
            $table->integer('amount_cents');
            $table->string('amount_currency', 3);
            $table->string('description')->nullable();
            $table->string('exchange_rate')->nullable();
            $table->string('exchange_rate_source_currency', 3)->nullable();
            $table->string('exchange_rate_target_currency', 3)->nullable();
            $table->string('distribution_type', 20);
            $table->timestampTz('exchange_rate_date')->nullable();
            $table->timestamps();

            $table->foreignUuid('expense_id')
                ->constrained('expenses')
                ->cascadeOnDelete();
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('expense_lines');
    }
};
