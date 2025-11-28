@extends('layouts.app')

@section('title', 'Создание задачи')

@section('content')
<div class="max-w-4xl mx-auto space-y-6">
    <!-- Header -->
    <div class="flex items-center justify-between">
        <div>
            <h1 class="text-3xl font-bold text-gray-900 dark:text-white">
                Создание задачи
            </h1>
            <p class="mt-1 text-sm text-gray-500 dark:text-gray-400">
                Создайте новую задачу и связанный email
            </p>
        </div>
        <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
            ← Назад к задачам
        </a>
    </div>

    <!-- Form -->
    <div class="bg-white dark:bg-gray-800 rounded-xl shadow-sm border border-gray-200 dark:border-gray-700 p-8">
        <form method="POST" action="{{ route('tasks.store') }}" class="space-y-6">
            @csrf

            <div class="grid grid-cols-1 md:grid-cols-2 gap-6">
                <!-- Title -->
                <div class="md:col-span-2">
                    <label for="title" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Название задачи *
                    </label>
                    <input type="text" id="title" name="title" value="{{ old('title') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Введите название задачи" required>
                </div>

                <!-- Email Subject -->
                <div class="md:col-span-2">
                    <label for="email_subject" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Тема email *
                    </label>
                    <input type="text" id="email_subject" name="email_subject" value="{{ old('email_subject') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Введите тему email" required>
                </div>

                <!-- From Address -->
                <div>
                    <label for="from_address" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Email отправителя *
                    </label>
                    <input type="email" id="from_address" name="from_address" value="{{ old('from_address') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                           placeholder="example@email.com" required>
                </div>

                <!-- From Name -->
                <div>
                    <label for="from_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Имя отправителя
                    </label>
                    <input type="text" id="from_name" name="from_name" value="{{ old('from_name') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                           placeholder="Иван Иванов">
                </div>

                <!-- Priority -->
                <div>
                    <label for="priority" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Приоритет *
                    </label>
                    <select id="priority" name="priority"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Выберите приоритет</option>
                        <option value="low" {{ old('priority') == 'low' ? 'selected' : '' }}>Низкий</option>
                        <option value="medium" {{ old('priority') == 'medium' ? 'selected' : '' }}>Средний</option>
                        <option value="high" {{ old('priority') == 'high' ? 'selected' : '' }}>Высокий</option>
                        <option value="urgent" {{ old('priority') == 'urgent' ? 'selected' : '' }}>Срочный</option>
                    </select>
                </div>

                <!-- Status -->
                <div>
                    <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Статус *
                    </label>
                    <select id="status" name="status"
                            class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500" required>
                        <option value="">Выберите статус</option>
                        <option value="new" {{ old('status') == 'new' ? 'selected' : '' }}>Новая</option>
                        <option value="in_progress" {{ old('status') == 'in_progress' ? 'selected' : '' }}>В работе</option>
                        <option value="completed" {{ old('status') == 'completed' ? 'selected' : '' }}>Завершена</option>
                        <option value="cancelled" {{ old('status') == 'cancelled' ? 'selected' : '' }}>Отменена</option>
                        <option value="archived" {{ old('status') == 'archived' ? 'selected' : '' }}>Архив</option>
                    </select>
                </div>

                <!-- Due Date -->
                <div class="md:col-span-2">
                    <label for="due_date" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Срок выполнения
                    </label>
                    <input type="datetime-local" id="due_date" name="due_date" value="{{ old('due_date') }}"
                           class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                           min="{{ now()->format('Y-m-d\TH:i') }}">
                    <p class="mt-1 text-xs text-gray-500 dark:text-gray-400">Оставьте пустым, если срок не установлен</p>
                </div>

                <!-- Content -->
                <div class="md:col-span-2">
                    <label for="content" class="block text-sm font-medium text-gray-700 dark:text-gray-300 mb-2">
                        Содержание email *
                    </label>
                    <textarea id="content" name="content" rows="6"
                              class="w-full px-3 py-2 border border-gray-300 dark:border-gray-600 rounded-lg bg-white dark:bg-gray-700 text-gray-900 dark:text-white focus:ring-blue-500 focus:border-blue-500"
                              placeholder="Введите текст email" required>{{ old('content') }}</textarea>
                </div>
            </div>

            <!-- Submit Buttons -->
            <div class="flex items-center justify-end gap-4 pt-6 border-t border-gray-200 dark:border-gray-700">
                <a href="{{ route('dashboard') }}" class="px-4 py-2 bg-gray-200 dark:bg-gray-700 text-gray-700 dark:text-gray-300 rounded-lg hover:bg-gray-300 dark:hover:bg-gray-600 transition-colors">
                    Отмена
                </a>
                <button type="submit" class="px-6 py-2 bg-blue-600 text-white rounded-lg hover:bg-blue-700 transition-colors">
                    Создать задачу
                </button>
            </div>
        </form>
    </div>
</div>
@endsection


