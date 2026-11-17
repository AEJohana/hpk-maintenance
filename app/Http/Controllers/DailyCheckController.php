<?php

namespace App\Http\Controllers;

use App\Models\ActivityLog;
use App\Models\Inspection;
use App\Models\Machine;
use App\Models\TransferRequest;
use App\Services\Access;
use App\Services\DailyCheckService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;

class DailyCheckController extends Controller
{
    public function scan(Request $request, DailyCheckService $service)
    {
        Access::require('inspections.scan');
        $data = $request->validate(['code' => 'required|string|max:255', 'lookup' => 'required|in:qr,code']);
        $code = trim($data['code']);
        if ($data['lookup'] === 'qr') {
            $machine = Access::scope(Machine::query())->where('qr_value', $code)->first();
            if (! $machine && str_contains($code, ' / ')) {
                $segments = array_map('trim', explode(' / ', $code));
                if (isset($segments[1]) && $segments[1] !== '') {
                    $machine = Access::scope(Machine::query())->where('code', $segments[1])->first();
                }
            }
        } else {
            $machine = Access::scope(Machine::query())->where('code', $code)->first();
        }
        if (! $machine) {
            return back()->withErrors(['code' => 'QR atau kode mesin belum terdaftar.']);
        }
        abort_unless($machine->active, 422, 'Mesin tidak aktif.');
        $inspection = $service->open($machine);
        // Scanning grants read access to this machine's current inspection, including
        // a result already submitted by another department. Editing stays restricted.
        $request->session()->put('scanned_inspections.'.$inspection->id, auth()->id());

        return redirect()->route('inspections.show', $inspection);
    }

    public function show(Inspection $inspection)
    {
        $this->authorizeView($inspection);
        $logs = ActivityLog::with('user')->where('subject_type', 'inspections')->where('subject_id', $inspection->id)->orderBy('id')->get();

        $pendingTransfer = TransferRequest::where('machine_id', $inspection->machine_id)->where('to_department_id', auth()->user()->department_id)->where('status', 'pending')->first();

        return view('inspections.show', compact('inspection', 'logs', 'pendingTransfer'));
    }

    private function authorizeView(Inspection $inspection): void
    {
        Access::require('inspections.view');
        Access::object($inspection);
        $u = auth()->user();
        abort_unless($u->is_admin || $u->hasModule('verification') || ($u->hasModule('monitoring') && ! $u->hasModule('daily')) || in_array($u->department_id, [$inspection->department_id, $inspection->owner_department_id, $inspection->machine->department_id], true)
            || session('scanned_inspections.'.$inspection->id) === $u->id, 403);
    }

    public function save(Request $request, Inspection $inspection, DailyCheckService $service)
    {
        $request->validate(['action' => 'required|in:draft,submit']);
        Access::require($request->action === 'submit' ? 'inspections.submit' : 'inspections.draft');
        Access::object($inspection);
        if ($request->action === 'submit' && $inspection->machine->department_id !== auth()->user()->department_id) {
            Access::require('transfers.request');
        }
        DB::transaction(function () use ($request, $inspection, $service) {
            $machine = Machine::whereKey($inspection->machine_id)->lockForUpdate()->firstOrFail();
            $inspection = Inspection::whereKey($inspection->id)->lockForUpdate()->firstOrFail();
            abort_unless($inspection->department_id === auth()->user()->department_id, 403, 'Draft dimiliki bagian lain.');
            abort_unless(in_array($inspection->status, ['draft', 'revision']), 409, 'Hasil sudah dikirim.');
            abort_unless($machine->active, 422, 'Mesin tidak aktif.');
            abort_unless($inspection->status === 'revision' || $inspection->inspection_date->isToday(), 422, 'Draft hanya dapat dikirim pada tanggal pemeriksaan.');
            $submit = $request->input('action') === 'submit';
            $rules = [];
            foreach ($inspection->snapshot['items'] as $i => $item) {
                $rules["answers.$i.value"] = array_merge([$submit ? 'required' : 'nullable'], $item['type'] === 'condition' ? [Rule::in(['OK', 'NG'])] : ['numeric']);
                $rules["answers.$i.note"] = [($submit && $request->input("answers.$i.value") === 'NG') ? 'required' : 'nullable', 'string', 'max:2000'];
                $rules["photos.$i"] = ['nullable', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'];
            }
            $validated = $request->validate($rules);
            $photos = $inspection->photos ?? [];
            foreach ($inspection->snapshot['items'] as $i => $item) {
                if ($request->hasFile("photos.$i")) {
                    $photos[$i] = $request->file("photos.$i")->store('inspection-photos', 'local');
                }
            }
            $inspection->update(['answers' => $validated['answers'] ?? [], 'photos' => $photos,
                'status' => $submit ? 'pending' : $inspection->status, 'submitted_by' => $submit ? auth()->id() : $inspection->submitted_by, 'submitted_at' => $submit ? now() : $inspection->submitted_at]);
            ActivityLog::record($inspection, $submit ? 'submitted' : 'draft_saved', ['answers' => $inspection->answers, 'photos' => $photos]);
            if ($submit) {
                $service->requestTransfer($machine);
            }
        });

        return back()->with('success', 'Hasil pemeriksaan disimpan.');
    }

    public function transfer(Inspection $inspection, DailyCheckService $service)
    {
        Access::require('transfers.request');
        Access::object($inspection);
        DB::transaction(function () use ($inspection, $service) {
            $machine = Machine::whereKey($inspection->machine_id)->lockForUpdate()->firstOrFail();
            abort_unless($machine->active, 422);
            $service->requestTransfer($machine);
        });

        return back()->with('success', 'Permintaan perpindahan dicatat jika bagian penanggung jawab berbeda.');
    }

    public function photo(Inspection $inspection, int $index)
    {
        $this->authorizeView($inspection);
        $path = $inspection->photos[$index] ?? null;
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return response()->file(Storage::disk('local')->path($path), ['Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff']);
    }
}
