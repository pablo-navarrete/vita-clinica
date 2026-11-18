<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void
    {
        Schema::table('customer_directory_profiles', function (Blueprint $table) {
            $table->string('commune', 100)->nullable();
            $table->boolean('show_public_contact')->default(false);
            $table->boolean('show_public_social_links')->default(false);
        });
        Schema::table('clinic_branches', function (Blueprint $table) {
            $table->boolean('directory_publication_enabled')->default(false);
            $table->string('directory_commune', 100)->nullable();
            $table->string('directory_region', 100)->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('clinic_branches', fn (Blueprint $table) => $table->dropColumn(['directory_publication_enabled', 'directory_commune', 'directory_region']));
        Schema::table('customer_directory_profiles', fn (Blueprint $table) => $table->dropColumn(['commune', 'show_public_contact', 'show_public_social_links']));
    }
};
