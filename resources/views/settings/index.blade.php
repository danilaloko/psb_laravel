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

    </div>

</div>

@push('scripts')

<script>

const realPassword = "mySecretPassword123";

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

</script>

@endpush

@endsection

