<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Game;
use Ernestdefoe\Roleplay\Models\Encounter;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/rp/encounters/{id}/{action} — GM-only lifecycle actions:
 *   start → roll initiative and begin · next → advance the turn · end → close it.
 */
class EncounterActionController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $enc = Encounter::findOrFail((int) Arr::get($request->getQueryParams(), 'id'));
        $actor->assertPermission((int) $enc->gm_user_id === (int) $actor->id);

        switch (Arr::get($request->getQueryParams(), 'action')) {
            case 'start':
                if ($enc->combatants()->count() < 1) {
                    throw new ValidationException(['combatants' => 'Add at least one combatant before starting.']);
                }
                Game::start($enc);
                break;

            case 'next':
                if ($enc->status !== 'active') {
                    throw new ValidationException(['status' => 'The encounter is not active.']);
                }
                Game::nextTurn($enc);
                break;

            case 'end':
                $enc->status = 'ended';
                $enc->save();
                break;

            default:
                throw new ValidationException(['action' => 'Unknown encounter action.']);
        }

        Touch::encounter($enc);

        return new JsonResponse(['data' => Present::encounter($enc, $actor)]);
    }
}
