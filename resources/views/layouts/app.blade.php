<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>{{ config('app.name', 'Kuis Online') }} - @yield('title', 'Beranda')</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/css/bootstrap.min.css" rel="stylesheet">
    <link href="{{ asset('css/brand-theme.css') }}" rel="stylesheet">

    <!-- PWA -->
    <link rel="manifest" href="{{ asset('manifest.json') }}">
    <meta name="theme-color" content="#DC3B1C">
    <link rel="apple-touch-icon" href="{{ asset('icons/icon-192.png') }}">
    <meta name="apple-mobile-web-app-capable" content="yes">
    <meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">
    <meta name="apple-mobile-web-app-title" content="Kuis Online">
</head>
<body class="bg-light">
    <nav class="navbar navbar-expand-lg navbar-dark bg-dark mb-4">
        <div class="container">
            <a class="navbar-brand" href="{{ route('dashboard') }}">Kuis Online</a>
            @auth
                <div class="d-flex text-light align-items-center gap-3">
                    @if (auth()->user()->role === 'admin')
                        <a href="{{ route('admin.dashboard') }}" class="btn btn-sm btn-outline-light">⚙ Admin</a>
                    @endif
                    @if (auth()->user()->role === 'mahasiswa')
                        <div class="dropdown">
                            <button class="btn btn-sm btn-outline-light position-relative" type="button" data-bs-toggle="dropdown">
                                🔔
                                @if (auth()->user()->unreadNotifications->count() > 0)
                                    <span class="position-absolute top-0 start-100 translate-middle badge rounded-pill bg-danger">
                                        {{ auth()->user()->unreadNotifications->count() }}
                                    </span>
                                @endif
                            </button>
                            <ul class="dropdown-menu dropdown-menu-end" style="min-width: 320px;">
                                @forelse (auth()->user()->unreadNotifications->take(10) as $notif)
                                    <li>
                                        <form method="POST" action="{{ route('mahasiswa.notifications.read', $notif->id) }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item text-wrap small">
                                                🆕 {{ $notif->data['message'] ?? 'Notifikasi baru' }}
                                            </button>
                                        </form>
                                    </li>
                                @empty
                                    <li><span class="dropdown-item-text text-muted small">Tidak ada notifikasi baru.</span></li>
                                @endforelse
                                @if (auth()->user()->unreadNotifications->count() > 0)
                                    <li><hr class="dropdown-divider"></li>
                                    <li>
                                        <form method="POST" action="{{ route('mahasiswa.notifications.readAll') }}">
                                            @csrf
                                            <button type="submit" class="dropdown-item small text-muted">Tandai semua dibaca</button>
                                        </form>
                                    </li>
                                @endif
                            </ul>
                        </div>
                    @endif
                    <span>{{ auth()->user()->name }} ({{ auth()->user()->role }})</span>
                    <a href="{{ route('profile.edit') }}" class="btn btn-sm btn-outline-light">Edit Profil</a>
                    <form method="POST" action="{{ route('logout') }}">
                        @csrf
                        <button class="btn btn-sm btn-outline-light">Keluar</button>
                    </form>
                </div>
            @endauth
        </div>
    </nav>

    <div class="container pb-5">
        @if (session('status'))
            <div class="alert alert-success">{{ session('status') }}</div>
        @endif

        @if ($errors->any())
            <div class="alert alert-danger">
                <ul class="mb-0">
                    @foreach ($errors->all() as $error)
                        <li>{{ $error }}</li>
                    @endforeach
                </ul>
            </div>
        @endif

        @yield('content')
    </div>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        if ('serviceWorker' in navigator) {
            navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(() => {});
        }
    </script>
    @yield('scripts')
</body>
</html>
