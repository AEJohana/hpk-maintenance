@php($tabs = ['departments' => 'Bagian', 'locations' => 'Lokasi', 'machines' => 'Mesin', 'checklists' => 'Checklist', 'schedules' => 'Jadwal', 'users' => 'Akun'])
@extends('layout')
@section('title', 'Administrasi · '.($tabs[$tab] ?? 'Master'))
@section('content')

<div class="heading">
    <div>
        <h1>Administrasi Sistem</h1>
        <p class="muted">Kelola master data mesin, penempatan lokasi, template formulir checklist, jadwal pemeriksaan, dan akun.</p>
    </div>
</div>

<!-- Navigasi Tab Master Data -->
<nav class="tabs" aria-label="Master data">
    @foreach($tabs as $key => $label)
        @continue(!auth()->user()->can($key.'.view'))
        <a @if($key === $tab) aria-current="page" @endif class="{{ $key === $tab ? 'active' : '' }}" href="{{ route('admin', ['tab' => $key]) }}">
            {{ $label }}
        </a>
    @endforeach
</nav>

<!-- Notifikasi Alert -->
@if(session('success'))
    <div class="alert success" role="status">
        @include('partials.icon', ['name' => 'check'])
        <span>{{ session('success') }}</span>
    </div>
@endif

@if(session('error'))
    <div class="alert error" role="alert">
        @include('partials.icon', ['name' => 'alert'])
        <span>{{ session('error') }}</span>
    </div>
@endif

@if($errors->any())
    <div class="alert error" role="alert">
        @include('partials.icon', ['name' => 'alert'])
        <div>
            <strong>Terjadi kesalahan pengisian formulir:</strong>
            <ul>
                @foreach($errors->all() as $error)
                    <li>{{ $error }}</li>
                @endforeach
            </ul>
        </div>
    </div>
@endif

<!-- Pemisahan Antara Tampilan Form Input/Edit dengan Tampilan Daftar Tabel -->
@if($mode === 'form')
    @include('admin.partials.form-view')
@else
    @include('admin.partials.list-view')
@endif

@endsection

@push('scripts')
@if($tab === 'checklists' && $mode === 'form')
<script>
let itemIndex = {{ count(old('items', $record?->items ?? [[]])) }};
const addItemBtn = document.getElementById('add-item');
if (addItemBtn) {
    addItemBtn.addEventListener('click', () => {
        const i = itemIndex++;
        const row = document.createElement('div');
        row.className = 'checkrow';
        row.style.cssText = 'background: #f8fafc; padding: 12px; border-radius: 8px; border: 1px solid var(--line); margin-bottom: 10px;';
        row.innerHTML = `
            <div>
                <label style="font-size: 11px; margin-bottom: 4px;">Butir Pemeriksaan</label>
                <input name="items[${i}][label]" placeholder="Item pemeriksaan" required>
            </div>
            <div>
                <label style="font-size: 11px; margin-bottom: 4px;">Jenis Penilaian</label>
                <select name="items[${i}][type]">
                    <option value="condition">Kondisi (OK / NG)</option>
                    <option value="number">Angka (Pengukuran)</option>
                </select>
            </div>
            <div>
                <label style="font-size: 11px; margin-bottom: 4px;">Satuan (Jika Angka)</label>
                <input name="items[${i}][unit]" placeholder="Bar / Volt / Ampere / mm">
            </div>
            <div style="display: flex; align-items: flex-end;">
                <button type="button" class="button secondary small-btn btn-deactivate" onclick="this.closest('.checkrow').remove()" title="Hapus butir ini">Hapus</button>
            </div>
        `;
        document.getElementById('checklist-items').appendChild(row);
    });
}
</script>
@endif
@endpush
