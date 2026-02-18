<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

return new class extends Migration
{
    public function up(): void
    {
        // 既存のレコードでpublic_urlがnullの場合、更新する
        $assets = DB::table('shop_media_assets')
            ->whereNull('public_url')
            ->whereNotNull('file_path')
            ->get();

        foreach ($assets as $asset) {
            // file_pathが古い形式（shops/{id}/media/...）の場合は新しい形式に変換
            $oldPath = $asset->file_path;
            $newPath = null;
            
            // 古い形式を検出して変換
            if (preg_match('/^shops\/(\d+)\/media\/(images|videos)\/(.+)$/', $oldPath, $matches)) {
                $shopId = $matches[1];
                $filename = $matches[3];
                $newPath = "media_assets/{$shopId}/{$filename}";
            } elseif (strpos($oldPath, 'media_assets/') === 0) {
                // 既に新しい形式の場合はそのまま
                $newPath = $oldPath;
            }
            
            if ($newPath && Storage::disk('public')->exists($newPath)) {
                $publicUrl = Storage::disk('public')->url($newPath);
                DB::table('shop_media_assets')
                    ->where('id', $asset->id)
                    ->update([
                        'file_path' => $newPath,
                        'public_url' => $publicUrl,
                    ]);
            }
        }
    }

    public function down(): void
    {
        // ロールバック時は何もしない（public_urlはnullableなので問題なし）
    }
};









