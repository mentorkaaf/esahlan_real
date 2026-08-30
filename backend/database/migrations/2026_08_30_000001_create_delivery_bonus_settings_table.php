<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('delivery_bonus_settings', function (Blueprint $table) {
            $table->id();
            $table->string('label')->default('Peak Hours Bonus');
            $table->decimal('bonus_amount', 8, 2)->default(2.00);
            $table->tinyInteger('start_hour')->default(11);   // 11:00 AM
            $table->tinyInteger('end_hour')->default(14);     //  2:00 PM
            $table->string('days_of_week')->default('1,2,3,4,5,6,7'); // 1=Mon…7=Sun (all)
            $table->boolean('is_active')->default(false);
            $table->text('description')->nullable();
            $table->timestamps();
        });

        // Insert one default row so admin can just toggle is_active
        DB::table('delivery_bonus_settings')->insert([
            'label'        => 'Lunch Peak Bonus',
            'bonus_amount' => 2.00,
            'start_hour'   => 11,
            'end_hour'     => 14,
            'days_of_week' => '1,2,3,4,5,6,7',
            'is_active'    => false,
            'description'  => 'Extra $2 per delivery during lunch rush (11 AM – 2 PM).',
            'created_at'   => now(),
            'updated_at'   => now(),
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('delivery_bonus_settings');
    }
};
