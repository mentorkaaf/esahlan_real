<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('house_requests', function (Blueprint $table) {
            $table->string('request_ref', 30)->nullable()->unique()->after('id');
            $table->unsignedBigInteger('agent_user_id')->nullable()->after('customer_user_id');
            $table->foreign('agent_user_id')->references('id')->on('users')->nullOnDelete();
            $table->timestamp('assigned_at')->nullable();
            $table->enum('purpose', ['rent', 'buy', 'lease'])->default('rent');
            $table->date('move_in_date')->nullable();
        });

        // Migrate existing statuses before altering enum
        DB::statement("UPDATE house_requests SET status = 'open'      WHERE status IN ('open', 'contacted')");
        DB::statement("UPDATE house_requests SET status = 'cancelled' WHERE status = 'closed'");

        // Expand status enum
        DB::statement("ALTER TABLE house_requests MODIFY COLUMN status ENUM('open','assigned','searching','matched','completed','cancelled') NOT NULL DEFAULT 'open'");

        // Generate request_ref for existing rows
        $rows = DB::table('house_requests')->whereNull('request_ref')->orderBy('id')->get();
        foreach ($rows as $row) {
            $ref = 'ERQ-' . date('Y') . '-' . str_pad($row->id, 6, '0', STR_PAD_LEFT);
            DB::table('house_requests')->where('id', $row->id)->update(['request_ref' => $ref]);
        }
    }

    public function down(): void
    {
        Schema::table('house_requests', function (Blueprint $table) {
            $table->dropForeign(['agent_user_id']);
            $table->dropColumn(['request_ref', 'agent_user_id', 'assigned_at', 'purpose', 'move_in_date']);
        });
        DB::statement("ALTER TABLE house_requests MODIFY COLUMN status ENUM('open','contacted','closed') NOT NULL DEFAULT 'open'");
    }
};
