@extends('layouts.app')

@section('title', 'Дашборд - Входящие письма')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                {{ ($isAdmin ?? false) ? 'Все задачи' : 'Входящие письма' }}
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                {{ ($isAdmin ?? false) ? 'Администрирование всех задач системы' : 'Управление задачами и письмами' }}
            </p>
        </div>
        <a href="{{ route('tasks.create') }}" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors flex items-center gap-2">
            <svg class="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6v6m0 0v6m0-6h6m-6 0H6" />
            </svg>
            Добавить задачу
        </a>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Всего</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['total'] }}</p>
                </div>
                <div class="p-3 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
                    <svg class="w-6 h-6 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Новые</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['new'] }}</p>
                </div>
                <div class="p-3 bg-green-100 dark:bg-green-900/20 rounded-lg">
                    <svg class="w-6 h-6 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">В работе</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['in_progress'] }}</p>
                </div>
                <div class="p-3 bg-yellow-100 dark:bg-yellow-900/20 rounded-lg">
                    <svg class="w-6 h-6 text-yellow-600 dark:text-yellow-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Завершено</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['completed'] }}</p>
                </div>
                <div class="p-3 bg-purple-100 dark:bg-purple-900/20 rounded-lg">
                    <svg class="w-6 h-6 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>

        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-sm font-medium text-gray-600 dark:text-gray-400">Отменено</p>
                    <p class="mt-2 text-3xl font-bold text-gray-900 dark:text-white">{{ $stats['cancelled'] ?? 0 }}</p>
                </div>
                <div class="p-3 bg-red-100 dark:bg-red-900/20 rounded-lg">
                    <svg class="w-6 h-6 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                    </svg>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Top 5 Tasks -->
    @if(isset($topTasks) && $topTasks->count() > 0)
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Топ 5 приоритетных задач</h2>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Задачи с высоким приоритетом и истекающим сроком</p>
        </div>
        
        <div class="divide-y divide-gray-200 dark:divide-gray-700">
            @foreach($topTasks as $task)
                <a href="{{ route('dashboard.task.show', $task) }}" class="block hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                    <div class="px-6 py-4">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-3 mb-2">
                                    <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $task->title }}</h3>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full
                                        @if($task->priority == 'urgent') bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400
                                        @elseif($task->priority == 'high') bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400
                                        @elseif($task->priority == 'medium') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                                        @else bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                                        @endif">
                                        @if($task->priority == 'urgent') Срочный
                                        @elseif($task->priority == 'high') Высокий
                                        @elseif($task->priority == 'medium') Средний
                                        @else Низкий
                                        @endif
                                    </span>
                                    <span class="px-2 py-1 text-xs font-medium rounded-full
                                        @if($task->status == 'new') bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400
                                        @elseif($task->status == 'in_progress') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                                        @elseif($task->status == 'completed') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400
                                        @endif">
                                        @if($task->status == 'new') Новая
                                        @elseif($task->status == 'in_progress') В работе
                                        @elseif($task->status == 'completed') Завершено
                                        @elseif($task->status == 'cancelled') Отменена
                                        @else Архив
                                        @endif
                                    </span>
                                    @if($task->due_date)
                                        <span class="px-2 py-1 text-xs font-medium rounded-full
                                            @if($task->due_date->isPast()) bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400
                                            @elseif($task->due_date->isToday()) bg-orange-100 text-orange-800 dark:bg-orange-900/20 dark:text-orange-400
                                            @else bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                                            @endif">
                                            @if($task->due_date->isPast())
                                                Просрочено: {{ $task->due_date->format('d.m.Y') }}
                                            @elseif($task->due_date->isToday())
                                                Сегодня
                                            @else
                                                До {{ $task->due_date->format('d.m.Y') }}
                                            @endif
                                        </span>
                                    @endif
                                </div>
                                <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{{ Str::limit($task->content, 150) }}</p>
                                <div class="mt-3 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                    @if($task->thread)
                                        <span>Поток: {{ $task->thread->title }}</span>
                                        <span>•</span>
                                    @endif
                                    @if($isAdmin ?? false && $task->executor)
                                        <span>Исполнитель: {{ $task->executor->name }}</span>
                                        @if($task->executor->department)
                                            <span>•</span>
                                            <span>Подразделение: {{ $task->executor->department->name }}</span>
                                        @endif
                                        <span>•</span>
                                    @endif
                                    @if($task->creator)
                                        <span>От: {{ $task->creator->name }}</span>
                                        <span>•</span>
                                    @endif
                                    <span>{{ $task->created_at->format('d.m.Y H:i') }}</span>
                                </div>
                            </div>
                        </div>
                    </div>
                </a>
            @endforeach
        </div>
    </div>
    @endif
    
    <!-- Filters -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <form method="GET" action="{{ route('dashboard') }}" class="flex flex-wrap gap-4">
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Статус</label>
                <select name="status" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Все статусы</option>
                    <option value="new" {{ request('status') == 'new' ? 'selected' : '' }}>Новые</option>
                    <option value="in_progress" {{ request('status') == 'in_progress' ? 'selected' : '' }}>В работе</option>
                    <option value="completed" {{ request('status') == 'completed' ? 'selected' : '' }}>Завершено</option>
                    <option value="cancelled" {{ request('status') == 'cancelled' ? 'selected' : '' }}>Отменено</option>
                    <option value="archived" {{ request('status') == 'archived' ? 'selected' : '' }}>Архив</option>
                </select>
            </div>

            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Приоритет</label>
                <select name="priority" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Все приоритеты</option>
                    <option value="low" {{ request('priority') == 'low' ? 'selected' : '' }}>Низкий</option>
                    <option value="medium" {{ request('priority') == 'medium' ? 'selected' : '' }}>Средний</option>
                    <option value="high" {{ request('priority') == 'high' ? 'selected' : '' }}>Высокий</option>
                    <option value="urgent" {{ request('priority') == 'urgent' ? 'selected' : '' }}>Срочный</option>
                </select>
            </div>

            @if(count($executors ?? []) > 0)
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Исполнитель</label>
                <select name="executor_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Все исполнители</option>
                    @foreach($executors as $executor)
                    <option value="{{ $executor->id }}" {{ request('executor_id') == $executor->id ? 'selected' : '' }}>
                        {{ $executor->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            @if(count($departments ?? []) > 0)
            <div class="flex-1 min-w-[200px]">
                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">Подразделение</label>
                <select name="department_id" class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                    <option value="">Все подразделения</option>
                    @foreach($departments as $department)
                    <option value="{{ $department->id }}" {{ request('department_id') == $department->id ? 'selected' : '' }}>
                        {{ $department->name }}
                    </option>
                    @endforeach
                </select>
            </div>
            @endif

            <div class="flex items-end gap-2">
                <button type="submit" class="px-4 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Применить
                </button>
                @if(request()->hasAny(['status', 'priority', 'executor_id', 'department_id']))
                <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                    Сбросить фильтры
                </a>
                @endif
            </div>
        </form>
    </div>
    
    <!-- Tasks List -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white">Список задач</h2>
        </div>
        
        @if($tasks->count() > 0)
            <div class="divide-y divide-gray-200 dark:divide-gray-700">
                @foreach($tasks as $task)
                    <a href="{{ route('dashboard.task.show', $task) }}" class="block hover:bg-gray-50 dark:hover:bg-gray-700/50 transition-colors">
                        <div class="px-6 py-4">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center gap-3 mb-2">
                                        <h3 class="text-lg font-medium text-gray-900 dark:text-white">{{ $task->title }}</h3>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full
                                            @if($task->priority == 'high') bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400
                                            @elseif($task->priority == 'medium') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                                            @else bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                                            @endif">
                                            @if($task->priority == 'urgent') Срочный
                                            @elseif($task->priority == 'high') Высокий
                                            @elseif($task->priority == 'medium') Средний
                                            @else Низкий
                                            @endif
                                        </span>
                                        <span class="px-2 py-1 text-xs font-medium rounded-full
                                            @if($task->status == 'new') bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400
                                            @elseif($task->status == 'in_progress') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                                            @elseif($task->status == 'completed') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                                            @else bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400
                                            @endif">
                                            @if($task->status == 'new') Новая
                                            @elseif($task->status == 'in_progress') В работе
                                            @elseif($task->status == 'completed') Завершено
                                            @elseif($task->status == 'cancelled') Отменена
                                            @else Архив
                                            @endif
                                        </span>
                                    </div>
                                    <p class="text-sm text-gray-600 dark:text-gray-400 line-clamp-2">{{ Str::limit($task->content, 150) }}</p>
                                    <div class="mt-3 flex items-center gap-4 text-xs text-gray-500 dark:text-gray-400">
                                        @if($task->thread)
                                            <span>Поток: {{ $task->thread->title }}</span>
                                            <span>•</span>
                                        @endif
                                        @if($isAdmin ?? false && $task->executor)
                                            <span>Исполнитель: {{ $task->executor->name }}</span>
                                            @if($task->executor->department)
                                                <span>•</span>
                                                <span>Подразделение: {{ $task->executor->department->name }}</span>
                                            @endif
                                            <span>•</span>
                                        @endif
                                        @if($task->creator)
                                            <span>От: {{ $task->creator->name }}</span>
                                            <span>•</span>
                                        @endif
                                        <span>{{ $task->created_at->format('d.m.Y H:i') }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </a>
                @endforeach
            </div>
            
            <!-- Pagination -->
            <div class="px-6 py-4 border-t border-gray-200 dark:border-gray-700">
                {{ $tasks->links() }}
            </div>
        @else
            <div class="px-6 py-12 text-center">
                <svg class="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 8l7.89 5.26a2 2 0 002.22 0L21 8M5 19h14a2 2 0 002-2V7a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z" />
                </svg>
                <h3 class="mt-2 text-sm font-medium text-gray-900 dark:text-white">Нет задач</h3>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Пока нет входящих писем</p>
            </div>
        @endif
    </div>
</div>
@endsection

