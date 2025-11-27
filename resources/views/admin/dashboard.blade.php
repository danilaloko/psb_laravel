@extends('layouts.app')

@section('title', 'Админ панель')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Админ панель</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Общая статистика и аналитика</p>
    </div>
    
    <!-- Statistics Cards -->
    <div class="grid grid-cols-1 md:grid-cols-4 gap-6">
        <div class="bg-gradient-to-br from-blue-500 to-blue-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-blue-100 text-sm font-medium">Всего задач</p>
                    <p class="mt-2 text-3xl font-bold">{{ $totalTasks }}</p>
                </div>
                <div class="p-3 bg-white/20 rounded-lg">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2" />
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-green-500 to-green-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-green-100 text-sm font-medium">Пользователей</p>
                    <p class="mt-2 text-3xl font-bold">{{ $totalUsers }}</p>
                </div>
                <div class="p-3 bg-white/20 rounded-lg">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4.354a4 4 0 110 5.292M15 21H3v-1a6 6 0 0112 0v1zm0 0h6v-1a6 6 0 00-9-5.197M13 7a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-yellow-500 to-yellow-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-yellow-100 text-sm font-medium">Новых задач</p>
                    <p class="mt-2 text-3xl font-bold">{{ $newTasks }}</p>
                </div>
                <div class="p-3 bg-white/20 rounded-lg">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
        
        <div class="bg-gradient-to-br from-purple-500 to-purple-600 rounded-xl shadow-lg p-6 text-white">
            <div class="flex items-center justify-between">
                <div>
                    <p class="text-purple-100 text-sm font-medium">Завершено</p>
                    <p class="mt-2 text-3xl font-bold">{{ $completedTasks }}</p>
                </div>
                <div class="p-3 bg-white/20 rounded-lg">
                    <svg class="w-8 h-8" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
            </div>
        </div>
    </div>
    
    <!-- Charts Row -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Status Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Распределение по статусам</h2>
            <div style="height: 300px; position: relative;">
                <canvas id="statusChart"></canvas>
            </div>
        </div>
        
        <!-- Priority Chart -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Распределение по приоритетам</h2>
            <div style="height: 300px; position: relative;">
                <canvas id="priorityChart"></canvas>
            </div>
        </div>
    </div>
    
    <!-- Tasks Over Time Chart -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Задачи по дням (последние 30 дней)</h2>
        <div style="height: 300px; position: relative;">
            <canvas id="tasksOverTimeChart"></canvas>
        </div>
    </div>
    
    <!-- Additional Info -->
    <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
        <!-- Top Users -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Топ пользователей</h2>
            <div class="space-y-4">
                @forelse($topUsers as $user)
                    <div class="flex items-center justify-between p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <div class="flex items-center">
                            <div class="w-10 h-10 rounded-full bg-blue-500 flex items-center justify-center text-white font-medium">
                                {{ strtoupper(substr($user->name, 0, 1)) }}
                            </div>
                            <div class="ml-3">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ $user->name }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400">{{ $user->email }}</p>
                            </div>
                        </div>
                        <div class="text-right">
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $user->tasks_count }}</p>
                            <p class="text-xs text-gray-500 dark:text-gray-400">задач</p>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Нет данных</p>
                @endforelse
            </div>
        </div>
        
        <!-- Recent Tasks -->
        <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
            <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Последние задачи</h2>
            <div class="space-y-3">
                @forelse($recentTasks as $task)
                    <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <p class="text-sm font-medium text-gray-900 dark:text-white">{{ Str::limit($task->title, 40) }}</p>
                                <p class="text-xs text-gray-500 dark:text-gray-400 mt-1">
                                    {{ $task->executor->name }} • {{ $task->created_at->diffForHumans() }}
                                </p>
                            </div>
                            <span class="px-2 py-1 text-xs font-medium rounded-full
                                @if($task->status == 'new') bg-blue-100 text-blue-800 dark:bg-blue-900/20 dark:text-blue-400
                                @elseif($task->status == 'in_progress') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                                @elseif($task->status == 'completed') bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                                @else bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400
                                @endif">
                                {{ $task->status == 'new' ? 'Новая' : ($task->status == 'in_progress' ? 'В работе' : ($task->status == 'completed' ? 'Завершено' : 'Архив')) }}
                            </span>
                        </div>
                    </div>
                @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Нет задач</p>
                @endforelse
            </div>
        </div>
    </div>
