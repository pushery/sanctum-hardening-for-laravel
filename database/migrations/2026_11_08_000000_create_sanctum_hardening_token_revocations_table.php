<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// Who revoked which token, and when. A table of its own rather than columns of
// `personal_access_tokens`, for the reason the prefixes have one: an application publishes Sanctum's
// migration with the day it runs `install:api`, so a migration here that altered that table could run
// before the table exists. The token's name and holder are kept here as well, so the record still says
// which token it was once the token itself is pruned.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_hardening_token_revocations', function (Blueprint $table): void {
            // Strings, so a token or a user keyed by a UUID or a ULID fits as well as one keyed by a number.
            $table->string('token_id', 64)->primary();
            $table->string('token_name');
            $table->string('tokenable_type');
            $table->string('tokenable_id', 64);
            // Null where no signed-in user revoked it: the application's own code, or a console command.
            $table->string('revoked_by_type')->nullable();
            $table->string('revoked_by_id', 64)->nullable();
            // dateTimeTz rather than timestamp(): TIMESTAMP on MySQL ends in 2038, and a column without a
            // zone on PostgreSQL stores a wall-clock reading without the zone it was taken in.
            $table->dateTimeTz('revoked_at');

            // Named, because the name Laravel derives runs to 69 characters: MySQL refuses an identifier
            // over 64, and PostgreSQL cuts one over 63 without an error.
            $table->index(['tokenable_type', 'tokenable_id'], 'sanctum_hardening_revocations_tokenable_index');
            $table->index('revoked_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_hardening_token_revocations');
    }
};
