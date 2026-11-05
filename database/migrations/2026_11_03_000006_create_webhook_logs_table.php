<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        // Log audit: SEMUA notifikasi yang diterima dicatat, termasuk yang ditolak.
        Schema::create('webhook_logs', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->nullable()->constrained('institusi')->nullOnDelete();
            $table->string('kode_institusi', 100)->nullable();
            $table->string('event_id', 191)->nullable();
            $table->string('event_type', 50)->nullable();
            $table->string('nomor_tagihan', 50)->nullable();
            $table->text('signature_header')->nullable();
            $table->boolean('signature_valid')->nullable();
            $table->string('outcome', 40)->default('diterima');
            $table->unsignedSmallInteger('http_status')->nullable();
            $table->text('catatan')->nullable();
            $table->jsonb('headers')->nullable();
            $table->text('raw_body')->nullable();
            $table->string('ip', 64)->nullable();
            $table->timestampTz('received_at');
            $table->timestamps();

            $table->index(['institusi_id', 'received_at']);
            $table->index('outcome');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('webhook_logs');
    }
};
