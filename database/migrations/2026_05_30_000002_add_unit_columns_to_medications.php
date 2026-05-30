<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->foreignUuid('stock_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignUuid('billing_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->foreignUuid('dose_unit_id')->nullable()->constrained('units')->nullOnDelete();
            $table->decimal('units_per_stock_unit', 12, 4)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->dropConstrainedForeignId('stock_unit_id');
            $table->dropConstrainedForeignId('billing_unit_id');
            $table->dropConstrainedForeignId('dose_unit_id');
            $table->dropColumn('units_per_stock_unit');
        });
    }
};
