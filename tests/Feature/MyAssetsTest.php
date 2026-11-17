<?php

namespace Tests\Feature;

use App\Models\Branch;
use App\Models\Checklist;
use App\Models\Department;
use App\Models\Inspection;
use App\Models\Location;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class MyAssetsTest extends TestCase
{
    use RefreshDatabase;

    private User $pic;
    private User $admin;
    private Branch $branch;
    private Department $dept;
    private Location $lineAksesoris;
    private Location $lineSubframe;
    private Checklist $checklist;
    private Machine $machineAksesoris1;
    private Machine $machineAksesoris2;
    private Machine $machineSubframe;

    protected function setUp(): void
    {
        parent::setUp();

        $this->branch = Branch::firstOrCreate(['name' => 'Cabang HPK']);
        $this->dept = Department::firstOrCreate(['name' => 'Produksi'], ['branch_id' => $this->branch->id]);

        $this->lineAksesoris = Location::firstOrCreate(
            ['name' => 'Line 1 Accesoris DT', 'branch_id' => $this->branch->id],
            ['area' => 'Area Pabrik']
        );

        $this->lineSubframe = Location::firstOrCreate(
            ['name' => 'Line 2 Subframe', 'branch_id' => $this->branch->id],
            ['area' => 'Area Pabrik']
        );

        $this->checklist = Checklist::firstOrCreate(
            ['name' => 'CEKLIST MESIN GMAW', 'branch_id' => $this->branch->id],
            ['items' => [['label' => 'Kondisi kabel massa', 'type' => 'condition', 'unit' => '']]]
        );

        // Machine in Aksesoris Line
        $this->machineAksesoris1 = Machine::create([
            'name' => 'GMAW 350',
            'code' => 'GMAW-ACC-01',
            'department_id' => $this->dept->id,
            'location_id' => $this->lineAksesoris->id,
            'branch_id' => $this->branch->id,
            'active' => true,
            'form_name' => $this->checklist->name,
        ]);

        $this->machineAksesoris2 = Machine::create([
            'name' => 'GMAW 500',
            'code' => 'GMAW-ACC-02',
            'department_id' => $this->dept->id,
            'location_id' => $this->lineAksesoris->id,
            'branch_id' => $this->branch->id,
            'active' => true,
            'form_name' => $this->checklist->name,
        ]);

        // Machine in Subframe Line
        $this->machineSubframe = Machine::create([
            'name' => 'GMAW Subframe',
            'code' => 'GMAW-SBF-01',
            'department_id' => $this->dept->id,
            'location_id' => $this->lineSubframe->id,
            'branch_id' => $this->branch->id,
            'active' => true,
            'form_name' => $this->checklist->name,
        ]);

        // Daily schedule
        foreach ([$this->machineAksesoris1, $this->machineAksesoris2, $this->machineSubframe] as $m) {
            MaintenanceSchedule::create([
                'machine_id' => $m->id,
                'checklist_id' => $this->checklist->id,
                'period' => 'daily',
                'starts_on' => today()->subDays(5),
                'weekdays' => [1, 2, 3, 4, 5, 6, 7],
                'active' => true,
            ]);
        }

        // PIC Kepala Regu Aksesoris (Pak Joni)
        $this->pic = User::create([
            'name' => 'Joni',
            'email' => 'joni.acc@hpk.test',
            'password' => Hash::make('password1234'),
            'is_admin' => false,
            'active' => true,
            'position' => 'Kepala Regu',
            'department_id' => $this->dept->id,
            'location_id' => $this->lineAksesoris->id,
            'branch_id' => $this->branch->id,
        ]);
        $this->pic->modules()->create(['module' => 'daily']);

        // Admin
        $this->admin = User::create([
            'name' => 'Super Admin',
            'email' => 'admin.super@hpk.test',
            'password' => Hash::make('password1234'),
            'is_admin' => true,
            'active' => true,
        ]);
    }

    public function test_user_can_view_machines_in_their_assigned_line(): void
    {
        $response = $this->actingAs($this->pic)->get(route('my-assets'));

        $response->assertOk();
        $response->assertSee('Aset Mesin Tanggung Jawab Saya');
        $response->assertSee('Line 1 Accesoris DT');
        $response->assertSee('Joni');
        $response->assertSee('GMAW-ACC-01');
        $response->assertSee('GMAW-ACC-02');
        // Machine in another line should not appear by default
        $response->assertDontSee('GMAW-SBF-01');
    }

    public function test_today_inspection_status_displayed_accurately(): void
    {
        // One machine is inspected with OK
        Inspection::create([
            'machine_id' => $this->machineAksesoris1->id,
            'department_id' => $this->dept->id,
            'owner_department_id' => $this->dept->id,
            'user_id' => $this->pic->id,
            'inspection_date' => today(),
            'status' => 'verified',
            'snapshot' => ['name' => $this->checklist->name, 'items' => []],
            'answers' => [['value' => 'OK', 'note' => '']],
        ]);

        $response = $this->actingAs($this->pic)->get(route('my-assets'));

        $response->assertOk();
        $response->assertSee('TERVERIFIKASI (OK)');
        $response->assertSee('BELUM DIISI HARI INI');
    }

    public function test_daily_check_cannot_be_bypassed_without_qr_scan_and_requires_physical_scan(): void
    {
        // 1. In my-assets, there is NO bypass "Periksa Sekarang" button, but instead "Scan QR"
        $response = $this->actingAs($this->pic)->get(route('my-assets'));
        $response->assertOk();
        $response->assertDontSee('Periksa Sekarang');
        $response->assertSee('Scan QR');
        $response->assertSee('Verifikasi Kehadiran Fisik (Anti-Bypass QR)');

        // 2. Direct POST bypass route does not exist
        $this->post('/my-assets/'.$this->machineAksesoris2->id.'/start-check')->assertNotFound();

        // 3. User MUST scan via /daily-check using the physical QR code
        $scanResponse = $this->post(route('daily.scan'), [
            'code' => $this->machineAksesoris2->qr_value,
            'lookup' => 'qr',
        ]);
        $scanResponse->assertRedirect();

        // Inspection is created only through the scan process
        $this->assertDatabaseHas(Inspection::class, [
            'machine_id' => $this->machineAksesoris2->id,
        ]);
        $inspection = Inspection::where('machine_id', $this->machineAksesoris2->id)->firstOrFail();
        $this->assertSame(today()->toDateString(), $inspection->inspection_date->toDateString());
    }

    public function test_admin_can_assign_location_line_to_user_account(): void
    {
        $payload = [
            'name' => 'Budi Santoso',
            'email' => 'budi.line@hpk.test',
            'password' => 'password1234',
            'position' => 'Kepala Regu Subframe',
            'is_admin' => 0,
            'active' => 1,
            'department_id' => $this->dept->id,
            'location_id' => $this->lineSubframe->id,
            'modules' => ['daily'],
        ];

        $response = $this->actingAs($this->admin)->post(route('admin.save', 'users'), $payload);
        $response->assertRedirect();

        $user = User::where('email', 'budi.line@hpk.test')->firstOrFail();
        $this->assertSame($this->lineSubframe->id, $user->location_id);
    }

    public function test_home_dashboard_displays_line_summary_widget(): void
    {
        $response = $this->actingAs($this->pic)->get(route('home'));

        $response->assertOk();
        $response->assertSee('LINE TANGGUNG JAWAB SAYA');
        $response->assertSee('Line 1 Accesoris DT');
        $response->assertSee('Buka Aset Line Saya →');
    }
}
