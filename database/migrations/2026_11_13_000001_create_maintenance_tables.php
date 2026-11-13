<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('departments', function (Blueprint $t) {
            $t->id();
            $t->string('name')->unique();
            $t->timestamps();
        });
        Schema::create('locations', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->string('area');
            $t->timestamps();
        });
        Schema::table('users', function (Blueprint $t) {
            $t->string('role')->default('department');
            $t->foreignId('department_id')->nullable()->constrained()->restrictOnDelete();
        });
        Schema::create('machines', function (Blueprint $t) {
            $t->id();
            $t->string('code')->unique();
            $t->string('name');
            $t->string('qr_value')->unique();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->foreignId('location_id')->constrained()->restrictOnDelete();
            $t->boolean('active')->default(true);
            $t->timestamps();
        });
        Schema::create('checklists', function (Blueprint $t) {
            $t->id();
            $t->string('name');
            $t->json('items');
            $t->timestamps();
        });
        Schema::create('maintenance_schedules', function (Blueprint $t) {
            $t->id();
            $t->foreignId('machine_id')->constrained()->restrictOnDelete();
            $t->foreignId('checklist_id')->constrained()->restrictOnDelete();
            $t->string('period');
            $t->date('starts_on');
            $t->json('weekdays');
            $t->boolean('active')->default(true);
            $t->timestamps();
            $t->unique(['machine_id', 'period']);
        });
        Schema::create('inspections', function (Blueprint $t) {
            $t->id();
            $t->foreignId('machine_id')->constrained()->restrictOnDelete();
            $t->foreignId('department_id')->constrained()->restrictOnDelete();
            $t->foreignId('owner_department_id')->constrained('departments')->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->date('inspection_date');
            $t->string('period')->default('daily');
            $t->string('status')->default('draft');
            $t->json('snapshot');
            $t->json('answers')->nullable();
            $t->json('photos')->nullable();
            $t->timestamp('submitted_at')->nullable();
            $t->timestamp('verified_at')->nullable();
            $t->timestamps();
            $t->unique(['machine_id', 'inspection_date', 'period']);
        });
        Schema::create('transfer_requests', function (Blueprint $t) {
            $t->id();
            $t->foreignId('machine_id')->constrained()->restrictOnDelete();
            $t->foreignId('from_department_id')->constrained('departments')->restrictOnDelete();
            $t->foreignId('to_department_id')->constrained('departments')->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('status')->default('pending');
            $t->text('reason')->nullable();
            $t->timestamps();
        });
        Schema::create('activity_logs', function (Blueprint $t) {
            $t->id();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();
            $t->string('subject_type');
            $t->unsignedBigInteger('subject_id');
            $t->string('action');
            $t->json('details')->nullable();
            $t->timestamps();
        });
    }

    public function down(): void
    {
        foreach (['activity_logs', 'transfer_requests', 'inspections', 'maintenance_schedules', 'checklists', 'machines'] as $table) {
            Schema::dropIfExists($table);
        }
        Schema::table('users', function (Blueprint $t) {
            $t->dropConstrainedForeignId('department_id');
            $t->dropColumn('role');
        });
        Schema::dropIfExists('locations');
        Schema::dropIfExists('departments');
    }
};
