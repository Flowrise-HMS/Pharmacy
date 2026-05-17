<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_details', function (Blueprint $table) {
            $table->unsignedInteger('total_administrations')->nullable()->after('refills');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_details', function (Blueprint $table) {
            $table->dropColumn('total_administrations');
        });
    }
};
