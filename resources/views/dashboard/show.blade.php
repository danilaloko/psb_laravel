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
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 overflow-hidden">
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
        
        <div class="px-6 py-6">
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
                <div class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap">{{ $task->content }}</div>
            </div>
            
            <!-- Related Emails -->
            @if($task->thread && $task->thread->emails->count() > 0)
            <div class="mt-8 pt-6 border-t border-gray-200 dark:border-gray-700">
                <h3 class="text-lg font-semibold text-gray-900 dark:text-white mb-4">Связанные письма в потоке:</h3>
                <div class="space-y-3">
                    @foreach($task->thread->emails as $email)
                    <div class="p-4 bg-gray-50 dark:bg-gray-700/50 rounded-lg cursor-pointer hover:bg-gray-100 dark:hover:bg-gray-600/50 transition-colors"
                         data-subject="{{ htmlspecialchars($email->subject) }}"
                         data-from-name="{{ htmlspecialchars($email->from_name) }}"
                         data-from-address="{{ htmlspecialchars($email->from_address) }}"
                         data-date="{{ $email->received_at->format('d.m.Y H:i') }}"
                         data-content="{{ htmlspecialchars($email->content) }}"
                         onclick="openEmailModal(this)">
                        <div class="flex items-start justify-between">
                            <div class="flex-1">
                                <h4 class="font-medium text-gray-900 dark:text-white">{{ $email->subject }}</h4>
                                <p class="text-sm text-gray-600 dark:text-gray-400 mt-1">
                                    От: {{ $email->from_name }} ({{ $email->from_address }}) •
                                    {{ $email->received_at->format('d.m.Y H:i') }}
                                </p>
                                <p class="text-sm text-gray-700 dark:text-gray-300 mt-2 line-clamp-2">
                                    {{ Str::limit($email->content, 200) }}
                                </p>
                            </div>
                            <div class="ml-3">
                                <svg class="w-5 h-5 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z" />
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z" />
                                </svg>
                            </div>
                        </div>
                    </div>
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

<!-- Email Modal -->
<div id="emailModal" class="fixed inset-0 bg-gray-600 bg-opacity-50 overflow-y-auto h-full w-full hidden z-50 flex items-center justify-center">
    <div class="p-5 border w-11/12 md:w-3/4 lg:w-1/2 shadow-lg rounded-md bg-white dark:bg-gray-800 max-h-[90vh] overflow-y-auto">
        <div class="flex justify-between items-center mb-4">
            <h3 id="modalSubject" class="text-lg font-semibold text-gray-900 dark:text-white"></h3>
            <button onclick="closeEmailModal()" class="text-gray-400 hover:text-gray-600 dark:hover:text-gray-300">
                <svg class="w-6 h-6" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12" />
                </svg>
            </button>
        </div>

        <div class="mb-4 pb-4 border-b border-gray-200 dark:border-gray-700">
            <div id="modalSender" class="text-sm text-gray-600 dark:text-gray-400 mb-2"></div>
            <div id="modalDate" class="text-sm text-gray-500 dark:text-gray-500"></div>
        </div>

        <div id="modalContent" class="text-gray-700 dark:text-gray-300 whitespace-pre-wrap max-h-96 overflow-y-auto"></div>
    </div>
</div>

<script>
function openEmailModal(element) {
    const subject = decodeHtmlEntities(element.getAttribute('data-subject'));
    const fromName = decodeHtmlEntities(element.getAttribute('data-from-name'));
    const fromAddress = decodeHtmlEntities(element.getAttribute('data-from-address'));
    const date = element.getAttribute('data-date');
    const content = decodeHtmlEntities(element.getAttribute('data-content'));

    document.getElementById('modalSubject').textContent = subject;
    document.getElementById('modalSender').innerHTML = '<strong>От:</strong> ' + fromName + ' (' + fromAddress + ')';
    document.getElementById('modalDate').innerHTML = '<strong>Дата:</strong> ' + date;
    document.getElementById('modalContent').textContent = content;
    document.getElementById('emailModal').classList.remove('hidden');
    document.body.style.overflow = 'hidden';
}

function decodeHtmlEntities(text) {
    const textarea = document.createElement('textarea');
    textarea.innerHTML = text;
    return textarea.value;
}

function closeEmailModal() {
    document.getElementById('emailModal').classList.add('hidden');
    document.body.style.overflow = 'auto';
}

// Close modal when clicking outside
document.getElementById('emailModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEmailModal();
    }
});

// Close modal with Escape key
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape' && !document.getElementById('emailModal').classList.contains('hidden')) {
        closeEmailModal();
    }
});
</script>
@endsection

