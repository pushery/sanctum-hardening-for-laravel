<?php

declare(strict_types=1);

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

// The requests a token made: which route, with which method, answered with which status, and when. No
// body and no address with its values, only the route's name or its URI pattern, so nothing a person
// sent is kept. A table of its own, for the reason the prefixes have one.
return new class extends Migration
{
    public function up(): void
    {
        Schema::create('sanctum_hardening_token_uses', function (Blueprint $table): void {
            $table->id();
            // A string, so a token model keyed by a UUID or a ULID fits as well as one keyed by a number.
            $table->string('token_id', 64);
            $table->string('method', 10);
            $table->string('route');
            $table->unsignedSmallInteger('status');
            // dateTimeTz rather than timestamp(): TIMESTAMP on MySQL ends in 2038, and a column without a
            // zone on PostgreSQL stores a wall-clock reading without the zone it was taken in.
            $table->dateTimeTz('used_at');

            $table->index(['token_id', 'used_at']);
            $table->index('used_at');
        });
    }

    public function down(): void
    {
        Schema::dropIfExists('sanctum_hardening_token_uses');
    }
};
