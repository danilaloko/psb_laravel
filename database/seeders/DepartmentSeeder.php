<?php

namespace Database\Seeders;

use App\Models\Department;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DepartmentSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $departments = [
            [
                'name' => 'Поддержка клиентов',
                'code' => 'support',
                'description' => 'Техническая поддержка, консультации по продуктам и услугам банка',
                'is_active' => true,
            ],
            [
                'name' => 'Юридические вопросы',
                'code' => 'legal',
                'description' => 'Обработка юридических вопросов, договоров, претензий и судебных дел',
                'is_active' => true,
            ],
            [
                'name' => 'Кредиты и продукты',
                'code' => 'credits',
                'description' => 'Заявки на кредиты, консультации по банковским продуктам',
                'is_active' => true,
            ],
            [
                'name' => 'Жалобы и претензии',
                'code' => 'complaints',
                'description' => 'Разрешение конфликтных ситуаций, обработка жалоб клиентов',
                'is_active' => true,
            ],
            [
                'name' => 'Общее',
                'code' => 'general',
                'description' => 'Общие вопросы, которые не относятся к другим категориям',
                'is_active' => true,
            ],
        ];

        foreach ($departments as $department) {
            Department::updateOrCreate(
                ['code' => $department['code']],
                $department
            );
        }
    }
}
