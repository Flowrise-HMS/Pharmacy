<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_details', function (Blueprint $table) {
            $table->string('schedule_type')->nullable()->after('administration_context');
            $table->json('custom_dose_times')->nullable()->after('schedule_type');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_details', function (Blueprint $table) {
            $table->dropColumn([
                'schedule_type',
                'custom_dose_times',
            ]);
        });
    }
};
