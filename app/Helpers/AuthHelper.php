<?php

namespace App\Helpers;

use Illuminate\Support\Facades\Auth;

class AuthHelper
{
    /**
     * 現在ログインしているユーザーのIDを取得
     * 管理者の場合は Auth::user()->id
     * オペレーターの場合は User テーブルで operator_id が一致するレコードのID
     * 
     * @return int|null
     */
    public static function getCurrentUserId(): ?int
    {
        $user = Auth::user();
        
        // 管理者でログインしている場合
        if ($user) {
            return $user->id;
        }
        
        // オペレーターでログインしている場合
        $operatorId = session('operator_id');
        if ($operatorId) {
            // User テーブルで operator_id が一致するレコードを探す
            $operatorUser = \App\Models\User::where('operator_id', $operatorId)->first();
            if ($operatorUser) {
                return $operatorUser->id;
            }
        }
        
        return null;
    }
}













