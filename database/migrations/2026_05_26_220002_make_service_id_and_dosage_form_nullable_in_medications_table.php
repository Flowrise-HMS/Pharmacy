<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->foreignUuid('service_id')->nullable()->change();
            $table->string('dosage_form')->nullable()->change();
        });
    }

    public function down(): void
    {
        Schema::table('medications', function (Blueprint $table) {
            $table->foreignUuid('service_id')->nullable(false)->change();
            $table->string('dosage_form')->nullable(false)->change();
        });
    }
};
