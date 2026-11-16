<?php

namespace App\Http\Controllers;

use App\Models\Branch;
use App\Models\Department;
use App\Models\Inspection;
use App\Models\Machine;
use App\Models\TransferRequest;
use App\Services\Access;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        Access::require('monitoring.view');
        $request->validate(['date' => 'nullable|date_format:Y-m-d|before_or_equal:today', 'department' => 'nullable|exists:departments,id']);
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
}
