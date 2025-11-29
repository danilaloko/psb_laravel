<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        $defaultPassword = Hash::make('password');

        // Создаем или обновляем администратора
        User::updateOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Администратор',
                'password' => $defaultPassword,
                'role' => 'admin',
                'email_verified_at' => now(),
            ]
        );

        // Создаем или обновляем тестового пользователя
        User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Тестовый Пользователь',
                'password' => $defaultPassword,
                'role' => 'user',
                'email_verified_at' => now(),
            ]
        );

        // Создаем еще несколько пользователей (если их меньше 5)
        $existingUsersCount = User::where('role', 'user')->whereNotIn('email', ['user@example.com'])->count();
        if ($existingUsersCount < 5) {
            User::factory(5 - $existingUsersCount)->create([
                'role' => 'user',
                'password' => $defaultPassword,
            ]);
        }

        // Обновляем пароли для всех пользователей без пароля
        User::whereNull('password')->orWhere('password', '')->update([
            'password' => $defaultPassword,
        ]);

        // Создаем потоки и письма
        $this->call(EmailSeeder::class);

        // Создаем подразделения
        $this->call(DepartmentSeeder::class);

        // Создаем задачи
        $this->call(TaskSeeder::class);
    }
}
