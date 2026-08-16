<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class ModuleDepartmentSeeder extends Seeder
{
    /**
     * Default departments for each business module.
     * 4 departments per module × 13 modules = 52 total.
     */
    public function run(): void
    {
        $defaults = [
            'efood' => [
                ['name' => 'Operations',         'description' => 'Order processing and fulfillment oversight'],
                ['name' => 'Delivery Dispatch',  'description' => 'Driver assignment and route optimization'],
                ['name' => 'Customer Support',   'description' => 'Handling customer enquiries and complaints'],
                ['name' => 'Quality Control',    'description' => 'Vendor audit, packaging standards, and ratings'],
            ],
            'eshop' => [
                ['name' => 'Sales',              'description' => 'Vendor onboarding and sales support'],
                ['name' => 'Warehouse',          'description' => 'Stock management and logistics'],
                ['name' => 'Customer Service',   'description' => 'Order queries, returns, and dispute resolution'],
                ['name' => 'Returns & Refunds',  'description' => 'Processing returns, refund approvals'],
            ],
            'eticket' => [
                ['name' => 'Reservations',       'description' => 'Flight and travel booking operations'],
                ['name' => 'Customer Care',      'description' => 'Traveller enquiries and itinerary changes'],
                ['name' => 'Finance',            'description' => 'Billing, refunds, and reconciliation'],
                ['name' => 'Operations',         'description' => 'Partner relations and route management'],
            ],
            'ehealth' => [
                ['name' => 'Clinical',           'description' => 'Doctor and healthcare provider coordination'],
                ['name' => 'Administration',     'description' => 'Appointment scheduling and medical records'],
                ['name' => 'Customer Support',   'description' => 'Patient enquiries and follow-ups'],
                ['name' => 'Analytics',          'description' => 'Health data reporting and insights'],
            ],
            'edata' => [
                ['name' => 'Technical Support',  'description' => 'Data bundle activation and troubleshooting'],
                ['name' => 'Sales',              'description' => 'Partner telco relations and promotions'],
                ['name' => 'Operations',         'description' => 'Bundle inventory and availability management'],
                ['name' => 'Analytics',          'description' => 'Usage analytics and reporting'],
            ],
            'eparcel' => [
                ['name' => 'Dispatch',           'description' => 'Shipment processing and route planning'],
                ['name' => 'Drivers',            'description' => 'Driver management and performance'],
                ['name' => 'Customer Support',   'description' => 'Tracking enquiries and delivery issues'],
                ['name' => 'Warehouse',          'description' => 'Sorting hub and inventory management'],
            ],
            'erent' => [
                ['name' => 'Listings',           'description' => 'Property and vehicle listing management'],
                ['name' => 'Agent Network',      'description' => 'Field agent coordination and territory management'],
                ['name' => 'Customer Relations', 'description' => 'Tenant/renter communication and support'],
                ['name' => 'Finance',            'description' => 'Rental payments, deposits, and reconciliation'],
            ],
            'emoving' => [
                ['name' => 'Operations',         'description' => 'Booking coordination and job scheduling'],
                ['name' => 'Fleet Management',   'description' => 'Vehicle and crew assignment'],
                ['name' => 'Customer Support',   'description' => 'Pre-move surveys and customer queries'],
                ['name' => 'Scheduling',         'description' => 'Calendar management and capacity planning'],
            ],
            'ewholesale' => [
                ['name' => 'Sales',              'description' => 'B2B client acquisition and account management'],
                ['name' => 'Procurement',        'description' => 'Supplier relations and bulk purchasing'],
                ['name' => 'Logistics',          'description' => 'Large-order shipping and freight coordination'],
                ['name' => 'Customer Support',   'description' => 'Order queries and after-sales support'],
            ],
            'egrocery' => [
                ['name' => 'Procurement',        'description' => 'Fresh produce sourcing and supplier management'],
                ['name' => 'Delivery',           'description' => 'Express delivery operations and route management'],
                ['name' => 'Customer Support',   'description' => 'Order enquiries and substitution handling'],
                ['name' => 'Quality Control',    'description' => 'Freshness standards and product auditing'],
            ],
            'eexchange' => [
                ['name' => 'Trading Desk',       'description' => 'Exchange order monitoring and rate management'],
                ['name' => 'Compliance',         'description' => 'KYC verification and regulatory reporting'],
                ['name' => 'Customer Support',   'description' => 'Transaction queries and dispute resolution'],
                ['name' => 'Tech Operations',    'description' => 'Platform reliability and integration monitoring'],
            ],
            'elaundry' => [
                ['name' => 'Operations',         'description' => 'Laundry processing and facility management'],
                ['name' => 'Pickup & Delivery',  'description' => 'Collection rounds and delivery scheduling'],
                ['name' => 'Customer Support',   'description' => 'Order status, damage claims, and queries'],
                ['name' => 'Quality Control',    'description' => 'Garment care standards and inspection'],
            ],
            'elearning' => [
                ['name' => 'Content Creation',   'description' => 'Course development and instructional design'],
                ['name' => 'Student Support',    'description' => 'Learner queries, progress tracking, and coaching'],
                ['name' => 'Instructor Relations','description' => 'Instructor onboarding, payments, and performance'],
                ['name' => 'Analytics',          'description' => 'Learning outcomes, completion rates, and reporting'],
            ],
        ];

        // Load modules by slug
        $modules = DB::table('modules')->pluck('id', 'slug');

        $rows = [];
        $sortOrder = 0;
        $now = now()->toDateTimeString();

        foreach ($defaults as $slug => $depts) {
            $moduleId = $modules[$slug] ?? null;
            if (!$moduleId) continue; // module not seeded yet — skip silently

            $sortOrder = 0;
            foreach ($depts as $dept) {
                $rows[] = [
                    'module_id'   => $moduleId,
                    'name'        => $dept['name'],
                    'description' => $dept['description'],
                    'manager_id'  => null,
                    'status'      => 'active',
                    'sort_order'  => $sortOrder++,
                    'created_at'  => $now,
                    'updated_at'  => $now,
                ];
            }
        }

        // insertOrIgnore — safe to re-run (unique: module_id + name)
        foreach (array_chunk($rows, 50) as $chunk) {
            DB::table('module_departments')->insertOrIgnore($chunk);
        }

        $this->command->info('ModuleDepartmentSeeder: ' . count($rows) . ' departments seeded.');
    }
}
