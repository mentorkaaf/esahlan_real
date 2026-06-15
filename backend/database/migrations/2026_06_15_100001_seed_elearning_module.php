<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::table('modules')->updateOrInsert(
            ['slug' => 'elearning'],
            [
                'name'             => 'eLearning',
                'slug'             => 'elearning',
                'description'      => 'Online courses and education',
                'icon'             => 'fas fa-graduation-cap',
                'color'            => '#6366F1',
                'sort_order'       => 13,
                'commission_type'  => 'percentage',
                'commission_value' => 20,
                'delivery_fee'     => 0,
                'settings'         => json_encode(['certificate_enabled' => true, 'quiz_enabled' => true]),
                'is_active'        => true,
                'created_at'       => now(),
                'updated_at'       => now(),
            ]
        );
    }

    public function down(): void
    {
        DB::table('modules')->where('slug', 'elearning')->delete();
    }
};
