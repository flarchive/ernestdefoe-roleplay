<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Models\Card;
use Flarum\Http\RequestUtil;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/rp/cards — the signed-in member's own cards, plus the public cards
 * other members have shared (so a deck can draw on a shared pool).
 */
class ListCardsController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        // Safety cap: this returns the actor's cards plus every public card on the
        // forum, which grows unbounded as the community shares more. Capped for now;
        // TODO: paginate (limit/offset) and split the public library into its own
        // endpoint so the personal deck stays small.
        $cards = Card::where('user_id', $actor->id)
            ->orWhere('is_public', true)
            ->orderBy('name')
            ->limit(200)
            ->get();

        return new JsonResponse(['data' => $cards->map(
            fn (Card $c) => Present::card($c, (int) $actor->id)
        )->values()->all()]);
    }
}
