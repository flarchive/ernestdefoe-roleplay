<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Game;
use Ernestdefoe\Roleplay\Models\Card;
use Ernestdefoe\Roleplay\Models\Character;
use Ernestdefoe\Roleplay\Models\Combatant;
use Ernestdefoe\Roleplay\Models\Encounter;

/** Shapes models into the plain JSON the forum frontend consumes. */
class Present
{
    public static function character(Character $c): array
    {
        return [
            'id' => (int) $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'avatarUrl' => $c->avatar_url,
            'color' => $c->color,
            'bio' => $c->bio,
            'status' => $c->status,
            'postCount' => (int) $c->post_count,
        ];
    }

    public static function card(Card $c, ?int $actorId = null): array
    {
        return [
            'id' => (int) $c->id,
            'name' => $c->name,
            'icon' => $c->icon,
            'type' => $c->type,
            'description' => $c->description,
            'attackExpr' => $c->attack_expr,
            'damageExpr' => $c->damage_expr,
            'defense' => $c->defense !== null ? (int) $c->defense : null,
            'hp' => $c->hp !== null ? (int) $c->hp : null,
            'cost' => (int) $c->cost,
            'isPublic' => (bool) $c->is_public,
            // Lets the UI gate edit/delete to the owner without a second request.
            'mine' => $actorId !== null && (int) $c->user_id === $actorId,
        ];
    }

    public static function combatant(Combatant $c): array
    {
        $char = $c->character;

        return [
            'id' => (int) $c->id,
            'name' => $c->name,
            'team' => $c->team,
            'maxHp' => (int) $c->max_hp,
            'hp' => (int) $c->hp,
            'initiative' => (int) $c->initiative,
            'isDown' => (bool) $c->is_down,
            'characterId' => $c->character_id ? (int) $c->character_id : null,
            'cardId' => $c->card_id ? (int) $c->card_id : null,
            'meta' => $c->meta ?: (object) [],   // {defense, agility}
            'character' => $char ? [
                'name' => $char->name,
                'color' => $char->color,
                'avatarUrl' => $char->avatar_url,
                'userId' => (int) $char->user_id,
            ] : null,
        ];
    }

    /** The full live tracker: the encounter, its combatants (initiative order) and whose turn it is. */
    public static function encounter(Encounter $enc, $actor = null): array
    {
        $combatants = $enc->combatants()->orderByDesc('initiative')->orderBy('id')->get();

        return [
            'id' => (int) $enc->id,
            'discussionId' => (int) $enc->discussion_id,
            'gmUserId' => (int) $enc->gm_user_id,
            'isGm' => $actor && (int) $actor->id === (int) $enc->gm_user_id,
            'name' => $enc->name,
            'status' => $enc->status,
            'round' => (int) $enc->round,
            'turnIndex' => (int) $enc->turn_index,
            'order' => $enc->order ?: [],
            'activeId' => Game::activeId($enc),
            'combatants' => $combatants->map([self::class, 'combatant'])->values()->all(),
        ];
    }
}
