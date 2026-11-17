<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mstrs_components', function (Blueprint $t) {
            $t->id();
            $t->foreignId('machine_id')->constrained('mstrs_machines')->cascadeOnDelete();
            $t->string('code', 100)->nullable();
            $t->string('name', 150);
            $t->string('specification')->nullable();
            $t->unsignedInteger('quantity')->default(1);
            $t->string('condition', 50)->default('Baik');
            $t->text('notes')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mstrs_components');
    }
};
