<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class CheckAdminPermission
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next, string $permission): Response
    {
        // オペレーターの場合は通過
        if (session('operator_id')) {
            return $next($request);
        }

        // 管理者でない場合は403エラー（ログイン画面へのリダイレクトはしない）
        // ログイン画面へのリダイレクトはLaravelの標準的な認証ミドルウェアが行う
        if (!auth()->check()) {
            abort(403, 'アクセス権限がありません。');
        }

        $permissions = session('admin_permissions', []);
        
        // 権限が空の場合はアクセス不可
        if (empty($permissions)) {
            abort(403, 'アクセス権限がありません。権限が設定されていません。');
        }

        // 指定された権限がない場合は403
        if (!in_array($permission, $permissions)) {
            abort(403, 'アクセス権限がありません。');
        }

        return $next($request);
    }
}

