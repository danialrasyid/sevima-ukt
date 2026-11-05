<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('institusi', function (Blueprint $table) {
            $table->id();
            $table->string('kode', 50)->unique();
            $table->string('nama');
            // Secret HMAC per institusi. Disimpan terenkripsi (cast "encrypted" pada model).
            $table->text('webhook_secret');
            $table->timestamps();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('institusi');
    }
};
