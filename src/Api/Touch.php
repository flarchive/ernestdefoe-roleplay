<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Models\Encounter;

/**
 * Pings live viewers that an encounter changed (a card played, a turn passed, it
 * started/ended) so every open combat tracker refetches the authoritative state.
 *
 * Broadcasts a lightweight event carrying only the discussion id on flarum/
 * realtime's shared public channel — each tracker filters by its own discussion
 * and refetches. Reuses realtime's pre-configured Pusher singleton, so it needs
 * no settings of its own. A graceful no-op when flarum/realtime isn't installed
 * or the daemon is down (the tracker's slow poll still covers that case).
 */
class Touch
{
    public static function encounter(Encounter $enc): void
    {
        if (! class_exists(\Pusher\Pusher::class)) {
            return;
        }

        try {
            resolve(\Pusher\Pusher::class)->trigger(
                'public',
                'rp.encounter.touched',
                ['discussionId' => (int) $enc->discussion_id]
            );
        } catch (\Throwable $e) {
            // Realtime unavailable — the poll fallback keeps the tracker fresh.
        }
    }
}
