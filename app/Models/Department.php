<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Department extends Model
{
    protected $fillable = [
        'name',
        'code',
        'description',
        'is_active',
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    /**
     * Пользователи подразделения
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * Обычные пользователи подразделения
     */
    public function regularUsers(): HasMany
    {
        return $this->hasMany(User::class)->where('department_admin', false);
    }

    /**
     * Админы подразделения
     */
    public function departmentAdmins(): HasMany
    {
        return $this->hasMany(User::class)->where('department_admin', true);
    }

    /**
     * Задачи подразделения (через пользователей)
     */
    public function tasks()
    {
        return Task::whereHas('executor', function ($query) {
            $query->where('department_id', $this->id);
        });
    }

    /**
     * Scope для активных подразделений
     */
    public function scopeActive($query)
    {
        return $query->where('is_active', true);
    }

    /**
     * Получить подразделение по коду
     */
    public static function findByCode(string $code): ?self
    {
        return self::where('code', $code)->first();
    }
}
