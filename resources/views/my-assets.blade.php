@extends('layout')
@section('title', 'Aset Mesin Saya · Line '.($user->location?->name ?? 'Tanggung Jawab'))
@section('content')

<div class="heading">
    <div>
        <span class="eyebrow">PENGELOLAAN ASET LINE</span>
        <h1>Aset Mesin Tanggung Jawab Saya</h1>
        <p class="muted">
            PIC: <strong>{{ $user->name }}</strong> ({{ $user->position ?? 'Kepala Regu' }}) · 
            Bagian: <strong>{{ $user->department?->name ?? 'Semua Bagian' }}</strong> · 
            Line Utama: <strong>{{ $user->location?->name ?? 'Belum Ditentukan (Semua Line)' }}</strong>
        </p>
    </div>
    @if(auth()->user()->can('inspections.scan'))
        <a class="button" href="{{ route('daily') }}">
            @include('partials.icon', ['name' => 'daily'])
            <span>+ Scan QR Kamera</span>
        </a>
    @endif
</div>

<!-- Statistik Kesiapan Aset Hari Ini -->
<div class="stats">
    <div class="stat">
        <small>Total Aset Mesin</small>
        <strong>{{ $allMachines->count() }}</strong>
    </div>
    <div class="stat">
        <small>Wajib Daily Hari Ini</small>
        <strong>{{ $dueMachines->count() }}</strong>
    </div>
    <div class="stat">
        <small>Sudah Diperiksa (OK)</small>
        <strong style="color: var(--green);">{{ $okCount }}</strong>
    </div>
    <div class="stat">
        <small>Temuan Masalah (NG)</small>
        <strong style="color: {{ $ngCount > 0 ? 'var(--red)' : 'var(--muted)' }};">{{ $ngCount }}</strong>
    </div>
    <div class="stat">
        <small>Belum Diperiksa</small>
        <strong style="color: {{ $pendingCount > 0 ? '#95620c' : 'var(--muted)' }};">{{ $pendingCount }}</strong>
    </div>
</div>

<div class="alert" style="margin-bottom: 20px; border-color: #dce6fc; background: #f0f5ff; color: var(--blue-dark);">
    @include('partials.icon', ['name' => 'daily'])
    <div>
        <strong>Verifikasi Kehadiran Fisik (Anti-Bypass QR):</strong>
        Untuk memastikan pemeriksaan dilakukan langsung di area mesin, pengisian formulir checklist harian wajib diawali dengan <strong>memindai stiker QR fisik pada mesin</strong> menggunakan kamera ponsel atau scanner barcode.
    </div>
</div>