</div>

@push('scripts')
<script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
<script>
    // Status Chart
    const statusCtx = document.getElementById('statusChart').getContext('2d');
    const statusChart = new Chart(statusCtx, {
        type: 'doughnut',
        data: {
            labels: ['Новые', 'В работе', 'Завершено', 'Отменено', 'Архив'],
            datasets: [{
                data: [
                    {{ $statusStats['new'] ?? 0 }},
                    {{ $statusStats['in_progress'] ?? 0 }},
                    {{ $statusStats['completed'] ?? 0 }},
                    {{ $statusStats['cancelled'] ?? 0 }},
                    {{ $statusStats['archived'] ?? 0 }}
                ],
                backgroundColor: [
                    'rgb(59, 130, 246)',
                    'rgb(234, 179, 8)',
                    'rgb(34, 197, 94)',
                    'rgb(239, 68, 68)',
                    'rgb(107, 114, 128)'
                ],
                borderWidth: 0
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    position: 'bottom',
                    labels: {
                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#374151',
                        padding: 15
                    }
                }
            }
        }
    });
    
    // Priority Chart
    const priorityCtx = document.getElementById('priorityChart').getContext('2d');
    const priorityChart = new Chart(priorityCtx, {
        type: 'bar',
        data: {
            labels: ['Низкий', 'Средний', 'Высокий', 'Срочный'],
            datasets: [{
                label: 'Количество задач',
                data: [
                    {{ $priorityStats['low'] ?? 0 }},
                    {{ $priorityStats['medium'] ?? 0 }},
                    {{ $priorityStats['high'] ?? 0 }},
                    {{ $priorityStats['urgent'] ?? 0 }}
                ],
                backgroundColor: [
                    'rgb(34, 197, 94)',
                    'rgb(234, 179, 8)',
                    'rgb(239, 68, 68)',
                    'rgb(220, 38, 38)'
                ],
                borderRadius: 8
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    display: false
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#374151'
                    },
                    grid: {
                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#374151'
                    },
                    grid: {
                        display: false
                    }
                }
            }
        }
    });
    
    // Tasks Over Time Chart
    const tasksData = @json($tasksByDay);
    const tasksOverTimeCtx = document.getElementById('tasksOverTimeChart').getContext('2d');
    const tasksOverTimeChart = new Chart(tasksOverTimeCtx, {
        type: 'line',
        data: {
            labels: tasksData.map(item => {
                const date = new Date(item.date);
                return date.toLocaleDateString('ru-RU', { day: '2-digit', month: '2-digit' });
            }),
            datasets: [{
                label: 'Задач создано',
                data: tasksData.map(item => item.count),
                borderColor: 'rgb(59, 130, 246)',
                backgroundColor: 'rgba(59, 130, 246, 0.1)',
                tension: 0.4,
                fill: true
            }]
        },
        options: {
            responsive: true,
            maintainAspectRatio: false,
            plugins: {
                legend: {
                    labels: {
                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#374151'
                    }
                }
            },
            scales: {
                y: {
                    beginAtZero: true,
                    ticks: {
                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#374151'
                    },
                    grid: {
                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                    }
                },
                x: {
                    ticks: {
                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? '#fff' : '#374151'
                    },
                    grid: {
                        color: window.matchMedia('(prefers-color-scheme: dark)').matches ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)'
                    }
                }
            }
        }
    });
</script>
@endpush
@endsection

