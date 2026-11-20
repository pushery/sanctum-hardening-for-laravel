<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Which token replaced which, and until when the replaced one still holds. A table of its own rather than
// columns of `personal_access_tokens`, for the reason the prefixes have one: an application publishes
// Sanctum's migration with the day it runs `install:api`, so a migration here that altered that table
// could run before the table exists.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_hardening_token_rotations', function (Blueprint $table): void {
            // Strings, so a token model keyed by a UUID or a ULID fits as well as one keyed by a number.
            $table->string('token_id', 64)->primary();
            $table->string('successor_id', 64);
            $table->string('rotated_by_type')->nullable();
            $table->string('rotated_by_id', 64)->nullable();
            $table->dateTimeTz('rotated_at');
            // The moment the replaced token stops holding, which its own expiry was set to.
            $table->dateTimeTz('ends_at');

            $table->index('ends_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_hardening_token_rotations');
    }
};
