<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('stock_movements', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->integer('qty');
            $table->string('type');
            $table->foreignId('document_line_id')->nullable()->constrained()->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->constrained()->restrictOnDelete();
            $table->timestamp('created_at')->useCurrent();

            $table->index(['item_id', 'created_at']);
            $table->index('from_location_id');
            $table->index('to_location_id');
            $table->index('document_line_id');
        });

        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_qty_positive CHECK (qty > 0)');
        DB::statement('ALTER TABLE stock_movements ADD CONSTRAINT stock_movements_has_location
            CHECK (from_location_id IS NOT NULL OR to_location_id IS NOT NULL)');

        // Aturan 3: ledger tidak pernah diubah atau dihapus; koreksi = transaksi balik.
        DB::unprepared(<<<'SQL'
            CREATE OR REPLACE FUNCTION stock_movements_immutable() RETURNS trigger AS $$
            BEGIN
                RAISE EXCEPTION 'stock_movements tidak boleh diubah atau dihapus (buat transaksi balik)';
            END;
            $$ LANGUAGE plpgsql;

            CREATE TRIGGER stock_movements_immutable
                BEFORE UPDATE OR DELETE ON stock_movements
                FOR EACH ROW EXECUTE FUNCTION stock_movements_immutable();
        SQL);

        Schema::create('stock_balances', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->integer('qty')->default(0);
            $table->timestamps();

            $table->unique(['item_id', 'location_id']);
            $table->index('location_id');
        });

        DB::statement('ALTER TABLE stock_balances ADD CONSTRAINT stock_balances_qty_non_negative CHECK (qty >= 0)');

        Schema::create('activity_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('user_id')->nullable()->constrained()->nullOnDelete();
            $table->string('action');
            $table->nullableMorphs('subject');
            $table->jsonb('before')->nullable();
            $table->jsonb('after')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });

        Schema::create('scan_logs', function (Blueprint $table) {
            $table->id();
            $table->uuid('client_scan_id')->nullable()->unique();
            $table->foreignId('user_id')->constrained()->restrictOnDelete();
            $table->foreignId('document_id')->nullable()->constrained()->nullOnDelete();
            $table->string('mode');
            $table->string('barcode');
            $table->foreignId('location_id')->nullable()->constrained()->nullOnDelete();
            $table->boolean('success');
            $table->string('code')->nullable();
            $table->string('message');
            $table->jsonb('response')->nullable();
            $table->timestamp('created_at')->useCurrent()->index();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('scan_logs');
        Schema::dropIfExists('activity_logs');
        Schema::dropIfExists('stock_balances');
        DB::unprepared('DROP TRIGGER IF EXISTS stock_movements_immutable ON stock_movements');
        Schema::dropIfExists('stock_movements');
        DB::unprepared('DROP FUNCTION IF EXISTS stock_movements_immutable()');
    }
};
