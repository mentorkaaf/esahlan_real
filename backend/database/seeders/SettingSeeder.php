<?php
namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class SettingSeeder extends Seeder
{
    public function run(): void
    {
        $settings = [
            // General
            ['key' => 'app_name',           'value' => 'eSahlan',                         'group' => 'general'],
            ['key' => 'app_tagline',         'value' => 'Everything You Need, Simplified', 'group' => 'general'],
            ['key' => 'app_email',           'value' => 'info@esahlan.com',               'group' => 'general'],
            ['key' => 'app_phone',           'value' => '+252617000000',                  'group' => 'general'],
            ['key' => 'app_address',         'value' => 'Mogadishu, Somalia',             'group' => 'general'],
            ['key' => 'app_currency',        'value' => 'USD',                            'group' => 'general'],
            ['key' => 'app_currency_symbol', 'value' => '$',                              'group' => 'general'],
            ['key' => 'app_language',        'value' => 'en',                             'group' => 'general'],
            ['key' => 'maintenance_mode',    'value' => '0',                              'group' => 'general'],

            // Commission
            ['key' => 'default_commission_type',  'value' => 'percentage', 'group' => 'commission'],
            ['key' => 'default_commission_value', 'value' => '10',         'group' => 'commission'],
            ['key' => 'admin_commission_type',    'value' => 'percentage', 'group' => 'commission'],
            ['key' => 'admin_commission_value',   'value' => '10',         'group' => 'commission'],

            // Delivery
            ['key' => 'default_delivery_fee',          'value' => '2.00', 'group' => 'delivery'],
            ['key' => 'deliveryman_earning_per_order',  'value' => '1.50', 'group' => 'delivery'],
            ['key' => 'max_delivery_radius_km',        'value' => '10',   'group' => 'delivery'],
            ['key' => 'auto_assign_deliveryman',       'value' => '1',    'group' => 'delivery'],
            ['key' => 'assignment_timeout_minutes',    'value' => '3',    'group' => 'delivery'],
            ['key' => 'max_assignment_attempts',       'value' => '5',    'group' => 'delivery'],

            // Tax
            ['key' => 'tax_enabled',     'value' => '0',  'group' => 'tax'],
            ['key' => 'tax_percentage',  'value' => '0',  'group' => 'tax'],
            ['key' => 'tax_name',        'value' => 'VAT','group' => 'tax'],

            // Wallet
            ['key' => 'wallet_enabled',        'value' => '1',    'group' => 'wallet'],
            ['key' => 'min_withdrawal_amount', 'value' => '10',   'group' => 'wallet'],
            ['key' => 'max_withdrawal_amount', 'value' => '1000', 'group' => 'wallet'],

            // Referral
            ['key' => 'referral_enabled',        'value' => '1',    'group' => 'referral'],
            ['key' => 'referral_reward_amount',  'value' => '2.00', 'group' => 'referral'],
            ['key' => 'referral_min_orders',     'value' => '1',    'group' => 'referral'],

            // Loyalty
            ['key' => 'loyalty_enabled',          'value' => '1',    'group' => 'loyalty'],
            ['key' => 'loyalty_points_per_dollar','value' => '10',   'group' => 'loyalty'],
            ['key' => 'loyalty_points_value',     'value' => '0.01', 'group' => 'loyalty'],
            ['key' => 'loyalty_min_redeem',       'value' => '100',  'group' => 'loyalty'],
            ['key' => 'loyalty_expiry_days',      'value' => '365',  'group' => 'loyalty'],

            // Payment
            ['key' => 'waafi_enabled',   'value' => '1',    'group' => 'payment'],
            ['key' => 'wallet_payment',  'value' => '1',    'group' => 'payment'],
            ['key' => 'cod_enabled',     'value' => '1',    'group' => 'payment'],

            // Google Maps
            ['key' => 'google_maps_api_key',      'value' => '', 'group' => 'maps', 'type' => 'string'],
            ['key' => 'google_maps_default_lat',  'value' => '2.0469', 'group' => 'maps', 'type' => 'decimal'],
            ['key' => 'google_maps_default_lng',  'value' => '45.3182', 'group' => 'maps', 'type' => 'decimal'],
            ['key' => 'google_maps_default_zoom', 'value' => '13', 'group' => 'maps', 'type' => 'integer'],

            // Firebase / Push Notifications
            ['key' => 'firebase_project_id',          'value' => '', 'group' => 'firebase', 'type' => 'string'],
            ['key' => 'firebase_api_key',             'value' => '', 'group' => 'firebase', 'type' => 'string'],
            ['key' => 'firebase_auth_domain',         'value' => '', 'group' => 'firebase', 'type' => 'string'],
            ['key' => 'firebase_storage_bucket',      'value' => '', 'group' => 'firebase', 'type' => 'string'],
            ['key' => 'firebase_sender_id',           'value' => '', 'group' => 'firebase', 'type' => 'string'],
            ['key' => 'firebase_app_id_web',          'value' => '', 'group' => 'firebase', 'type' => 'string'],
            ['key' => 'fcm_enabled',                  'value' => '1', 'group' => 'firebase', 'type' => 'boolean'],
            ['key' => 'fcm_order_notifications',      'value' => '1', 'group' => 'firebase', 'type' => 'boolean'],
            ['key' => 'firebase_service_account_json','value' => '', 'group' => 'firebase', 'type' => 'string'],

            // Auth
            ['key' => 'otp_expiry_minutes',     'value' => '10', 'group' => 'auth'],
            ['key' => 'otp_enabled',            'value' => '1',  'group' => 'auth'],
            ['key' => 'phone_verification',     'value' => '1',  'group' => 'auth'],

            // Refund
            ['key' => 'refund_enabled',          'value' => '1',  'group' => 'refund'],
            ['key' => 'refund_to_wallet',        'value' => '1',  'group' => 'refund'],
            ['key' => 'refund_days',             'value' => '7',  'group' => 'refund'],
        ];

        foreach ($settings as $setting) {
            DB::table('settings')->updateOrInsert(
                ['key' => $setting['key']],
                array_merge($setting, ['created_at' => now(), 'updated_at' => now()])
            );
        }
    }
}
