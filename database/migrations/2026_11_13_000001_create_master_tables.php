<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('warehouses', function (Blueprint $table) {
            $table->id();
            $table->string('code')->unique();
            $table->string('name');
            $table->text('address')->nullable();
            $table->timestamps();
        });

        Schema::create('locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->foreignId('parent_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->string('code');
            $table->string('name')->nullable();
            $table->string('type');
            $table->string('barcode')->unique();
            $table->boolean('is_active')->default(true);
            $table->timestamps();

            $table->unique(['warehouse_id', 'code']);
            $table->index('code');
        });

        Schema::create('items', function (Blueprint $table) {
            $table->id();
            $table->string('sku')->unique();
            $table->string('name');
            $table->string('category')->nullable();
            $table->string('base_unit');
            $table->unsignedInteger('min_stock')->default(0);
            $table->string('photo_path')->nullable();
            $table->boolean('is_active')->default(true);
            $table->timestamps();
        });

        Schema::create('item_barcodes', function (Blueprint $table) {
            $table->id();
            $table->foreignId('item_id')->constrained()->cascadeOnDelete();
            $table->string('barcode')->unique();
            $table->string('type');
            $table->string('unit');
            $table->unsignedInteger('factor')->default(1);
            $table->timestamps();
        });

        DB::statement('ALTER TABLE item_barcodes ADD CONSTRAINT item_barcodes_factor_positive CHECK (factor >= 1)');
    }

    public function down(): void
    {
        Schema::dropIfExists('item_barcodes');
        Schema::dropIfExists('items');
        Schema::dropIfExists('locations');
        Schema::dropIfExists('warehouses');
    }
};
