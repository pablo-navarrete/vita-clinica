<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('customers', function (Blueprint $table) {
            $table->char('operating_country_code', 2)->charset('ascii')->collation('ascii_bin')->default('CL');
        });
        DB::statement("ALTER TABLE customers ADD CONSTRAINT customers_chile_operation CHECK (operating_country_code = 'CL')");
        Schema::create('customer_operating_declarations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->string('version', 80);
            $table->char('operating_country_code', 2)->charset('ascii')->collation('ascii_bin');
            $table->text('declaration_text');
            $table->char('content_hash', 64)->charset('ascii')->collation('ascii_bin');
            $table->boolean('accepted');
            $table->foreignId('accepted_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('accepted_at', 6);
            $table->unique(['customer_id', 'version'], 'operating_declaration_version_unique');
            $table->unique(['customer_id', 'id'], 'operating_declaration_owner_unique');
        });
        DB::statement("ALTER TABLE customer_operating_declarations ADD CONSTRAINT operating_declaration_acceptance CHECK (accepted = 1 AND operating_country_code = 'CL')");
        Schema::create('customer_operating_reviews', function (Blueprint $table) {
            $table->id();
            $table->foreignId('customer_id')->constrained('customers')->restrictOnDelete();
            $table->unsignedBigInteger('declaration_id');
            $table->foreign(['customer_id', 'declaration_id'], 'operating_review_declaration_owner')->references(['customer_id', 'id'])->on('customer_operating_declarations')->restrictOnDelete();
            $table->string('status', 30);
            $table->text('note')->nullable();
            $table->foreignId('reviewed_by')->constrained('users')->restrictOnDelete();
            $table->dateTime('reviewed_at', 6);
            $table->index(['customer_id', 'id']);
        });
        DB::statement("ALTER TABLE customer_operating_reviews ADD CONSTRAINT operating_review_status CHECK (status IN ('pending', 'reviewed', 'needs_information'))");
        foreach (['declarations', 'reviews'] as $kind) {
            foreach (['UPDATE', 'DELETE'] as $event) {
                DB::unprepared("CREATE TRIGGER vita_operating_{$kind}_".strtolower($event)." BEFORE $event ON customer_operating_$kind FOR EACH ROW SIGNAL SQLSTATE '45000' SET MESSAGE_TEXT='Operating evidence is append-only'");
            }
        }
    }

    public function down(): void
    {
        if (DB::table('customer_operating_declarations')->exists() || DB::table('customer_operating_reviews')->exists()) {
            throw new LogicException('Operating evidence must be retained; automatic rollback refused.');
        }
        foreach (['declarations', 'reviews'] as $kind) {
            foreach (['update', 'delete'] as $event) DB::unprepared("DROP TRIGGER IF EXISTS vita_operating_{$kind}_$event");
        }
        Schema::drop('customer_operating_reviews');
        Schema::drop('customer_operating_declarations');
        DB::statement('ALTER TABLE customers DROP CHECK customers_chile_operation');
        Schema::table('customers', fn (Blueprint $table) => $table->dropColumn('operating_country_code'));
    }
};
