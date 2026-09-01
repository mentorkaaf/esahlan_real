<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('qr_codes', function (Blueprint $table) {
            $table->id();
            $table->string('token', 12)->unique();        // unique short token: esahlan.com/qr/{token}
            $table->string('title');                       // admin label e.g. "ILIGBEYLE Vendor Card"
            $table->string('type')->default('custom');     // vendor|driver|order|event|promo|custom
            $table->string('headline')->nullable();        // big text on public page
            $table->text('description')->nullable();       // body text
            $table->string('logo_url')->nullable();        // image/logo URL
            $table->json('fields')->nullable();            // extra key-value rows: [{"label":"Phone","value":"..."}]
            $table->string('cta_label')->nullable();       // button label e.g. "Open in App"
            $table->string('cta_url')->nullable();         // button URL
            $table->string('color')->default('#FF8A00');   // accent color
            $table->unsignedBigInteger('scan_count')->default(0);
            $table->boolean('is_active')->default(true);
            $table->foreignId('created_by')->constrained('users')->cascadeOnDelete();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('qr_codes');
    }
};
