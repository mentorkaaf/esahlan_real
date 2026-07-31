<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        // House requests table
        Schema::create('house_requests', function (Blueprint $table) {
            $table->id();
            $table->unsignedBigInteger('customer_user_id');
            $table->unsignedBigInteger('district_id')->nullable();
            $table->string('type', 50)->nullable(); // apartment,house,villa,room,office,shop
            $table->integer('bedrooms')->nullable();
            $table->decimal('budget_min', 10, 2)->nullable();
            $table->decimal('budget_max', 10, 2)->nullable();
            $table->text('description')->nullable();
            $table->enum('status', ['open', 'contacted', 'closed'])->default('open');
            $table->timestamps();

            $table->foreign('customer_user_id')->references('id')->on('users')->onDelete('cascade');
            $table->foreign('district_id')->references('id')->on('districts')->onDelete('set null');
        });

        // Insert default commission setting
        DB::table('settings')->insertOrIgnore([
            'key'   => 'erent_commission_pct',
            'value' => '10',
            'type'  => 'number',
            'group' => 'erent',
        ]);
    }

    public function down(): void
    {
        Schema::dropIfExists('house_requests');
        DB::table('settings')->where('key', 'erent_commission_pct')->delete();
    }
};
