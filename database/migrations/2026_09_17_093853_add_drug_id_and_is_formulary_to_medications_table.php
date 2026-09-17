<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * A Drug (reference catalog row) may materialize into at most one Medication
 * (formulary/stock row). Without an explicit link the only dedupe was a fuzzy
 * rxnorm/ndc match, and prescribing created a fresh Medication every time.
 *
 * is_formulary=false marks rows that a clinician put into the catalog by
 * prescribing a reference drug; Pharmacy has not yet reviewed or priced them.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->foreignUuid('drug_id')
                ->nullable()
                ->after('service_id')
                ->constrained('drugs')
                ->nullOnDelete();
            $table->boolean('is_formulary')->default(true)->after('is_active');

            $table->unique('drug_id');
            $table->index('ndc_code');
            $table->index('is_formulary');
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->dropUnique(['drug_id']);
            $table->dropIndex(['ndc_code']);
            $table->dropIndex(['is_formulary']);
            $table->dropConstrainedForeignId('drug_id');
            $table->dropColumn('is_formulary');
        });
    }
};
