<?php

use Illuminate\Database\Schema\Blueprint;
use Illuminate\Database\Schema\Builder;

/**
 * Role-Play — self-owned companion tables (no core tables touched). FK columns
 * that point at core rows use INT UNSIGNED because Flarum's users.id / posts.id /
 * discussions.id are INT UNSIGNED; the extension's own ids are BIGINT.
 */
return [
    'up' => function (Builder $schema) {
        if (! $schema->hasTable('rp_characters')) {
            $schema->create('rp_characters', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('user_id')->index();      // owner (users.id)
                $t->string('name');
                $t->string('slug')->unique();
                $t->string('avatar_url')->nullable();
                $t->string('color', 16)->nullable();          // hex accent; derived when null
                $t->text('bio')->nullable();
                $t->string('status')->default('approved');    // pending | approved | archived
                $t->unsignedInteger('post_count')->default(0);
                $t->timestamp('last_posted_at')->nullable();
                $t->timestamps();
            });
        }

        // One character per post — the identity a post was authored as.
        if (! $schema->hasTable('rp_post_character')) {
            $schema->create('rp_post_character', function (Blueprint $t) {
                $t->unsignedInteger('post_id')->primary();    // posts.id
                $t->unsignedBigInteger('character_id')->index();
                $t->timestamp('created_at')->nullable();
            });
        }

        if (! $schema->hasTable('rp_cards')) {
            $schema->create('rp_cards', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('user_id')->index();      // owner
                $t->string('name');
                $t->string('icon')->nullable();               // any Font Awesome class
                $t->string('type')->default('ability');       // ability | item | spell | enemy
                $t->text('description')->nullable();
                $t->string('attack_expr', 40)->nullable();    // to-hit roll, e.g. "1d20+3"
                $t->string('damage_expr', 40)->nullable();    // damage roll, e.g. "2d6+1"
                $t->unsignedSmallInteger('defense')->nullable();
                $t->unsignedSmallInteger('hp')->nullable();
                $t->unsignedSmallInteger('cost')->default(0);
                $t->boolean('is_public')->default(false);
                $t->timestamps();
            });
        }

        if (! $schema->hasTable('rp_sheets')) {
            $schema->create('rp_sheets', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('character_id')->unique();
                $t->unsignedSmallInteger('max_hp')->default(20);
                $t->smallInteger('hp')->default(20);
                $t->json('attributes')->nullable();           // {might, agility, wits, heart}
                $t->json('equipped')->nullable();             // [card_id, ...] — the hand
                $t->timestamps();
            });
        }

        if (! $schema->hasTable('rp_encounters')) {
            $schema->create('rp_encounters', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedInteger('discussion_id')->index(); // the RP discussion it runs in
                $t->unsignedInteger('gm_user_id')->index();    // storyteller
                $t->string('name')->nullable();
                $t->string('status')->default('setup');        // setup | active | ended
                $t->unsignedSmallInteger('round')->default(1);
                $t->unsignedSmallInteger('turn_index')->default(0);
                $t->json('order')->nullable();                 // [combatant_id, ...]
                $t->timestamps();
            });
        }

        if (! $schema->hasTable('rp_combatants')) {
            $schema->create('rp_combatants', function (Blueprint $t) {
                $t->bigIncrements('id');
                $t->unsignedBigInteger('encounter_id')->index();
                $t->unsignedBigInteger('character_id')->nullable();
                $t->unsignedBigInteger('card_id')->nullable(); // enemy/summon card
                $t->string('name');
                $t->unsignedSmallInteger('max_hp')->default(10);
                $t->smallInteger('hp')->default(10);
                $t->smallInteger('initiative')->default(0);
                $t->string('team')->default('party');          // party | foe
                $t->boolean('is_down')->default(false);
                $t->json('meta')->nullable();
                $t->timestamps();
            });
        }
    },
    'down' => function (Builder $schema) {
        $schema->dropIfExists('rp_combatants');
        $schema->dropIfExists('rp_encounters');
        $schema->dropIfExists('rp_sheets');
        $schema->dropIfExists('rp_cards');
        $schema->dropIfExists('rp_post_character');
        $schema->dropIfExists('rp_characters');
    },
];
