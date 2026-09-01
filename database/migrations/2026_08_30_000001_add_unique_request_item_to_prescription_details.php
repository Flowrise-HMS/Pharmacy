<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

/**
 * PrescriptionDetail is consumed through a hasOne on RequestItem, so a second
 * row for the same request_item_id makes the dosage that a clinician sees
 * arbitrary. Nothing enforced one-per-item at the database level: the create
 * migration declared no indexes and no unique constraints at all.
 */
return new class extends Migration
{
    public function up(): void
    {
        $this->deleteDuplicateDetails();

        Schema::table('prescription_details', function (Blueprint $table) {
            $table->unique('request_item_id');
        });
    }

    public function down(): void
    {
        Schema::table('prescription_details', function (Blueprint $table) {
            $table->dropUnique(['request_item_id']);
        });
    }

    /**
     * Keep the most recent detail per request item so the unique index can be
     * created on existing data. Anything older was already unreachable through
     * the hasOne relation.
     */
    private function deleteDuplicateDetails(): void
    {
        $duplicated = DB::table('prescription_details')
            ->select('request_item_id')
            ->groupBy('request_item_id')
            ->havingRaw('COUNT(*) > 1')
            ->pluck('request_item_id');

        foreach ($duplicated as $requestItemId) {
            $keepId = DB::table('prescription_details')
                ->where('request_item_id', $requestItemId)
                ->orderByDesc('created_at')
                ->orderByDesc('id')
                ->value('id');

            DB::table('prescription_details')
                ->where('request_item_id', $requestItemId)
                ->where('id', '!=', $keepId)
                ->delete();
        }
    }
};
