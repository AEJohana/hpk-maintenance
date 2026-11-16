<?php

namespace Tests\Feature;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Checklist;
use App\Models\Department;
use App\Models\Inspection;
use App\Models\Location;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\TransferRequest;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

class AuthorizationTest extends TestCase
{
    use RefreshDatabase;

    private function user(array $modules = [], bool $admin = false, int $branch = 1, ?int $department = null): User
    {
        $u = User::create(['name' => 'Kepala Regu '.uniqid(), 'email' => uniqid().'@test.example', 'password' => Hash::make('password1234'), 'is_admin' => $admin, 'active' => true, 'position' => 'Kepala Regu Produksi', 'branch_id' => $admin ? null : $branch, 'department_id' => $department]);
        foreach ($modules as $module) {
            DB::table('user_modules')->insert(['user_id' => $u->id, 'module' => $module]);
        }

        return $u;
    }

    private function machine(int $branch = 1): Machine
    {
        $d = Department::create(['name' => 'Produksi '.uniqid(), 'branch_id' => $branch]);
        $l = Location::create(['name' => 'Line', 'area' => 'A', 'branch_id' => $branch]);
        $m = Machine::create(['name' => 'Mesin '.uniqid(), 'code' => uniqid(), 'qr_value' => uniqid(), 'department_id' => $d->id, 'location_id' => $l->id, 'branch_id' => $branch]);
        $c = Checklist::create(['name' => 'Daily', 'branch_id' => $branch, 'items' => [['label' => 'Kondisi', 'type' => 'condition', 'unit' => '']]]);
        MaintenanceSchedule::create(['machine_id' => $m->id, 'checklist_id' => $c->id, 'period' => 'daily', 'active' => true, 'starts_on' => today()->subDay(), 'weekdays' => [1, 2, 3, 4, 5, 6, 7]]);

        return $m;
    }

    public function test_admin_assigns_modules_to_personal_account_without_roles_or_branch_form(): void
    {
        $m = $this->machine();
        $admin = $this->user([], true);
        $this->actingAs($admin)->get('/admin?tab=users')->assertOk()->assertSee('Akses modul')->assertDontSee('Branch Admin')->assertDontSee('Filter cabang');
        $payload = ['name' => 'Budi', 'email' => 'budi@test.example', 'password' => 'password1234', 'position' => 'Kepala Regu', 'is_admin' => 0, 'active' => 1, 'department_id' => $m->department_id, 'modules' => ['daily']];
        $this->post('/admin/users', $payload)->assertRedirect();
        $budi = User::where('email', $payload['email'])->firstOrFail();
        $this->assertTrue($budi->hasModule('daily'));
        $this->assertNull($budi->role_id);
        $this->actingAs($budi)->get('/')->assertOk()->assertSee('Pemeriksaan Harian')->assertDontSee('Buka verifikasi perpindahan');
        $this->get('/daily-check')->assertOk();
        $this->get('/monitoring')->assertForbidden();
        $this->get('/reviews')->assertForbidden();
        $this->get('/admin?tab=users')->assertForbidden();
        $this->post('/admin/users', $payload + ['id' => $admin->id])->assertForbidden();
        $log = ActivityLog::where('action', 'account_access_saved')->firstOrFail();
        $this->assertStringNotContainsString('password', json_encode($log->details));
    }

    public function test_cross_department_submission_requests_transfer_and_notifies_verifier_separately(): void
    {
        $m = $this->machine();
        $oldOwner = $m->department_id;
        $new = Department::create(['name' => 'Produksi Baru', 'branch_id' => 1]);
        $kr = $this->user(['daily'], false, 1, $new->id);
        $reviewer = $this->user(['verification', 'transfers']);
        $this->actingAs($kr)->post('/daily-check', ['lookup' => 'qr', 'code' => $m->qr_value])->assertRedirect();
        $i = Inspection::firstOrFail();
        $this->get('/inspections/'.$i->id)->assertOk()->assertSee('Anda tetap dapat mengisi checklist');
        $this->post('/inspections/'.$i->id, ['action' => 'submit', 'answers' => [['value' => 'OK', 'note' => '']]])->assertRedirect();
        $transfer = TransferRequest::firstOrFail();
        $this->assertEquals($oldOwner, $m->fresh()->department_id);
        $this->assertEquals($kr->id, $i->fresh()->submitted_by);
        $this->get('/inspections/'.$i->id)->assertSee('sedang menunggu verifikasi')->assertSee($kr->name);
        $this->post('/inspections/'.$i->id.'/transfer')->assertRedirect();
        $this->assertDatabaseCount('transfer_requests', 1);
        $this->actingAs($reviewer)->get('/')->assertOk()->assertSee('1 permintaan perpindahan mesin menunggu keputusan');
        $this->post('/reviews/transfers/'.$transfer->id, ['decision' => 'approved', 'reason' => 'Mesin sudah dipindahkan'])->assertRedirect();
        $this->assertEquals($new->id, $m->fresh()->department_id);
        $this->assertEquals('pending', $i->fresh()->status);
        $this->post('/reviews/inspections/'.$i->id, ['decision' => 'verified'])->assertRedirect();
        $this->assertEquals($reviewer->id, $i->fresh()->verified_by);
        $this->get('/')->assertDontSee('1 permintaan perpindahan mesin menunggu keputusan');
    }

