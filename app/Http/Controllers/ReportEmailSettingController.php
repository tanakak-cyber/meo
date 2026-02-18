<?php

namespace App\Http\Controllers;

use App\Models\ReportEmailSetting;
use Illuminate\Http\Request;

class ReportEmailSettingController extends Controller
{
    public function index()
    {
        $setting = ReportEmailSetting::getSettings();
        
        // 管理者メールアドレスが設定されていない場合は、ログインユーザーのメールアドレスを使用
        if (empty($setting->admin_email) && auth()->check()) {
            $setting->admin_email = auth()->user()->email;
        }
        
        return view('report-email-settings.index', compact('setting'));
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'subject' => 'required|string|max:255',
            'body' => 'required|string',
            'admin_email' => 'required|email|max:255',
        ]);

        $setting = ReportEmailSetting::getSettings();
        $setting->update($validated);

        return redirect()->route('report-email-settings.index')
            ->with('success', 'メール文面設定を更新しました。');
    }
}

