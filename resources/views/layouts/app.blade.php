<!doctype html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', 'Website Monitor')</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script defer src="https://cdn.jsdelivr.net/npm/alpinejs@3.x.x/dist/cdn.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body class="bg-slate-50 text-slate-900">
@auth
    <div class="min-h-screen lg:flex">
        <aside class="bg-white border-r border-slate-200 lg:w-64">
            <div class="p-5 border-b">
                <a href="{{ route('dashboard') }}" class="font-bold text-lg">Website Monitor</a>
            </div>
            <nav class="p-3 space-y-1">
                @foreach ([['Dashboard','dashboard'],['Sites','sites.index'],['Security','security.index'],['Analytics','analytics.index'],['Incidents','incidents.index'],['Settings','settings.index']] as [$label,$route])
                    <a href="{{ route($route) }}" class="block px-3 py-2 rounded-md text-sm {{ request()->routeIs($route) ? 'bg-slate-900 text-white' : 'hover:bg-slate-100' }}">{{ $label }}</a>
                @endforeach
            </nav>
            <div class="p-4 text-xs text-slate-500">{{ auth()->user()->email }}</div>
        </aside>
        <main class="flex-1">
            <header class="bg-white border-b px-6 py-4 flex items-center justify-between">
                <h1 class="text-xl font-semibold">@yield('title', 'Dashboard')</h1>
                <form method="POST" action="{{ route('logout') }}">@csrf <button class="text-sm text-slate-600 hover:text-slate-950">Logout</button></form>
            </header>
            <div class="p-6">
                @if (session('success')) <div class="mb-4 rounded-md border border-emerald-200 bg-emerald-50 p-3 text-sm text-emerald-800">{{ session('success') }}</div> @endif
                @if ($errors->any()) <div class="mb-4 rounded-md border border-red-200 bg-red-50 p-3 text-sm text-red-800">{{ $errors->first() }}</div> @endif
                @yield('content')
            </div>
        </main>
    </div>
@else
    @yield('content')
@endauth
@stack('scripts')
@hasSection('autoRefresh')
    <script>
        window.setTimeout(function () {
            if (!document.hidden) {
                window.location.reload();
            }
        }, 60000);
    </script>
@endif
</body>
</html>
