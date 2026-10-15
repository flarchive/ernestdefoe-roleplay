<?php

namespace Ernestdefoe\Roleplay\Api;

use Flarum\Discussion\Discussion;
use Flarum\User\User;

/**
 * Shared access guard for the encounter/combat endpoints.
 *
 * Every endpoint that takes a caller-supplied encounter or discussion id must
 * prove the actor can actually see the underlying discussion before returning or
 * mutating anything — otherwise a registered member could enumerate encounters
 * (combatant names, HP, player user-ids) in discussions they cannot view, or
 * join/act in private games by guessing the id.
 */
class Guard
{
    /**
     * Assert the actor can see the discussion and return it. Throws a
     * ModelNotFoundException (404) when the discussion is missing or hidden —
     * deliberately indistinguishable, so visibility can't be probed.
     */
    public static function discussion(User $actor, int $discussionId): Discussion
    {
        return Discussion::whereVisibleTo($actor)->findOrFail($discussionId);
    }
}
