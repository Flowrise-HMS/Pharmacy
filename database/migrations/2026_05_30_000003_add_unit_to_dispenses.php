<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('dispenses', function (Blueprint $table) {
            $table->foreignUuid('unit_id')->nullable()->constrained('units')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('dispenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('unit_id');
        });
    }
};
