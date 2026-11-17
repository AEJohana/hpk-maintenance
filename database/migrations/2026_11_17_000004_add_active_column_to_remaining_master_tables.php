<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        $tables = [
            'mstrs_locations',
            'mstrs_checklists',
            'mstrs_departments',
            'mstrs_components',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && ! Schema::hasColumn($table, 'active')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->boolean('active')->default(true)->after('id');
                });
            }
        }
    }

    public function down(): void
    {
        $tables = [
            'mstrs_locations',
            'mstrs_checklists',
            'mstrs_departments',
            'mstrs_components',
        ];

        foreach ($tables as $table) {
            if (Schema::hasTable($table) && Schema::hasColumn($table, 'active')) {
                Schema::table($table, function (Blueprint $t) {
                    $t->dropColumn('active');
                });
            }
        }
    }
};
