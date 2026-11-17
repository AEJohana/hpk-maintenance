@extends('layout')
@section('title',$machine->code)
@section('content')
<div class="heading">
    <div>
        <h1>{{ $machine->code }} · {{ $machine->name }}</h1>
        <p>{{ $machine->location?->area }} / {{ $machine->location?->name }} · {{ $machine->department->name }} · {{ $machine->active?'Aktif':'Nonaktif' }}</p>
    </div>
</div>

<div class="card">
    <h2>Spesifikasi Teknis & QR Code</h2>
    <div class="grid">
        <div><strong>Nilai QR:</strong> <code>{{ $machine->qr_value }}</code></div>
        <div><strong>Merk & Model:</strong> {{ $machine->brand ?? '—' }} {{ $machine->model ? '('.$machine->model.')' : '' }}</div>
    </div>
    <div class="grid">
        <div><strong>Daya:</strong> {{ $machine->power_kw ? $machine->power_kw.' KW' : '—' }} / {{ $machine->power_hp ? $machine->power_hp.' HP' : '—' }}</div>
        <div><strong>Kapasitas:</strong> {{ $machine->capacity ?? '—' }}</div>
    </div>
    <div class="grid">
        <div><strong>Kondisi:</strong> {{ $machine->condition ?? 'Baik' }} ({{ $machine->condition_percentage ?? 100 }}%) · {{ $machine->machine_status ?? 'Beroperasi' }}</div>
        <div><strong>No. Seri / Aset:</strong> {{ $machine->serial_number ?? '—' }} / {{ $machine->asset_number ?? '—' }}</div>
    </div>
    @if($machine->form_name)
        <div><strong>Form Checklist:</strong> {{ $machine->form_name }}</div>
    @endif
    <div style="margin-top: 16px; display: flex; gap: 10px; flex-wrap: wrap;">
        <a href="{{ route('machines.print-qr', $machine) }}" target="_blank" class="button">Cetak Label QR</a>
        <a href="{{ route('machines.qr-svg', $machine) }}" download class="button secondary">Unduh SVG</a>
        @if($machine->image_url)
            <a href="{{ $machine->image_url }}" target="_blank" rel="noopener" class="button secondary">Lihat Gambar Mesin (Google Drive)</a>
        @endif
    </div>
</div>

<div class="card">
    <h2>Jadwal pemeriksaan</h2>
    @forelse($machine->schedules()->with('checklist')->get() as $s)
        <p><strong>{{ ['daily'=>'Daily','monthly'=>'Monthly','quarterly'=>'Setiap 3 bulan','semiannual'=>'Setiap 6 bulan'][$s->period] }}</strong> · {{ $s->checklist->name }} · {{ $s->active?'Aktif':'Nonaktif' }} @if($s->period!=='daily')<span class="badge">Pengisian belum diaktifkan</span>@endif</p>
    @empty
        <p>Belum ada jadwal.</p>
    @endforelse
</div>

<div class="card">
    <h2>Riwayat pemeriksaan</h2>
    <div class="table-wrap">
        <table class="responsive-table" role="table">
            <thead role="rowgroup">
                <tr role="row">
                    <th scope="col" role="columnheader">Tanggal</th>
                    <th scope="col" role="columnheader">Bagian pengisi</th>
                    <th scope="col" role="columnheader">Penanggung jawab saat diperiksa</th>
                    <th scope="col" role="columnheader">Status</th>
                </tr>
            </thead>
            <tbody role="rowgroup">
                @forelse($history as $i)
                    <tr role="row">
                        <td data-label="Tanggal" role="cell"><div><a href="{{ route('inspections.show',$i) }}">{{ $i->inspection_date->format('d/m/Y') }}</a></div></td>
                        <td data-label="Bagian pengisi" role="cell"><div>{{ $i->department->name }}</div></td>
                        <td data-label="Penanggung jawab saat diperiksa" role="cell"><div>{{ $i->ownerDepartment->name }}</div></td>
                        <td data-label="Status" role="cell"><div>@include('status',['status'=>$i->status])</div></td>
                    </tr>
                @empty
                    <tr role="row"><td colspan="4" role="cell">Belum ada pemeriksaan.</td></tr>
                @endforelse
            </tbody>
        </table>
    </div>
    {{ $history->links() }}
</div>

<div class="card">
    <h2>Riwayat perpindahan</h2>
    @forelse($transfers as $t)
        <div class="item">{{ $t->fromDepartment->name }} → {{ $t->toDepartment->name }} · @include('status',['status'=>$t->status])<small>{{ $t->updated_at->format('d/m/Y H:i') }}</small><p>{{ $t->reason }}</p></div>
    @empty
        <p>Belum ada perpindahan.</p>
    @endforelse
</div>
@endsection
