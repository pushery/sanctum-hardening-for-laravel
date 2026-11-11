<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The alerts a token's holder was mailed: one row for each token and kind, naming the occasion the last
// mail was about, so the same expiry or the same last use is never mailed twice. A table of its own, for
// the reason the prefixes have one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_hardening_token_alerts', function (Blueprint $table): void {
            $table->id();
            // A string, so a token model keyed by a UUID or a ULID fits as well as one keyed by a number.
            $table->string('token_id', 64);
            $table->string('kind', 16);
            // The expiry a mail before an expiry was about, or the last use a mail after a silence was about.
            // dateTimeTz rather than timestamp(): TIMESTAMP on MySQL ends in 2038, and a column without a
            // zone on PostgreSQL stores a wall-clock reading without the zone it was taken in.
            $table->dateTimeTz('occasion_at');
            $table->dateTimeTz('sent_at');

            $table->unique(['token_id', 'kind']);
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_hardening_token_alerts');
    }
};
