<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('license_activations', function (Blueprint $table) {
            $table->id();
            $table->foreignId('license_id')->constrained()->cascadeOnDelete();
            $table->string('instance'); // domain or host identifier
            $table->timestamp('activated_at');
            $table->timestamps();

            $table->unique(['license_id', 'instance']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('license_activations');
    }
};
