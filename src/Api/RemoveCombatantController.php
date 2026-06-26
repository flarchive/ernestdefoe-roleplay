<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Models\Combatant;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\EmptyResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** DELETE /api/rp/combatants/{id} — the GM removes a fighter (setup only). */
class RemoveCombatantController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $c = Combatant::with('encounter')->findOrFail((int) Arr::get($request->getQueryParams(), 'id'));
        $actor->assertPermission($c->encounter && (int) $c->encounter->gm_user_id === (int) $actor->id);

        $c->delete();

        return new EmptyResponse(204);
    }
}
