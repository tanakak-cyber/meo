<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ReportEmailSetting extends Model
{
    use HasFactory;

    protected $fillable = [
        'subject',
        'body',
        'admin_email',
    ];

    /**
     * シングルトンとして設定を取得
     */
    public static function getSettings()
    {
        // ログインユーザーのメールアドレスをデフォルト値として使用
        $defaultAdminEmail = auth()->check() ? auth()->user()->email : config('mail.from.address');
        
        return static::firstOrCreate(
            ['id' => 1],
            [
                'subject' => '【{{shop_name}}】月次レポート',
                'body' => "{{shop_name}}様\n\n前月の月次レポートをお送りいたします。\n\nご確認のほどよろしくお願いいたします。",
                'admin_email' => $defaultAdminEmail,
            ]
        );
    }

    /**
     * メール本文の変数を置換
     */
    public function replaceVariables(string $text, array $variables): string
    {
        foreach ($variables as $key => $value) {
            $text = str_replace('{{' . $key . '}}', $value, $text);
        }
        return $text;
    }
}

