@extends('layout')
@section('title','Modul Saya')
@section('content')
<div class="heading"><div><span class="eyebrow">RUANG KERJA</span><h1>Modul saya</h1><p class="muted">Selamat datang, {{ auth()->user()->name }}. Pilih modul untuk mulai bekerja.</p></div><span class="workspace-badge">@include('partials.icon', ['name' => 'verification']) Daily check mesin</span></div>
@if($pendingTransfers)<div class="alert success" role="status"><div><strong>{{ $pendingTransfers }} permintaan perpindahan mesin menunggu keputusan.</strong> <a href="{{ route('reviews') }}#transfers">Buka verifikasi perpindahan →</a></div></div>@endif
<div class="module-grid">
    @forelse($modules as $key=>$module)
        <a class="card module-card" href="{{ route($module['route']) }}">
            <span class="module-icon module-icon-{{ $key }}">@include('partials.icon', ['name' => $key])</span>
            <h2>{{ $module['name'] }}</h2><p class="muted">{{ $module['description'] }}</p>
            <span class="module-action">Buka modul @include('partials.icon', ['name' => 'arrow'])</span>
        </a>
    @empty
        <div class="card empty-state">@include('partials.icon', ['name' => 'home'])<h2>Modul belum tersedia</h2><p>Belum ada modul yang diberikan. Hubungi administrator untuk mendapatkan akses.</p></div>
    @endforelse
    @if(auth()->user()->is_admin)
        <a class="card module-card" href="{{ route('access') }}"><span class="module-icon module-icon-users">@include('partials.icon', ['name' => 'users'])</span><h2>Akun & Akses Modul</h2><p class="muted">Kelola akun pribadi, jabatan, bagian dan modul yang boleh digunakan.</p><span class="module-action">Kelola akun @include('partials.icon', ['name' => 'arrow'])</span></a>
    @endif
</div>
@endsection
