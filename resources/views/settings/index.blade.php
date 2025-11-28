@extends('layouts.app')

@section('title', 'Настройки')

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

        <!-- BLOCK 2 — ИЗМЕНЯЕМАЯ ФОРМА -->

        <form method="POST" action="#" class="space-y-6">
            @csrf

        <div class="rounded-lg bg-white dark:bg-gray-800 shadow-xl p-8 space-y-6">

            <!-- Title Field -->

            <div>

                <label for="title_field" class="block text-sm font-medium text-gray-700 dark:text-gray-300">

                    Название

                </label>

                <input id="title_field"

                       type="text"

                       name="title"

                       value=""

                       class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600

                              text-gray-900 dark:text-white bg-white dark:bg-gray-700

                              rounded-lg focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"

                       placeholder="Введите название">

            </div>

            <!-- Upload Button -->

            <div class="flex items-center space-x-3">

                <button type="button"

                        class="w-10 h-10 flex items-center justify-center rounded-full bg-blue-600 hover:bg-blue-700

                               text-white text-xl font-bold shadow focus:outline-none">

                    +

                </button>

                <span class="text-sm text-gray-600 dark:text-gray-400">

                    Добавить файл или документ

                </span>

            </div>

            <!-- Big Textarea -->

            <div>

                <label for="big_text" class="block text-sm font-medium text-gray-700 dark:text-gray-300">

                    Описание / Примечание

                </label>

                <textarea id="big_text"

                          name="description"

                          rows="6"

                          class="mt-1 block w-full px-3 py-2 border border-gray-300 dark:border-gray-600

                                 text-gray-900 dark:text-white bg-white dark:bg-gray-700 rounded-lg

                                 focus:outline-none focus:ring-blue-500 focus:border-blue-500 sm:text-sm"

                          placeholder="Введите содержание письма..."></textarea>

            </div>

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

        <div>

            <button type="submit"

                    class="group relative w-full flex justify-center py-2.5 px-4 border border-transparent

                            text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700

                            focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">

                Сохранить

            </button>

        </div>

        </form>

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

