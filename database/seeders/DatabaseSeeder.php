<?php

namespace Database\Seeders;

use App\Models\Department;
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

        // Создаем подразделения
        $this->call(DepartmentSeeder::class);

        // Создаем пользователей для каждого подразделения (5-8 пользователей в каждом)
        $departments = Department::all();
        
        foreach ($departments as $department) {
            // Генерируем случайное количество пользователей от 5 до 8
            $usersCount = rand(5, 8);
            
            // Проверяем, сколько пользователей уже есть в этом подразделении
            $existingUsersCount = User::where('department_id', $department->id)->count();
            
            // Создаем недостающих пользователей
            if ($existingUsersCount < $usersCount) {
                $usersToCreate = $usersCount - $existingUsersCount;
                
                User::factory($usersToCreate)->create([
                    'role' => 'user',
                    'password' => $defaultPassword,
                    'department_id' => $department->id,
                    'department_admin' => false,
                    'email_verified_at' => now(),
                ]);
            }
        }

        // Создаем или обновляем тестового пользователя (без подразделения)
        User::updateOrCreate(
            ['email' => 'user@example.com'],
            [
                'name' => 'Тестовый Пользователь',
                'password' => $defaultPassword,
                'role' => 'user',
                'email_verified_at' => now(),
            ]
        );

        // Обновляем пароли для всех пользователей без пароля
        User::whereNull('password')->orWhere('password', '')->update([
            'password' => $defaultPassword,
        ]);

        // Создаем потоки и письма
        $this->call(EmailSeeder::class);

        // Создаем задачи
        $this->call(TaskSeeder::class);
    }
}
