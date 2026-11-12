<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('document_sequences', function (Blueprint $table) {
            $table->id();
            $table->string('type');
            $table->unsignedSmallInteger('year');
            $table->unsignedInteger('last_number')->default(0);

            $table->unique(['type', 'year']);
        });

        Schema::create('documents', function (Blueprint $table) {
            $table->id();
            $table->string('number')->unique();
            $table->string('type');
            $table->string('status')->default('draft');
            $table->foreignId('warehouse_id')->constrained()->restrictOnDelete();
            $table->string('reference')->nullable();
            $table->string('partner')->nullable();
            $table->text('notes')->nullable();
            $table->foreignId('related_document_id')->nullable()->constrained('documents')->nullOnDelete();
            $table->foreignId('reversal_of_id')->nullable()->constrained('documents')->restrictOnDelete();
            $table->foreignId('created_by')->constrained('users')->restrictOnDelete();
            $table->timestamp('submitted_at')->nullable();
            $table->timestamp('completed_at')->nullable();
            $table->foreignId('completed_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->foreignId('approved_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('approved_at')->nullable();
            $table->text('approval_reason')->nullable();
            $table->timestamps();

            $table->index(['type', 'status']);
        });

        Schema::create('document_lines', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('item_id')->constrained()->restrictOnDelete();
            $table->foreignId('from_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->foreignId('to_location_id')->nullable()->constrained('locations')->restrictOnDelete();
            $table->integer('qty_planned')->default(0);
            $table->integer('qty_scanned')->default(0);
            $table->integer('qty_packed')->default(0);
            $table->integer('qty_system')->nullable();
            $table->timestamps();

            $table->index(['item_id', 'from_location_id']);
        });

        DB::statement('ALTER TABLE document_lines ADD CONSTRAINT document_lines_qty_non_negative
            CHECK (qty_planned >= 0 AND qty_scanned >= 0 AND qty_packed >= 0 AND (qty_system IS NULL OR qty_system >= 0))');

        Schema::create('opname_locations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('document_id')->constrained()->cascadeOnDelete();
            $table->foreignId('location_id')->constrained()->restrictOnDelete();
            $table->string('status')->default('belum');
            $table->foreignId('counted_by')->nullable()->constrained('users')->restrictOnDelete();
            $table->timestamp('counted_at')->nullable();
            $table->timestamps();

            $table->unique(['document_id', 'location_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('opname_locations');
        Schema::dropIfExists('document_lines');
        Schema::dropIfExists('documents');
        Schema::dropIfExists('document_sequences');
    }
};
