<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <title>@yield('title', config('app.name', 'Laravel'))</title>
    <link rel="preconnect" href="https://fonts.bunny.net">
    <link href="https://fonts.bunny.net/css?family=inter:400,500,600,700" rel="stylesheet" />
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    @stack('styles')
</head>
<body class="bg-gray-50 dark:bg-gray-900 font-sans antialiased">
    <div class="min-h-screen flex flex-col">
        @auth
            <!-- Header -->
            <header class="bg-white dark:bg-gray-800 border-b border-gray-200 dark:border-gray-700">
                <div class="px-6 py-4">
                    <div class="flex items-center justify-between">
                        <!-- Logo -->
                        <h1 class="text-xl font-bold text-gray-900 dark:text-white">ПСБ.почта</h1>

                        <!-- Navigation -->
                        <nav class="flex items-center space-x-6">
                            @if(auth()->user()->isAdmin())
                                <a href="{{ route('admin.dashboard') }}" class="text-sm font-medium transition-colors {{ request()->routeIs('admin.*') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}">
                                    Админская панель
                                </a>
                            @endif
                            <a href="{{ route('dashboard') }}" class="text-sm font-medium transition-colors {{ request()->routeIs('dashboard*') ? 'text-blue-600 dark:text-blue-400' : 'text-gray-700 dark:text-gray-300 hover:text-gray-900 dark:hover:text-white' }}">
                                Входящие письма
                            </a>
                        </nav>

                        <!-- User Menu -->
                        <div class="flex items-center space-x-4">
                            <!-- Desktop User Info -->
                            <div class="hidden sm:flex items-center space-x-3">
                                <div>
                                    <p class="text-sm font-medium text-gray-900 dark:text-white">{{ auth()->user()->name }}</p>
                                    <p class="text-xs text-gray-500 dark:text-gray-400">{{ auth()->user()->email }}</p>
                                </div>

                                <!-- Desktop Logout Button -->
                                <form method="POST" action="{{ route('logout') }}" class="ml-3">
                                    @csrf
                                    <button type="submit" class="px-4 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                        Выйти
                                    </button>
                                </form>
                            </div>

                            <!-- Mobile Logout Button -->
                            <div class="sm:hidden">
                                <form method="POST" action="{{ route('logout') }}">
                                    @csrf
                                    <button type="submit" class="px-3 py-2 text-sm font-medium text-gray-700 dark:text-gray-300 hover:bg-gray-100 dark:hover:bg-gray-700 rounded-lg transition-colors">
                                        Выйти
                                    </button>
                                </form>
                            </div>
                        </div>
                    </div>
                </div>
            </header>

            <!-- Main Content -->
            <main class="flex-1 p-8">
                @if(session('success'))
                    <div class="mb-6 bg-green-50 dark:bg-green-900/20 border border-green-200 dark:border-green-800 text-green-800 dark:text-green-200 px-4 py-3 rounded-lg">
                        {{ session('success') }}
                    </div>
                @endif

                @if($errors->any())
                    <div class="mb-6 bg-red-50 dark:bg-red-900/20 border border-red-200 dark:border-red-800 text-red-800 dark:text-red-200 px-4 py-3 rounded-lg">
                        <ul class="list-disc list-inside">
                            @foreach($errors->all() as $error)
                                <li>{{ $error }}</li>
                            @endforeach
                        </ul>
                    </div>
                @endif

                @yield('content')
            </main>
        @else
            @yield('content')
        @endauth
    </div>
    
    @stack('scripts')
</body>
</html>

