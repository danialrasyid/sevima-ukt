<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('transaksi', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->restrictOnDelete();
            $table->unsignedBigInteger('tagihan_id');
            $table->string('event_id', 191);
            $table->string('event_type', 50);
            $table->unsignedBigInteger('nominal')->nullable();
            $table->string('status_sebelum', 20)->nullable();
            $table->string('status_sesudah', 20)->nullable();
            $table->string('hasil', 20); // diterapkan | diabaikan | ditandai
            $table->text('keterangan')->nullable();
            $table->jsonb('payload')->nullable();
            $table->timestampTz('occurred_at');
            $table->timestamps();

            // Idempotensi: event yang sama dalam satu institusi hanya tercatat sekali.
            $table->unique(['institusi_id', 'event_id']);

            $table->foreign(['institusi_id', 'tagihan_id'])
                ->references(['institusi_id', 'id'])->on('tagihan')
                ->restrictOnDelete();

            $table->index(['tagihan_id', 'occurred_at']);
        });

        DB::statement("ALTER TABLE transaksi ADD CONSTRAINT transaksi_hasil_check CHECK (hasil IN ('diterapkan', 'diabaikan', 'ditandai'))");
    }

    public function down(): void
    {
        Schema::dropIfExists('transaksi');
    }
};
