<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Models\Encounter;
use Illuminate\Contracts\Container\Container;

/**
 * Pings live viewers that an encounter changed (a card played, a turn passed, it
 * started/ended) so every open combat tracker refetches the authoritative state.
 *
 * Broadcasts a lightweight event carrying only the discussion id on flarum/
 * realtime's shared public channel — each tracker filters by its own discussion
 * and refetches. Reuses realtime's pre-configured Pusher singleton, so it needs
 * no settings of its own. A graceful no-op when flarum/realtime isn't installed
 * or the daemon is down (the tracker's slow poll still covers that case).
 *
 * Resolved from the container and injected into the controllers that emit it, so
 * the Pusher dependency is wired through DI rather than service-located.
 */
class Touch
{
    public function __construct(private Container $container)
    {
    }

    public function encounter(Encounter $enc): void
    {
        // flarum/realtime binds Pusher; if it isn't installed/configured, bail.
        if (! $this->container->bound(\Pusher\Pusher::class)) {
            return;
        }

        try {
            $this->container->make(\Pusher\Pusher::class)->trigger(
                'public',
                'rp.encounter.touched',
                ['discussionId' => (int) $enc->discussion_id]
            );
        } catch (\Throwable $e) {
            // Realtime unavailable — the poll fallback keeps the tracker fresh.
        }
    }
}
