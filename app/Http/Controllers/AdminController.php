<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Checklist;
use App\Models\Department;
use App\Models\Location;
use App\Models\Machine;
use App\Models\MaintenanceSchedule;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rule;

class AdminController extends Controller
{
    private const MODELS = ['departments' => Department::class, 'locations' => Location::class, 'machines' => Machine::class, 'checklists' => Checklist::class, 'schedules' => MaintenanceSchedule::class, 'users' => User::class];

    public function index(Request $request)
    {
        $tab = $request->query('tab', 'machines');
        abort_unless(isset(self::MODELS[$tab]), 404);
        $record = $request->filled('edit') ? self::MODELS[$tab]::findOrFail($request->query('edit')) : null;

        return view('admin', ['tab' => $tab, 'record' => $record, 'records' => self::MODELS[$tab]::orderByDesc('id')->paginate(20)->withQueryString(),
            'departments' => Department::all(), 'locations' => Location::all(), 'machines' => Machine::all(), 'checklists' => Checklist::all()]);
    }

    public function save(Request $request, string $type)
    {
        abort_unless(isset(self::MODELS[$type]), 404);
        $request->validate(['id' => 'nullable|integer']);
        $record = $request->filled('id') ? self::MODELS[$type]::findOrFail($request->id) : new (self::MODELS[$type]);
        $id = $record->id;
        $rules = match ($type) {
            'departments' => ['name' => ['required', 'string', 'max:100', Rule::unique('departments')->ignore($id)]],
            'locations' => ['name' => 'required|string|max:100', 'area' => 'required|string|max:100'],
            'machines' => ['code' => ['required', 'string', 'max:100', Rule::unique('machines')->ignore($id)], 'name' => 'required|string|max:150',
                'qr_value' => ['required', 'string', 'max:255', Rule::unique('machines')->ignore($id)], 'department_id' => 'required|exists:departments,id', 'location_id' => 'required|exists:locations,id', 'active' => 'required|boolean'],
            'checklists' => ['name' => 'required|string|max:150', 'items' => 'required|array|min:1|max:100', 'items.*.label' => 'required|string|max:200',
                'items.*.type' => 'required|in:condition,number', 'items.*.unit' => 'nullable|string|max:30'],
            'schedules' => ['machine_id' => ['required', 'exists:machines,id', Rule::unique('maintenance_schedules')->where(fn ($q) => $q->where('period', $request->period))->ignore($id)],
                'checklist_id' => 'required|exists:checklists,id', 'period' => 'required|in:daily,monthly,quarterly,semiannual', 'starts_on' => 'required|date_format:Y-m-d',
                'weekdays' => 'required|array|min:1', 'weekdays.*' => 'required|integer|between:1,7|distinct', 'active' => 'required|boolean'],
            'users' => ['name' => 'required|string|max:100', 'email' => ['required', 'email', 'max:150', Rule::unique('users')->ignore($id)],
                'password' => [$id ? 'nullable' : 'required', 'string', 'min:10', 'max:100'], 'role' => 'required|in:admin,maintenance,department', 'department_id' => 'required_if:role,department|nullable|exists:departments,id'],
        };
        $data = $request->validate($rules);
        if ($type === 'users') {
            if (! empty($data['password'])) {
                $data['password'] = Hash::make($data['password']);
            } else {
                unset($data['password']);
            }
            if ($data['role'] !== 'department') {
                $data['department_id'] = null;
            }
            abort_if($id === auth()->id() && $data['role'] !== 'admin', 422, 'Tidak dapat mengganti peran admin yang sedang digunakan.');
        }
        if ($type === 'checklists') {
            $data['items'] = array_values(array_map(fn ($i) => ['label' => $i['label'], 'type' => $i['type'], 'unit' => $i['unit'] ?? ''], $data['items']));
        }
        DB::transaction(function () use ($record, $data) {
            if ($record instanceof Machine && $record->exists) {
                $locked = Machine::whereKey($record->id)->lockForUpdate()->firstOrFail();
                abort_if((int) $data['department_id'] !== $locked->department_id, 422, 'Gunakan alur perpindahan untuk mengubah bagian penanggung jawab.');
            }
            $record->fill($data)->save();
            ActivityLog::record($record, 'master_saved');
        });

        return redirect()->route('admin', ['tab' => $type])->with('success','Data berhasil disimpan.');
    }
}
