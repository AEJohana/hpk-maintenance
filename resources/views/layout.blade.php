<!doctype html>
<html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>@yield('title', 'Daily Check') · HPK Maintenance</title><link rel="stylesheet" href="{{ asset('maintenance.css') }}"></head>
<body>
<header><a class="brand" href="{{ url('/') }}"><span class="logo">H</span> HPK <span>Maintenance</span></a>@auth<div class="account">{{ auth()->user()->name }} <small>{{ auth()->user()->role }}</small></div>@endauth</header>
@auth<nav><a href="{{ route('dashboard') }}">Monitoring</a>@if(auth()->user()->role==='department')<a href="{{ route('daily') }}">Scan Daily Check</a>@endif @if(in_array(auth()->user()->role,['admin','maintenance']))<a href="{{ route('reviews') }}">Verifikasi</a>@endif @if(auth()->user()->role==='admin')<a href="{{ route('admin') }}">Administrasi</a>@endif<form method="post" action="{{ route('logout') }}">@csrf<button class="quiet">Keluar</button></form></nav>@endauth
<main>@if(session('success'))<div class="alert success" role="status">{{ session('success') }}</div>@endif
@if($errors->any())<div class="alert error" role="alert"><strong>Periksa kembali isian Anda.</strong><ul>@foreach($errors->all() as $error)<li>{{ $error }}</li>@endforeach</ul></div>@endif
@yield('content')</main><footer>HPK Maintenance · Pemeriksaan mesin & riwayat kondisi</footer>@stack('scripts')</body></html>
