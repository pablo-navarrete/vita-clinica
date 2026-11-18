<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{Schema,DB};
return new class extends Migration {
    public function up(): void {
        Schema::create('portal_profile_sharing',function(Blueprint $t){
            $t->foreignId('portal_account_id')->primary()->constrained()->restrictOnDelete();
            $t->longText('encrypted_profile')->nullable();$t->boolean('enabled')->default(false);
            $t->string('consent_version',24)->nullable();$t->timestamp('accepted_at')->nullable();$t->timestamps();
        });
        Schema::create('portal_profile_lookup_events',function(Blueprint $t){
            $t->id();$t->foreignId('customer_id')->constrained()->restrictOnDelete();
            $t->foreignId('user_id')->constrained()->restrictOnDelete();$t->foreignId('clinic_branch_id')->constrained()->restrictOnDelete();
            $t->string('result',16);$t->timestamp('created_at');
        });
    }
    public function down(): void {
        if(DB::table('portal_profile_sharing')->exists() || DB::table('portal_profile_lookup_events')->exists())throw new RuntimeException('Sharing history exists; archive explicitly before rollback.');
        Schema::dropIfExists('portal_profile_lookup_events');Schema::dropIfExists('portal_profile_sharing');
    }
};
