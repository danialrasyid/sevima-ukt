<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('tagihan', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->restrictOnDelete();
            $table->unsignedBigInteger('mahasiswa_id');
            $table->string('nomor_tagihan', 50);
            $table->string('semester', 30);
            $table->unsignedBigInteger('nominal'); // rupiah, tanpa desimal
            $table->timestampTz('tanggal_kedaluwarsa');
            $table->string('status', 20)->default('belum_bayar');
            $table->timestampTz('paid_at')->nullable();
            $table->boolean('perlu_review')->default(false);
            $table->text('catatan_review')->nullable();
            $table->timestamps();

            // Nomor tagihan unik per institusi (bukan global).
            $table->unique(['institusi_id', 'nomor_tagihan']);
            // Satu tagihan UKT per mahasiswa per semester.
            $table->unique(['institusi_id', 'mahasiswa_id', 'semester']);
            // Target foreign key komposit dari tabel transaksi.
            $table->unique(['institusi_id', 'id']);

            // Tagihan tidak mungkin menunjuk mahasiswa milik institusi lain.
            $table->foreign(['institusi_id', 'mahasiswa_id'])
                ->references(['institusi_id', 'id'])->on('mahasiswa')
                ->restrictOnDelete();

            $table->index(['institusi_id', 'status']);
            $table->index(['status', 'tanggal_kedaluwarsa']);
        });

        DB::statement("ALTER TABLE tagihan ADD CONSTRAINT tagihan_status_check CHECK (status IN ('belum_bayar', 'lunas', 'kedaluwarsa'))");
        DB::statement('ALTER TABLE tagihan ADD CONSTRAINT tagihan_nominal_check CHECK (nominal > 0)');
    }

    public function down(): void
    {
        Schema::dropIfExists('tagihan');
    }
};
