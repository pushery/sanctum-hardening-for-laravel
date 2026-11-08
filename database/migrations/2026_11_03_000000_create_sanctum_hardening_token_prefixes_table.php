<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The first characters of each token's secret, by token. A table of its own rather than a column of
// `personal_access_tokens`: an application publishes Sanctum's migration with the day it runs
// `install:api`, so a migration here that altered that table could run before the table exists.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_hardening_token_prefixes', function (Blueprint $table): void {
            // A string, so a token model keyed by a UUID or a ULID fits as well as one keyed by a number.
            $table->string('token_id', 64)->primary();
            $table->string('display_prefix', 32);
            // dateTimeTz rather than timestamp(): TIMESTAMP on MySQL ends in 2038, and a column without a
            // zone on PostgreSQL stores a wall-clock reading without the zone it was taken in.
            $table->dateTimeTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_hardening_token_prefixes');
    }
};
