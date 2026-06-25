<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('community_profiles', function (Blueprint $t) {
            if (!Schema::hasColumn('community_profiles', 'country')) {
                $t->string('country')->nullable()->after('privacy');
                $t->string('city')->nullable()->after('country');
                $t->enum('gender', ['male', 'female', 'other'])->nullable()->after('city');
                $t->date('date_of_birth')->nullable()->after('gender');
                $t->json('interests')->nullable()->after('date_of_birth');
                $t->boolean('onboarding_completed')->default(false)->after('interests');
            }
        });
    }

    public function down(): void
    {
        Schema::table('community_profiles', function (Blueprint $t) {
            $t->dropColumn(['country', 'city', 'gender', 'date_of_birth', 'interests', 'onboarding_completed']);
        });
    }
};
