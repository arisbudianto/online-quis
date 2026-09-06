<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->unsignedInteger('tab_switch_count')->default(0)->after('status');
            $table->string('browser_session_id')->nullable()->after('tab_switch_count');
            $table->string('ip_address', 45)->nullable()->after('browser_session_id');
            $table->text('user_agent')->nullable()->after('ip_address');
        });
    }

    public function down(): void
    {
        Schema::table('attempts', function (Blueprint $table) {
            $table->dropColumn(['tab_switch_count', 'browser_session_id', 'ip_address', 'user_agent']);
        });
    }
};
