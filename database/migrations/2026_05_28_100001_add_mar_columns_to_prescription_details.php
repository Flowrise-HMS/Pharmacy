<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('prescription_details', function (Blueprint $table) {
            $table->string('administration_context')->default('take_home')->after('total_administrations');
            $table->timestamp('course_started_at')->nullable()->after('administration_context');
            $table->timestamp('course_end_at')->nullable()->after('course_started_at');
            $table->timestamp('next_dose_at')->nullable()->after('course_end_at');
            $table->unsignedInteger('max_administrations')->nullable()->after('next_dose_at');
            $table->boolean('administer_in_facility_flag')->default(false)->after('max_administrations');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_details', function (Blueprint $table) {
            $table->dropColumn([
                'administration_context',
                'course_started_at',
                'course_end_at',
                'next_dose_at',
                'max_administrations',
                'administer_in_facility_flag',
            ]);
        });
    }
};
