<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('mstrs_machines', function (Blueprint $t) {
            $t->string('brand', 100)->nullable()->after('name');
            $t->string('machine_type', 50)->nullable()->after('brand');
            $t->string('model', 100)->nullable()->after('machine_type');
            $t->string('serial_number', 100)->nullable()->after('model');
            $t->string('asset_number', 100)->nullable()->after('serial_number');
            $t->text('asset_description')->nullable()->after('asset_number');
            $t->string('origin_country', 100)->nullable()->after('asset_description');
            $t->smallInteger('manufacture_year')->nullable()->after('origin_country');
            $t->date('received_date')->nullable()->after('manufacture_year');
            $t->date('operational_date')->nullable()->after('received_date');
            $t->string('condition', 50)->default('Baik')->after('operational_date');
            $t->decimal('power_kw', 8, 2)->nullable()->after('condition');
            $t->decimal('power_hp', 8, 2)->nullable()->after('power_kw');
            $t->string('capacity', 100)->nullable()->after('power_hp');
            $t->string('machine_status', 50)->default('Beroperasi')->after('capacity');
            $t->unsignedTinyInteger('condition_percentage')->default(100)->after('machine_status');
            $t->unsignedSmallInteger('quantity')->default(1)->after('condition_percentage');
            $t->string('po_number', 100)->nullable()->after('quantity');
            $t->text('image_url')->nullable()->after('po_number');
            $t->string('form_name', 150)->nullable()->after('image_url');
        });
    }

    public function down(): void
    {
        Schema::table('mstrs_machines', function (Blueprint $t) {
            $t->dropColumn([
                'brand',
                'machine_type',
                'model',
                'serial_number',
                'asset_number',
                'asset_description',
                'origin_country',
                'manufacture_year',
                'received_date',
                'operational_date',
                'condition',
                'power_kw',
                'power_hp',
                'capacity',
                'machine_status',
                'condition_percentage',
                'quantity',
                'po_number',
                'image_url',
                'form_name',
            ]);
        });
    }
};
