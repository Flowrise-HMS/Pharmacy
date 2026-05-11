<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('drugs', function (Blueprint $table) {
            $table->uuid('id')->primary();
            $table->string('source_provider')->default('local');
            $table->string('source_identifier')->nullable();
            $table->string('rxnorm_code')->nullable();
            $table->string('ndc_code')->nullable();
            $table->string('generic_name');
            $table->string('display_name');
            $table->string('brand_name')->nullable();
            $table->string('strength_text')->nullable();
            $table->string('dosage_form_text')->nullable();
            $table->json('synonyms')->nullable();
            $table->json('raw_payload')->nullable();
            $table->unsignedInteger('search_rank')->default(0);
            $table->unsignedInteger('times_prescribed')->default(0);
            $table->unsignedInteger('times_stocked')->default(0);
            $table->boolean('is_cached_external')->default(false);
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['source_provider', 'source_identifier']);
            $table->index('rxnorm_code');
            $table->index('ndc_code');
            $table->index('generic_name');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('drugs');
    }
};
