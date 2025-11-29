<?php

namespace App\Http\Controllers;

use App\Models\Department;
use App\Models\User;
use App\Services\YandexAIService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;

class SettingsController extends Controller
{
    public function index(YandexAIService $yandexService)
    {
        try {
            $searchIndexes = $yandexService->getSearchIndexes();
        } catch (\Exception $e) {
            Log::error('Failed to load search indexes', [
                'error' => $e->getMessage()
            ]);

            // При ошибке передаем пустой массив
            $searchIndexes = [];
        }

        // Получаем все активные подразделения для админов
        $departments = [];
        if (auth()->user()->isAdmin()) {
            $departments = Department::active()->get();
        }

        return view('settings.index', compact('searchIndexes', 'departments'));
    }

    public function storeDepartment(Request $request)
    {
        // Только админы могут создавать подразделения
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Недостаточно прав доступа');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code',
            'description' => 'nullable|string|max:1000',
        ]);

        if ($validator->fails()) {
            return redirect()->route('settings')
                ->withErrors($validator)
                ->withInput();
        }

        Department::create([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'is_active' => true,
        ]);

        return redirect()->route('settings')
            ->with('success', 'Подразделение успешно создано');
    }

    public function updateDepartment(Request $request, Department $department)
    {
        // Только админы могут обновлять подразделения
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Недостаточно прав доступа');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'code' => 'required|string|max:50|unique:departments,code,' . $department->id,
            'description' => 'nullable|string|max:1000',
            'is_active' => 'boolean',
        ]);

        if ($validator->fails()) {
            return redirect()->route('settings')
                ->withErrors($validator)
                ->withInput();
        }

        $department->update([
            'name' => $request->name,
            'code' => $request->code,
            'description' => $request->description,
            'is_active' => $request->has('is_active'),
        ]);

        return redirect()->route('settings')
            ->with('success', 'Подразделение успешно обновлено');
    }

    public function destroyDepartment(Department $department)
    {
        // Только админы могут удалять подразделения
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Недостаточно прав доступа');
        }

        // Проверяем, есть ли пользователи в подразделении
        if ($department->users()->count() > 0) {
            return redirect()->route('settings')
                ->with('error', 'Нельзя удалить подразделение с активными пользователями');
        }

        $department->delete();

        return redirect()->route('settings')
            ->with('success', 'Подразделение успешно удалено');
    }

    public function storeUser(Request $request)
    {
        // Только админы могут создавать пользователей
        if (!auth()->user()->isAdmin()) {
            abort(403, 'Недостаточно прав доступа');
        }

        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'department_id' => 'nullable|exists:departments,id',
            'status' => 'required|in:admin,department_admin,regular_user',
        ]);

        if ($validator->fails()) {
            return redirect()->route('settings')
                ->withErrors($validator)
                ->withInput();
        }

        // Генерируем email и пароль
        $generatedEmail = $this->generateUniqueEmail();
        $generatedPassword = Str::random(12);

        // Определяем параметры пользователя в зависимости от статуса
        $userData = [
            'name' => $request->name,
            'email' => $generatedEmail,
            'password' => Hash::make($generatedPassword),
        ];

        if ($request->status === 'admin') {
            $userData['role'] = 'admin';
            $userData['department_id'] = null;
            $userData['department_admin'] = false;
        } elseif ($request->status === 'department_admin') {
            $userData['role'] = 'user';
            $userData['department_id'] = $request->department_id;
            $userData['department_admin'] = true;
        } else { // regular_user
            $userData['role'] = 'user';
            $userData['department_id'] = $request->department_id;
            $userData['department_admin'] = false;
        }

        User::create($userData);

        return redirect()->route('settings')
            ->with('success', 'Пользователь успешно создан')
            ->with('user_credentials', [
                'email' => $generatedEmail,
                'password' => $generatedPassword,
                'name' => $request->name
            ]);
    }

    private function generateUniqueEmail(): string
    {
        do {
            $email = 'user_' . Str::random(8) . '@example.com';
        } while (User::where('email', $email)->exists());

        return $email;
    }
}
