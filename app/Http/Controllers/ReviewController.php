<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Inspection;
use App\Models\Machine;
use App\Models\TransferRequest;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

class ReviewController extends Controller
{
    public function index()
    {
        return view('reviews', ['inspections' => Inspection::with('machine', 'department')->where('status', 'pending')->orderBy('submitted_at')->get(),
            'transfers' => TransferRequest::with('machine', 'fromDepartment', 'toDepartment')->where('status', 'pending')->get()]);
    }

    public function inspection(Request $request, Inspection $inspection)
    {
        $data = $request->validate(['decision' => 'required|in:verified,revision', 'reason' => 'required_if:decision,revision|nullable|string|max:2000']);
        DB::transaction(function () use ($inspection, $data) {
            $inspection = Inspection::whereKey($inspection->id)->lockForUpdate()->firstOrFail();
            abort_unless($inspection->status === 'pending', 409);
            $inspection->update(['status' => $data['decision'], 'verified_at' => $data['decision'] === 'verified' ? now() : null]);
            ActivityLog::record($inspection, $data['decision'], ['reason' => $data['reason'] ?? null]);
        });

        return back()->with('success', 'Keputusan pemeriksaan disimpan.');
    }

    public function transfer(Request $request, TransferRequest $transfer)
    {
        $data = $request->validate(['decision' => 'required|in:approved,rejected', 'reason' => 'required|string|max:2000']);
        DB::transaction(function () use ($transfer, $data) {
            $machine = Machine::whereKey($transfer->machine_id)->lockForUpdate()->firstOrFail();
            $transfer = TransferRequest::whereKey($transfer->id)->lockForUpdate()->firstOrFail();
            abort_unless($transfer->status === 'pending', 409);
            if ($data['decision'] === 'approved') {
                abort_unless($machine->department_id === $transfer->from_department_id, 409, 'Penanggung jawab sudah berubah. Tolak permintaan lama lalu ajukan kembali.');
                $machine->update(['department_id' => $transfer->to_department_id]);
            }
            $transfer->update(['status' => $data['decision'], 'reason' => $data['reason']]);
            ActivityLog::record($transfer, $data['decision'], $data);
        });

        return back()->with('success','Keputusan perpindahan disimpan.');
    }
}
