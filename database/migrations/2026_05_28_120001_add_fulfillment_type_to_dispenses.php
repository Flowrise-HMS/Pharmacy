<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispenses', function (Blueprint $table) {
            $table->string('fulfillment_type')->nullable()->after('dispensed_at');
        });

        Schema::table('dispenses', function (Blueprint $table) {
            $table->dropForeign(['medication_id']);
        });

        Schema::table('dispenses', function (Blueprint $table) {
            $table->uuid('medication_id')->nullable()->change();
            $table->foreign('medication_id')->references('id')->on('medications')->cascadeOnDelete();
        });

        DB::table('dispenses')->whereNull('fulfillment_type')->update(['fulfillment_type' => 'in_house']);
    }

    public function down(): void
    {
        Schema::table('dispenses', function (Blueprint $table) {
            $table->dropColumn('fulfillment_type');
        });

        Schema::table('dispenses', function (Blueprint $table) {
            $table->dropForeign(['medication_id']);
        });

        Schema::table('dispenses', function (Blueprint $table) {
            $table->uuid('medication_id')->nullable(false)->change();
            $table->foreign('medication_id')->references('id')->on('medications')->cascadeOnDelete();
        });
    }
};
