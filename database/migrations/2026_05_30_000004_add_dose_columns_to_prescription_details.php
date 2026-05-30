<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_details', function (Blueprint $table) {
            $table->decimal('dose_amount', 12, 4)->nullable()->after('dosage');
            $table->foreignUuid('dose_unit_id')->nullable()->constrained('units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('prescription_details', function (Blueprint $table) {
            $table->dropColumn('dose_amount');
            $table->dropConstrainedForeignId('dose_unit_id');
        });
    }
};
