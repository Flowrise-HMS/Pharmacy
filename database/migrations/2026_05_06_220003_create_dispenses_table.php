<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('dispenses', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_item_id')->constrained('request_items')->cascadeOnDelete();
            $table->foreignUuid('medication_id')->constrained('medications')->cascadeOnDelete();
            $table->foreignId('dispensed_by')->nullable()->constrained('users')->nullOnDelete();
            $table->unsignedInteger('quantity');
            $table->string('batch_number')->nullable();
            $table->date('expiry_date')->nullable();
            $table->text('notes')->nullable();
            $table->timestamp('dispensed_at')->nullable();
            $table->timestamps();

            $table->index('request_item_id');
            $table->index('medication_id');
            $table->index('dispensed_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('dispenses');
    }
};
