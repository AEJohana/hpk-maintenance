<?php

namespace Tests\Feature;

use App\Models\AccessRole;
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
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class DailyCheckTest extends TestCase
{
    use RefreshDatabase;

    private User $a;

    private User $b;

    private User $reviewer;

    private User $admin;

    private Machine $machine;

    private Checklist $checklist;

    protected function setUp(): void
    {
        parent::setUp();
        $this->travelTo(now()->setDate(2026, 11, 13)->setTime(10, 0));
        $branch = Branch::first()->id;
        $da = Department::create(['name' => 'Produksi A', 'branch_id' => $branch]);
        $db = Department::create(['name' => 'Produksi B', 'branch_id' => $branch]);
        $this->a = $this->account('a', 'department', $da->id);
        $this->b = $this->account('b', 'department', $db->id);
        $this->reviewer = $this->account('reviewer', 'maintenance');
        $this->admin = $this->account('admin', 'admin');
        $l = Location::create(['name' => 'Line 1', 'area' => 'Gedung A', 'branch_id' => $branch]);
        $this->machine = Machine::create(['branch_id' => $branch, 'code' => 'MC-001', 'name' => 'Mesin potong', 'qr_value' => 'existing://machine/001', 'department_id' => $da->id, 'location_id' => $l->id]);
        $this->checklist = Checklist::create(['branch_id' => $branch, 'name' => 'Pemeriksaan harian', 'items' => [['label' => 'Kebersihan', 'type' => 'condition', 'unit' => ''], ['label' => 'Suhu', 'type' => 'number', 'unit' => 'C']]]);
        MaintenanceSchedule::create(['machine_id' => $this->machine->id, 'checklist_id' => $this->checklist->id, 'period' => 'daily', 'starts_on' => '2026-01-01', 'weekdays' => [1, 2, 3, 4, 5, 6, 7]]);
    }

    private function account($name, $role, $department = null): User
    {
        $user = User::create(['name' => $name, 'email' => "$name@example.test", 'password' => Hash::make('password1234'), 'is_admin' => $role === 'admin', 'role' => $role, 'active' => true, 'role_id' => AccessRole::where('type', $role)->first()->id, 'branch_id' => $role === 'admin' ? null : Branch::first()->id, 'department_id' => $department]);
        $modules = match ($role) {
            'department' => ['monitoring', 'daily'], 'maintenance' => ['monitoring', 'verification', 'transfers'], default => []
        };
        foreach ($modules as $module) {
            DB::table((new \App\Models\UserModule)->getTable())->insert(['user_id' => $user->id, 'module' => $module]);
        }

        return $user;
    }

    private function open(?User $user = null): Inspection
    {
        $this->actingAs($user ?? $this->a)->post(route('daily.scan'), ['code' => $this->machine->qr_value, 'lookup' => 'qr'])->assertRedirect();

        return Inspection::firstOrFail();
    }

    private function answers($value = 'OK'): array
    {
        return ['action' => 'submit', 'answers' => [['value' => $value, 'note' => $value === 'NG' ? 'Kotor' : ''], ['value' => '32.5', 'note' => '']]];
    }

    public function test_login_session_and_role_guards(): void
    {
        $this->post('/login', ['email' => $this->a->email, 'password' => 'bad'])->assertSessionHasErrors();
        $this->post('/login', ['email' => $this->a->email, 'password' => 'password1234'])->assertRedirect('/');
        $this->get('/admin')->assertForbidden();
        $this->get('/reviews')->assertForbidden();
        $this->post('/logout')->assertRedirect('/login');
        $this->get('/')->assertRedirect('/login');
    }

    public function test_unknown_qr_no_schedule_inactive_and_manual_lookup(): void
    {
        $this->actingAs($this->a)->post('/daily-check', ['code' => 'unknown', 'lookup' => 'qr'])->assertSessionHasErrors('code');
        $this->post('/daily-check', ['code' => 'MC-001', 'lookup' => 'code'])->assertRedirect();
        $this->assertDatabaseCount(Inspection::class, 1);
        $this->machine->update(['active' => false]);
        $this->post('/daily-check', ['code' => 'MC-001', 'lookup' => 'code'])->assertStatus(422);
        $this->machine->update(['active' => true]);
        Inspection::query()->delete();
        MaintenanceSchedule::query()->update(['active' => false]);
        $this->post('/daily-check', ['code' => 'MC-001', 'lookup' => 'code'])->assertSessionHasErrors('machine');
    }

    public function test_daily_unique_draft_ng_validation_and_submission(): void
    {
        $i = $this->open();
        $this->open();
        $this->assertDatabaseCount(Inspection::class, 1);
        $this->post(route('inspections.save', $i), ['action' => 'draft'])->assertRedirect();
        $bad = $this->answers('NG');
        $bad['answers'][0]['note'] = '';
        $this->post(route('inspections.save', $i), $bad)->assertSessionHasErrors('answers.0.note');
        $this->post(route('inspections.save', $i), $this->answers('NG'))->assertRedirect();
        $this->assertSame('pending', $i->fresh()->status);
        $this->assertTrue($i->fresh()->hasNg());
        $this->post(route('inspections.save', $i), $this->answers())->assertStatus(409);
        $this->assertDatabaseCount(TransferRequest::class, 0);
    }

    public function test_snapshot_revision_and_verification_history(): void
    {
        $i = $this->open();
        $this->checklist->update(['items' => [['label' => 'Changed', 'type' => 'number', 'unit' => 'A']]]);
        $this->post(route('inspections.save', $i), $this->answers())->assertRedirect();
        $this->assertSame('Kebersihan', $i->fresh()->snapshot['items'][0]['label']);
        $this->actingAs($this->reviewer)->post(route('reviews.inspection', $i), ['decision' => 'revision', 'reason' => 'Periksa suhu'])->assertRedirect();
        $this->travel(1)->days();
        $this->actingAs($this->a)->post(route('inspections.save', $i), $this->answers('NG'))->assertRedirect();
        $this->actingAs($this->reviewer)->post(route('reviews.inspection', $i), ['decision' => 'verified'])->assertRedirect();
        $this->assertSame('verified', $i->fresh()->status);
        $this->assertNotNull($i->fresh()->verified_at);
        $this->assertDatabaseHas(ActivityLog::class, ['action' => 'revision']);
    }

    public function test_cross_department_transfer_is_independent_and_preserves_owner(): void
    {
        $i = $this->open($this->b);
        $this->post(route('inspections.save', $i), $this->answers())->assertRedirect();
        $this->post(route('inspections.transfer', $i))->assertRedirect();
        $this->assertDatabaseCount(TransferRequest::class, 1);
        $this->assertSame($this->a->department_id, $this->machine->fresh()->department_id);
        $t = TransferRequest::firstOrFail();
        $this->actingAs($this->reviewer)->post(route('reviews.transfer', $t), ['decision' => 'approved', 'reason' => 'Sudah di area B'])->assertRedirect();
        $this->assertSame($this->b->department_id, $this->machine->fresh()->department_id);
        $this->assertSame($this->a->department_id, $i->fresh()->owner_department_id);
        $this->assertSame('pending', $i->fresh()->status);
        $this->assertSame($this->a->department_id, $t->fresh()->from_department_id);
    }

    public function test_rejected_transfer_keeps_result_and_owner(): void
    {
        $i = $this->open($this->b);
        $this->post(route('inspections.save', $i), $this->answers());
        $t = TransferRequest::firstOrFail();
        $this->actingAs($this->reviewer)->post(route('reviews.transfer', $t), ['decision' => 'rejected', 'reason' => 'Mesin pinjaman'])->assertRedirect();
        $this->assertSame($this->a->department_id, $this->machine->fresh()->department_id);
        $this->assertSame($this->b->department_id, $i->fresh()->department_id);
    }

    public function test_other_department_cannot_edit_or_see_private_inspection(): void
    {
        $i = $this->open();
        $this->actingAs($this->b)->post(route('inspections.save', $i), $this->answers())->assertForbidden();
        $this->get(route('inspections.show', $i))->assertForbidden();
        $this->actingAs($this->a)->get('/monitoring?department='.$this->b->department_id)->assertOk()->assertSee('Mesin potong');
    }

    public function test_attachments_are_private_and_validated(): void
    {
        Storage::fake('local');
        $i = $this->open();
        $data = $this->answers();
        $data['photos'] = [UploadedFile::fake()->create('document.txt', 10, 'text/plain')];
        $this->post(route('inspections.save', $i), $data)->assertSessionHasErrors('photos.0');
        // A real tiny PNG, without requiring the GD extension on the server.
        $path = tempnam(sys_get_temp_dir(), 'daily');
        file_put_contents($path, base64_decode('iVBORw0KGgoAAAANSUhEUgAAAAEAAAABCAQAAAC1HAwCAAAAC0lEQVR42mP8/x8AAwMCAO+a2ioAAAAASUVORK5CYII='));
        $data['photos'] = [new UploadedFile($path, 'photo.png', 'image/png', null, true)];
        $this->post(route('inspections.save', $i), $data)->assertSessionHasNoErrors();
        $url = route('inspections.photo', [$i, 0]);
        $this->get($url)->assertOk();
        $this->actingAs($this->b)->get($url)->assertForbidden();
        $this->post('/logout');
        $this->get($url)->assertRedirect('/login');
    }

    public function test_schedule_rules_and_date_rollover(): void
    {
        $s = MaintenanceSchedule::first();
        $s->update(['weekdays' => [1]]);
        $this->assertFalse($s->dueOn(today()));
        $this->assertTrue($s->dueOn(today()->next('Monday')));
        $s->update(['weekdays' => [1, 2, 3, 4, 5, 6, 7]]);
        $this->open();
        $this->travel(1)->days();
        $this->open();
        $this->assertDatabaseCount(Inspection::class, 2);
        $s->update(['period' => 'monthly']);
        $this->travel(1)->days();
        $this->post('/daily-check', ['code' => 'MC-001', 'lookup' => 'code'])->assertSessionHasErrors('machine');
    }

    public function test_scanning_an_already_submitted_machine_shows_existing_result_without_duplicate(): void
    {
        $i = $this->open();
        $this->post(route('inspections.save', $i), $this->answers());
        $this->actingAs($this->b)->post('/daily-check', ['code' => 'MC-001', 'lookup' => 'code'])->assertRedirect(route('inspections.show', $i));
        $this->get(route('inspections.show', $i))->assertOk()->assertSee('Menunggu verifikasi');
        $this->post(route('inspections.save', $i), $this->answers())->assertForbidden();
        $this->post(route('inspections.transfer', $i))->assertRedirect();
        $this->assertDatabaseCount(Inspection::class, 1);
        $this->assertDatabaseCount(TransferRequest::class, 1);
    }

    public function test_all_pages_render_and_admin_master_management(): void
    {
        $i = $this->open();
        foreach (['/monitoring', '/daily-check', route('inspections.show', $i), route('machines.show', $this->machine)] as $url) {
            $this->get($url)->assertOk();
        }
        $this->actingAs($this->reviewer)->get('/reviews')->assertOk();
        $this->actingAs($this->admin);
        foreach (['departments', 'locations', 'machines', 'checklists', 'schedules', 'users'] as $tab) {
            $this->get('/admin?tab='.$tab)->assertOk();
            $id = match ($tab) {
                'departments' => $this->a->department_id,'locations' => $this->machine->location_id,'machines' => $this->machine->id,'checklists' => $this->checklist->id,'schedules' => MaintenanceSchedule::first()->id,'users' => $this->a->id
            };
            $this->get('/admin?tab='.$tab.'&edit='.$id)->assertOk();
        }
        $this->post('/admin/departments', ['name' => 'Utilities'])->assertRedirect();
        $this->assertDatabaseHas(Department::class, ['name' => 'Utilities']);
        $this->post('/admin/users', ['name' => 'Utility', 'email' => 'utility@example.test', 'password' => 'password1234', 'modules' => ['daily'], 'is_admin' => 0, 'active' => 1])->assertSessionHasErrors('department_id');
    }
}
