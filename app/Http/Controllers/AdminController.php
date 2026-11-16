<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Checklist;
use App\Models\Department;
use App\Models\Location;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Services\Access;
use App\Services\Modules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private const MODELS = ['departments' => Department::class, 'locations' => Location::class, 'machines' => Machine::class, 'checklists' => Checklist::class, 'schedules' => MaintenanceSchedule::class, 'users' => User::class];

    public function index(Request $request)
    {
        $default = collect(array_keys(self::MODELS))->first(fn ($m) => auth()->user()->can($m.'.view'));
        abort_unless($default, 403);
        $tab = $request->query('tab', $default);
        abort_unless(isset(self::MODELS[$tab]), 404);
        Access::require($tab.'.view');
        $record = $request->filled('edit') ? self::MODELS[$tab]::findOrFail($request->edit) : null;
        if ($record) {
            Access::object($record);
            Access::require($tab.'.update');
        }

        return view('admin', ['tab' => $tab, 'record' => $record, 'records' => Access::scope(self::MODELS[$tab]::query())->latest('id')->paginate(20)->withQueryString(),
            'departments' => Access::scope(Department::query())->get(), 'locations' => Access::scope(Location::query())->get(), 'machines' => Access::scope(Machine::query())->get(), 'checklists' => Access::scope(Checklist::query())->get(), 'moduleCatalog' => Modules::all()]);
    }

    public function save(Request $request, string $type)
    {
        abort_unless(isset(self::MODELS[$type]), 404);
        $request->validate(['id' => 'nullable|integer']);
        Access::require($type.($request->filled('id') ? '.update' : '.create'));
        $record = $request->filled('id') ? self::MODELS[$type]::findOrFail($request->id) : new (self::MODELS[$type]);
        if ($record->exists) {
            Access::object($record);
        }
        if ($type === 'users') {
            return $this->saveUser($request, $record);
        }
        $branch = $record->exists ? Access::branch($record) : (auth()->user()->branch_id ?? Branch::firstOrFail()->id);
        // Infer the retained internal data boundary from the selected master reference.
        if (! $record->exists && auth()->user()->is_admin) {
            if ($request->filled('machine_id')) {
                $branch = Machine::findOrFail($request->machine_id)->branch_id;
            } elseif ($request->filled('department_id')) {
                $branch = Department::findOrFail($request->department_id)->branch_id;
            }
        }
        $id = $record->id;
        $rules = match ($type) {
            'departments' => ['name' => ['required', 'string', 'max:100', Rule::unique('departments')->where('branch_id', $branch)->ignore($id)]],
            'locations' => ['name' => 'required|string|max:100', 'area' => 'required|string|max:100'],
            'machines' => ['code' => ['required', 'string', 'max:100', Rule::unique('machines')->ignore($id)], 'name' => 'required|string|max:150', 'qr_value' => ['required', 'string', 'max:255', Rule::unique('machines')->ignore($id)], 'department_id' => 'required|exists:departments,id', 'location_id' => 'required|exists:locations,id', 'active' => 'required|boolean'],
            'checklists' => ['name' => 'required|string|max:150', 'items' => 'required|array|min:1|max:100', 'items.*.label' => 'required|string|max:200', 'items.*.type' => 'required|in:condition,number', 'items.*.unit' => 'nullable|string|max:30'],
            'schedules' => ['machine_id' => ['required', 'exists:machines,id', Rule::unique('maintenance_schedules')->where(fn ($q) => $q->where('period', $request->period))->ignore($id)], 'checklist_id' => 'required|exists:checklists,id', 'period' => 'required|in:daily,monthly,quarterly,semiannual', 'starts_on' => 'required|date_format:Y-m-d', 'weekdays' => 'required|array|min:1', 'weekdays.*' => 'required|integer|between:1,7|distinct', 'active' => 'required|boolean'],
        };
        $data = $request->validate($rules);
        foreach (['department_id' => Department::class, 'location_id' => Location::class, 'machine_id' => Machine::class, 'checklist_id' => Checklist::class] as $field => $model) {
            if (! empty($data[$field])) {
                abort_unless(Access::branch($model::findOrFail($data[$field])) === $branch, 422, 'Referensi data tidak sesuai.');
            }
        }
        if ($type !== 'schedules') {
            $data['branch_id'] = $branch;
        }
        if ($type === 'checklists') {
            $data['items'] = array_values($data['items']);
        }
        DB::transaction(function () use ($record, $data) {
            if ($record instanceof Machine && $record->exists) {
                $locked = Machine::whereKey($record->id)->lockForUpdate()->firstOrFail();
                abort_if((int) $data['department_id'] !== $locked->department_id, 422, 'Gunakan verifikasi perpindahan untuk mengubah bagian penanggung jawab.');
            }
            $before = $record->getAttributes();
            $record->fill($data)->save();
            ActivityLog::record($record, 'master_saved', ['before' => $before, 'after' => $record->getAttributes()]);
        });

        return redirect()->route('admin', ['tab' => $type])->with('success', 'Data berhasil disimpan.');
    }

    private function saveUser(Request $request, User $record)
    {
        abort_unless(auth()->user()->is_admin, 403);
        $data = $request->validate(['name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($record->id)], 'position' => 'nullable|string|max:100', 'password' => [$record->exists ? 'nullable' : 'required', 'string', 'min:10', 'max:100'], 'is_admin' => 'required|boolean', 'active' => 'required|boolean', 'department_id' => 'nullable|exists:departments,id', 'modules' => 'sometimes|array', 'modules.*' => ['required', 'string', 'distinct', Rule::in(array_keys(Modules::all()))]]);
        $modules = $data['modules'] ?? [];
        unset($data['modules']);
        if (in_array('daily', $modules, true) && ! $data['is_admin']) {
            $request->validate(['department_id' => 'required|exists:departments,id']);
        }
        $branch = ! empty($data['department_id']) ? Department::findOrFail($data['department_id'])->branch_id : ($record->branch_id ?? Branch::firstOrFail()->id);
        if ($record->exists && ! $record->is_admin) {
            abort_unless($branch === $record->branch_id, 422, 'Bagian akun tidak sesuai dengan data yang sudah ada.');
        }
        $data['branch_id'] = $data['is_admin'] ? null : $branch;
        if (! empty($data['password'])) {
            $data['password'] = Hash::make($data['password']);
        } else {
            unset($data['password']);
        }
        DB::transaction(function () use ($record, $data, $modules) {
            $admins = User::where('is_admin', true)->where('active', true)->orderBy('id')->lockForUpdate()->get();
            $current = $record->exists ? User::whereKey($record->id)->lockForUpdate()->firstOrFail() : null;
            abort_if($current?->is_admin && $current->active && (! $data['is_admin'] || ! $data['active']) && $admins->count() <= 1, 422, 'Admin aktif terakhir harus dipertahankan.');
            $before = $record->getAttributes();
            unset($before['password'],$before['remember_token']);
            $before['modules'] = $record->exists ? $record->modules()->pluck('module')->all() : [];
            $record->fill($data)->save();
            DB::table('user_modules')->where('user_id', $record->id)->delete();
            foreach ($data['is_admin'] ? [] : $modules as $module) {
                DB::table('user_modules')->insert(['user_id' => $record->id, 'module' => $module]);
            }
            $after = $record->getAttributes();
            unset($after['password'],$after['remember_token']);
            $after['modules'] = $data['is_admin'] ? [] : $modules;
            ActivityLog::record($record, 'account_access_saved', ['before' => $before, 'after' => $after]);
        });

        return redirect()->route('admin',['tab' => 'users'])->with('success','Akun dan akses modul berhasil disimpan.');
    }
}
