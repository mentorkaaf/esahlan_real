<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('community_posts', function (Blueprint $t) {
            $t->foreignId('page_id')->nullable()->after('group_id')
              ->constrained('community_business_pages')->nullOnDelete();
        });
    }

    public function down(): void
    {
        Schema::table('community_posts', function (Blueprint $t) {
            $t->dropConstrainedForeignId('page_id');
        });
    }
};
