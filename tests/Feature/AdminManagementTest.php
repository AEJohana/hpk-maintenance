<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Checklist;
use App\Models\Department;
use App\Models\Location;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AdminManagementTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;
    private Branch $branch;
    private Department $dept;
    private Location $loc;
    private Checklist $checklist;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['name' => 'Cabang Test']);
        $this->dept = Department::firstOrCreate(['name' => 'Produksi Test'], ['branch_id' => $this->branch->id]);
        $this->loc = Location::firstOrCreate(['name' => 'Line 1 Test', 'area' => 'Pabrik Utama', 'branch_id' => $this->branch->id]);
        $this->checklist = Checklist::firstOrCreate(
            ['name' => 'CEKLIST MESIN TEST', 'branch_id' => $this->branch->id],
            ['items' => [['label' => 'Kondisi fisik', 'type' => 'condition', 'unit' => '']]]
        );

        $this->admin = User::create([
            'name' => 'Admin Sistem',
            'email' => 'admin@test.example',
            'password' => Hash::make('password1234'),
            'is_admin' => true,
            'active' => true,
            'branch_id' => null,
            'department_id' => $this->dept->id,
        ]);
    }

    public function test_list_view_renders_table_without_showing_create_or_edit_form(): void
    {
        $machine = Machine::create([
            'name' => 'GMAW Welding',
            'code' => 'GMAW-999',
            'department_id' => $this->dept->id,
            'location_id' => $this->loc->id,
            'branch_id' => $this->branch->id,
            'active' => true,
            'form_name' => $this->checklist->name,
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin', ['tab' => 'machines']));

        $response->assertOk();
        $response->assertSee('Daftar Mesin');
        $response->assertSee('GMAW-999');
        $response->assertSee('+ Tambah Mesin');
        // Form field labels should not appear in list mode
        $response->assertDontSee('1. Identitas & Penempatan Mesin');
        $response->assertDontSee('2. Spesifikasi Teknis Mesin');
    }

    public function test_create_view_renders_dedicated_form_with_back_link(): void
    {
        $response = $this->actingAs($this->admin)->get(route('admin', ['tab' => 'machines', 'action' => 'create']));

        $response->assertOk();
        $response->assertSee('Tambah Mesin Baru');
        $response->assertSee('← Kembali ke Daftar Mesin');
        $response->assertSee('1. Identitas', false);
        $response->assertSee('Simpan Mesin');
    }

    public function test_edit_view_renders_form_prefilled_with_record_data(): void
    {
        $machine = Machine::create([
            'name' => 'Bubut Presisi',
            'code' => 'BBT-001',
            'department_id' => $this->dept->id,
            'location_id' => $this->loc->id,
            'branch_id' => $this->branch->id,
            'active' => true,
            'brand' => 'Mazak',
        ]);

        $response = $this->actingAs($this->admin)->get(route('admin', ['tab' => 'machines', 'edit' => $machine->id]));

        $response->assertOk();
        $response->assertSee('Edit Data: Bubut Presisi');
        $response->assertSee('← Kembali ke Daftar Mesin');
        $response->assertSee('BBT-001');
        $response->assertSee('Mazak');
    }

    public function test_toggle_active_soft_delete_action_toggles_status_without_deleting_record(): void
    {
        $machine = Machine::create([
            'name' => 'Overhead Crane 5T',
            'code' => 'CRN-005',
            'department_id' => $this->dept->id,
            'location_id' => $this->loc->id,
            'branch_id' => $this->branch->id,
            'active' => true,
        ]);

        $this->assertTrue($machine->fresh()->active);

        // Deactivate machine
        $resDeactivate = $this->actingAs($this->admin)->post(route('admin.toggle-active', ['type' => 'machines', 'id' => $machine->id]));
        $resDeactivate->assertRedirect();
        $this->assertFalse($machine->fresh()->active);

        // Record MUST still exist in database (not hard deleted!)
        $this->assertDatabaseHas(Machine::class, ['id' => $machine->id, 'active' => false]);
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'master_status_toggled']);

        // Reactivate machine
        $resActivate = $this->actingAs($this->admin)->post(route('admin.toggle-active', ['type' => 'machines', 'id' => $machine->id]));
        $resActivate->assertRedirect();
        $this->assertTrue($machine->fresh()->active);
        $this->assertDatabaseHas(Machine::class, ['id' => $machine->id, 'active' => true]);
    }

    public function test_status_filter_and_search_query_in_list_view(): void
    {
        $activeMachine = Machine::create([
            'name' => 'Mesin Aktif Satu',
            'code' => 'ACT-001',
            'department_id' => $this->dept->id,
            'location_id' => $this->loc->id,
            'branch_id' => $this->branch->id,
            'active' => true,
        ]);

        $inactiveMachine = Machine::create([
            'name' => 'Mesin Rusak Nonaktif',
            'code' => 'NACT-999',
            'department_id' => $this->dept->id,
            'location_id' => $this->loc->id,
            'branch_id' => $this->branch->id,
            'active' => false,
        ]);

        // Default list shows active machines
        $resDefault = $this->actingAs($this->admin)->get(route('admin', ['tab' => 'machines', 'status' => 'active']));
        $resDefault->assertOk();
        $resDefault->assertSee('ACT-001');
        $resDefault->assertDontSee('NACT-999');

        // Filter inactive shows only inactive machines
        $resInactive = $this->actingAs($this->admin)->get(route('admin', ['tab' => 'machines', 'status' => 'inactive']));
        $resInactive->assertOk();
        $resInactive->assertSee('NACT-999');
        $resInactive->assertDontSee('ACT-001');

        // Search by keyword
        $resSearch = $this->actingAs($this->admin)->get(route('admin', ['tab' => 'machines', 'status' => 'all', 'q' => 'NACT']));
        $resSearch->assertOk();
        $resSearch->assertSee('NACT-999');
        $resSearch->assertDontSee('ACT-001');
    }

    public function test_saving_machine_with_form_name_automatically_syncs_daily_maintenance_schedule(): void
    {
        $payload = [
            'name' => 'Mesin Las Auto Schedule',
            'code' => 'LAS-AUTO-01',
            'department_id' => $this->dept->id,
            'location_id' => $this->loc->id,
            'active' => 1,
            'form_name' => $this->checklist->name,
        ];

        $res = $this->actingAs($this->admin)->post(route('admin.save', 'machines'), $payload);
        $res->assertRedirect();

        $machine = Machine::where('code', 'LAS-AUTO-01')->firstOrFail();
        $this->assertSame($this->checklist->name, $machine->form_name);

        // Verify schedule was automatically generated and connected
        $schedule = MaintenanceSchedule::where('machine_id', $machine->id)->where('period', 'daily')->first();
        $this->assertNotNull($schedule, 'Jadwal harian harus tersinkronisasi otomatis saat form_name diisi.');
        $this->assertSame($this->checklist->id, $schedule->checklist_id);
        $this->assertTrue($schedule->active);
    }
}
