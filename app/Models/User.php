<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable
{
    /** @use HasFactory<\Database\Factories\UserFactory> */
    use HasFactory, Notifiable;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'department_id',
        'department_admin',
        'is_active',
        'last_task_assigned_at',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'department_admin' => 'boolean',
            'is_active' => 'boolean',
            'last_task_assigned_at' => 'datetime',
        ];
    }

    public function tasks(): HasMany
    {
        return $this->hasMany(Task::class);
    }

    public function assignedTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'executor_id');
    }

    public function createdTasks(): HasMany
    {
        return $this->hasMany(Task::class, 'creator_id');
    }

    /**
     * Подразделение пользователя
     */
    public function department(): BelongsTo
    {
        return $this->belongsTo(Department::class);
    }

    /**
     * Получить код департамента
     */
    public function getDepartmentCodeAttribute(): ?string
    {
        return $this->department?->code;
    }

    /**
     * Проверка на полного админа (видит все задачи)
     */
    public function isAdmin(): bool
    {
        return $this->role === 'admin';
    }

    /**
     * Проверка на админа подразделения (видит все задачи своего подразделения)
     */
    public function isDepartmentAdmin(): bool
    {
        return $this->department_admin && $this->department_id;
    }

    /**
     * Проверка на обычного пользователя (видит только свои задачи)
     */
    public function isRegularUser(): bool
    {
        return !$this->isAdmin() && !$this->isDepartmentAdmin();
    }

    /**
     * Получить задачи доступные пользователю
     */
    public function getAccessibleTasks()
    {
        if ($this->isAdmin()) {
            // Полный админ видит все задачи
            return Task::query();
        }

        if ($this->isDepartmentAdmin()) {
            // Админ подразделения видит все задачи своего подразделения
            return Task::whereHas('executor', function ($query) {
                $query->where('department_id', $this->department_id);
            });
        }

        // Обычный пользователь видит только свои задачи
        return $this->assignedTasks();
    }
}
