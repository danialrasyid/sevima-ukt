<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('mahasiswa', function (Blueprint $table) {
            $table->id();
            $table->foreignId('institusi_id')->constrained('institusi')->restrictOnDelete();
            $table->foreignId('user_id')->nullable()->unique()->constrained('users')->nullOnDelete();
            $table->string('nim', 30);
            $table->string('nama');
            $table->string('program_studi')->nullable();
            $table->timestamps();

            // NIM unik di dalam satu institusi, boleh sama di institusi lain.
            $table->unique(['institusi_id', 'nim']);
            // Target foreign key komposit dari tabel tagihan (mencegah lintas institusi).
            $table->unique(['institusi_id', 'id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('mahasiswa');
    }
};
