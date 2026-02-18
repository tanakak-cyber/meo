<?php

namespace App\Http\Controllers;

use App\Models\Plan;
use App\Models\SalesPerson;
use App\Models\OperationPerson;
use App\Models\User;
use Illuminate\Http\Request;

class MasterController extends Controller
{
    /**
     * マスタ管理画面のトップページ
     */
    public function index()
    {
        return view('masters.index');
    }

    /**
     * プラン一覧
     */
    public function plans()
    {
        $plans = Plan::orderBy('display_order')->orderBy('id')->get();
        return view('masters.plans.index', compact('plans'));
    }

    /**
     * プラン作成フォーム
     */
    public function createPlan()
    {
        return view('masters.plans.create');
    }

    /**
     * プラン保存
     */
    public function storePlan(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:plans,name',
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        Plan::create($validated);

        return redirect()->route('masters.plans.index')->with('success', 'プランを登録しました。');
    }

    /**
     * プラン編集フォーム
     */
    public function editPlan(Plan $plan)
    {
        return view('masters.plans.edit', compact('plan'));
    }

    /**
     * プラン更新
     */
    public function updatePlan(Request $request, Plan $plan)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255|unique:plans,name,' . $plan->id,
            'description' => 'nullable|string',
            'price' => 'nullable|numeric|min:0',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $plan->update($validated);

