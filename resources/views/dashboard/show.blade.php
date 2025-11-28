@extends('layouts.app')

@section('title', $task->title)

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Back Button -->
    <a href="{{ route('dashboard') }}" class="inline-flex items-center text-sm text-gray-600 dark:text-gray-400 hover:text-gray-900 dark:hover:text-white">
        <svg class="w-4 h-4 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7" />
        </svg>
        Назад к списку
    </a>
    
    <!-- Task Card -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700">
        <div class="px-6 py-4 border-b border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-700/50">
            <div class="flex items-start justify-between">
                <div class="flex-1">
                    <h1 class="text-2xl font-bold text-gray-900 dark:text-white mb-3">{{ $task->title }}</h1>
                    <div class="flex items-center gap-3 flex-wrap">
                        <span class="px-3 py-1 text-sm font-medium rounded-full
                            @if($task->priority == 'high') bg-red-100 text-red-800 dark:bg-red-900/20 dark:text-red-400
                            @elseif($task->priority == 'medium') bg-yellow-100 text-yellow-800 dark:bg-yellow-900/20 dark:text-yellow-400
                            @else bg-green-100 text-green-800 dark:bg-green-900/20 dark:text-green-400
                            @endif">
                            Приоритет: @if($task->priority == 'urgent') Срочный
                                @elseif($task->priority == 'high') Высокий
                                @elseif($task->priority == 'medium') Средний
                                @else Низкий
                                @endif
                        </span>
                        <span class="px-3 py-1 text-sm font-medium rounded-full
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
                </div>
            </div>
        </div>
        
        <div class="px-6 py-6" style="word-break: break-word; overflow-wrap: break-word;">
            <!-- Task Info -->
            <div class="mb-6 space-y-3">
                @if($task->thread)
                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4V2a1 1 0 011-1h8a1 1 0 011 1v2M7 4H5a2 2 0 00-2 2v12a2 2 0 002 2h14a2 2 0 002-2V6a2 2 0 00-2-2h-2M7 4h10M9 10h6M9 14h6" />
                    </svg>
                    <span><strong>Поток:</strong> {{ $task->thread->title }}</span>
                </div>
                @endif
                @if($task->executor)
                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span><strong>Исполнитель:</strong> {{ $task->executor->name }}</span>
                </div>
                @endif
                @if($task->creator)
                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 7a4 4 0 11-8 0 4 4 0 018 0zM12 14a7 7 0 00-7 7h14a7 7 0 00-7-7z" />
                    </svg>
                    <span><strong>Автор:</strong> {{ $task->creator->name }}</span>
                </div>
                @endif
                <div class="flex items-center text-sm text-gray-600 dark:text-gray-400">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><strong>Создана:</strong> {{ $task->created_at->format('d.m.Y в H:i') }}</span>
                </div>
                @if($task->due_date)
                <div class="flex items-center text-sm {{ $task->isOverdue() ? 'text-red-600 dark:text-red-400' : 'text-gray-600 dark:text-gray-400' }}">
                    <svg class="w-5 h-5 mr-2" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                    </svg>
                    <span><strong>Срок:</strong> {{ $task->due_date->format('d.m.Y в H:i') }}</span>
                </div>
                @endif
            </div>
            
            <!-- Content -->
            <div class="prose dark:prose-invert max-w-none">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-3">Содержание:</h3>
                <div class="text-gray-700 dark:text-gray-300 break-words" style="word-break: break-word; overflow-wrap: break-word; white-space: pre-wrap; word-wrap: break-word;">{{ $task->content }}</div>
            </div>
            
            <!-- Related Emails -->
            @if($task->thread && $task->thread->emails->count() > 0)
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Связанные письма в потоке:</h3>
                <div class="space-y-3">
                    @foreach($task->thread->emails as $email)
                    <a href="{{ route('dashboard.email.show', $email) }}" class="block p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-600/50 transition-colors">
                        <div class="flex items-start justify-between">
                            <div class="flex-1 min-w-0">
                                <h4 class="font-medium text-gray-900 dark:text-white break-words" style="word-break: break-word; overflow-wrap: break-word;">{{ $email->subject }}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1 break-words" style="word-break: break-word; overflow-wrap: break-word;">
                                    От: {{ $email->from_name }} ({{ $email->from_address }}) •
                                    {{ $email->received_at->format('d.m.Y H:i') }}
                                </p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-2 line-clamp-2 break-words" style="word-break: break-word; overflow-wrap: break-word;">
                                    {{ Str::limit($email->content, 200) }}
                                </p>
                            </div>
                            <div class="ml-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7" />
                                </svg>
                            </div>
                        </div>
                    </a>
                    @endforeach
                </div>
            </div>
            @endif

            <!-- Status Update Form -->
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Изменить статус:</h3>
                <form method="POST" action="{{ route('dashboard.task.status', $task) }}" class="flex gap-3">
                    @csrf
                    <select name="status" class="flex-1 px-4 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500">
                        <option value="new" {{ $task->status == 'new' ? 'selected' : '' }}>Новая</option>
                        <option value="in_progress" {{ $task->status == 'in_progress' ? 'selected' : '' }}>В работе</option>
                        <option value="completed" {{ $task->status == 'completed' ? 'selected' : '' }}>Завершено</option>
                        <option value="cancelled" {{ $task->status == 'cancelled' ? 'selected' : '' }}>Отменено</option>
                        <option value="archived" {{ $task->status == 'archived' ? 'selected' : '' }}>Архив</option>
                    </select>
                    <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                        Обновить
                    </button>
                </form>
            </div>
        </div>
    </div>
</div>
@endsection

