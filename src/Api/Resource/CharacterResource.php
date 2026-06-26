<?php

namespace Ernestdefoe\Roleplay\Api\Resource;

use Ernestdefoe\Roleplay\Models\Character;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * JSON:API resource for role-play characters (type `roleplay-characters`).
 *
 * Like the rest of this extension (and the giveaways/projects extensions), the
 * forum talks to the plain-JSON controllers at /api/rp/characters; this resource
 * exists so characters are a registered JSON:API *type*. That makes them
 * observable/extendable by other extensions and lets a post's authored-as
 * character be exposed as a proper relationship rather than an inline blob.
 * It intentionally registers NO endpoints — the custom routes own the HTTP API.
 */
class CharacterResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'roleplay-characters';
    }

    public function model(): string
    {
        return Character::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        // A character's public identity (name, avatar, colour) is shown wherever
        // it has posted, so no per-row gating is needed when one is referenced.
    }

    public function endpoints(): array
    {
        // No endpoints: the custom /api/rp/characters controllers own the HTTP
        // surface; registering endpoints here would collide with those routes.
        return [];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name'),
            Schema\Str::make('slug'),
            Schema\Str::make('avatarUrl')->property('avatar_url')->nullable(),
            Schema\Str::make('color')->nullable(),
            Schema\Str::make('bio')->nullable(),
            Schema\Str::make('status'),
            Schema\Integer::make('postCount')->property('post_count'),
            Schema\DateTime::make('lastPostedAt')->property('last_posted_at')->nullable(),
        ];
    }
}
