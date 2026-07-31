<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::create('licenses', function (Blueprint $table) {
            $table->id();
            $table->foreignId('team_id')->constrained()->cascadeOnDelete();
            $table->foreignId('order_item_id')->nullable()->constrained()->nullOnDelete();
            $table->foreignId('product_id')->constrained()->cascadeOnDelete();
            $table->string('key')->unique();
            $table->string('status')->default('active'); // active | revoked
            $table->unsignedInteger('activation_limit')->default(1);
            // End of the free-updates window. The license keeps working
            // past it; only newer releases stop being downloadable.
            $table->timestamp('expires_at')->nullable();
            $table->timestamps();

            $table->index(['team_id', 'product_id']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('licenses');
    }
};
