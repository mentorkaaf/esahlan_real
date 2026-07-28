<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('cart_notification_templates', function (Blueprint $table) {
            $table->id();
            $table->string('module');          // efood, eshop, egrocery, elaundry, eparcel...
            $table->string('stage');           // 30min, 2h, 24h
            $table->string('title');
            $table->text('body');
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['module', 'stage']);
        });

        // Seed default templates
        $now = now();
        $templates = [
            // eFood
            ['efood','30min','🍕 eFood — Gaajoonaysaa?',      '"{{product_name}}" weli cart-kaaga ku jiraa. Dalbo hadda!',              true,$now,$now],
            ['efood','2h',   '🍕 eFood — Ha daalin!',         '"{{product_name}}"{{extra_items}} cart-kaaga ku sugayaan. ${{total}} oo keliya!', true,$now,$now],
            ['efood','24h',  '🍕 eFood — Fursad ugu dambeysa! ⏰','Cart-kaaga weli buuxaa. "{{product_name}}"{{extra_items}} — dhameystir ama la lumeyso!', true,$now,$now],

            // eShop
            ['eshop','30min','🛍️ eShop Cart',                 '"{{product_name}}" weli cart-kaaga ku jiraa. Dalbo hadda!',              true,$now,$now],
            ['eshop','2h',   '🛍️ eShop — Hadhow dhamaaneysa!','"{{product_name}}"{{extra_items}} cart-kaaga ku sugayaan. ${{total}} oo keliya!', true,$now,$now],
            ['eshop','24h',  '🛍️ eShop — Fursad ugu dambeysa! ⏰','Cart-kaaga weli buuxaa. "{{product_name}}"{{extra_items}} — dhameystir ama la lumeyso!', true,$now,$now],

            // eGrocery
            ['egrocery','30min','🥦 eGrocery Cart',            '"{{product_name}}" weli cart-kaaga ku jiraa. Dalbo hadda!',              true,$now,$now],
            ['egrocery','2h',   '🥦 eGrocery — Ha daalin!',   '"{{product_name}}"{{extra_items}} cart-kaaga ku sugayaan. ${{total}} oo keliya!', true,$now,$now],
            ['egrocery','24h',  '🥦 eGrocery — Ugu dambeysa! ⏰','Cart-kaaga weli buuxaa. "{{product_name}}"{{extra_items}} — dhameystir ama la lumeyso!', true,$now,$now],

            // eLaundry
            ['elaundry','30min','👕 eLaundry Cart',            'Laundry service weli cart-kaaga ku jiraa. Book hadda!',                  true,$now,$now],
            ['elaundry','2h',   '👕 eLaundry — Ha daalin!',   'Dharka xasuuso! Service cart-kaaga ku sugaysaa.',                        true,$now,$now],
            ['elaundry','24h',  '👕 eLaundry — Ugu dambeysa! ⏰','Cart-kaaga weli buuxaa. Dhameystir ama la lumeyso!',                  true,$now,$now],

            // eParcel
            ['eparcel','30min','📦 eParcel Cart',              '"{{product_name}}" weli cart-kaaga ku jiraa. Dalbo hadda!',              true,$now,$now],
            ['eparcel','2h',   '📦 eParcel — Ha daalin!',     'Parcel-kaaga weli pending. ${{total}} oo keliya!',                       true,$now,$now],
            ['eparcel','24h',  '📦 eParcel — Ugu dambeysa! ⏰','Cart-kaaga weli buuxaa. Dhameystir ama la lumeyso!',                    true,$now,$now],

            // eMoving
            ['emoving','30min','🚛 eMoving Cart',              'Moving service weli cart-kaaga ku jiraa. Book hadda!',                   true,$now,$now],
            ['emoving','2h',   '🚛 eMoving — Ha daalin!',     'Service cart-kaaga ku sugaysaa. Ha daalin!',                             true,$now,$now],
            ['emoving','24h',  '🚛 eMoving — Ugu dambeysa! ⏰','Cart-kaaga weli buuxaa. Dhameystir ama la lumeyso!',                   true,$now,$now],

            // eRent
            ['erent','30min','🏠 eRent Cart',                  'Rental service weli cart-kaaga ku jiraa. Book hadda!',                   true,$now,$now],
            ['erent','2h',   '🏠 eRent — Ha daalin!',         'Rent-kaaga weli pending. Ha daalin!',                                    true,$now,$now],
            ['erent','24h',  '🏠 eRent — Ugu dambeysa! ⏰',   'Cart-kaaga weli buuxaa. Dhameystir ama la lumeyso!',                    true,$now,$now],
        ];

        foreach ($templates as $t) {
            DB::table('cart_notification_templates')->insert([
                'module'=>$t[0],'stage'=>$t[1],'title'=>$t[2],
                'body'=>$t[3],'is_active'=>$t[4],'created_at'=>$t[5],'updated_at'=>$t[6],
            ]);
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('cart_notification_templates');
    }
};
