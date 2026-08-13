<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::create("db_clean_logs", function (Blueprint $table) {
            $table->id();
            $table->foreignId("admin_id")->nullable()->constrained("users")->nullOnDelete();
            $table->json("tables_cleaned");   // ["orders","carts",...]
            $table->json("rows_deleted");     // {"orders":1234,"carts":5}
            $table->string("status")->default("completed"); // completed|failed
            $table->text("notes")->nullable();
            $table->timestamps();
        });
    }
    public function down(): void { Schema::dropIfExists("db_clean_logs"); }
};
