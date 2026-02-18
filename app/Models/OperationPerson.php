<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class OperationPerson extends Model
{
    use HasFactory;

    protected $table = 'operation_persons';

    protected $fillable = [
        'name',
        'email',
        'phone',
        'password_hash',
        'password_plain',
        'display_order',
        'is_active',
    ];

    protected $hidden = [
        'password_hash',
        // password_plainは管理者画面で表示するため、hiddenから除外
    ];

    protected $casts = [
        'is_active' => 'boolean',
    ];

    public function shops(): HasMany
    {
        return $this->hasMany(Shop::class, 'operation_person_id');
    }

    /**
     * パスワードを検証
     */
    public function verifyPassword(string $password): bool
    {
        if (empty($this->password_hash)) {
            return false;
        }
        return password_verify($password, $this->password_hash);
    }

    /**
     * パスワードをハッシュ化して設定（平文も保存）
     */
    public function setPassword(string $password): void
    {
        $this->password_hash = password_hash($password, PASSWORD_DEFAULT);
        $this->password_plain = $password; // 管理者画面で表示するため平文も保存
    }

    /**
     * パスワードが設定されているかどうか
     */
    public function hasPassword(): bool
    {
        return !empty($this->password_hash);
    }
}

