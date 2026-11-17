<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Branch;
use App\Models\Checklist;
use App\Models\Component;
use App\Models\Department;
use App\Models\Location;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use App\Models\UserModule;
use App\Services\Access;
use App\Services\Modules;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private const MODELS = [
        'departments' => Department::class,
        'locations' => Location::class,
        'machines' => Machine::class,
        'components' => Component::class,
        'checklists' => Checklist::class,
        'schedules' => MaintenanceSchedule::class,
        'users' => User::class,
    ];

    public function index(Request $request)
    {
        $default = collect(array_keys(self::MODELS))->first(fn ($m) => auth()->user()->can($m.'.view'));
        abort_unless($default, 403);
        $tab = $request->query('tab', $default);
        abort_unless(isset(self::MODELS[$tab]), 404);
        Access::require($tab.'.view');

        $action = $request->query('action');
        $isCreate = $action === 'create';
        $isEdit = $request->filled('edit');
        $mode = ($isCreate || $isEdit) ? 'form' : 'list';

        $record = null;
        if ($isCreate) {
            Access::require($tab.'.create');
        } elseif ($isEdit) {
            $record = self::MODELS[$tab]::findOrFail($request->edit);
            Access::object($record);
            Access::require($tab.'.update');
        }

        $records = null;
        $status = $request->query('status', 'active');
        $q = trim((string) $request->query('q', ''));

        if ($mode === 'list') {
            $query = Access::scope(self::MODELS[$tab]::query());
            $table = (new (self::MODELS[$tab]))->getTable();

            // Filter active/inactive
            if (\Illuminate\Support\Facades\Schema::hasColumn($table, 'active')) {
                if ($status === 'active') {
                    $query->where($table.'.active', true);
                } elseif ($status === 'inactive') {
                    $query->where($table.'.active', false);
                }
            }

            // Keyword search
            if ($q !== '') {
                match ($tab) {
                    'machines' => $query->where(fn ($sq) => $sq->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")
                        ->orWhere('brand', 'like', "%{$q}%")
                        ->orWhere('qr_value', 'like', "%{$q}%")),
                    'locations' => $query->where(fn ($sq) => $sq->where('name', 'like', "%{$q}%")
                        ->orWhere('area', 'like', "%{$q}%")),
                    'checklists' => $query->where('name', 'like', "%{$q}%"),
                    'departments' => $query->where('name', 'like', "%{$q}%"),
                    'components' => $query->where(fn ($sq) => $sq->where('name', 'like', "%{$q}%")
                        ->orWhere('code', 'like', "%{$q}%")),
                    'users' => $query->where(fn ($sq) => $sq->where('name', 'like', "%{$q}%")
                        ->orWhere('email', 'like', "%{$q}%")
                        ->orWhere('position', 'like', "%{$q}%")),
                    'schedules' => $query->whereHas('machine', fn ($mq) => $mq->where('code', 'like', "%{$q}%")
                        ->orWhere('name', 'like', "%{$q}%")),
                    default => null,
                };
            }

            $records = $query->latest($table.'.id')->paginate(20)->withQueryString();
        }

        return view('admin', [
            'tab' => $tab,
            'mode' => $mode,
            'record' => $record,
            'records' => $records,
            'status' => $status,
            'q' => $q,
            'departments' => Access::scope(Department::query())->where('active', true)->get(),
            'locations' => Access::scope(Location::query())->where('active', true)->get(),
            'machines' => Access::scope(Machine::query())->where('active', true)->get(),
            'checklists' => Access::scope(Checklist::query())->where('active', true)->get(),
            'moduleCatalog' => Modules::all(),
        ]);
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
            'departments' => [
                'name' => ['required', 'string', 'max:100', Rule::unique(Department::class)->where('branch_id', $branch)->ignore($id)],
                'active' => 'sometimes|boolean',
            ],
            'locations' => [
                'name' => 'required|string|max:100',
                'area' => 'required|string|max:100',
                'active' => 'sometimes|boolean',
            ],
            'machines' => [
                'code' => ['required', 'string', 'max:100', Rule::unique(Machine::class)->ignore($id)],
                'name' => 'required|string|max:150',
                'qr_value' => ['nullable', 'string', 'max:255', Rule::unique(Machine::class)->ignore($id)],
                'department_id' => ['required', 'exists:'.(new Department)->getTable().',id'],
                'location_id' => ['required', 'exists:'.(new Location)->getTable().',id'],
                'active' => 'required|boolean',
                'brand' => 'nullable|string|max:100',
                'machine_type' => 'nullable|string|max:50',
                'model' => 'nullable|string|max:100',
                'serial_number' => 'nullable|string|max:100',
                'asset_number' => 'nullable|string|max:100',
                'asset_description' => 'nullable|string',
                'origin_country' => 'nullable|string|max:100',
                'manufacture_year' => 'nullable|integer',
                'received_date' => 'nullable|date',
                'operational_date' => 'nullable|date',
                'condition' => 'nullable|string|max:50',
                'power_kw' => 'nullable|numeric',
                'power_hp' => 'nullable|numeric',
                'capacity' => 'nullable|string|max:100',
                'machine_status' => 'nullable|string|max:50',
                'condition_percentage' => 'nullable|integer|between:0,100',
                'quantity' => 'nullable|integer|min:1',
                'po_number' => 'nullable|string|max:100',
                'image_url' => 'nullable|string',
                'form_name' => 'nullable|string|max:150',
            ],
            'components' => [
                'machine_id' => ['required', 'exists:'.(new Machine)->getTable().',id'],
                'name' => 'required|string|max:150',
                'code' => 'nullable|string|max:100',
                'specification' => 'nullable|string',
                'quantity' => 'required|integer|min:1',
                'condition' => 'required|string|max:50',
                'notes' => 'nullable|string',
                'active' => 'sometimes|boolean',
            ],
            'checklists' => [
                'name' => 'required|string|max:150',
                'items' => 'required|array|min:1|max:100',
                'items.*.label' => 'required|string|max:200',
                'items.*.type' => 'required|in:condition,number',
                'items.*.unit' => 'nullable|string|max:30',
                'active' => 'sometimes|boolean',
            ],
            'schedules' => [
                'machine_id' => ['required', 'exists:'.(new Machine)->getTable().',id', Rule::unique(MaintenanceSchedule::class)->where(fn ($q) => $q->where('period', $request->period))->ignore($id)],
                'checklist_id' => ['required', 'exists:'.(new Checklist)->getTable().',id'],
                'period' => 'required|in:daily,monthly,quarterly,semiannual',
                'starts_on' => 'required|date_format:Y-m-d',
                'weekdays' => 'required|array|min:1',
                'weekdays.*' => 'required|integer|between:1,7|distinct',
                'active' => 'required|boolean',
            ],
        };
        $data = $request->validate($rules);
        foreach (['department_id' => Department::class, 'location_id' => Location::class, 'machine_id' => Machine::class, 'checklist_id' => Checklist::class] as $field => $model) {
            if (! empty($data[$field])) {
                abort_unless(Access::branch($model::findOrFail($data[$field])) === $branch, 422, 'Referensi data tidak sesuai.');
            }
        }
        if (! in_array($type, ['schedules', 'components'], true)) {
            $data['branch_id'] = $branch;
        }
        if ($type === 'checklists') {
            $data['items'] = array_values($data['items']);
        }
        if ($type === 'machines' && empty($data['qr_value'])) {
            $data['qr_value'] = Machine::formatQrValue($data['name'], $data['code']);
        }
        DB::transaction(function () use ($record, $data) {
            if ($record instanceof Machine && $record->exists) {
                $locked = Machine::whereKey($record->id)->lockForUpdate()->firstOrFail();
                abort_if((int) $data['department_id'] !== $locked->department_id, 422, 'Gunakan verifikasi perpindahan untuk mengubah bagian penanggung jawab.');
            }
            $before = $record->getAttributes();
            $record->fill($data)->save();
            ActivityLog::record($record, 'master_saved', ['before' => $before, 'after' => $record->getAttributes()]);

            if ($record instanceof Machine && ! empty($record->form_name)) {
                $checklist = Checklist::where('name', $record->form_name)->first();
                if ($checklist) {
                    MaintenanceSchedule::updateOrCreate(
                        ['machine_id' => $record->id, 'period' => 'daily'],
                        [
                            'checklist_id' => $checklist->id,
                            'starts_on' => today(),
                            'weekdays' => [1, 2, 3, 4, 5, 6, 7],
                            'active' => $record->active,
                        ]
                    );
                }
            }
        });

        return redirect()->route('admin', ['tab' => $type])->with('success', 'Data berhasil disimpan.');
    }

    public function toggleActive(Request $request, string $type, int $id)
    {
        abort_unless(isset(self::MODELS[$type]), 404);
        Access::require($type.'.update');
        $record = self::MODELS[$type]::findOrFail($id);
        Access::object($record);

        if ($type === 'users') {
            abort_unless(auth()->user()->is_admin, 403);
            if ($record->is_admin && $record->active) {
                $admins = User::where('is_admin', true)->where('active', true)->count();
                abort_if($admins <= 1, 422, 'Admin aktif terakhir harus dipertahankan.');
            }
        }

        DB::transaction(function () use ($record) {
            $record->active = ! $record->active;
            $record->save();

            if ($record instanceof Machine) {
                $record->schedules()->update(['active' => $record->active]);
            }

            ActivityLog::record($record, 'master_status_toggled', [
                'active' => $record->active,
                'status' => $record->active ? 'Diaktifkan' : 'Dinonaktifkan',
            ]);
        });

        $statusLabel = $record->active ? 'diaktifkan' : 'dinonaktifkan';

        return redirect()->route('admin', ['tab' => $type, 'status' => $request->query('status', 'all')])
            ->with('success', "Status data berhasil {$statusLabel}.");
    }

    private function saveUser(Request $request, User $record)
    {
        abort_unless(auth()->user()->is_admin, 403);
        $data = $request->validate([
            'name' => 'required|string|max:100',
            'email' => ['required', 'email', 'max:150', Rule::unique(User::class)->ignore($record->id)],
            'position' => 'nullable|string|max:100',
            'password' => [$record->exists ? 'nullable' : 'required', 'string', 'min:10', 'max:100'],
            'is_admin' => 'required|boolean',
            'active' => 'required|boolean',
            'department_id' => ['nullable', 'exists:'.(new Department)->getTable().',id'],
            'location_id' => ['nullable', 'exists:'.(new Location)->getTable().',id'],
            'modules' => 'sometimes|array',
            'modules.*' => ['required', 'string', 'distinct', Rule::in(array_keys(Modules::all()))],
        ]);
        $modules = $data['modules'] ?? [];
        unset($data['modules']);
        if (in_array('daily', $modules, true) && ! $data['is_admin']) {
            $request->validate(['department_id' => ['required', 'exists:'.(new Department)->getTable().',id']]);
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
            unset($before['password'], $before['remember_token']);
            $before['modules'] = $record->exists ? $record->modules()->pluck('module')->all() : [];
            $record->fill($data)->save();
            UserModule::where('user_id', $record->id)->delete();
            foreach ($data['is_admin'] ? [] : $modules as $module) {
                UserModule::create(['user_id' => $record->id, 'module' => $module]);
            }
            $after = $record->getAttributes();
            unset($after['password'], $after['remember_token']);
            $after['modules'] = $data['is_admin'] ? [] : $modules;
            ActivityLog::record($record, 'account_access_saved', ['before' => $before, 'after' => $after]);
        });

        return redirect()->route('admin', ['tab' => 'users'])->with('success', 'Akun dan akses modul berhasil disimpan.');
    }
}
