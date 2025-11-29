@extends('layouts.app')

@section('title', 'Админ панель')

@section('content')
<div class="space-y-6">
    <!-- Header -->
    <div>
        <h1 class="text-3xl font-bold text-gray-900 dark:text-white">Админ панель</h1>
        <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">Общая статистика и аналитика</p>
    </div>
    
    <!-- Основные статистические карточки -->
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
                    <p class="text-yellow-100 text-sm font-medium">Активных задач</p>
                    <p class="mt-2 text-3xl font-bold">{{ $activeTasks }}</p>
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

    <!-- Количество по приоритетам -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Количество задач по приоритетам</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-4">
            <div class="p-4 bg-red-900/10 rounded-lg border border-white">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-white">Срочный</span>
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                    </svg>
                </div>
                <p class="text-3xl font-bold text-white">{{ $priorityStats['urgent'] ?? 0 }}</p>
            </div>

            <div class="p-4 bg-orange-900/10 rounded-lg border border-white">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-white">Высокий</span>
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6" />
                    </svg>
                </div>
                <p class="text-3xl font-bold text-white">{{ $priorityStats['high'] ?? 0 }}</p>
            </div>

            <div class="p-4 bg-yellow-900/10 rounded-lg border border-white">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-white">Средний</span>
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                </div>
                <p class="text-3xl font-bold text-white">{{ $priorityStats['medium'] ?? 0 }}</p>
            </div>

            <div class="p-4 bg-green-900/10 rounded-lg border border-white">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-sm font-medium text-white">Низкий</span>
                    <svg class="w-5 h-5 text-white" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7" />
                    </svg>
                </div>
                <p class="text-3xl font-bold text-white">{{ $priorityStats['low'] ?? 0 }}</p>
            </div>
        </div>
    </div>

    <!-- Просроченные задачи -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
        <h2 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Просроченные задачи</h2>
        @if($overdueTasksList->count() > 0)
            <div class="space-y-3">
                @foreach($overdueTasksList as $task)
                    <div class="p-4 bg-red-900/10 border border-red-800 rounded-lg">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <div class="flex items-center gap-2 mb-2">
                                    <h3 class="text-sm font-semibold text-gray-900 dark:text-white">{{ Str::limit($task->title, 60) }}</h3>
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
                                        @else bg-gray-100 text-gray-800 dark:bg-gray-900/20 dark:text-gray-400
                                        @endif">
                                        {{ $task->status == 'new' ? 'Новая' : ($task->status == 'in_progress' ? 'В работе' : 'Другое') }}
                                    </span>
                                </div>
                                <div class="flex items-center gap-4 text-xs text-gray-600 dark:text-gray-400">
                                    <span>Исполнитель: <span class="font-medium">{{ $task->executor->name ?? 'Не назначен' }}</span></span>
                                    <span>Дедлайн: <span class="font-medium text-red-600 dark:text-red-400">{{ $task->due_date->format('d.m.Y H:i') }}</span></span>
                                    <span>Просрочено на: <span class="font-medium text-red-600 dark:text-red-400">{{ $task->due_date->diffForHumans() }}</span></span>
                                </div>
                            </div>
                        </div>
                    </div>
                @endforeach
            </div>
        @else
            <div class="text-center py-8">
                <svg class="w-16 h-16 mx-auto text-green-500 mb-4" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <p class="text-gray-500 dark:text-gray-400">Нет просроченных задач</p>
            </div>
        @endif
    </div>
    
    <!-- Графики -->
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

    <!-- AI Метрики -->
    <div>
        <h2 class="text-xl font-bold text-gray-900 dark:text-white mb-4">AI Метрики</h2>
        <div class="grid grid-cols-1 md:grid-cols-4 gap-6 mb-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Успешность</h3>
                    <div class="p-2 bg-green-100 dark:bg-green-900/20 rounded-lg">
                        <svg class="w-5 h-5 text-green-600 dark:text-green-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $aiSuccessRate }}%</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">{{ $successfulGenerations }} / {{ $totalGenerations }}</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Среднее время</h3>
                    <div class="p-2 bg-blue-100 dark:bg-blue-900/20 rounded-lg">
                        <svg class="w-5 h-5 text-blue-600 dark:text-blue-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $avgGenerationTime }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">секунд</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Общая стоимость</h3>
                    <div class="p-2 bg-purple-100 dark:bg-purple-900/20 rounded-lg">
                        <svg class="w-5 h-5 text-purple-600 dark:text-purple-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-gray-900 dark:text-white">${{ $totalAICost }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">всего</p>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <div class="flex items-center justify-between mb-4">
                    <h3 class="text-sm font-medium text-gray-500 dark:text-gray-400">Ошибок</h3>
                    <div class="p-2 bg-red-100 dark:bg-red-900/20 rounded-lg">
                        <svg class="w-5 h-5 text-red-600 dark:text-red-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                    </div>
                </div>
                <p class="text-3xl font-bold text-gray-900 dark:text-white">{{ $failedGenerations }}</p>
                <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">из {{ $totalGenerations }}</p>
            </div>
        </div>

        <!-- Статистика по типам и моделям -->
        <div class="grid grid-cols-1 lg:grid-cols-2 gap-6">
            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Генерации по типам</h3>
                <div style="height: 250px; position: relative;">
                    <canvas id="generationsByTypeChart"></canvas>
                </div>
            </div>

            <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-6">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Статистика по моделям</h3>
                <div class="space-y-3">
                    @forelse($generationsByModel as $model => $stats)
                    <div class="p-3 bg-gray-50 dark:bg-gray-700/50 rounded-lg">
                        <div class="flex items-center justify-between mb-2">
                            <span class="font-medium text-gray-900 dark:text-white">{{ $model }}</span>
                            <span class="text-sm text-gray-500 dark:text-gray-400">{{ $stats['count'] }} генераций</span>
                        </div>
                        <div class="flex items-center justify-between text-sm">
                            <span class="text-gray-600 dark:text-gray-400">Среднее время: {{ $stats['avg_time'] }}с</span>
                            <span class="text-gray-600 dark:text-gray-400">Стоимость: ${{ $stats['total_cost'] }}</span>
                        </div>
                    </div>
                    @empty
                    <p class="text-sm text-gray-500 dark:text-gray-400">Нет данных</p>
                    @endforelse
                </div>
            </div>
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
                            <p class="text-lg font-bold text-gray-900 dark:text-white">{{ $user->assigned_tasks_count }}</p>
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
                                    {{ $task->executor->name ?? 'нет' }} • {{ $task->created_at->diffForHumans() }}
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
    const isDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    const textColor = isDark ? '#fff' : '#374151';
    const gridColor = isDark ? 'rgba(255, 255, 255, 0.1)' : 'rgba(0, 0, 0, 0.1)';

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
                        color: textColor,
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
                    ticks: { color: textColor },
                    grid: { color: gridColor }
                },
                x: {
                    ticks: { color: textColor },
                    grid: { display: false }
                }
            }
        }
    });

    // Генерации по типам
    const generationsByTypeData = @json($generationsByType);
    const generationsByTypeCtx = document.getElementById('generationsByTypeChart').getContext('2d');
    const generationsByTypeChart = new Chart(generationsByTypeCtx, {
        type: 'doughnut',
        data: {
            labels: Object.keys(generationsByTypeData).map(key => key === 'reply' ? 'Ответы' : 'Анализ'),
            datasets: [{
                data: Object.values(generationsByTypeData),
                backgroundColor: [
                    'rgb(59, 130, 246)',
                    'rgb(168, 85, 247)'
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
                        color: textColor,
                        padding: 15
                    }
                }
            }
        }
    });
</script>
@endpush
@endsection
