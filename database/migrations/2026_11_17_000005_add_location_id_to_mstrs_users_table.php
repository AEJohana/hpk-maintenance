<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        if (Schema::hasTable('mstrs_users') && ! Schema::hasColumn('mstrs_users', 'location_id')) {
            Schema::table('mstrs_users', function (Blueprint $t) {
                $t->unsignedBigInteger('location_id')->nullable()->after('department_id');
                $t->foreign('location_id')->references('id')->on('mstrs_locations')->nullOnDelete();
            });
        }
    }

    public function down(): void
    {
        if (Schema::hasTable('mstrs_users') && Schema::hasColumn('mstrs_users', 'location_id')) {
            Schema::table('mstrs_users', function (Blueprint $t) {
                $t->dropForeign(['location_id']);
                $t->dropColumn('location_id');
            });
        }
    }
};
