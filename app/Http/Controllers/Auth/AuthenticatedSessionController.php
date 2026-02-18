<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;
use Illuminate\Support\Facades\Redirect;

class AuthenticatedSessionController extends Controller
{
    /**
     * Display the login view.
     */
    public function create(): View
    {
        // 管理者ログイン画面を表示（オペレーターログインへのリンクも表示）
        return view('auth.login');
    }

    /**
     * Handle an incoming authentication request.
     */
    public function store(LoginRequest $request): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        // ログインしたユーザーの権限をセッションに保存
        $user = Auth::user();
        if ($user && $user->permissions) {
            session(['admin_permissions' => $user->permissions]);
        } else {
            session(['admin_permissions' => []]);
        }

        // 権限が設定されている場合、ダッシュボードの権限があるかチェック
        $permissions = session('admin_permissions', []);
        if (!empty($permissions) && in_array('dashboard', $permissions)) {
            return redirect()->intended(route('dashboard', absolute: false));
        } elseif (!empty($permissions)) {
            // ダッシュボードの権限がない場合は、最初の権限があるページにリダイレクト
            $firstPermission = $permissions[0];
            $routeMap = [
                'shops.index' => 'shops.index',
                'shops.schedule' => 'shops.schedule',
                'reviews.index' => 'reviews.index',
                'reports.index' => 'reports.index',
                'report-email-settings.index' => 'report-email-settings.index',
                'masters.index' => 'masters.index',
            ];
            if (isset($routeMap[$firstPermission])) {
                $route = $routeMap[$firstPermission];
                if ($route === 'shops.schedule') {
                    return redirect()->route($route, ['year' => now()->year, 'month' => now()->month]);
                }
                return redirect()->route($route);
            }
        }
        
        // 権限がない場合は、ログイン画面にリダイレクト（403エラーではなく）
        // ただし、intended URLが設定されている場合は、そこにリダイレクト
        $intended = session()->pull('url.intended', route('login'));
        return redirect($intended)->with('error', 'アクセス権限がありません。権限が設定されていません。');
    }

    /**
     * Destroy an authenticated session.
     */
    public function destroy(Request $request): RedirectResponse
    {
        Auth::guard('web')->logout();

        $request->session()->invalidate();

        $request->session()->regenerateToken();

        return redirect('/');
    }
}
