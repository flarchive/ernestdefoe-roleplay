<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Models\Card;
use Ernestdefoe\Roleplay\Models\Character;
use Ernestdefoe\Roleplay\Models\Combatant;
use Ernestdefoe\Roleplay\Models\Encounter;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/rp/encounters/{id}/combatants — the GM adds a fighter to an
 * encounter during setup: a party member (optionally tied to a character) or a
 * foe (optionally spawned from an enemy card, which seeds HP + defense).
 */
class AddCombatantController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $enc = Encounter::findOrFail((int) Arr::get($request->getQueryParams(), 'id'));
        $actor->assertPermission((int) $enc->gm_user_id === (int) $actor->id);

        $body = (array) $request->getParsedBody();
        $name = trim((string) Arr::get($body, 'name', ''));
        $team = Arr::get($body, 'team') === 'foe' ? 'foe' : 'party';
        $defense = self::int(Arr::get($body, 'defense'), 0, 999);
        $agility = self::int(Arr::get($body, 'agility'), -20, 20);
        $maxHp = self::int(Arr::get($body, 'maxHp'), 1, 9999) ?? 10;

        $c = new Combatant();
        $c->encounter_id = $enc->id;
        $c->team = $team;

        // Spawn a foe from an enemy card: seed HP + defense from the card.
        if ($cardId = (int) Arr::get($body, 'cardId')) {
            $card = Card::where('id', $cardId)->where(fn ($q) => $q->where('user_id', $actor->id)->orWhere('is_public', true))->first();
            if ($card) {
                $c->card_id = $card->id;
                $name = $name !== '' ? $name : $card->name;
                $maxHp = (int) ($card->hp ?: $maxHp);
                $defense = $defense ?? ($card->defense !== null ? (int) $card->defense : null);
            }
        }

        // Tie a party member to one of the actor's characters (for the badge).
        if ($charId = (int) Arr::get($body, 'characterId')) {
            $char = Character::where('user_id', $actor->id)->find($charId);
            if ($char) {
                $c->character_id = $char->id;
                $name = $name !== '' ? $name : $char->name;
            }
        }

        if ($name === '') {
            throw new ValidationException(['name' => 'A combatant name is required.']);
        }

        $c->name = mb_substr($name, 0, 80);
        $c->max_hp = $maxHp;
        $c->hp = $maxHp;
        $c->meta = array_filter(['defense' => $defense, 'agility' => $agility], fn ($v) => $v !== null);
        $c->save();

        return new JsonResponse(['data' => Present::combatant($c)]);
    }

    private static function int($v, int $min, int $max): ?int
    {
        if ($v === null || $v === '') {
            return null;
        }

        return max($min, min($max, (int) $v));
    }
}
