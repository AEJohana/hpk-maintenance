<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\Inspection;
use App\Models\Machine;
use App\Models\TransferRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;

class DashboardController extends Controller
{
    public function index(Request $request)
    {
        $request->validate(['date' => 'nullable|date_format:Y-m-d|before_or_equal:today', 'department' => 'nullable|exists:departments,id']);
        $date = Carbon::parse($request->query('date', today()->toDateString()));
        $department = auth()->user()->role === 'department' ? auth()->user()->department_id : $request->query('department');
        $machines = Machine::with(['department', 'location', 'schedules.checklist', 'inspections' => fn ($q) => $q->whereDate('inspection_date', $date)])
            ->when($department, fn ($q) => $q->where('department_id', $department))->orderBy('code')->get();
        $due = $machines->filter(fn ($m) => $m->active && $m->schedules->contains(fn ($s) => $s->dueOn($date)));
        $results = Inspection::with('machine', 'department')->whereDate('inspection_date', $date)
            ->when($department, fn ($q) => $q->where(fn ($q) => $q->where('department_id', $department)->orWhere('owner_department_id', $department)
                ->orWhereHas('machine', fn ($m) => $m->where('department_id', $department))))->get();

        return view('dashboard', compact('machines', 'due', 'results', 'date', 'department') + ['departments' => Department::all()]);
    }

    public function machine(Machine $machine)
    {
        abort_unless(auth()->user()->role !== 'department' || auth()->user()->department_id === $machine->department_id, 403);
        $history = $machine->inspections()->with('department', 'ownerDepartment')->latest('inspection_date')->paginate(20);
        $transfers = TransferRequest::with('fromDepartment', 'toDepartment')->where('machine_id', $machine->id)->latest()->get();

        return view('machine',compact('machine','history','transfers'));
    }
}
