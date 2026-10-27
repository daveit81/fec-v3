<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The redazione's accounts: who they are in the legacy tables
 * (joomla_user_id drives JFactory::getUser() in the ported desks), their
 * role, the one-time login code sent by email, the last login.
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->unsignedInteger('joomla_user_id')->nullable()->after('email');
            $table->string('role', 32)->default('editor')->after('joomla_user_id');
            $table->string('login_code')->nullable()->after('remember_token');
            $table->timestamp('login_code_expires_at')->nullable()->after('login_code');
            $table->timestamp('last_login_at')->nullable()->after('login_code_expires_at');
        });
    }

    public function down(): void
    {
        Schema::table('users', function (Blueprint $table) {
            $table->dropColumn(['joomla_user_id', 'role', 'login_code', 'login_code_expires_at', 'last_login_at']);
        });
    }
};
