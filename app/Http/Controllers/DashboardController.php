<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Inspection;
use App\Models\Location;
use App\Models\Machine;
use App\Models\TransferRequest;
use App\Services\Access;
use App\Services\DailyCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        Access::require('monitoring.view');
        $request->validate(['date' => 'nullable|date_format:Y-m-d|before_or_equal:today', 'department' => ['nullable', 'exists:'.(new Department)->getTable().',id']]);
        if ($request->filled('department')) {
            Access::object(Department::findOrFail($request->department));
        }
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        $department = auth()->user()->hasModule('daily') && ! auth()->user()->is_admin && ! auth()->user()->hasModule('verification') ? auth()->user()->department_id : $request->query('department');
        $branch = auth()->user()->role === 'admin' ? null : auth()->user()->branch_id;
        if ($branch) {
            Branch::findOrFail($branch);
        }
        $machines = Access::scope(Machine::query())->with(['department', 'location', 'schedules.checklist', 'inspections' => fn ($q) => $q->whereDate('inspection_date', $date)])
            ->when($department, fn ($q) => $q->where('department_id', $department))->when($branch, fn ($q) => $q->where('branch_id', $branch))->orderBy('code')->get();
        $due = $machines->filter(fn ($m) => $m->active && $m->schedules->contains(fn ($s) => $s->dueOn($date)));
        $results = Access::scope(Inspection::query())->with('machine', 'department')->whereDate('inspection_date', $date)
            ->when($department, fn ($q) => $q->where(fn ($q) => $q->where('department_id', $department)->orWhere('owner_department_id', $department)
                ->orWhereHas('machine', fn ($m) => $m->where('department_id', $department))))->when($branch, fn ($q) => $q->whereHas('machine', fn ($m) => $m->where('branch_id', $branch)))->get();

        return view('dashboard', ['branches' => Branch::all(), 'branch' => $branch] + compact('machines', 'due', 'results', 'date', 'department') + ['departments' => Access::scope(Department::query())->get()]);
    }

    public function machine(Machine $machine)
    {
        Access::require('machines.history');
        Access::object($machine);
        abort_unless(! auth()->user()->hasModule('daily') || auth()->user()->is_admin || auth()->user()->hasModule('verification') || auth()->user()->department_id === $machine->department_id, 403);
        $history = $machine->inspections()->with('department', 'ownerDepartment')->latest('inspection_date')->paginate(20);
        $transfers = TransferRequest::with('fromDepartment', 'toDepartment')->where('machine_id', $machine->id)->latest()->get();

        return view('machine', compact('machine', 'history', 'transfers'));
    }

    public function printQr(Machine $machine)
    {
        Access::object($machine);
        $qrSvg = $machine->getQrSvg(240);

        return view('machines.print-qr', compact('machine', 'qrSvg'));
    }

    public function qrCodeSvg(Machine $machine)
    {
        Access::object($machine);
        $qrSvg = $machine->getQrSvg(300);

        return response($qrSvg, 200, [
            'Content-Type' => 'image/svg+xml',
            'Content-Disposition' => 'inline; filename="qr-'.$machine->code.'.svg"',
        ]);
    }

    public function myAssets(Request $request)
    {
        $user = auth()->user();
        abort_unless($user->is_admin || $user->hasModule('daily') || $user->hasModule('monitoring') || $user->department_id || $user->location_id, 403);

        $selectedLocation = $request->query('location_id', $user->location_id);
        $statusFilter = $request->query('status', 'all');
        $q = trim((string) $request->query('q', ''));
        $today = today();

        $query = Machine::with(['department', 'location', 'schedules.checklist', 'inspections' => fn ($iq) => $iq->whereDate('inspection_date', $today)])
            ->where('active', true);

        // Scope to user's assigned line/department
        if (! $user->is_admin) {
            $query->where(function ($sq) use ($user, $selectedLocation) {
                if ($selectedLocation) {
                    $sq->where('location_id', $selectedLocation);
                } elseif ($user->location_id && $user->department_id) {
                    $sq->where('location_id', $user->location_id)
                        ->orWhere('department_id', $user->department_id);
                } elseif ($user->location_id) {
                    $sq->where('location_id', $user->location_id);
                } elseif ($user->department_id) {
                    $sq->where('department_id', $user->department_id);
                }
            });
        } elseif ($selectedLocation) {
            $query->where('location_id', $selectedLocation);
        }

        if ($q !== '') {
            $query->where(fn ($sq) => $sq->where('code', 'like', "%{$q}%")
                ->orWhere('name', 'like', "%{$q}%")
                ->orWhere('brand', 'like', "%{$q}%"));
        }

        $allMachines = $query->orderBy('code')->get();

        // Calculate statistics for today
        $dueMachines = $allMachines->filter(fn ($m) => $m->schedules->contains(fn ($s) => $s->dueOn($today)));
        $checkedToday = $allMachines->filter(fn ($m) => $m->inspections->isNotEmpty());
        $ngCount = $checkedToday->filter(fn ($m) => $m->inspections->contains(fn ($i) => $i->hasNg()))->count();
        $okCount = $checkedToday->filter(fn ($m) => ! $m->inspections->contains(fn ($i) => $i->hasNg()))->count();
        $pendingCount = $allMachines->filter(fn ($m) => $dueMachines->contains('id', $m->id) && $m->inspections->isEmpty())->count();

        // Filter by inspection status if requested
        $machines = match ($statusFilter) {
            'checked' => $checkedToday,
            'ok' => $checkedToday->filter(fn ($m) => ! $m->inspections->contains(fn ($i) => $i->hasNg())),
            'ng' => $checkedToday->filter(fn ($m) => $m->inspections->contains(fn ($i) => $i->hasNg())),
            'pending' => $allMachines->filter(fn ($m) => $dueMachines->contains('id', $m->id) && $m->inspections->isEmpty()),
            default => $allMachines,
        };

        $availableLocations = Location::where('active', true)->orderBy('name')->get();

        return view('my-assets', compact(
            'user',
            'machines',
            'allMachines',
            'dueMachines',
            'checkedToday',
            'ngCount',
            'okCount',
            'pendingCount',
            'selectedLocation',
            'statusFilter',
            'q',
            'availableLocations',
            'today'
        ));
    }
}
