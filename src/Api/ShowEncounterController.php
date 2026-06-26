<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Models\Encounter;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * GET /api/rp/encounters?discussionId=N — the live (non-ended) encounter for a
 * discussion, or null. Every viewer of the role-play discussion polls/subscribes
 * to this to render the combat tracker.
 */
class ShowEncounterController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $discussionId = (int) Arr::get($request->getQueryParams(), 'discussionId');
        $enc = Encounter::where('discussion_id', $discussionId)
            ->where('status', '!=', 'ended')
            ->latest('id')
            ->first();

        return new JsonResponse(['data' => $enc ? Present::encounter($enc, $actor) : null]);
    }
}
