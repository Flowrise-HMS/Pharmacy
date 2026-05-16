<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('prescription_details', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('request_item_id')->constrained()->cascadeOnDelete();
            $table->string('dosage')->nullable();
            $table->string('frequency')->nullable();
            $table->string('route')->nullable();
            $table->unsignedInteger('duration_days')->nullable();
            $table->text('instructions')->nullable();
            $table->boolean('prn')->default(false);
            $table->string('indication')->nullable();
            $table->unsignedInteger('refills')->default(0);
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('prescription_details');
    }
};
