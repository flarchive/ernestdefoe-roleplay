<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Models\Character;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** GET /api/rp/characters — the signed-in member's own characters. */
class ListCharactersController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $chars = Character::where('user_id', $actor->id)
            ->where('status', '!=', 'archived')
            ->orderBy('name')
            ->get();

        return new JsonResponse(['data' => $chars->map([Present::class, 'character'])->values()->all()]);
    }
}
