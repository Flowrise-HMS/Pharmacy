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
            $table->foreignUuid('branch_id')
                ->nullable()
                ->after('id')
                ->constrained('branches')
                ->nullOnDelete();

            $table->index('branch_id');
        });

        $driver = Schema::getConnection()->getDriverName();

        if ($driver === 'sqlite') {
            $rows = DB::table('dispenses')
                ->join('request_items', 'request_items.id', '=', 'dispenses.request_item_id')
                ->join('service_requests', 'service_requests.id', '=', 'request_items.service_request_id')
                ->whereNull('dispenses.branch_id')
                ->select('dispenses.id', 'service_requests.branch_id')
                ->get();

            foreach ($rows as $row) {
                DB::table('dispenses')
                    ->where('id', $row->id)
                    ->update(['branch_id' => $row->branch_id]);
            }
        } else {
            DB::statement('
                UPDATE dispenses
                SET branch_id = (
                    SELECT service_requests.branch_id
                    FROM request_items
                    INNER JOIN service_requests ON service_requests.id = request_items.service_request_id
                    WHERE request_items.id = dispenses.request_item_id
                )
                WHERE branch_id IS NULL
            ');
        }
    }

    public function down(): void
    {
        Schema::table('dispenses', function (Blueprint $table) {
            $table->dropConstrainedForeignId('branch_id');
        });
    }
};
