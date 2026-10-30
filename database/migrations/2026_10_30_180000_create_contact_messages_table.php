<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

/**
 * The messages of the contact form of /contactus/ (phase 5): each is mailed to
 * its topic's mailbox and kept here too, so a mail that never arrives loses
 * nothing; /desk/messages lists them. The sender's address is kept, the IP only
 * as a salted hash (the hourly limit counts on it).
 */
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('contact_messages', function (Blueprint $table) {
            $table->id();
            $table->string('topic', 32)->index();
            $table->string('recipient', 254);
            $table->string('name', 100);
            $table->string('email', 254);
            $table->string('page_url', 500)->nullable();
            $table->string('subject', 150);
            $table->text('message');
            $table->string('country', 2)->nullable();
            $table->string('ip_hash', 64)->index();
            $table->string('user_agent', 255)->nullable();
            $table->timestamp('mailed_at')->nullable();
            $table->string('mail_error', 255)->nullable();
            $table->string('status', 16)->default('new')->index();
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('contact_messages');
    }
};
