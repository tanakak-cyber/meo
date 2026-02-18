<?php

namespace App\Http\Controllers;

use App\Models\OperationPerson;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;

class OperatorAuthController extends Controller
{
    /**
     * ログイン画面を表示
     */
    public function showLoginForm()
    {
        // 既にログインしている場合はリダイレクト
        if (session('operator_id')) {
            return redirect()->route('operator.dashboard');
        }
        
        return view('operator.login');
    }

    /**
     * ログイン処理
     */
    public function login(Request $request)
    {
        $validated = $request->validate([
            'email' => 'required|email',
            'password' => 'required|string',
        ]);

        // オペレーターを検索（is_active = true のみ）
        $operator = OperationPerson::where('email', $validated['email'])
            ->where('is_active', true)
            ->first();

        if (!$operator) {
            Log::warning('OPERATOR_LOGIN_FAILED_EMAIL_NOT_FOUND', [
                'email' => $validated['email'],
            ]);
            return back()->withErrors([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ])->withInput($request->only('email'));
        }

        // デバッグログ: パスワード検証前の状態
        Log::debug('OPERATOR_LOGIN_ATTEMPT', [
            'operator_id' => $operator->id,
            'email' => $validated['email'],
            'has_password_hash' => !empty($operator->password_hash),
            'password_plain_length' => strlen($operator->password_plain ?? ''),
        ]);

        // パスワード検証
        if (!$operator->verifyPassword($validated['password'])) {
            Log::warning('OPERATOR_LOGIN_FAILED_PASSWORD_MISMATCH', [
                'operator_id' => $operator->id,
                'email' => $validated['email'],
                'has_password_hash' => !empty($operator->password_hash),
            ]);
            return back()->withErrors([
                'email' => 'メールアドレスまたはパスワードが正しくありません。',
            ])->withInput($request->only('email'));
        }

        // セッションにオペレーターIDを保存
        session(['operator_id' => $operator->id]);
        session(['operator_name' => $operator->name]);

        Log::info('OPERATOR_LOGIN_SUCCESS', [
            'operator_id' => $operator->id,
            'email' => $validated['email'],
        ]);

        return redirect()->route('operator.dashboard');
    }

    /**
     * ログアウト処理
     */
    public function logout(Request $request)
    {
        $operatorId = session('operator_id');
        
        session()->forget(['operator_id', 'operator_name']);
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        Log::info('OPERATOR_LOGOUT', [
            'operator_id' => $operatorId,
        ]);

        return redirect()->route('operator.login');
    }
}

