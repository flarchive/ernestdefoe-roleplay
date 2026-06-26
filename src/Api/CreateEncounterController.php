<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Models\Encounter;
use Flarum\Discussion\Discussion;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * POST /api/rp/encounters — start a tactical encounter in a discussion. The
 * creator becomes its storyteller (GM). One live encounter per discussion: if
 * one already exists it's returned as-is rather than duplicated.
 */
class CreateEncounterController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $body = (array) $request->getParsedBody();
        $discussionId = (int) Arr::get($body, 'discussionId');

        // Must be a discussion the actor can actually see.
        Discussion::whereVisibleTo($actor)->findOrFail($discussionId);

        $existing = Encounter::where('discussion_id', $discussionId)->where('status', '!=', 'ended')->first();
        if ($existing) {
            return new JsonResponse(['data' => Present::encounter($existing, $actor)]);
        }

        $enc = new Encounter();
        $enc->discussion_id = $discussionId;
        $enc->gm_user_id = $actor->id;
        $enc->name = mb_substr(trim((string) Arr::get($body, 'name', '')), 0, 80) ?: null;
        $enc->status = 'setup';
        $enc->save();

        return new JsonResponse(['data' => Present::encounter($enc, $actor)]);
    }
}
