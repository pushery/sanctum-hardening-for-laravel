<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The public identifier inside each token's key, by token. A table of its own rather than a column of
// `personal_access_tokens`, for the reason the other tables of the package have one: an application
// publishes Sanctum's migration with the day it runs `install:api`, so a migration here that altered
// that table could run before the table exists.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_hardening_key_ids', function (Blueprint $table): void {
            // The identifier finds its token when a key comes in, and names it on the screen, in a mail and
            // in a log, so it is unique.
            $table->string('key_id', 12)->primary();
            // A string, so a token model keyed by a UUID or a ULID fits as well as one keyed by a number.
            $table->string('token_id', 64)->unique();
            // The key up to and including the identifier, what the holder sees at the start of the key: product,
            // environment, a tenant of up to 32 characters and the identifier, 61 characters at most.
            $table->string('prefix', 64);
            // dateTimeTz rather than timestamp(): TIMESTAMP on MySQL ends in 2038, and a column without a
            // zone on PostgreSQL stores a wall-clock reading without the zone it was taken in.
            $table->dateTimeTz('created_at')->nullable();
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_hardening_key_ids');
    }
};
