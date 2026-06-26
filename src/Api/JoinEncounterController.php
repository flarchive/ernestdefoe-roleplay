<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Models\Character;
use Ernestdefoe\Roleplay\Models\Combatant;
use Ernestdefoe\Roleplay\Models\Encounter;
use Ernestdefoe\Roleplay\Models\Sheet;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/rp/encounters/{id}/join — a player adds themselves to an encounter
 * (during setup) as a party combatant tied to one of their own characters, so
 * the table fills with real characters instead of GM-typed names. HP/agility are
 * seeded from the character's combat sheet when one exists.
 */
class JoinEncounterController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $enc = Encounter::findOrFail((int) Arr::get($request->getQueryParams(), 'id'));
        if ($enc->status !== 'setup') {
            throw new ValidationException(['status' => 'You can only join before the encounter starts.']);
        }

        $body = (array) $request->getParsedBody();
        $character = Character::where('user_id', $actor->id)
            ->where('status', 'approved')
            ->findOrFail((int) Arr::get($body, 'characterId'));

        // No duplicate: if this character is already in, hand it back unchanged.
        $existing = Combatant::where('encounter_id', $enc->id)->where('character_id', $character->id)->first();
        if ($existing) {
            return new JsonResponse(['data' => Present::combatant($existing)]);
        }

        $sheet = Sheet::where('character_id', $character->id)->first();
        $attrs = $sheet?->attributes ?: [];
        $maxHp = (int) ($sheet?->max_hp ?: self::int(Arr::get($body, 'maxHp'), 1, 9999) ?? 20);

        $c = new Combatant();
        $c->encounter_id = $enc->id;
        $c->character_id = $character->id;
        $c->team = 'party';
        $c->name = $character->name;
        $c->max_hp = $maxHp;
        $c->hp = $maxHp;
        $c->meta = ['defense' => (int) ($attrs['defense'] ?? 10), 'agility' => (int) ($attrs['agility'] ?? 0)];
        $c->save();

        Touch::encounter($enc);

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
