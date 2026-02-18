<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Shop extends Model
{
    use HasFactory;

    protected $fillable = [
        'name',
        'created_by',
        'plan_id',
        'sales_person_id',
        'operation_person_id',
        'shop_contact_name',
        'shop_contact_phone',
        'price',
        'initial_cost',
        'contract_type',
        'referral_fee',
        'contract_date',
        'contract_end_date',
        'blog_option',
        'blog_list_url',
        'blog_link_selector',
        'blog_item_selector',
        'blog_date_selector',
        'blog_image_selector',
        'blog_content_selector',
        'blog_crawl_time',
        'blog_fallback_image_url',
        'integration_type',
        'instagram_crawl_time',
        'instagram_item_selector',
        'wp_post_enabled',
        'wp_post_type',
        'wp_post_status',
        'wp_base_url',
        'wp_username',
        'wp_app_password',
        'review_monthly_target',
        'photo_monthly_target',
        'video_monthly_target',
        'gbp_location_id',
        'gbp_account_id',
        'gbp_account_id_v4',
        'gbp_refresh_token',
        'gbp_access_token',
        'gbp_photo_api_disabled',
        'gbp_photo_api_disabled_reason',
        'gbp_name',
        'last_reviews_synced_at',
        'last_reviews_synced_update_time',
        'last_reviews_sync_started_at',
        'last_reviews_sync_finished_at',
        'last_photos_synced_at',
        'last_posts_synced_at',
        'ai_reply_keywords',
        'low_rating_response',
        'memo',
        'google_place_id',
        'report_email_1',
        'report_email_2',
        'report_email_3',
        'report_email_4',
        'report_email_5',
    ];

    protected $casts = [
        'contract_date' => 'date',
        'contract_end_date' => 'date',
        'blog_option' => 'boolean',
        'gbp_photo_api_disabled' => 'boolean',
        'wp_post_enabled' => 'boolean',
        'wp_app_password' => 'encrypted',
        'price' => 'decimal:2',
        'initial_cost' => 'decimal:2',
        'last_reviews_synced_at' => 'datetime',
        'last_reviews_synced_update_time' => 'datetime',
        'last_reviews_sync_started_at' => 'datetime',
        'last_reviews_sync_finished_at' => 'datetime',
        'last_photos_synced_at' => 'datetime',
        'last_posts_synced_at' => 'datetime',
    ];

    public function reviews(): HasMany
    {
        return $this->hasMany(Review::class);
    }

    public function meoKeywords(): HasMany
    {
        return $this->hasMany(MeoKeyword::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class);
    }

    public function posts(): HasMany
    {
        return $this->hasMany(GbpPost::class);
    }

    public function plan(): BelongsTo
    {
        return $this->belongsTo(Plan::class);
    }

    public function salesPerson(): BelongsTo
    {
        return $this->belongsTo(SalesPerson::class);
    }

    public function operationPerson(): BelongsTo
    {
        return $this->belongsTo(OperationPerson::class);
    }

    public function createdBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    public function contactLogs(): HasMany
    {
        return $this->hasMany(ContactLog::class);
    }

    public function mediaAssets(): HasMany
    {
        return $this->hasMany(ShopMediaAsset::class);
    }

    public function getAverageRankAttribute(): ?float
    {
        $ranks = $this->meoKeywords()
            ->with('rankLogs')
            ->get()
            ->flatMap(function ($keyword) {
                return $keyword->rankLogs()->whereNotNull('position')->pluck('position');
            });
        
        if ($ranks->isEmpty()) {
            return null;
        }
        
        return $ranks->average();
    }

    /**
     * 契約中かどうかを判定
     */
    public function isContractActive(): bool
    {
        if (is_null($this->contract_end_date)) {
            return true; // 契約終了日が設定されていない場合は契約中とみなす
        }
        
        return $this->contract_end_date->isFuture() || $this->contract_end_date->isToday();
    }

    /**
     * 契約中の店舗のみを取得するスコープ
     */
    public function scopeActiveContract($query)
    {
        $today = \Carbon\Carbon::today();
        return $query->where(function ($q) use ($today) {
            $q->whereNull('contract_end_date')
              ->orWhere('contract_end_date', '>=', $today);
        });
    }

    /**
     * Google Mapsの口コミ投稿URLを生成
     * 
     * @return string|null Place IDが設定されている場合はURL、それ以外はnull
     */
    public function getGoogleMapsReviewUrl(): ?string
    {
        // Place IDが空の場合はnullを返す
        if (empty($this->google_place_id) || trim($this->google_place_id) === '') {
            \Log::warning('Google Place IDが設定されていません', [
                'shop_id' => $this->id,
                'shop_name' => $this->name,
                'google_place_id' => $this->google_place_id,
            ]);
            return null;
        }

        // Place IDはそのまま使用
        $placeId = trim($this->google_place_id);
        
        // Place IDの形式に応じてURLを生成
        // ChIJ...形式（新しい形式）の場合
        if (preg_match('/^ChIJ/', $placeId)) {
            // ChIJ形式のPlace ID用のURL形式
            // https://www.google.com/maps/place/?cid={cid} または
            // https://www.google.com/maps/place/?q=place_id:{placeId}
            // ただし、口コミ投稿用のURLは別の形式が必要
            // 試行1: 標準的なPlace URL形式
            $url = "https://www.google.com/maps/place/?q=place_id:{$placeId}&source=g.page.m._&laa=merchant-review-solicitation";
        } 
        // 0x...:0x...形式（古い形式）の場合
        elseif (preg_match('/^0x/', $placeId)) {
            // 古い形式のPlace ID用のURL形式
            $url = "https://www.google.com/maps/place//data=!4m3!3m2!1s{$placeId}!12e1?source=g.page.m._&laa=merchant-review-solicitation";
        } 
        // その他の形式の場合
        else {
            // デフォルト: 標準的なPlace URL形式を試す
            $url = "https://www.google.com/maps/place/?q=place_id:{$placeId}&source=g.page.m._&laa=merchant-review-solicitation";
        }
        
        \Log::info('Google Maps口コミ投稿URLを生成', [
            'shop_id' => $this->id,
            'shop_name' => $this->name,
            'place_id' => $placeId,
            'url' => $url,
        ]);
        
        return $url;
    }
}

