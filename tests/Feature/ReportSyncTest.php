<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Shop;
use App\Models\User;
use App\Models\GbpSnapshot;
use App\Models\Review;
use App\Models\Photo;
use App\Services\GoogleBusinessProfileService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Carbon\Carbon;

class ReportSyncTest extends TestCase
{
    use RefreshDatabase;

    /**
     * 同期ボタン（ReportController::sync）のテスト
     * 既存ロジックの挙動を固定するためのテスト
     */
    public function test_sync_creates_snapshot_and_saves_reviews_photos_posts_count(): void
    {
        // ダミーshopを作成
        $shop = Shop::factory()->create([
            'name' => 'テスト店舗',
            'gbp_account_id' => '123456789012345678901',
            'gbp_location_id' => 'locations/987654321098765432109',
            'gbp_refresh_token' => 'dummy_refresh_token',
            'contract_end_date' => null, // 契約中
        ]);

        // 認証済みユーザーを作成
        $user = User::factory()->create([
            'is_admin' => true,
            'permissions' => ['reports.index'],
        ]);

        // Google API レスポンスをモック
        Http::fake([
            // リフレッシュトークンでアクセストークン取得
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'dummy_access_token',
                'expires_in' => 3600,
            ], 200),
            
            // アクセストークン取得（tokeninfo）
            'https://www.googleapis.com/oauth2/v3/tokeninfo*' => Http::response([
                'email' => 'test@example.com',
                'expires_in' => 3600,
            ], 200),
            
            // 口コミ一覧取得
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/reviews' => Http::response([
                'reviews' => [
                    [
                        'name' => 'accounts/123456789012345678901/locations/987654321098765432109/reviews/1111111111111111111',
                        'reviewer' => [
                            'displayName' => 'テストユーザー1',
                        ],
                        'starRating' => 'FIVE',
                        'comment' => '素晴らしい店です！',
                        'createTime' => '2024-01-01T10:00:00Z',
                    ],
                    [
                        'name' => 'accounts/123456789012345678901/locations/987654321098765432109/reviews/2222222222222222222',
                        'reviewer' => [
                            'displayName' => 'テストユーザー2',
                        ],
                        'starRating' => 'FOUR',
                        'comment' => '良い店です',
                        'createTime' => '2024-01-02T10:00:00Z',
                    ],
                ],
            ], 200),
            
            // 写真一覧取得
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/media' => Http::response([
                'mediaItems' => [
                    [
                        'name' => 'accounts/123456789012345678901/locations/987654321098765432109/media/1111111111111111111',
                        'mediaFormat' => 'PHOTO',
                        'googleUrl' => 'https://example.com/photo1.jpg',
                        'thumbnailUrl' => 'https://example.com/photo1_thumb.jpg',
                        'createTime' => '2024-01-01T10:00:00Z',
                        'dimensions' => [
                            'widthPixels' => 1920,
                            'heightPixels' => 1080,
                        ],
                    ],
                    [
                        'name' => 'accounts/123456789012345678901/locations/987654321098765432109/media/2222222222222222222',
                        'mediaFormat' => 'PHOTO',
                        'googleUrl' => 'https://example.com/photo2.jpg',
                        'thumbnailUrl' => 'https://example.com/photo2_thumb.jpg',
                        'createTime' => '2024-01-02T10:00:00Z',
                        'dimensions' => [
                            'widthPixels' => 1920,
                            'heightPixels' => 1080,
                        ],
                    ],
                ],
                'totalMediaItemCount' => 2,
            ], 200),
            
            // 投稿一覧取得
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/localPosts' => Http::response([
                'localPosts' => [
                    [
                        'name' => 'localPosts/1111111111111111111',
                        'summary' => '投稿1',
                    ],
                    [
                        'name' => 'localPosts/2222222222222222222',
                        'summary' => '投稿2',
                    ],
                    [
                        'name' => 'localPosts/3333333333333333333',
                        'summary' => '投稿3',
                    ],
                ],
            ], 200),
        ]);

        // 同期前の状態を確認
        $snapshotCountBefore = GbpSnapshot::where('shop_id', $shop->id)->count();
        $reviewCountBefore = Review::where('shop_id', $shop->id)->count();
        $photoCountBefore = Photo::where('shop_id', $shop->id)->count();

        // 同期を実行
        $response = $this->actingAs($user)
            ->withSession(['admin_permissions' => ['reports.index']])
            ->post(route('reports.sync', $shop->id), [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]);

        // レスポンスを確認
        $response->assertRedirect();

        // A) gbp_snapshots が1件増えている
        $snapshotCountAfter = GbpSnapshot::where('shop_id', $shop->id)->count();
        $this->assertEquals($snapshotCountBefore + 1, $snapshotCountAfter, 'gbp_snapshots が1件増えていること');

        // 最新のスナップショットを取得
        $snapshot = GbpSnapshot::where('shop_id', $shop->id)
            ->orderBy('synced_at', 'desc')
            ->first();

        $this->assertNotNull($snapshot, 'スナップショットが作成されていること');
        $this->assertEquals($shop->id, $snapshot->shop_id, 'shop_id が正しく保存されていること');
        $this->assertEquals($user->id, $snapshot->user_id, 'user_id が正しく保存されていること');
        $this->assertEquals(2, $snapshot->reviews_count, 'reviews_count が正しく保存されていること');
        $this->assertEquals(2, $snapshot->photos_count, 'photos_count が正しく保存されていること');
        $this->assertEquals(3, $snapshot->posts_count, 'posts_count が正しく保存されていること');

        // B) reviews が snapshot_id で保存されている
        $reviewCountAfter = Review::where('shop_id', $shop->id)->count();
        $this->assertEquals($reviewCountBefore + 2, $reviewCountAfter, 'reviews が2件増えていること');

        $reviews = Review::where('shop_id', $shop->id)
            ->where('snapshot_id', $snapshot->id)
            ->get();

        $this->assertCount(2, $reviews, 'snapshot_id で reviews が2件保存されていること');
        $this->assertTrue($reviews->every(fn($review) => $review->snapshot_id === $snapshot->id), 'すべての reviews が snapshot_id を持っていること');

        // レビューの内容を確認
        $review1 = $reviews->firstWhere('gbp_review_id', '1111111111111111111');
        $this->assertNotNull($review1, 'レビュー1が保存されていること');
        $this->assertEquals('テストユーザー1', $review1->author_name, 'author_name が正しく保存されていること');
        $this->assertEquals(5, $review1->rating, 'rating が正しく保存されていること');
        $this->assertEquals('素晴らしい店です！', $review1->comment, 'comment が正しく保存されていること');

        // C) photos が snapshot_id で保存されている
        $photoCountAfter = Photo::where('shop_id', $shop->id)->count();
        $this->assertEquals($photoCountBefore + 2, $photoCountAfter, 'photos が2件増えていること');

        $photos = Photo::where('shop_id', $shop->id)
            ->where('snapshot_id', $snapshot->id)
            ->get();

        $this->assertCount(2, $photos, 'snapshot_id で photos が2件保存されていること');
        $this->assertTrue($photos->every(fn($photo) => $photo->snapshot_id === $snapshot->id), 'すべての photos が snapshot_id を持っていること');

        // D) gbp_snapshots.posts_count が保存されている（上記で確認済み）
        $this->assertEquals(3, $snapshot->posts_count, 'gbp_snapshots.posts_count が3件保存されていること');
    }

    /**
     * 同期を2回実行した場合の挙動をテスト
     * snapshotは2件になること、reviewsは重複保存されない設計であることを確認
     */
    public function test_sync_twice_creates_two_snapshots_and_duplicates_reviews_photos(): void
    {
        // ダミーshopを作成
        $shop = Shop::factory()->create([
            'name' => 'テスト店舗',
            'gbp_account_id' => '123456789012345678901',
            'gbp_location_id' => 'locations/987654321098765432109',
            'gbp_refresh_token' => 'dummy_refresh_token',
            'contract_end_date' => null, // 契約中
        ]);

        // 認証済みユーザーを作成
        $user = User::factory()->create([
            'is_admin' => true,
            'permissions' => ['reports.index'],
        ]);

        // Google API レスポンスをモック（同じレスポンスを返す）
        Http::fake([
            // リフレッシュトークンでアクセストークン取得
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'dummy_access_token',
                'expires_in' => 3600,
            ], 200),
            
            // アクセストークン取得（tokeninfo）
            'https://www.googleapis.com/oauth2/v3/tokeninfo*' => Http::response([
                'email' => 'test@example.com',
                'expires_in' => 3600,
            ], 200),
            
            // 口コミ一覧取得（同じデータを返す）
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/reviews' => Http::response([
                'reviews' => [
                    [
                        'name' => 'accounts/123456789012345678901/locations/987654321098765432109/reviews/1111111111111111111',
                        'reviewer' => [
                            'displayName' => 'テストユーザー1',
                        ],
                        'starRating' => 'FIVE',
                        'comment' => '素晴らしい店です！',
                        'createTime' => '2024-01-01T10:00:00Z',
                    ],
                ],
            ], 200),
            
            // 写真一覧取得（同じデータを返す）
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/media' => Http::response([
                'mediaItems' => [
                    [
                        'name' => 'accounts/123456789012345678901/locations/987654321098765432109/media/1111111111111111111',
                        'mediaFormat' => 'PHOTO',
                        'googleUrl' => 'https://example.com/photo1.jpg',
                        'thumbnailUrl' => 'https://example.com/photo1_thumb.jpg',
                        'createTime' => '2024-01-01T10:00:00Z',
                        'dimensions' => [
                            'widthPixels' => 1920,
                            'heightPixels' => 1080,
                        ],
                    ],
                ],
                'totalMediaItemCount' => 1,
            ], 200),
            
            // 投稿一覧取得（同じデータを返す）
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/localPosts' => Http::response([
                'localPosts' => [
                    [
                        'name' => 'localPosts/1111111111111111111',
                        'summary' => '投稿1',
                    ],
                ],
            ], 200),
        ]);

        // 1回目の同期
        $response1 = $this->actingAs($user)
            ->withSession(['admin_permissions' => ['reports.index']])
            ->post(route('reports.sync', $shop->id), [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]);
        $response1->assertRedirect();

        // 1回目後の状態を確認
        $snapshotsAfterFirst = GbpSnapshot::where('shop_id', $shop->id)->get();
        $this->assertCount(1, $snapshotsAfterFirst, '1回目の同期後、snapshot が1件作成されていること');

        $snapshot1 = $snapshotsAfterFirst->first();
        $reviewsAfterFirst = Review::where('shop_id', $shop->id)->count();
        $this->assertEquals(1, $reviewsAfterFirst, '1回目の同期後、reviews が1件保存されていること');

        $photosAfterFirst = Photo::where('shop_id', $shop->id)
            ->where('snapshot_id', $snapshot1->id)
            ->count();
        $this->assertEquals(1, $photosAfterFirst, '1回目の同期後、photos が1件保存されていること');

        // 少し待ってから2回目の同期（synced_at が異なることを確認するため）
        sleep(1);

        // 2回目の同期
        $response2 = $this->actingAs($user)
            ->withSession(['admin_permissions' => ['reports.index']])
            ->post(route('reports.sync', $shop->id), [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]);
        $response2->assertRedirect();

        // 2回目後の状態を確認
        $snapshotsAfterSecond = GbpSnapshot::where('shop_id', $shop->id)->get();
        $this->assertCount(2, $snapshotsAfterSecond, '2回目の同期後、snapshot が2件作成されていること');

        $snapshot2 = $snapshotsAfterSecond->sortByDesc('synced_at')->first();
        $this->assertNotEquals($snapshot1->id, $snapshot2->id, '2つの snapshot が異なるIDであること');
        $this->assertTrue($snapshot2->synced_at->gt($snapshot1->synced_at), '2回目の snapshot の synced_at が1回目より後であること');

        // 同じ gbp_review_id は1件のみ保存される（重複しない）
        $allReviews = Review::where('shop_id', $shop->id)->get();
        $this->assertCount(1, $allReviews, '2回目の同期後、reviews が合計1件保存されていること（重複しない）');

        // 最新のsnapshot_idで保存されていることを確認
        $review = Review::where('shop_id', $shop->id)
            ->where('gbp_review_id', '1111111111111111111')
            ->first();
        $this->assertNotNull($review, 'レビューが保存されていること');
        $this->assertEquals($snapshot2->id, $review->snapshot_id, '最新のsnapshot_idで保存されていること');

        // 同じ gbp_media_id でも異なる snapshot_id で保存される
        $allPhotos = Photo::where('shop_id', $shop->id)->get();
        $this->assertCount(2, $allPhotos, '2回目の同期後、photos が合計2件保存されていること（重複保存）');

        // snapshot1 と snapshot2 の両方に同じ gbp_media_id の写真が保存されている
        $photo1InSnapshot1 = Photo::where('shop_id', $shop->id)
            ->where('snapshot_id', $snapshot1->id)
            ->exists();
        $this->assertTrue($photo1InSnapshot1, 'snapshot1 に写真が保存されていること');

        $photo1InSnapshot2 = Photo::where('shop_id', $shop->id)
            ->where('snapshot_id', $snapshot2->id)
            ->exists();
        $this->assertTrue($photo1InSnapshot2, 'snapshot2 に写真が保存されていること（重複保存）');

        // 各 snapshot の reviews_count, photos_count, posts_count を確認
        $this->assertEquals(1, $snapshot1->reviews_count, 'snapshot1 の reviews_count が1であること');
        $this->assertEquals(1, $snapshot1->photos_count, 'snapshot1 の photos_count が1であること');
        $this->assertEquals(1, $snapshot1->posts_count, 'snapshot1 の posts_count が1であること');

        $this->assertEquals(1, $snapshot2->reviews_count, 'snapshot2 の reviews_count が1であること');
        $this->assertEquals(1, $snapshot2->photos_count, 'snapshot2 の photos_count が1であること');
        $this->assertEquals(1, $snapshot2->posts_count, 'snapshot2 の posts_count が1であること');
    }

    /**
     * ユーザーごとにデータが分離されていることを確認するテスト
     * userAで同期実行、userBで同期実行し、それぞれのユーザーで表示した場合に
     * 自分のスナップショットのみが取得されることを確認
     */
    public function test_sync_isolated_by_user(): void
    {
        // ダミーshopを作成
        $shop = Shop::factory()->create([
            'name' => 'テスト店舗',
            'gbp_account_id' => '123456789012345678901',
            'gbp_location_id' => 'locations/987654321098765432109',
            'gbp_refresh_token' => 'dummy_refresh_token',
            'contract_end_date' => null, // 契約中
        ]);

        // userAを作成
        $userA = User::factory()->create([
            'name' => 'ユーザーA',
            'is_admin' => true,
            'permissions' => ['reports.index'],
        ]);

        // userBを作成
        $userB = User::factory()->create([
            'name' => 'ユーザーB',
            'is_admin' => true,
            'permissions' => ['reports.index'],
        ]);

        // Google API レスポンスをモック
        Http::fake([
            // リフレッシュトークンでアクセストークン取得
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'dummy_access_token',
                'expires_in' => 3600,
            ], 200),
            
            // アクセストークン取得（tokeninfo）
            'https://www.googleapis.com/oauth2/v3/tokeninfo*' => Http::response([
                'email' => 'test@example.com',
                'expires_in' => 3600,
            ], 200),
            
            // 口コミ一覧取得
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/reviews' => Http::response([
                'reviews' => [
                    [
                        'name' => 'accounts/123456789012345678901/locations/987654321098765432109/reviews/1111111111111111111',
                        'reviewer' => [
                            'displayName' => 'テストユーザー1',
                        ],
                        'starRating' => 'FIVE',
                        'comment' => '素晴らしい店です！',
                        'createTime' => '2024-01-01T10:00:00Z',
                    ],
                ],
            ], 200),
            
            // 写真一覧取得
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/media' => Http::response([
                'mediaItems' => [
                    [
                        'name' => 'accounts/123456789012345678901/locations/987654321098765432109/media/1111111111111111111',
                        'mediaFormat' => 'PHOTO',
                        'googleUrl' => 'https://example.com/photo1.jpg',
                        'thumbnailUrl' => 'https://example.com/photo1_thumb.jpg',
                        'createTime' => '2024-01-01T10:00:00Z',
                        'dimensions' => [
                            'widthPixels' => 1920,
                            'heightPixels' => 1080,
                        ],
                    ],
                ],
                'totalMediaItemCount' => 1,
            ], 200),
            
            // 投稿一覧取得
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/localPosts' => Http::response([
                'localPosts' => [
                    [
                        'name' => 'localPosts/1111111111111111111',
                        'summary' => '投稿1',
                    ],
                ],
            ], 200),
        ]);

        // userAで同期実行
        $responseA = $this->actingAs($userA)
            ->withSession(['admin_permissions' => ['reports.index']])
            ->post(route('reports.sync', $shop->id), [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]);
        $responseA->assertRedirect();

        // 少し待ってからuserBで同期実行（synced_at が異なることを確認するため）
        sleep(1);

        // userBで同期実行
        $responseB = $this->actingAs($userB)
            ->withSession(['admin_permissions' => ['reports.index']])
            ->post(route('reports.sync', $shop->id), [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]);
        $responseB->assertRedirect();

        // gbp_snapshots が2件ある
        $allSnapshots = GbpSnapshot::where('shop_id', $shop->id)->get();
        $this->assertCount(2, $allSnapshots, 'gbp_snapshots が2件あること');

        // snapshot1.user_id != snapshot2.user_id
        $snapshot1 = $allSnapshots->sortBy('synced_at')->first();
        $snapshot2 = $allSnapshots->sortByDesc('synced_at')->first();
        
        $this->assertNotEquals($snapshot1->user_id, $snapshot2->user_id, 'snapshot1.user_id != snapshot2.user_id であること');
        
        // userAのスナップショットとuserBのスナップショットを特定
        $snapshotA = $allSnapshots->firstWhere('user_id', $userA->id);
        $snapshotB = $allSnapshots->firstWhere('user_id', $userB->id);
        
        $this->assertNotNull($snapshotA, 'userAのスナップショットが存在すること');
        $this->assertNotNull($snapshotB, 'userBのスナップショットが存在すること');
        $this->assertEquals($userA->id, $snapshotA->user_id, 'snapshotA の user_id が userA であること');
        $this->assertEquals($userB->id, $snapshotB->user_id, 'snapshotB の user_id が userB であること');

        // userAで表示した場合は userAのsnapshotのみ取得される
        // actingAs を設定してから AuthHelper を使用
        $this->actingAs($userA)
            ->withSession(['admin_permissions' => ['reports.index']]);
        $currentUserIdA = \App\Helpers\AuthHelper::getCurrentUserId();
        $latestSnapshotA = GbpSnapshot::where('shop_id', $shop->id)
            ->where('user_id', $currentUserIdA)
            ->orderBy('synced_at', 'desc')
            ->first();
        
        $this->assertNotNull($latestSnapshotA, 'userAで表示した場合、スナップショットが取得されること');
        $this->assertEquals($userA->id, $latestSnapshotA->user_id, 'userAで表示した場合、userAのsnapshotのみ取得されること');
        $this->assertEquals($snapshotA->id, $latestSnapshotA->id, 'userAで表示した場合、userAのsnapshotが取得されること');
        
        // userAのスナップショット一覧を取得（すべてuserAのもののみ）
        $snapshotsA = GbpSnapshot::where('shop_id', $shop->id)
            ->where('user_id', $currentUserIdA)
            ->orderBy('synced_at', 'desc')
            ->get();
        
        $this->assertCount(1, $snapshotsA, 'userAで表示した場合、userAのsnapshotが1件のみ取得されること');
        $this->assertTrue($snapshotsA->every(fn($snapshot) => $snapshot->user_id === $userA->id), 'userAで表示した場合、すべてのsnapshotがuserAのものであること');

        // userBで表示した場合は userBのsnapshotのみ取得される
        // actingAs を設定してから AuthHelper を使用
        $this->actingAs($userB)
            ->withSession(['admin_permissions' => ['reports.index']]);
        $currentUserIdB = \App\Helpers\AuthHelper::getCurrentUserId();
        $latestSnapshotB = GbpSnapshot::where('shop_id', $shop->id)
            ->where('user_id', $currentUserIdB)
            ->orderBy('synced_at', 'desc')
            ->first();
        
        $this->assertNotNull($latestSnapshotB, 'userBで表示した場合、スナップショットが取得されること');
        $this->assertEquals($userB->id, $latestSnapshotB->user_id, 'userBで表示した場合、userBのsnapshotのみ取得されること');
        $this->assertEquals($snapshotB->id, $latestSnapshotB->id, 'userBで表示した場合、userBのsnapshotが取得されること');
        
        // userBのスナップショット一覧を取得（すべてuserBのもののみ）
        $snapshotsB = GbpSnapshot::where('shop_id', $shop->id)
            ->where('user_id', $currentUserIdB)
            ->orderBy('synced_at', 'desc')
            ->get();
        
        $this->assertCount(1, $snapshotsB, 'userBで表示した場合、userBのsnapshotが1件のみ取得されること');
        $this->assertTrue($snapshotsB->every(fn($snapshot) => $snapshot->user_id === $userB->id), 'userBで表示した場合、すべてのsnapshotがuserBのものであること');

        // 念のため、userAとuserBのスナップショットが異なることを確認
        $this->assertNotEquals($latestSnapshotA->id, $latestSnapshotB->id, 'userAとuserBのスナップショットが異なること');
    }

    /**
     * 同じgbp_review_idは複数回同期しても1件しか保存されないテスト
     * 現在の実装では失敗するはず（異なるsnapshot_idで複数保存されるため）
     */
    public function test_sync_same_review_id_should_not_duplicate(): void
    {
        // ダミーshopを作成
        $shop = Shop::factory()->create([
            'name' => 'テスト店舗',
            'gbp_account_id' => '123456789012345678901',
            'gbp_location_id' => 'locations/987654321098765432109',
            'gbp_refresh_token' => 'dummy_refresh_token',
            'contract_end_date' => null, // 契約中
        ]);

        // 認証済みユーザーを作成
        $user = User::factory()->create([
            'is_admin' => true,
            'permissions' => ['reports.index'],
        ]);

        // Google API レスポンスをモック（同じレビューを返す）
        Http::fake([
            // リフレッシュトークンでアクセストークン取得
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'dummy_access_token',
                'expires_in' => 3600,
            ], 200),
            
            // アクセストークン取得（tokeninfo）
            'https://www.googleapis.com/oauth2/v3/tokeninfo*' => Http::response([
                'email' => 'test@example.com',
                'expires_in' => 3600,
            ], 200),
            
            // 口コミ一覧取得（同じレビューを返す）
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/reviews' => Http::response([
                'reviews' => [
                    [
                        'name' => 'accounts/123456789012345678901/locations/987654321098765432109/reviews/1111111111111111111',
                        'reviewer' => [
                            'displayName' => 'テストユーザー1',
                        ],
                        'starRating' => 'FIVE',
                        'comment' => '素晴らしい店です！',
                        'createTime' => '2024-01-01T10:00:00Z',
                    ],
                ],
            ], 200),
            
            // 写真一覧取得
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/media' => Http::response([
                'mediaItems' => [],
                'totalMediaItemCount' => 0,
            ], 200),
            
            // 投稿一覧取得
            'mybusiness.googleapis.com/v4/accounts/*/locations/*/localPosts' => Http::response([
                'localPosts' => [],
            ], 200),
        ]);

        // 1回目の同期
        $response1 = $this->actingAs($user)
            ->withSession(['admin_permissions' => ['reports.index']])
            ->post(route('reports.sync', $shop->id), [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]);
        $response1->assertRedirect();

        // 1回目後のレビュー数を確認
        $reviewCountAfterFirst = Review::where('shop_id', $shop->id)
            ->where('gbp_review_id', '1111111111111111111')
            ->count();
        $this->assertEquals(1, $reviewCountAfterFirst, '1回目の同期後、同じgbp_review_idのレビューが1件保存されていること');

        // 少し待ってから2回目の同期
        sleep(1);

        // 2回目の同期（同じレビューを再度取得）
        $response2 = $this->actingAs($user)
            ->withSession(['admin_permissions' => ['reports.index']])
            ->post(route('reports.sync', $shop->id), [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]);
        $response2->assertRedirect();

        // 2回目後のレビュー数を確認（現在の実装では2件になるはず）
        $reviewCountAfterSecond = Review::where('shop_id', $shop->id)
            ->where('gbp_review_id', '1111111111111111111')
            ->count();
        
        // 現在の実装では失敗するはず（異なるsnapshot_idで複数保存される）
        // 新しい設計では1件のみであるべき
        $this->assertEquals(1, $reviewCountAfterSecond, '2回目の同期後も、同じgbp_review_idのレビューが1件のみ保存されていること（重複しない）');
    }

    /**
     * 返信状態が変わった場合はupdateされるテスト
     * 現在の実装では失敗するはず（新しいsnapshot_idで新規作成されるため、既存のreviewは更新されない）
     */
    public function test_sync_updates_reply_status_when_changed(): void
    {
        // 他テストのfakeを完全に分離
        Http::preventStrayRequests();
        Http::fake([]);

        // ダミーshopを作成
        $shop = Shop::factory()->create([
            'name' => 'テスト店舗',
            'gbp_account_id' => '123456789012345678901',
            'gbp_location_id' => 'locations/987654321098765432109',
            'gbp_refresh_token' => 'dummy_refresh_token',
            'contract_end_date' => null, // 契約中
        ]);

        // 認証済みユーザーを作成
        $user = User::factory()->create([
            'is_admin' => true,
            'permissions' => ['reports.index'],
        ]);

        // reviews APIのレスポンスをシーケンスで設定（1回目：返信なし、2回目：返信あり）
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'dummy_access_token',
                'expires_in' => 3600,
            ], 200),
            'https://www.googleapis.com/oauth2/v3/tokeninfo*' => Http::response([
                'email' => 'test@example.com',
                'expires_in' => 3600,
            ], 200),
            'https://mybusiness.googleapis.com/v4/*/reviews*' => Http::sequence()
                ->push([
                    'reviews' => [
                        [
                            'name' => 'accounts/123456789012345678901/locations/987654321098765432109/reviews/1111111111111111111',
                            'reviewer' => [
                                'displayName' => 'テストユーザー1',
                            ],
                            'starRating' => 'FIVE',
                            'comment' => '素晴らしい店です！',
                            'createTime' => '2024-01-01T10:00:00Z',
                            // 返信なし
                        ],
                    ],
                ], 200)
                ->push([
                    'reviews' => [
                        [
                            'name' => 'accounts/123456789012345678901/locations/987654321098765432109/reviews/1111111111111111111',
                            'reviewer' => [
                                'displayName' => 'テストユーザー1',
                            ],
                            'starRating' => 'FIVE',
                            'comment' => '素晴らしい店です！',
                            'createTime' => '2024-01-01T10:00:00Z',
                            // 返信あり
                            'reviewReply' => [
                                'comment' => 'ありがとうございます！',
                                'updateTime' => '2024-01-02T15:00:00Z',
                            ],
                        ],
                    ],
                ], 200),
            'https://mybusiness.googleapis.com/v4/*/media*' => Http::response([
                'mediaItems' => [],
                'totalMediaItemCount' => 0,
            ], 200),
            'https://mybusiness.googleapis.com/v4/*/localPosts*' => Http::response([
                'localPosts' => [],
            ], 200),
        ]);

        $response1 = $this->actingAs($user)
            ->withSession(['admin_permissions' => ['reports.index']])
            ->post(route('reports.sync', $shop->id), [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]);
        $response1->assertRedirect();

        // 1回目後のレビューを取得
        $review1 = Review::where('shop_id', $shop->id)
            ->where('gbp_review_id', '1111111111111111111')
            ->first();
        $this->assertNotNull($review1, '1回目の同期後、レビューが保存されていること');
        $this->assertNull($review1->reply_text, '1回目の同期後、返信テキストがnullであること');
        $this->assertNull($review1->replied_at, '1回目の同期後、返信日時がnullであること');

        // 少し待ってから2回目：返信ありのレビューを同期（sequenceの2回目が返される）
        sleep(1);

        $response2 = $this->actingAs($user)
            ->withSession(['admin_permissions' => ['reports.index']])
            ->post(route('reports.sync', $shop->id), [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]);
        $response2->assertRedirect();

        // 2回目後のレビューを取得（同じレビューが更新されているか確認）
        $review2 = Review::where('shop_id', $shop->id)
            ->where('gbp_review_id', '1111111111111111111')
            ->first();
        
        // 現在の実装では失敗するはず（新しいsnapshot_idで新規作成されるため、既存のreviewは更新されない）
        // 新しい設計では、同じレビューが更新されるべき
        $this->assertNotNull($review2, '2回目の同期後、レビューが存在すること');
        $this->assertEquals('ありがとうございます！', $review2->reply_text, '2回目の同期後、返信テキストが更新されていること');
        $this->assertNotNull($review2->replied_at, '2回目の同期後、返信日時が更新されていること');
        
        // 同じレビューIDが1件のみであることを確認（重複していない）
        $reviewCount = Review::where('shop_id', $shop->id)
            ->where('gbp_review_id', '1111111111111111111')
            ->count();
        $this->assertEquals(1, $reviewCount, '同じgbp_review_idのレビューが1件のみ保存されていること（重複していない）');
    }

    /**
     * 新規レビューのみ追加され、既存はスキップされることを確認する
     */
    public function test_sync_incremental_only_adds_new_reviews(): void
    {
        \Log::info('TEST_START', ['test_name' => __METHOD__]);

        // 他テストのfakeを完全に分離
        Http::preventStrayRequests();
        
        // 1回のHttp::fakeで全APIを制御（reviews APIはsequenceで1回目：1件、2回目：2件）
        Http::fake([
            'https://oauth2.googleapis.com/token' => Http::response([
                'access_token' => 'dummy_access_token',
                'expires_in' => 3600,
            ], 200),
            'https://www.googleapis.com/oauth2/v3/tokeninfo*' => Http::response([
                'email' => 'test@example.com',
                'expires_in' => 3600,
            ], 200),
            'https://mybusiness.googleapis.com/v4/*/reviews*' => Http::sequence()
                ->push([
                    'reviews' => [
                        [
                            'name' => 'accounts/123456789012345678901/locations/987654321098765432109/reviews/1111111111111111111',
                            'reviewer' => ['displayName' => 'テストユーザー1'],
                            'starRating' => 'FIVE',
                            'comment' => '素晴らしい店です！',
                            'createTime' => '2024-01-01T10:00:00Z',
                        ],
                    ],
                ], 200)
                ->push([
                    'reviews' => [
                        [
                            'name' => 'accounts/123456789012345678901/locations/987654321098765432109/reviews/1111111111111111111',
                            'reviewer' => ['displayName' => 'テストユーザー1'],
                            'starRating' => 'FIVE',
                            'comment' => '素晴らしい店です！',
                            'createTime' => '2024-01-01T10:00:00Z',
                        ],
                        [
                            'name' => 'accounts/123456789012345678901/locations/987654321098765432109/reviews/2222222222222222222',
                            'reviewer' => ['displayName' => 'テストユーザー2'],
                            'starRating' => 'FOUR',
                            'comment' => '良い店です',
                            'createTime' => '2024-01-02T10:00:00Z',
                        ],
                    ],
                ], 200),
            'https://mybusiness.googleapis.com/v4/*/media*' => Http::response([
                'mediaItems' => [],
                'totalMediaItemCount' => 0,
            ], 200),
            'https://mybusiness.googleapis.com/v4/*/localPosts*' => Http::response([
                'localPosts' => [],
            ], 200),
        ]);

        // ダミーshopを作成
        $shop = Shop::factory()->create([
            'name' => 'テスト店舗',
            'gbp_account_id' => '123456789012345678901',
            'gbp_location_id' => 'locations/987654321098765432109',
            'gbp_refresh_token' => 'dummy_refresh_token',
            'contract_end_date' => null, // 契約中
        ]);

        // 認証済みユーザーを作成
        $user = User::factory()->create([
            'is_admin' => true,
            'permissions' => ['reports.index'],
        ]);

        \Log::info('TEST_FAKE_SEQUENCE_CREATED', [
            'test_name' => __METHOD__,
            'sequence_count' => 2,
        ]);

        \Log::info('TEST_SYNC_FIRST_START', ['test_name' => __METHOD__]);
        $response1 = $this->actingAs($user)
            ->withSession(['admin_permissions' => ['reports.index']])
            ->post(route('reports.sync', $shop->id), [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]);
        $response1->assertRedirect();

        // 1回目sync後のHTTPリクエスト記録を確認
        $recordedAfterFirst = Http::recorded();
        $reviewsRequestsAfterFirst = collect($recordedAfterFirst)->filter(function ($call) {
            return str_contains($call[0]->url(), '/reviews');
        });
        
        \Log::info('TEST_HTTP_RECORDED_AFTER_FIRST', [
            'test_name' => __METHOD__,
            'total_requests' => count($recordedAfterFirst),
            'reviews_requests_count' => $reviewsRequestsAfterFirst->count(),
            'reviews_details' => $reviewsRequestsAfterFirst->map(function ($call, $index) {
                $response = $call[1] ?? null;
                $responseData = $response ? $response->json() : null;
                return [
                    'index' => $index,
                    'url' => $call[0]->url(),
                    'reviews_count' => count($responseData['reviews'] ?? []),
                    'review_ids' => collect($responseData['reviews'] ?? [])->map(function ($review) {
                        return basename($review['name'] ?? '');
                    })->toArray(),
                ];
            })->toArray(),
        ]);

        // 1回目のsyncでreviewsが1回だけ呼ばれていることを確認
        $this->assertEquals(1, $reviewsRequestsAfterFirst->count(), '1回目の同期でreviewsエンドポイントが1回だけ呼ばれていること');

        // 1回目後のレビュー数を確認
        $reviewCountAfterFirst = Review::where('shop_id', $shop->id)->count();
        \Log::info('TEST_SYNC_FIRST_COMPLETE', [
            'test_name' => __METHOD__,
            'review_count' => $reviewCountAfterFirst,
        ]);
        $this->assertEquals(1, $reviewCountAfterFirst, '1回目の同期後、レビューが1件保存されていること');

        // 少し待ってから2回目：レビュー2件（既存1件 + 新規1件）を同期
        sleep(1);

        \Log::info('INCREMENTAL_TEST_SECOND_FAKE_HIT');
        \Log::info('TEST_SYNC_SECOND_START', ['test_name' => __METHOD__]);
        $response2 = $this->actingAs($user)
            ->withSession(['admin_permissions' => ['reports.index']])
            ->post(route('reports.sync', $shop->id), [
                'from' => '2024-01-01',
                'to' => '2024-12-31',
            ]);
        $response2->assertRedirect();

        // 2回目sync後のHTTPリクエスト記録を確認
        $recorded = Http::recorded();
        $reviewsRequests = collect($recorded)->filter(function ($call) {
            return str_contains($call[0]->url(), '/reviews');
        });
        
        \Log::info('TEST_HTTP_RECORDED_AFTER_SECOND', [
            'test_name' => __METHOD__,
            'total_requests' => count($recorded),
            'reviews_requests_count' => $reviewsRequests->count(),
            'reviews_details' => $reviewsRequests->map(function ($call, $index) {
                $response = $call[1] ?? null;
                $responseData = $response ? $response->json() : null;
                return [
                    'index' => $index,
                    'url' => $call[0]->url(),
                    'reviews_count' => count($responseData['reviews'] ?? []),
                    'review_ids' => collect($responseData['reviews'] ?? [])->map(function ($review) {
                        return basename($review['name'] ?? '');
                    })->toArray(),
                ];
            })->toArray(),
        ]);

        // reviewsエンドポイントが2回呼ばれたことを確認
        $this->assertEquals(2, $reviewsRequests->count(), 'reviewsエンドポイントが2回呼ばれていること');

        // 2回目のレスポンス内容を確認
        if ($reviewsRequests->count() >= 2) {
            $secondResponse = $reviewsRequests->get(1);
            if ($secondResponse && isset($secondResponse[1])) {
                $secondResponseData = $secondResponse[1]->json();
                \Log::info('TEST_SECOND_RESPONSE_DATA', [
                    'test_name' => __METHOD__,
                    'reviews_count' => count($secondResponseData['reviews'] ?? []),
                    'review_ids' => collect($secondResponseData['reviews'] ?? [])->map(function ($review) {
                        return basename($review['name'] ?? '');
                    })->toArray(),
                ]);
                
                // 2回目のレスポンスに2件のレビューが含まれていることを確認
                $this->assertEquals(2, count($secondResponseData['reviews'] ?? []), '2回目のレスポンスに2件のレビューが含まれていること');
                $reviewIds = collect($secondResponseData['reviews'] ?? [])->map(function ($review) {
                    return basename($review['name'] ?? '');
                })->toArray();
                $this->assertContains('1111111111111111111', $reviewIds, '2回目のレスポンスに1111111111111111111が含まれていること');
                $this->assertContains('2222222222222222222', $reviewIds, '2回目のレスポンスに2222222222222222222が含まれていること');
            }
        }

        // 2回目後のレビュー数を確認
        $reviewCountAfterSecond = Review::where('shop_id', $shop->id)->count();
        \Log::info('TEST_SYNC_SECOND_COMPLETE', [
            'test_name' => __METHOD__,
            'review_count' => $reviewCountAfterSecond,
        ]);
        
        $this->assertEquals(2, $reviewCountAfterSecond, '2回目の同期後、レビューが2件保存されていること（新規1件のみ追加）');
        
        // 既存のレビューが存在することを確認
        $existingReview = Review::where('shop_id', $shop->id)
            ->where('gbp_review_id', '1111111111111111111')
            ->first();
        $this->assertNotNull($existingReview, '既存のレビューが存在すること');
        
        // 新規のレビューが存在することを確認
        $newReview = Review::where('shop_id', $shop->id)
            ->where('gbp_review_id', '2222222222222222222')
            ->first();
        $this->assertNotNull($newReview, '新規のレビューが存在すること');
        
        // 同じgbp_review_idが重複していないことを確認
        $duplicateCount = Review::where('shop_id', $shop->id)
            ->where('gbp_review_id', '1111111111111111111')
            ->count();
        $this->assertEquals(1, $duplicateCount, '同じgbp_review_idのレビューが1件のみ保存されていること（重複していない）');

        \Log::info('TEST_END', ['test_name' => __METHOD__]);
    }
}

