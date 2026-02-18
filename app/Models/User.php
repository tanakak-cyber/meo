<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Factories\HasFactory;
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
        'password_plain',
        'permissions',
        'is_admin',
        'customer_scope',
        'operator_id',
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
            'permissions' => 'array',
            'is_admin' => 'boolean',
        ];
    }

    /**
     * 権限をチェック
     */
    public function hasPermission(string $permission): bool
    {
        if (empty($this->permissions)) {
            return false;
        }
        return in_array($permission, $this->permissions);
    }

    /**
     * パスワードをハッシュ化して設定（平文も保存）
     */
    public function setPassword(string $password): void
    {
        $this->password = \Illuminate\Support\Facades\Hash::make($password);
        $this->password_plain = $password; // 管理者画面で表示するため平文も保存
    }
}
