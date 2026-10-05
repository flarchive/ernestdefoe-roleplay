<?php

namespace Ernestdefoe\Roleplay\Api\Resource;

use Ernestdefoe\Roleplay\Models\Card;
use Flarum\Api\Resource\AbstractDatabaseResource;
use Flarum\Api\Schema;
use Illuminate\Database\Eloquent\Builder;
use Tobyz\JsonApiServer\Context as BaseContext;

/**
 * JSON:API resource for role-play cards (type `roleplay-cards`).
 *
 * Type-only registration (no endpoints): the forum uses the plain-JSON
 * controllers at /api/rp/cards. Registering the type makes cards observable and
 * extendable by other extensions and safe to include as a relationship.
 */
class CardResource extends AbstractDatabaseResource
{
    public function type(): string
    {
        return 'roleplay-cards';
    }

    public function model(): string
    {
        return Card::class;
    }

    public function scope(Builder $query, BaseContext $context): void
    {
        // Mirror the controller's visibility: a card is readable by its owner or
        // by anyone if it has been shared publicly.
        $actor = $context->getActor();

        $query->where(function (Builder $q) use ($actor) {
            $q->where('is_public', true);

            if ($actor->exists) {
                $q->orWhere('user_id', $actor->id);
            }
        });
    }

    public function endpoints(): array
    {
        return [];
    }

    public function fields(): array
    {
        return [
            Schema\Str::make('name'),
            Schema\Str::make('icon')->nullable(),
            Schema\Str::make('type'),
            Schema\Str::make('description')->nullable(),
            Schema\Str::make('attackExpr')->property('attack_expr')->nullable(),
            Schema\Str::make('damageExpr')->property('damage_expr')->nullable(),
            Schema\Integer::make('defense')->nullable(),
            Schema\Integer::make('hp')->nullable(),
            Schema\Integer::make('cost'),
            Schema\Boolean::make('isPublic')->property('is_public'),
        ];
    }
}
