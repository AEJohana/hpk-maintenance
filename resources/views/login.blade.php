@extends('layout')
@section('title','Masuk')
@section('content')
<div class="login-shell">
    <div class="login-intro"><span class="eyebrow">HPK MAINTENANCE</span><h1>Pemeriksaan rutin.<br>Kerja lebih tenang.</h1><p class="muted">Catat kondisi mesin, pantau hasil pemeriksaan, dan kelola tindak lanjut dalam satu tempat.</p><div class="login-feature">@include('partials.icon', ['name' => 'daily'])<span>Scan mesin & isi checklist harian</span></div><div class="login-feature">@include('partials.icon', ['name' => 'monitoring'])<span>Pantau kondisi dan riwayat mesin</span></div></div>
    <section class="card login" aria-labelledby="login-title"><span class="module-icon">@include('partials.icon', ['name' => 'lock'])</span><h2 id="login-title">Selamat datang</h2><p class="muted">Masuk menggunakan akun pribadi Anda.</p><form method="post" action="{{ url('/login') }}" class="stack">@csrf<div><label for="email">Email akun</label><input id="email" type="email" name="email" value="{{ old('email') }}" required autocomplete="username" inputmode="email" placeholder="nama@perusahaan.com" @if($errors->has('email')) aria-invalid="true" @endif></div><div><label for="password">Kata sandi</label><input id="password" type="password" name="password" required autocomplete="current-password" placeholder="Masukkan kata sandi"></div><button class="login-submit">Masuk @include('partials.icon', ['name' => 'arrow'])</button></form><p class="login-help">Belum memiliki akses? Hubungi administrator.</p></section>
</div>
@endsection