    public function test_module_revocation_and_disabled_account_apply_to_active_sessions(): void
    {
        $m = $this->machine();
        $u = $this->user(['daily'], false, 1, $m->department_id);
        $this->actingAs($u)->get('/daily-check')->assertOk();
        DB::table('user_modules')->where('user_id', $u->id)->delete();
        $this->get('/daily-check')->assertForbidden();
        $this->post('/daily-check', ['lookup' => 'qr', 'code' => $m->qr_value])->assertForbidden();
        $this->get('/')->assertOk()->assertSee('Belum ada modul');
        $u->update(['active' => false]);
        $this->get('/')->assertForbidden();
        $this->post('/logout')->assertRedirect('/login');
        $this->post('/login', ['email' => $u->email, 'password' => 'password1234'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_account_validation_and_last_administrator_protection(): void
    {
        $admin = $this->user([], true);
        $this->actingAs($admin);
        $payload = ['name' => 'KR', 'email' => 'kr@test.example', 'password' => 'password1234', 'is_admin' => 0, 'active' => 1, 'modules' => ['daily']];
        $this->post('/admin/users', $payload)->assertSessionHasErrors('department_id');
        $payload['modules'] = ['unknown'];
        $this->post('/admin/users', $payload)->assertSessionHasErrors('modules.0');
        $this->post('/admin/users', ['id' => $admin->id, 'name' => $admin->name, 'email' => $admin->email, 'is_admin' => 1, 'active' => 0])->assertStatus(422);
        $this->post('/access/roles', [])->assertNotFound();
        $this->post('/access/branches', [])->assertNotFound();
    }

    public function test_retained_legacy_data_boundaries_cannot_be_bypassed_by_scan_or_session(): void
    {
        $b = Branch::create(['name' => 'Data lama terpisah']);
        $local = $this->machine();
        $foreign = $this->machine($b->id);
        $u = $this->user(['daily'], false, 1, $local->department_id);
        $other = $this->user(['daily'], false, $b->id, $foreign->department_id);
        $i = Inspection::create(['machine_id' => $foreign->id, 'department_id' => $foreign->department_id, 'owner_department_id' => $foreign->department_id, 'user_id' => $other->id, 'inspection_date' => today(), 'snapshot' => ['name' => 'X', 'items' => []]]);
        $this->actingAs($u)->post('/daily-check', ['lookup' => 'qr', 'code' => $foreign->qr_value])->assertSessionHasErrors('code');
        $this->withSession(['scanned_inspections' => [$i->id => $u->id]])->get('/inspections/'.$i->id)->assertNotFound();
        $this->get('/inspections/'.$i->id.'/photos/0')->assertNotFound();
        $this->post('/inspections/'.$i->id.'/transfer')->assertNotFound();
    }

    public function test_verification_modules_are_independent_and_master_cannot_manage_accounts(): void
    {
        $u = $this->user(['master']);
        $this->actingAs($u)->get('/admin')->assertOk();
        $this->get('/admin?tab=users')->assertForbidden();
        $this->get('/access')->assertForbidden();
        $this->get('/audit')->assertForbidden();
        $this->post('/reviews/transfers/1', ['decision' => 'approved', 'reason' => 'x'])->assertNotFound();
        $this->assertFalse($u->can('transfers.decide'));
        $this->assertFalse($u->can('inspections.verify'));
        DB::table('user_modules')->insert(['user_id' => $u->id, 'module' => 'transfers']);
        $this->get('/reviews')->assertOk();
        $this->assertTrue($u->can('transfers.decide'));
        $this->assertFalse($u->can('inspections.verify'));
    }
}
