<?php

namespace Database\Factories;

use App\Models\Shop;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Shop>
 */
class ShopFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var string
     */
    protected $model = Shop::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->company() . '店',
            'plan_id' => null,
            'sales_person_id' => null,
            'operation_person_id' => null,
            'shop_contact_name' => fake()->name(),
            'shop_contact_phone' => fake()->phoneNumber(),
            'price' => fake()->randomFloat(2, 10000, 100000),
            'contract_date' => fake()->dateTimeBetween('-1 year', 'now'),
            'contract_end_date' => null,
            'blog_option' => false,
            'review_monthly_target' => null,
            'photo_monthly_target' => null,
            'video_monthly_target' => null,
            'gbp_account_id' => null,
            'gbp_location_id' => null,
            'gbp_refresh_token' => null,
            'gbp_name' => null,
            'ai_reply_keywords' => null,
            'low_rating_response' => null,
            'memo' => null,
            'google_place_id' => null,
        ];
    }
}









