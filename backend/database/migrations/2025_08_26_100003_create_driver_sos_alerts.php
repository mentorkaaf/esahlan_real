<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
return new class extends Migration {
    public function up(): void {
        if (!Schema::hasTable('driver_sos_alerts')) {
            Schema::create('driver_sos_alerts', function (Blueprint $table) {
                $table->id();
                $table->unsignedBigInteger('deliveryman_id');
                $table->unsignedBigInteger('order_id')->nullable();
                $table->decimal('latitude', 10, 7)->nullable();
                $table->decimal('longitude', 10, 7)->nullable();
                $table->text('message')->nullable();
                $table->string('status')->default('pending');
                $table->timestamps();
                $table->foreign('deliveryman_id')->references('id')->on('deliverymen')->onDelete('cascade');
            });
        }
    }
    public function down(): void { Schema::dropIfExists('driver_sos_alerts'); }
};