<div class="card">
    <!-- Bilah Filter & Pencarian -->
    <form method="get" action="{{ route('my-assets') }}" class="filter-bar" style="margin-top: 0;">
        <div class="filter-group">
            <label for="filter-status">Status Pemeriksaan Hari Ini</label>
            <select id="filter-status" name="status" onchange="this.form.submit()">
                <option value="all" @selected($statusFilter === 'all')>Semua Mesin ({{ $allMachines->count() }})</option>
                <option value="pending" @selected($statusFilter === 'pending')>Belum Diperiksa Hari Ini ({{ $pendingCount }})</option>
                <option value="checked" @selected($statusFilter === 'checked')>Sudah Diperiksa ({{ $checkedToday->count() }})</option>
                <option value="ng" @selected($statusFilter === 'ng')>Ada Temuan Masalah (NG) ({{ $ngCount }})</option>
            </select>
        </div>

        <div class="filter-group">
            <label for="filter-loc">Line / Lokasi Fisik</label>
            <select id="filter-loc" name="location_id" onchange="this.form.submit()">
                <option value="">— Semua Line Tanggung Jawab —</option>
                @foreach($availableLocations as $l)
                    <option value="{{ $l->id }}" @selected($selectedLocation == $l->id)>{{ $l->area }} / {{ $l->name }}</option>
                @endforeach
            </select>
        </div>

        <div class="filter-group filter-search">
            <label for="filter-q">Pencarian Mesin</label>
            <div class="search-input-wrap">
                <input id="filter-q" type="search" name="q" value="{{ $q }}" placeholder="Ketik kode mesin, nama, merk...">
                <button type="submit" class="secondary">Cari</button>
                @if($q !== '' || $statusFilter !== 'all' || ($selectedLocation && $selectedLocation != $user->location_id))
                    <a href="{{ route('my-assets') }}" class="button secondary" title="Reset filter">Reset</a>
                @endif
            </div>
        </div>
    </form>

    <!-- Tabel Daftar Aset Mesin Line -->
    <div class="table-wrap">
        <table class="responsive-table" role="table">
            <thead role="rowgroup">
                <tr role="row">
                    <th scope="col" role="columnheader">Mesin</th>
                    <th scope="col" role="columnheader">Line / Lokasi</th>
                    <th scope="col" role="columnheader">Kondisi & Status</th>
                    <th scope="col" role="columnheader">Status Daily Check Hari Ini</th>
                    <th scope="col" role="columnheader">Aksi</th>
                </tr>
            </thead>
            <tbody role="rowgroup">
                @forelse($machines as $m)
                    @php($todayInspection = $m->inspections->first())
                    <tr role="row">
                        <td data-label="Mesin" role="cell">
                            <div>
                                <a href="{{ route('machines.show', $m) }}">
                                    <strong>{{ $m->code }}</strong>
                                </a>
                                <span class="muted"> · {{ $m->name }}</span>
                            </div>
                            @if($m->brand || $m->model)
                                <small class="muted">{{ $m->brand }} {{ $m->model ? '('.$m->model.')' : '' }}</small>
                            @endif
                            @if($m->form_name)
                                <div><small class="badge draft" style="margin-top: 3px;">{{ $m->form_name }}</small></div>
                            @endif
                        </td>

                        <td data-label="Line / Lokasi" role="cell">
                            <strong>{{ $m->location?->name ?? '—' }}</strong>
                            <small class="muted" style="display:block;">{{ $m->location?->area }}</small>
                            <small class="muted">PJ: {{ $m->department->name }}</small>
                        </td>

                        <td data-label="Kondisi & Status" role="cell">
                            <div>
                                <small>Kondisi: <strong>{{ $m->condition ?? 'Baik' }}</strong> ({{ $m->condition_percentage ?? 100 }}%)</small>
                            </div>
                            <div>
                                <small class="muted">Status: {{ $m->machine_status ?? 'Beroperasi' }}</small>
                            </div>
                            @if($m->power_kw || $m->capacity)
                                <div><small class="muted">{{ $m->power_kw ? $m->power_kw.' KW' : '' }} {{ $m->capacity ? '· '.$m->capacity : '' }}</small></div>
                            @endif
                        </td>

                        <td data-label="Status Daily Check Hari Ini" role="cell">
                            @if($todayInspection)
                                <div>
                                    @if($todayInspection->hasNg())
                                        <span class="badge rejected">ADA TEMUAN NG</span>
                                    @elseif($todayInspection->status === 'verified')
                                        <span class="badge verified">TERVERIFIKASI (OK)</span>
                                    @elseif($todayInspection->status === 'pending')
                                        <span class="badge pending">MENUNGGU VERIFIKASI</span>
                                    @else
                                        <span class="badge draft">DRAFT TERSIMPAN</span>
                                    @endif
                                </div>
                                <small class="muted" style="display:block; margin-top: 4px;">
                                    Diperiksa oleh: <strong>{{ $todayInspection->submitter?->name ?? $todayInspection->creator?->name }}</strong>
                                </small>
                            @elseif(!$m->active)
                                <span class="badge draft">Mesin Nonaktif</span>
                            @elseif($dueMachines->contains('id', $m->id))
                                <span class="badge pending" style="background:#fff3cd; color:#856404; border-color:#ffeeba;">
                                    BELUM DIISI HARI INI
                                </span>
                            @else
                                <span class="muted"><small>Tidak dijadwalkan hari ini</small></span>
                            @endif
                        </td>

                        <td data-label="Aksi" role="cell">
                            <div class="table-actions">
                                @if($todayInspection)
                                    <a class="button secondary small-btn" href="{{ route('inspections.show', $todayInspection) }}">
                                        Lihat Hasil
                                    </a>
                                @elseif($m->active && auth()->user()->can('inspections.scan') && $dueMachines->contains('id', $m->id))
                                    <a class="button small-btn" href="{{ route('daily') }}" title="Wajib scan QR code fisik pada mesin untuk membuka form pemeriksaan">
                                        @include('partials.icon', ['name' => 'daily'])
                                        <span>Scan QR</span>
                                    </a>
                                @endif

                                <a class="button secondary small-btn" href="{{ route('machines.show', $m) }}" title="Buku riwayat mesin">
                                    Riwayat
                                </a>

                                <a class="button secondary small-btn" href="{{ route('machines.print-qr', $m) }}" target="_blank" title="Cetak label QR code">
                                    QR
                                </a>
                            </div>
                        </td>
                    </tr>
                @empty
                    <tr role="row">
                        <td colspan="5" role="cell" class="empty-state">
                            @include('partials.icon', ['name' => 'machines'])
                            <h2>Tidak Ada Aset Mesin</h2>
                            <p>Tidak ditemukan mesin yang terdaftar di line penugasan Anda.</p>
                            @if($q !== '' || $statusFilter !== 'all' || ($selectedLocation && $selectedLocation != $user->location_id))
                                <a href="{{ route('my-assets') }}" class="button secondary small-btn" style="margin-top: 10px;">
                                    Tampilkan Semua Aset Tanggung Jawab Saya
                                </a>
                            @endif
                        </td>
                    </tr>
                @endforelse
            </tbody>
        </table>
    </div>
</div>

@endsection
