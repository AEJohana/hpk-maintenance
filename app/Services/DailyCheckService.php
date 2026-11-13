<?php

namespace App\Services;

use App\Models\ActivityLog;
use App\Models\Inspection;
use App\Models\Machine;
use App\Models\TransferRequest;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class DailyCheckService
{
    public function open(Machine $machine): Inspection
    {
        return DB::transaction(function () use ($machine) {
            $machine = Machine::whereKey($machine->id)->lockForUpdate()->firstOrFail();
            $existing = Inspection::where('machine_id', $machine->id)->whereDate('inspection_date', today())->where('period', 'daily')->first();
            if ($existing) {
                return $existing;
            }
            $schedule = $machine->schedules()->with('checklist')->where('period', 'daily')->first();
            if (! $machine->active || ! $schedule?->dueOn(today())) {
                throw ValidationException::withMessages(['machine' => 'Mesin tidak aktif atau tidak memiliki jadwal daily hari ini.']);
            }
            $inspection = Inspection::create(['machine_id' => $machine->id, 'department_id' => auth()->user()->department_id,
                'owner_department_id' => $machine->department_id, 'user_id' => auth()->id(), 'inspection_date' => today(),
                'snapshot' => ['name' => $schedule->checklist->name, 'items' => $schedule->checklist->items]]);
            ActivityLog::record($inspection, 'draft_created');

            return $inspection;
        });
    }

    public function requestTransfer(Machine $machine): void
    {
        // Caller holds the machine row lock; this also serializes different submitting accounts.
        $to = auth()->user()->department_id;
        if ($machine->department_id === $to) {
            return;
        }
        $transfer = TransferRequest::firstOrCreate(['machine_id' => $machine->id, 'to_department_id' => $to, 'status' => 'pending'],
            ['from_department_id' => $machine->department_id, 'user_id' => auth()->id()]);
        if ($transfer->wasRecentlyCreated) {
            ActivityLog::record($transfer, 'transfer_requested');
        }
    }
}
