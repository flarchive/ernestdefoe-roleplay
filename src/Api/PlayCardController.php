<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Game;
use Ernestdefoe\Roleplay\Models\Card;
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
 * POST /api/rp/encounters/{id}/play — resolve a card play. Allowed for the GM,
 * or for the player who owns the character of the *active* combatant (so people
 * act on their own turn). The Game engine rolls to-hit/damage; we persist the
 * target's HP and return the structured result for the action log.
 */
class PlayCardController implements RequestHandlerInterface
{
    public function __construct(private Touch $touch)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $enc = Encounter::findOrFail((int) Arr::get($request->getQueryParams(), 'id'));

        // Prove discussion access before any ownership/turn check.
        Guard::discussion($actor, (int) $enc->discussion_id);

        if ($enc->status !== 'active') {
            throw new ValidationException(['status' => 'The encounter is not active.']);
        }

        $body = (array) $request->getParsedBody();
        $actorC = Combatant::where('encounter_id', $enc->id)->findOrFail((int) Arr::get($body, 'actorCombatantId'));

        $isGm = (int) $enc->gm_user_id === (int) $actor->id;
        $ownsActor = $actorC->character && (int) $actorC->character->user_id === (int) $actor->id;
        $isActiveTurn = Game::activeId($enc) === (int) $actorC->id;
        $actor->assertPermission($isGm || ($ownsActor && $isActiveTurn));

        if ($actorC->is_down) {
            throw new ValidationException(['actor' => 'A downed combatant cannot act.']);
        }

        $card = Card::where('id', (int) Arr::get($body, 'cardId'))
            ->where(fn ($q) => $q->where('user_id', $actor->id)->orWhere('is_public', true))
            ->firstOrFail();

        $target = null;
        if ($targetId = (int) Arr::get($body, 'targetCombatantId')) {
            $target = Combatant::where('encounter_id', $enc->id)->find($targetId);
        }

        $result = Game::play($actorC, $card, $target);

        if ($target) {
            $target->save(); // Game::play mutated hp/is_down in memory
        }

        $this->touch->encounter($enc);

        return new JsonResponse(['data' => [
            'result' => $result,
            'encounter' => Present::encounter($enc, $actor),
        ]]);
    }
}
