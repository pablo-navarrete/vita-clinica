<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB, Schema};

return new class extends Migration {
    public function up(): void
    {
        Schema::create('billing_features', function (Blueprint $table) {
            $table->engine = 'InnoDB';
            $table->charset = 'utf8mb4';
            $table->collation = 'utf8mb4_unicode_ci';
            $table->bigIncrements('id');
            $table->string('code', 80);
            $table->string('name', 255);
            $table->unique(['code'], 'billing_features_code_unique');
        });
        foreach (\App\Services\Billing\BillingCapabilities::FEATURES as $code => $name) {
            DB::table('billing_features')->insert(compact('code', 'name'));
        }
    }

    public function down(): void
    {
        Schema::dropIfExists('billing_features');
    }
};
