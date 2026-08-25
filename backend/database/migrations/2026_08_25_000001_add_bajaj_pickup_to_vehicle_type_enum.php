<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Extends the vehicle_type ENUM to include all values the app supports.
     * Original: motorcycle, car, van, truck, bicycle
     * Added:    bajaj, pickup
     */
    public function up(): void
    {
        DB::statement("ALTER TABLE deliverymen MODIFY COLUMN vehicle_type ENUM('motorcycle','car','van','truck','bicycle','bajaj','pickup') NOT NULL DEFAULT 'motorcycle'");
    }

    public function down(): void
    {
        // NOTE: Any rows with bajaj/pickup will be truncated if rolled back.
        DB::statement("ALTER TABLE deliverymen MODIFY COLUMN vehicle_type ENUM('motorcycle','car','van','truck','bicycle') NOT NULL DEFAULT 'motorcycle'");
    }
};
