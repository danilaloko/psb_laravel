@extends('layouts.app')

@section('title', 'Настройки')

@section('styles')
<style>
.search-index-card {
    transition: all 0.2s ease;
}
.search-index-card:hover {
    transform: translateY(-2px);
    box-shadow: 0 8px 25px rgba(0, 0, 0, 0.1);
}
.index-status-ready {
    background-color: #10b981;
}
.index-status-creating {
    background-color: #f59e0b;
}
.index-status-error {
    background-color: #ef4444;
}
</style>
@endsection

@section('content')

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 py-12 px-4 sm:px-6 lg:px-8">

    <div class="max-w-md w-full space-y-10">

        <!-- HEADER -->

        <div>

            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">

                Настройки пользователя

            </h2>

            <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">

                Ваши данные учетной записи

            </p>

        </div>

        <!-- BLOCK 1 — НЕИЗМЕНЯЕМЫЕ ДАННЫЕ -->

        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-xl p-8 space-y-6">

            <!-- Login -->

            <div>

                <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">

                    Логин

                </label>

                <input type="text"

                       value="{{ auth()->user()->email ?? 'user@example.com' }}"

                       readonly

                       class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600

                              text-gray-900 dark:text-white bg-gray-100 dark:bg-gray-700

                              rounded-lg focus:outline-none sm:text-sm select-all cursor-text">

            </div>

            

        <div class="relative">

            <label class="block text-sm font-medium text-gray-700 dark:text-gray-300">

                Пароль

            </label>

            <div class="relative">

                <input type="text"

                    value="********"

                    readonly

                    class="mt-1 block w-full px-3 py-2 pr-24 border border-gray-300 dark:border-gray-600

                            text-gray-900 dark:text-white input-dark

                            rounded-lg focus:outline-none sm:text-sm select-all cursor-text"

                    id="password-field">

                

                <button type="button"

                        class="absolute right-2 top-1/2 transform -translate-y-1/2 

                            px-3 py-1 bg-blue-500/30 hover:bg-blue-500/50 

                            text-blue-700 dark:text-blue-300 text-xs font-medium

                            rounded-md border border-blue-400/30 transition-all duration-200

                            focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50"

                        onclick="copyPassword()">

                    Копировать

                </button>

            </div>

        </div>

            <p class="text-xs text-gray-500 dark:text-gray-400">

                Эти данные нельзя изменить вручную. Чтобы изменить пароль — обратитесь к администратору.

            </p>

        </div>

        <!-- BLOCK 2 — ПОИСКОВЫЕ ИНДЕКСЫ -->

        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-xl p-8 space-y-6">

            <div class="flex items-center justify-between">

                <h3 class="text-lg font-medium text-gray-900 dark:text-white">

                    Поисковые индексы Yandex AI Studio

                </h3>

                <span class="text-sm text-gray-500 dark:text-gray-400">

                    {{ count($searchIndexes) }} индексов доступно

                </span>

            </div>

            @if(count($searchIndexes) > 0)

                <div class="grid gap-4 md:grid-cols-2">

                    @foreach($searchIndexes as $index)

                        <div class="search-index-card rounded-lg border border-gray-200 dark:border-gray-600 p-4 bg-gray-50 dark:bg-gray-700">

                            <div class="flex items-start justify-between">

                                <div class="flex-1">

                                    <div class="flex items-center space-x-2">

                                        <h4 class="text-sm font-medium text-gray-900 dark:text-white">

                                            {{ $index['name'] ?? 'Без названия' }}

                                        </h4>

                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                   {{ $index['status'] === 'READY' ? 'index-status-ready' :
                                                      ($index['status'] === 'CREATING' ? 'index-status-creating' : 'index-status-error') }}
                                                   text-white">

                                            {{ $index['status'] ?? 'UNKNOWN' }}

                                        </span>

                                    </div>

                                    <p class="mt-1 text-xs text-gray-600 dark:text-gray-400">

                                        ID: {{ $index['id'] }}

                                    </p>

                                    <p class="mt-2 text-sm text-gray-700 dark:text-gray-300">

                                        {{ $index['description'] ?: 'Описание не указано' }}

                                    </p>

                                    @if($index['created_at'])

                                        <p class="mt-1 text-xs text-gray-500 dark:text-gray-500">

                                            Создан: {{ \Carbon\Carbon::parse($index['created_at'])->format('d.m.Y H:i') }}

                                        </p>

                                    @endif

                                </div>

                            </div>

                        </div>

                    @endforeach

                </div>

            @else

                <div class="text-center py-8">

                    <div class="text-gray-400 dark:text-gray-500 text-4xl mb-4">📄</div>

                    <p class="text-gray-600 dark:text-gray-400">

                        Нет доступных поисковых индексов

                    </p>

                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-2">

                        Индексы должны быть созданы в Yandex AI Studio

                    </p>

                </div>

            @endif

        </div>

        <!-- BLOCK 3 — EMPLOYEE TYPE + NAME -->

        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-xl p-8 space-y-6">

            <!-- SELECT EMPLOYEE TYPE -->

            <div>

                <label for="employee_type" class="block text-sm font-medium text-gray-700 dark:text-gray-300">

                    Тип отдела

                </label>

                <select id="employee_type" name="employee_type"

                        class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600

                               bg-white dark:bg-gray-700 text-gray-900 dark:text-white

                               rounded-lg focus:ring-blue-500 focus:border-blue-500 sm:text-sm">

                    <option value="">Выберите тип</option>

                    <option value="manager">Менеджмент по работе с клиентами</option>

                    <option value="client_service">Клиентский сервис</option>

                    <option value="legal_department">Юридический отдел</option>

                    <option value="security_department">Кассир</option>

                    <option value="credit_department">Кредитный отдел</option>

                    <option value="secretary">Секретарь</option>

                    <option value="operator">Оператор</option>

                    <option value="analytics_department">Аналитический отдел</option>

                    <option value="advisor">Финансовый консультант</option>

                </select>

            </div>

            <!-- EMPLOYEE NAME -->

            <div>

                <label for="employee_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">

                    Имя сотрудника

                </label>

                <input id="employee_name" type="text" name="employee_name"

                       class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600

                              bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg

                              focus:ring-blue-500 focus:border-blue-500 sm:text-sm"

                       placeholder="Введите имя сотрудника">

            </div>

        </div>

        <!-- BLOCK 4 — УПРАВЛЕНИЕ ПОДРАЗДЕЛЕНИЯМИ (ТОЛЬКО ДЛЯ АДМИНОВ) -->
        @if(auth()->user()->isAdmin())

        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-xl p-8 space-y-6">

            <!-- Сообщения об успехе/ошибках -->
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    Управление подразделениями
                </h3>
                <span class="text-sm text-gray-500 dark:text-gray-400">
                    {{ count($departments) }} подразделений
                </span>
            </div>

            <!-- Создание нового подразделения -->
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-700">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Создать новое подразделение</h4>

                <form action="{{ route('settings.departments.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Название подразделения *
                            </label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600
                                          bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg
                                          focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   placeholder="Например: Поддержка клиентов" required>
                        </div>

                        <div>
                            <label for="code" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Код подразделения *
                            </label>
                            <input type="text" id="code" name="code" value="{{ old('code') }}"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600
                                          bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg
                                          focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   placeholder="Например: support" required>
                        </div>
                    </div>

                    <div>
                        <label for="description" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Описание
                        </label>
                        <textarea id="description" name="description" rows="3"
                                  class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600
                                         bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg
                                         focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                  placeholder="Описание деятельности подразделения">{{ old('description') }}</textarea>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium
                                       rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2
                                       focus:ring-blue-500 focus:ring-opacity-50">
                            Создать подразделение
                        </button>
                    </div>
                </form>
            </div>

            <!-- Список подразделений -->
            @if(count($departments) > 0)
                <div class="space-y-4">
                    <h4 class="text-sm font-medium text-gray-900 dark:text-white">Существующие подразделения</h4>

                    @foreach($departments as $department)
                        <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 bg-white dark:bg-gray-700">
                            <div class="flex items-start justify-between">
                                <div class="flex-1">
                                    <div class="flex items-center space-x-3">
                                        <h5 class="text-sm font-medium text-gray-900 dark:text-white">
                                            {{ $department->name }}
                                        </h5>
                                        <span class="inline-flex items-center px-2 py-0.5 rounded-full text-xs font-medium
                                                     {{ $department->is_active ? 'bg-green-100 text-green-800 dark:bg-green-800 dark:text-green-100' : 'bg-red-100 text-red-800 dark:bg-red-800 dark:text-red-100' }}">
                                            {{ $department->is_active ? 'Активно' : 'Неактивно' }}
                                        </span>
                                    </div>

                                    @if($department->description)
                                        <p class="mt-2 text-sm text-gray-600 dark:text-gray-400">
                                            {{ $department->description }}
                                        </p>
                                    @endif

                                    <div class="mt-2 flex items-center space-x-4 text-xs text-gray-500 dark:text-gray-400">
                                        <span>Пользователей: {{ $department->users()->count() }}</span>
                                        <span>Задач: {{ $department->tasks()->count() }}</span>
                                    </div>
                                </div>
                            </div>
                        </div>
                    @endforeach
                </div>
            @else
                <div class="text-center py-8">
                    <div class="text-gray-400 dark:text-gray-500 text-4xl mb-4">🏢</div>
                    <p class="text-gray-600 dark:text-gray-400">
                        Нет созданных подразделений
                    </p>
                    <p class="text-sm text-gray-500 dark:text-gray-500 mt-2">
                        Создайте первое подразделение выше
                    </p>
                </div>
            @endif

        </div>

        @endif

        <!-- BLOCK 5 — УПРАВЛЕНИЕ ПОЛЬЗОВАТЕЛЯМИ (ТОЛЬКО ДЛЯ АДМИНОВ) -->
        @if(auth()->user()->isAdmin())

        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-xl p-8 space-y-6">

            <!-- Сообщения об успехе/ошибках -->
            @if(session('success'))
                <div class="bg-green-100 border border-green-400 text-green-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('success') }}</span>
                </div>
            @endif

            @if(session('error'))
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <span class="block sm:inline">{{ session('error') }}</span>
                </div>
            @endif

            @if($errors->any())
                <div class="bg-red-100 border border-red-400 text-red-700 px-4 py-3 rounded relative" role="alert">
                    <ul class="list-disc list-inside">
                        @foreach($errors->all() as $error)
                            <li>{{ $error }}</li>
                        @endforeach
                    </ul>
                </div>
            @endif

            <div class="flex items-center justify-between">
                <h3 class="text-lg font-medium text-gray-900 dark:text-white">
                    Управление пользователями
                </h3>
            </div>

            <!-- Создание нового пользователя -->
            <div class="border border-gray-200 dark:border-gray-600 rounded-lg p-4 bg-gray-50 dark:bg-gray-700">
                <h4 class="text-sm font-medium text-gray-900 dark:text-white mb-4">Создать нового пользователя</h4>

                <form action="{{ route('settings.users.store') }}" method="POST" class="space-y-4">
                    @csrf

                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                        <div>
                            <label for="name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                ФИО *
                            </label>
                            <input type="text" id="name" name="name" value="{{ old('name') }}"
                                   class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600
                                          bg-white dark:bg-gray-700 text-gray-900 dark:text-white rounded-lg
                                          focus:ring-blue-500 focus:border-blue-500 sm:text-sm"
                                   placeholder="Иванов Иван Иванович" required>
                        </div>

                        <div>
                            <label for="status" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                                Статус *
                            </label>
                            <div class="mt-2 space-y-2">
                                <div class="flex items-center">
                                    <input id="status_admin" name="status" type="radio" value="admin"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                           {{ old('status') === 'admin' ? 'checked' : '' }}>
                                    <label for="status_admin" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                        Админ
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input id="status_department_admin" name="status" type="radio" value="department_admin"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                           {{ old('status') === 'department_admin' ? 'checked' : '' }}>
                                    <label for="status_department_admin" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                        Админ подразделения
                                    </label>
                                </div>
                                <div class="flex items-center">
                                    <input id="status_regular_user" name="status" type="radio" value="regular_user"
                                           class="h-4 w-4 text-blue-600 focus:ring-blue-500 border-gray-300"
                                           {{ old('status') === 'regular_user' ? 'checked' : '' }}>
                                    <label for="status_regular_user" class="ml-2 block text-sm text-gray-900 dark:text-gray-300">
                                        Обычный пользователь
                                    </label>
                                </div>
                            </div>
                        </div>
                    </div>

                    <div id="department_field" class="{{ in_array(old('status'), ['admin']) ? 'hidden' : '' }}">
                        <label for="department_id" class="block text-sm font-medium text-gray-700 dark:text-gray-300">
                            Подразделение *
                        </label>
                        <select id="department_id" name="department_id"
                                class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600
                                       bg-white dark:bg-gray-700 text-gray-900 dark:text-white
                                       rounded-lg focus:ring-blue-500 focus:border-blue-500 sm:text-sm">
                            <option value="">Выберите подразделение</option>
                            @foreach($departments as $department)
                                <option value="{{ $department->id }}" {{ old('department_id') == $department->id ? 'selected' : '' }}>
                                    {{ $department->name }}
                                </option>
                            @endforeach
                        </select>
                    </div>

                    <div class="flex justify-end">
                        <button type="submit"
                                class="px-4 py-2 bg-blue-600 hover:bg-blue-700 text-white text-sm font-medium
                                       rounded-lg transition-colors duration-200 focus:outline-none focus:ring-2
                                       focus:ring-blue-500 focus:ring-opacity-50">
                            Создать пользователя
                        </button>
                    </div>
                </form>
            </div>

            <!-- Показать ссылку на xpaste с учетными данными -->
            @if(session('user_credentials_url'))
                <div class="border border-green-200 dark:border-green-600 rounded-lg p-4 bg-green-50 dark:bg-green-900/20">
                    <h4 class="text-sm font-medium text-green-900 dark:text-green-100 mb-3">
                        Пользователь "{{ session('user_name') }}" создан успешно!
                    </h4>
                    <div class="space-y-3 text-sm">
                        <div>
                            <span class="text-gray-600 dark:text-gray-400 block mb-1">Учетные данные сохранены в заметке:</span>
                            <div class="flex items-center gap-2 mt-2">
                                <a href="{{ session('user_credentials_url') }}" 
                                   target="_blank"
                                   class="text-blue-600 dark:text-blue-400 hover:text-blue-800 dark:hover:text-blue-300 font-medium break-all underline">
                                    {{ session('user_credentials_url') }}
                                </a>
                                <button type="button"
                                        onclick="copyToClipboard('{{ session('user_credentials_url') }}', this)"
                                        class="px-2 py-1 bg-blue-500/30 hover:bg-blue-500/50 text-blue-700 dark:text-blue-300 text-xs font-medium rounded-md border border-blue-400/30 transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-blue-500 focus:ring-opacity-50">
                                    Копировать
                                </button>
                            </div>
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Откройте ссылку, чтобы получить логин и пароль для входа в систему
                    </div>
                </div>
            @elseif(session('user_credentials'))
                <!-- Fallback: показать данные напрямую, если не удалось создать заметку -->
                <div class="border border-yellow-200 dark:border-yellow-600 rounded-lg p-4 bg-yellow-50 dark:bg-yellow-900/20">
                    @if(session('warning'))
                        <div class="text-yellow-800 dark:text-yellow-200 text-sm mb-3">{{ session('warning') }}</div>
                    @endif
                    <h4 class="text-sm font-medium text-yellow-900 dark:text-yellow-100 mb-3">Пользователь создан успешно!</h4>
                    <div class="space-y-2 text-sm">
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Имя:</span>
                            <span class="text-yellow-900 dark:text-yellow-100 font-medium">{{ session('user_credentials')['name'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Email (логин):</span>
                            <span class="text-yellow-900 dark:text-yellow-100 font-medium">{{ session('user_credentials')['email'] }}</span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-gray-600 dark:text-gray-400">Пароль:</span>
                            <span class="text-yellow-900 dark:text-yellow-100 font-medium">{{ session('user_credentials')['password'] }}</span>
                        </div>
                    </div>
                    <div class="mt-3 text-xs text-gray-500 dark:text-gray-400">
                        Сохраните эти данные - они нужны для входа в систему
                    </div>
                </div>
            @endif

        </div>

        @endif

    </div>

</div>

@push('scripts')

<script>

const realPassword = "mySecretPassword123";

function copyToClipboard(text, button) {
    navigator.clipboard.writeText(text).then(() => {
        const originalText = button.textContent;
        button.textContent = 'Скопировано!';
        button.classList.add('bg-green-500/50', 'text-green-700', 'dark:text-green-300');
        setTimeout(() => {
            button.textContent = originalText;
            button.classList.remove('bg-green-500/50', 'text-green-700', 'dark:text-green-300');
        }, 2000);
    }).catch(err => {
        console.error('Ошибка копирования:', err);
        alert('Не удалось скопировать в буфер обмена');
    });
}

function copyPassword() {

    navigator.clipboard.writeText(realPassword).then(() => {

        const button = event.target;

        const originalText = button.textContent;

        button.textContent = "Скопировано!";

        button.classList.remove('bg-blue-500/30', 'hover:bg-blue-500/50');

        button.classList.add('bg-green-500/50', 'text-green-700', 'dark:text-green-300');

        

        setTimeout(() => {

            button.textContent = originalText;

            button.classList.remove('bg-green-500/50', 'text-green-700', 'dark:text-green-300');

            button.classList.add('bg-blue-500/30', 'hover:bg-blue-500/50');

        }, 2000);

        

    }).catch(err => {

        console.error('Ошибка копирования: ', err);

        alert('Не удалось скопировать пароль');

    });

}

document.getElementById('password-field').addEventListener('mouseenter', function() {

    this.value = realPassword;

});

document.getElementById('password-field').addEventListener('mouseleave', function() {

    this.value = "********";

});

// Управление видимостью поля подразделения
document.querySelectorAll('input[name="status"]').forEach(function(radio) {
    radio.addEventListener('change', function() {
        const departmentField = document.getElementById('department_field');
        const departmentSelect = document.getElementById('department_id');

        if (this.value === 'admin') {
            departmentField.classList.add('hidden');
            departmentSelect.required = false;
            departmentSelect.value = '';
        } else {
            departmentField.classList.remove('hidden');
            departmentSelect.required = true;
        }
    });
});

</script>

@endpush

@endsection

