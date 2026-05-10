<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('medications', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->foreignUuid('service_id')->constrained('services')->cascadeOnDelete();
            $table->string('rxnorm_code')->nullable();
            $table->string('ndc_code')->nullable();
            $table->string('generic_name');
            $table->string('brand_name')->nullable();
            $table->string('dosage_form');
            $table->string('strength')->nullable();
            $table->string('controlled_schedule')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique('service_id');
            $table->index('rxnorm_code');
            $table->index('controlled_schedule');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('medications');
    }
};