        return redirect()->route('masters.plans.index')->with('success', 'プランを更新しました。');
    }

    /**
     * プラン削除
     */
    public function destroyPlan(Plan $plan)
    {
        // 使用中のプランは削除できない
        if ($plan->shops()->count() > 0) {
            return redirect()->route('masters.plans.index')
                ->with('error', 'このプランは使用中のため削除できません。');
        }

        $plan->delete();

        return redirect()->route('masters.plans.index')->with('success', 'プランを削除しました。');
    }

    /**
     * 担当営業一覧
     */
    public function salesPersons()
    {
        $salesPersons = SalesPerson::orderBy('display_order')->orderBy('id')->get();
        return view('masters.sales-persons.index', compact('salesPersons'));
    }

    /**
     * 担当営業作成フォーム
     */
    public function createSalesPerson()
    {
        return view('masters.sales-persons.create');
    }

    /**
     * 担当営業保存
     */
    public function storeSalesPerson(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        SalesPerson::create($validated);

        return redirect()->route('masters.sales-persons.index')->with('success', '担当営業を登録しました。');
    }

    /**
     * 担当営業編集フォーム
     */
    public function editSalesPerson(SalesPerson $salesPerson)
    {
        return view('masters.sales-persons.edit', compact('salesPerson'));
    }

    /**
     * 担当営業更新
     */
    public function updateSalesPerson(Request $request, SalesPerson $salesPerson)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $salesPerson->update($validated);

        return redirect()->route('masters.sales-persons.index')->with('success', '担当営業を更新しました。');
    }

    /**
     * 担当営業削除
     */
    public function destroySalesPerson(SalesPerson $salesPerson)
    {
        // 使用中の担当営業は削除できない
        if ($salesPerson->shops()->count() > 0) {
            return redirect()->route('masters.sales-persons.index')
                ->with('error', 'この担当営業は使用中のため削除できません。');
        }

        $salesPerson->delete();

        return redirect()->route('masters.sales-persons.index')->with('success', '担当営業を削除しました。');
    }

    /**
     * オペレーション担当一覧
     */
    public function operationPersons()
    {
        $operationPersons = OperationPerson::orderBy('display_order')->orderBy('id')->get();
        return view('masters.operation-persons.index', compact('operationPersons'));
    }

    /**
     * オペレーション担当作成フォーム
     */
    public function createOperationPerson()
    {
        return view('masters.operation-persons.create');
    }

    /**
     * オペレーション担当保存
     */
    public function storeOperationPerson(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'password' => 'required|string|min:8',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $operationPerson = new OperationPerson();
        $operationPerson->name = $validated['name'];
        $operationPerson->email = $validated['email'] ?? null;
        $operationPerson->phone = $validated['phone'] ?? null;
        $operationPerson->display_order = $validated['display_order'] ?? 0;
        $operationPerson->is_active = $validated['is_active'] ?? true;
        $operationPerson->setPassword($validated['password']);
        $operationPerson->save();

        return redirect()->route('masters.operation-persons.index')->with('success', 'オペレーション担当を登録しました。');
    }

    /**
     * オペレーション担当編集フォーム
     */
    public function editOperationPerson(OperationPerson $operationPerson)
    {
        return view('masters.operation-persons.edit', compact('operationPerson'));
    }

    /**
     * オペレーション担当更新
     */
    public function updateOperationPerson(Request $request, OperationPerson $operationPerson)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'nullable|email|max:255',
            'phone' => 'nullable|string|max:255',
            'password' => 'nullable|string|min:8',
            'display_order' => 'nullable|integer|min:0',
            'is_active' => 'boolean',
        ]);

        $operationPerson->name = $validated['name'];
        $operationPerson->email = $validated['email'] ?? null;
        $operationPerson->phone = $validated['phone'] ?? null;
        $operationPerson->display_order = $validated['display_order'] ?? 0;
        $operationPerson->is_active = $validated['is_active'] ?? true;
        
        // パスワードが入力されている場合のみ更新
        if (!empty($validated['password'])) {
            $operationPerson->setPassword($validated['password']);
        }
        
        $operationPerson->save();

        return redirect()->route('masters.operation-persons.index')->with('success', 'オペレーション担当を更新しました。');
    }

    /**
     * オペレーション担当削除
     */
    public function destroyOperationPerson(OperationPerson $operationPerson)
    {
        // スナップショットが存在する場合は削除できない（データ整合性のため）
        // 同期実行者として記録されているスナップショットをチェック
        $snapshotCount = \App\Models\GbpSnapshot::where('synced_by_operator_id', $operationPerson->id)->count();
        if ($snapshotCount > 0) {
            return redirect()->route('masters.operation-persons.index')
                ->with('error', "このオペレーション担当は同期データ（{$snapshotCount}件）が存在するため削除できません。");
        }

        // shopsで使用中でも削除可能（operation_person_idは自動的にnullになる）
        // ただし、警告メッセージを表示
        $shopCount = $operationPerson->shops()->count();
        if ($shopCount > 0) {
            // 店舗のoperation_person_idをnullに設定
            $operationPerson->shops()->update(['operation_person_id' => null]);
        }

        $operationPerson->delete();

        $message = 'オペレーション担当を削除しました。';
        if ($shopCount > 0) {
            $message .= " 関連する{$shopCount}件の店舗のオペレーション担当が解除されました。";
        }

        return redirect()->route('masters.operation-persons.index')->with('success', $message);
    }

    /**
     * 管理者一覧
     */
    public function admins()
    {
        $admins = User::orderBy('id')->get();
        return view('masters.admins.index', compact('admins'));
    }

    /**
     * 管理者作成フォーム
     */
    public function createAdmin()
    {
        return view('masters.admins.create');
    }

    /**
     * 管理者保存
     */
    public function storeAdmin(Request $request)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email',
            'password' => 'required|string|min:8',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:dashboard,shops.index,shops.schedule,reviews.index,reports.index,gbp-insights.import,report-email-settings.index,masters.index,competitor-analysis',
            'customer_scope' => 'nullable|string|in:own,all',
        ]);

        $admin = new User();
        $admin->name = $validated['name'];
        $admin->email = $validated['email'];
        $admin->setPassword($validated['password']);
        $admin->permissions = $validated['permissions'] ?? [];
        $admin->is_admin = true;
        // 顧客一覧権限がある場合のみcustomer_scopeを設定
        if (in_array('shops.index', $admin->permissions)) {
            $admin->customer_scope = $validated['customer_scope'] ?? 'own';
        } else {
            $admin->customer_scope = 'own'; // デフォルト値
        }
        $admin->save();

        return redirect()->route('masters.admins.index')->with('success', '管理者を登録しました。');
    }

    /**
     * 管理者編集フォーム
     */
    public function editAdmin(User $admin)
    {
        return view('masters.admins.edit', compact('admin'));
    }

    /**
     * 管理者更新
     */
    public function updateAdmin(Request $request, User $admin)
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $admin->id,
            'password' => 'nullable|string|min:8',
            'permissions' => 'nullable|array',
            'permissions.*' => 'string|in:dashboard,shops.index,shops.schedule,reviews.index,reports.index,gbp-insights.import,report-email-settings.index,masters.index,competitor-analysis',
            'customer_scope' => 'nullable|string|in:own,all',
        ]);

        $admin->name = $validated['name'];
        $admin->email = $validated['email'];
        
        // パスワードが入力されている場合のみ更新
        if (!empty($validated['password'])) {
            $admin->setPassword($validated['password']);
        }
        
        $admin->permissions = $validated['permissions'] ?? [];
        
        // 顧客一覧権限がある場合のみcustomer_scopeを更新
        if (in_array('shops.index', $admin->permissions)) {
            $admin->customer_scope = $validated['customer_scope'] ?? 'own';
        } else {
            // 顧客一覧権限がない場合はcustomer_scopeを'own'に設定（デフォルト）
            $admin->customer_scope = 'own';
        }
        
        $admin->save();

        return redirect()->route('masters.admins.index')->with('success', '管理者を更新しました。');
    }

    /**
     * 管理者削除
     */
    public function destroyAdmin(User $admin)
    {
        // 自分自身は削除できない
        if ($admin->id === auth()->id()) {
            return redirect()->route('masters.admins.index')
                ->with('error', '自分自身を削除することはできません。');
        }

        $admin->delete();

        return redirect()->route('masters.admins.index')->with('success', '管理者を削除しました。');
    }
}

