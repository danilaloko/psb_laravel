@extends('layouts.app')

@section('title', 'Создание компании')

@section('content')

<div class="min-h-screen flex items-center justify-center bg-gradient-to-br from-blue-50 via-white to-purple-50 dark:from-gray-900 dark:via-gray-800 dark:to-gray-900 py-12 px-4 sm:px-6 lg:px-8">

    <div class="max-w-md w-full space-y-8">

        <div>

            <h2 class="mt-6 text-center text-3xl font-extrabold text-gray-900 dark:text-white">

                Создание компании

            </h2>

            <p class="mt-2 text-center text-sm text-gray-600 dark:text-gray-400">

                Укажите название компании, чтобы продолжить

            </p>

        </div>

        

        <form class="mt-8 space-y-6" method="POST" action="#">

            @csrf

            

            <div class="rounded-lg bg-white dark:bg-gray-800 shadow-xl p-8 space-y-6">

                

                <!-- Company Name -->

                <div>

                    <label for="company_name" class="block text-sm font-medium text-gray-700 dark:text-gray-300">

                        Название компании

                    </label>

                    <input id="company_name" 

                           name="company_name" 

                           type="text" 

                           required

                           value="{{ old('company_name') }}"

                           class="mt-1 appearance-none block w-full px-3 py-2 border border-gray-300 dark:border-gray-600

                                  placeholder-gray-500 dark:placeholder-gray-400 text-gray-900 dark:text-white

                                  bg-white dark:bg-gray-700 rounded-lg focus:outline-none focus:ring-blue-500 

                                  focus:border-blue-500 sm:text-sm"

                           placeholder="Введите название компании">

                    @error('company_name')

                        <p class="mt-1 text-sm text-red-600 dark:text-red-400">{{ $message }}</p>

                    @enderror

                </div>

                <!-- Submit Button -->

                <div>

                    <button type="submit"

                            class="group relative w-full flex justify-center py-2.5 px-4 border border-transparent

                                   text-sm font-medium rounded-lg text-white bg-blue-600 hover:bg-blue-700

                                   focus:outline-none focus:ring-2 focus:ring-offset-2 focus:ring-blue-500 transition-colors">

                        Создать компанию

                    </button>

                </div>

            </div>

        </form>

    </div>

</div>

@endsection

