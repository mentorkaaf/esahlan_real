<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleSeeder extends Seeder
{
    public function run(): void
    {
        $modules = [
            [
                'name' => 'eFood', 'slug' => 'efood', 'description' => 'Food delivery from restaurants',
                'icon' => 'fas fa-utensils', 'color' => '#FF5722', 'sort_order' => 1,
                'commission_type' => 'percentage', 'commission_value' => 12,
                'delivery_fee' => 2.00,
                'settings' => json_encode(['allow_scheduled_orders' => true, 'max_schedule_days' => 7]),
            ],
            [
                'name' => 'eShop', 'slug' => 'eshop', 'description' => 'Online shopping and retail',
                'icon' => 'fas fa-shopping-bag', 'color' => '#E91E63', 'sort_order' => 2,
                'commission_type' => 'percentage', 'commission_value' => 10,
                'delivery_fee' => 3.00,
                'settings' => json_encode(['allow_cod' => true, 'free_delivery_threshold' => 30]),
            ],
            [
                'name' => 'eTicket', 'slug' => 'eticket', 'description' => 'Flight and travel booking',
                'icon' => 'fas fa-plane', 'color' => '#2196F3', 'sort_order' => 3,
                'commission_type' => 'percentage', 'commission_value' => 5,
                'delivery_fee' => 0,
                'settings' => json_encode(['booking_fee' => 5, 'cancellation_fee' => 10]),
            ],
            [
                'name' => 'eHealth', 'slug' => 'ehealth', 'description' => 'Doctor appointments and healthcare',
                'icon' => 'fas fa-heartbeat', 'color' => '#4CAF50', 'sort_order' => 4,
                'commission_type' => 'percentage', 'commission_value' => 8,
                'delivery_fee' => 2.50,
                'settings' => json_encode(['consultation_fee' => true]),
            ],
            [
                'name' => 'eData', 'slug' => 'edata', 'description' => 'Mobile data bundles',
                'icon' => 'fas fa-wifi', 'color' => '#9C27B0', 'sort_order' => 5,
                'commission_type' => 'percentage', 'commission_value' => 3,
                'delivery_fee' => 0,
                'settings' => json_encode(['instant_delivery' => true]),
            ],
            [
                'name' => 'eParcel', 'slug' => 'eparcel', 'description' => 'Parcel and courier delivery',
                'icon' => 'fas fa-box', 'color' => '#FF9800', 'sort_order' => 6,
                'commission_type' => 'percentage', 'commission_value' => 15,
                'delivery_fee' => 0,
                'settings' => json_encode(['insurance_option' => true]),
            ],
            [
                'name' => 'eRent', 'slug' => 'erent', 'description' => 'Property and car rental',
                'icon' => 'fas fa-home', 'color' => '#607D8B', 'sort_order' => 7,
                'commission_type' => 'percentage', 'commission_value' => 8,
                'delivery_fee' => 0,
                'settings' => json_encode(['booking_deposit' => 20]),
            ],
            [
                'name' => 'eMoving', 'slug' => 'emoving', 'description' => 'Home and office moving services',
                'icon' => 'fas fa-truck-moving', 'color' => '#795548', 'sort_order' => 8,
                'commission_type' => 'percentage', 'commission_value' => 12,
                'delivery_fee' => 0,
                'settings' => json_encode(['min_booking_hours' => 2]),
            ],
            [
                'name' => 'eWholesale', 'slug' => 'ewholesale', 'description' => 'Bulk buying and B2B',
                'icon' => 'fas fa-industry', 'color' => '#FF5722', 'sort_order' => 9,
                'commission_type' => 'percentage', 'commission_value' => 5,
                'delivery_fee' => 10.00,
                'settings' => json_encode(['min_order_amount' => 100]),
            ],
            [
                'name' => 'eGrocery', 'slug' => 'egrocery', 'description' => 'Fresh groceries delivery',
                'icon' => 'fas fa-shopping-cart', 'color' => '#8BC34A', 'sort_order' => 10,
                'commission_type' => 'percentage', 'commission_value' => 10,
                'delivery_fee' => 1.50,
                'settings' => json_encode(['express_delivery' => true, 'express_fee' => 2]),
            ],
            [
                'name' => 'eExchange', 'slug' => 'eexchange', 'description' => 'Currency exchange and transfers',
                'icon' => 'fas fa-exchange-alt', 'color' => '#FFC107', 'sort_order' => 11,
                'commission_type' => 'percentage', 'commission_value' => 2,
                'delivery_fee' => 0,
                'settings' => json_encode(['max_transfer_usd' => 1000, 'kyc_required_above' => 200]),
            ],
            [
                'name' => 'eLaundry', 'slug' => 'elaundry', 'description' => 'Laundry and dry cleaning',
                'icon' => 'fas fa-tshirt', 'color' => '#00BCD4', 'sort_order' => 12,
                'commission_type' => 'percentage', 'commission_value' => 15,
                'delivery_fee' => 2.00,
                'settings' => json_encode(['express_option' => true, 'pickup_scheduling' => true]),
            ],
        ];

        foreach ($modules as $module) {
            DB::table('modules')->updateOrInsert(
                ['slug' => $module['slug']],
                [
                    'name'             => $module['name'],
                    'slug'             => $module['slug'],
                    'description'      => $module['description'],
                    'icon'             => $module['icon'],
                    'color'            => $module['color'],
                    'sort_order'       => $module['sort_order'],
                    'commission_type'  => $module['commission_type'],
                    'commission_value' => $module['commission_value'],
                    'settings'         => $module['settings'],
                    'is_active'        => true,
                    'created_at'       => now(),
                    'updated_at'       => now(),
                ]
            );
        }
    }
}
