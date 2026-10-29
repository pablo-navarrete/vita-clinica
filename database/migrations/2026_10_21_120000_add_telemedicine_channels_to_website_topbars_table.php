<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('website_topbars', function (Blueprint $table) {
            $table->string('reply_whatsapp', 40)->nullable()->after('reply_email');
            $table->string('telemedicine_delivery', 10)->default('email')->after('reply_whatsapp');
        });
    }

    public function down(): void
    {
        Schema::table('website_topbars', function (Blueprint $table) {
            $table->dropColumn(['reply_whatsapp', 'telemedicine_delivery']);
        });
    }
};
