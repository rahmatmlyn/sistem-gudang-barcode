<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('items', function (Blueprint $table) {
            // terisi saat notifikasi stok minimum terkirim; dikosongkan saat stok kembali di atas minimum
            $table->timestamp('min_stock_alerted_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::table('items', fn (Blueprint $table) => $table->dropColumn('min_stock_alerted_at'));
    }
};
