<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration {
    public function up(): void
    {
        Schema::create('email_templates', function (Blueprint $table) {
            $table->id();
            $table->string('key')->unique();
            $table->string('name');
            $table->string('subject');
            $table->longText('body');
            $table->json('variables')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        // Seed default templates
        $templates = [
            [
                'key'       => 'otp_reset_password',
                'name'      => 'Password Reset OTP',
                'subject'   => 'Reset Your eSahlan Password',
                'variables' => json_encode(['{{name}}', '{{code}}']),
                'body'      => file_get_contents(resource_path('views/emails/otp_default.html')),
            ],
            [
                'key'       => 'otp_register',
                'name'      => 'Registration OTP',
                'subject'   => 'Verify Your eSahlan Account',
                'variables' => json_encode(['{{name}}', '{{code}}']),
                'body'      => file_get_contents(resource_path('views/emails/otp_default.html')),
            ],
            [
                'key'       => 'otp_login',
                'name'      => 'Login OTP',
                'subject'   => 'Your eSahlan Login Code',
                'variables' => json_encode(['{{name}}', '{{code}}']),
                'body'      => file_get_contents(resource_path('views/emails/otp_default.html')),
            ],
            [
                'key'       => 'welcome',
                'name'      => 'Welcome Email',
                'subject'   => 'Welcome to eSahlan!',
                'variables' => json_encode(['{{name}}']),
                'body'      => file_get_contents(resource_path('views/emails/welcome_default.html')),
            ],
        ];

        foreach ($templates as $t) {
            DB::table('email_templates')->insert(array_merge($t, [
                'created_at' => now(),
                'updated_at' => now(),
            ]));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('email_templates');
    }
};
