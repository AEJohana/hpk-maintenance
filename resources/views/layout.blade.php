<!doctype html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1, viewport-fit=cover">
    <meta name="theme-color" content="#f6f8fc">
    <title>@yield('title', 'Daily Check') · HPK Maintenance</title>
    <link rel="stylesheet" href="{{ asset('maintenance.css') }}?v={{ filemtime(public_path('maintenance.css')) }}">
    <script src="{{ asset('maintenance.js') }}?v={{ filemtime(public_path('maintenance.js')) }}" defer></script>
</head>
<body>
<a class="skip-link" href="#main-content">Langsung ke konten</a>

<div class="app-layout {{ auth()->check() ? 'has-sidebar' : 'no-sidebar' }}">
    @auth
        <!-- Header khusus tampilan mobile/tablet -->
        <header class="mobile-header">
            <a class="brand" href="{{ url('/') }}" aria-label="HPK Maintenance, beranda">
                <span class="logo" aria-hidden="true">H<span></span></span>
                <span class="brand-copy"><strong>HPK <span>Maintenance</span></strong></span>
            </a>
            <div class="mobile-header-actions">
                <span class="avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <button type="button" class="menu-toggle secondary" aria-controls="primary-navigation" aria-expanded="false">
                    @include('partials.icon', ['name' => 'menu'])<span>Menu</span>
                </button>
            </div>
        </header>

        <!-- Sidebar Navigasi (Desktop: Kolom Kiri Permanen | Mobile: Drawer Terbuka Saat Tombol Menu Diklik) -->
        <aside class="site-sidebar" id="primary-navigation" aria-label="Navigasi utama">
            <div class="sidebar-brand">
                <a class="brand" href="{{ url('/') }}" aria-label="HPK Maintenance, beranda">
                    <span class="logo" aria-hidden="true">H<span></span></span>
                    <span class="brand-copy"><strong>HPK <span>Maintenance</span></strong><small>Daily check mesin</small></span>
                </a>
            </div>

            <div class="sidebar-account">
                <span class="avatar" aria-hidden="true">{{ mb_substr(auth()->user()->name, 0, 1) }}</span>
                <div class="account-info">
                    <strong>{{ auth()->user()->name }}</strong>
                    <small>{{ auth()->user()->is_admin ? 'Administrator' : auth()->user()->position }} {{ auth()->user()->department?->name }}@if(auth()->user()->location) · {{ auth()->user()->location->name }}@endif</small>
                </div>
            </div>

            <nav class="sidebar-nav">
                <a href="{{ route('home') }}" @if(request()->routeIs('home')) aria-current="page" @endif>
                    @include('partials.icon', ['name' => 'home'])<span>Modul Saya</span>
                </a>
                @if(auth()->user()->department_id || auth()->user()->location_id || auth()->user()->is_admin)
                    <a href="{{ route('my-assets') }}" @if(request()->routeIs('my-assets')) aria-current="page" @endif>
                        @include('partials.icon', ['name' => 'machines'])<span>Aset Line Saya</span>
                    </a>
                @endif
                @can('monitoring.view')
                    <a href="{{ route('dashboard') }}" @if(request()->routeIs('dashboard', 'machines.show') && !request()->routeIs('my-assets')) aria-current="page" @endif>
                        @include('partials.icon', ['name' => 'monitoring'])<span>Monitoring</span>
                    </a>
                @endcan
                @can('inspections.scan')
                    <a href="{{ route('daily') }}" @if(request()->routeIs('daily', 'inspections.*')) aria-current="page" @endif>
                        @include('partials.icon', ['name' => 'daily'])<span>Pemeriksaan Harian</span>
                    </a>
                @endcan
                @if(auth()->user()->can('inspections.verify') || auth()->user()->can('inspections.revise') || auth()->user()->can('transfers.decide'))
                    <a href="{{ route('reviews') }}" @if(request()->routeIs('reviews') || (request()->routeIs('inspections.*') && !auth()->user()->can('inspections.scan'))) aria-current="page" @endif>
                        @include('partials.icon', ['name' => 'verification'])<span>Verifikasi</span>
                        @if(auth()->user()->can('transfers.decide'))
                            @php($navPendingTransfers = \App\Services\Access::scope(\App\Models\TransferRequest::query())->where('status', 'pending')->count())
                            @if($navPendingTransfers)
                                <span class="badge" aria-label="{{ $navPendingTransfers }} perpindahan menunggu">{{ $navPendingTransfers }}</span>
                            @endif
                        @endif
                    </a>
                @endif
                @if(collect(['departments','locations','machines','checklists','schedules','users'])->contains(fn($m)=>auth()->user()->can($m.'.view')))
                    <a href="{{ route('admin') }}" @if(request()->routeIs('admin') && request('tab') !== 'users') aria-current="page" @endif>
                        @include('partials.icon', ['name' => 'master'])<span>Administrasi</span>
                    </a>
                @endif
                @if(auth()->user()->is_admin)
                    <a href="{{ route('access') }}" @if(request()->routeIs('access') || (request()->routeIs('admin') && request('tab') === 'users')) aria-current="page" @endif>
                        @include('partials.icon', ['name' => 'users'])<span>Akun & Akses Modul</span>
                    </a>
                @endif
                @can('audit.view')
                    <a href="{{ route('audit') }}" @if(request()->routeIs('audit')) aria-current="page" @endif>
                        @include('partials.icon', ['name' => 'audit'])<span>Audit</span>
                    </a>
                @endcan
            </nav>

            <div class="sidebar-footer">
                <form method="post" action="{{ route('logout') }}">
                    @csrf
                    <button class="quiet logout-btn">
                        @include('partials.icon', ['name' => 'logout'])<span>Keluar</span>
                    </button>
                </form>
            </div>
        </aside>
    @endauth

    <div class="content-wrapper">
        <main id="main-content" tabindex="-1">
            @if(session('success'))
                <div class="alert success" role="status">
                    @include('partials.icon', ['name' => 'verification'])
                    <div>{{ session('success') }}</div>
                </div>
            @endif
            @if($errors->any())
                <div class="alert error" role="alert">
                    <div>
                        <strong>Periksa kembali isian Anda.</strong>
                        <ul>
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                </div>
            @endif
            @yield('content')
        </main>
        <footer class="site-footer">
            <span>HPK Maintenance</span>
            <span>Pemeriksaan mesin & riwayat kondisi</span>
        </footer>
    </div>
</div>

@stack('scripts')
</body>
</html>
