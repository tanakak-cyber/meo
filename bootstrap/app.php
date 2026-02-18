<?php

use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Support\Facades\Route;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
        then: function () {
            Route::middleware('web')
                ->group(base_path('routes/auth.php'));
        },
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->alias([
            'operator.auth' => \App\Http\Middleware\OperatorAuth::class,
            'admin.only' => \App\Http\Middleware\AdminOnly::class,
            'admin' => \App\Http\Middleware\AdminOnly::class,
            'admin.permission' => \App\Http\Middleware\CheckAdminPermission::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        // CSRFトークン切れ（419）の処理
        $exceptions->render(function (\Illuminate\Session\TokenMismatchException $e, \Illuminate\Http\Request $request) {
            \Illuminate\Support\Facades\Log::warning('CSRF_TOKEN_MISMATCH', [
                'url' => $request->fullUrl(),
                'method' => $request->method(),
                'ip' => $request->ip(),
                'user_agent' => $request->userAgent(),
                'user_id' => auth()->id(),
                'session_id' => session()->getId(),
                'exception_message' => $e->getMessage(),
            ]);
            
            if ($request->expectsJson()) {
                return response()->json(['message' => 'セッションの有効期限が切れました。もう一度ログインしてください。'], 419);
            }
            
            return redirect()->route('login')->with(
                'message',
                'セッションの有効期限が切れました。もう一度ログインしてください。'
            );
        });
        
        // 419エラーの一般的な処理（TokenMismatchExceptionがキャッチされない場合のフォールバック）
        $exceptions->render(function (\Symfony\Component\HttpKernel\Exception\HttpException $e, \Illuminate\Http\Request $request) {
            if ($e->getStatusCode() === 419) {
                \Illuminate\Support\Facades\Log::warning('HTTP_419_PAGE_EXPIRED', [
                    'url' => $request->fullUrl(),
                    'method' => $request->method(),
                    'ip' => $request->ip(),
                    'user_agent' => $request->userAgent(),
                    'user_id' => auth()->id(),
                    'session_id' => session()->getId(),
                    'exception_message' => $e->getMessage(),
                ]);
                
                if ($request->expectsJson()) {
                    return response()->json(['message' => 'セッションの有効期限が切れました。もう一度ログインしてください。'], 419);
                }
                
                return redirect()->route('login')->with(
                    'message',
                    'セッションの有効期限が切れました。もう一度ログインしてください。'
                );
            }
        });
    })->create();
