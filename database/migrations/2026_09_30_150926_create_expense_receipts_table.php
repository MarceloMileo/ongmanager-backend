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
        Schema::create('expense_receipts', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('file_reference');
            $table->integer('document_value_cents');
            $table->string('document_value_currency', 3);
            $table->string('document_number')->nullable();
            $table->string('issuer_identifier')->nullable();
            $table->timestampTz('issued_at')->nullable();
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
        Schema::dropIfExists('expense_receipts');
    }
};
