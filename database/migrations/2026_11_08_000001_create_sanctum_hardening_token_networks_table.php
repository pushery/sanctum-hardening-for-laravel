<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The addresses and networks a token is limited to, by token. A table of its own rather than a column
// of `personal_access_tokens`, for the reason the prefixes have one: an application publishes Sanctum's
// migration with the day it runs `install:api`, so a migration here that altered that table could run
// before the table exists. A token without a row is limited to nothing.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_hardening_token_networks', function (Blueprint $table): void {
            // A string, so a token model keyed by a UUID or a ULID fits as well as one keyed by a number.
            $table->string('token_id', 64)->primary();
            // The addresses and networks as a JSON list of strings, each an address or one in CIDR notation.
            $table->json('networks');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_hardening_token_networks');
    }
};
