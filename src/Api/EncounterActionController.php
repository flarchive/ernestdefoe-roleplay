<?php

namespace Ernestdefoe\Roleplay\Api;

use Carbon\Carbon;
use Ernestdefoe\Roleplay\Game;
use Ernestdefoe\Roleplay\Models\Encounter;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\Post\CommentPost;
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
    public function __construct(private Touch $touch)
    {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $enc = Encounter::findOrFail((int) Arr::get($request->getQueryParams(), 'id'));

        // Prove discussion access before the GM-ownership check.
        Guard::discussion($actor, (int) $enc->discussion_id);

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
                $this->postSummary($enc, $actor, $request);
                break;

            default:
                throw new ValidationException(['action' => 'Unknown encounter action.']);
        }

        $this->touch->encounter($enc);

        return new JsonResponse(['data' => Present::encounter($enc, $actor)]);
    }

    /**
     * Drop a narrative recap into the discussion when an encounter ends, so the
     * fight becomes a permanent part of the thread. Non-fatal — a formatter
     * hiccup must never stop the encounter from ending.
     */
    private function postSummary(Encounter $enc, $actor, ServerRequestInterface $request): void
    {
        try {
            $combatants = $enc->combatants()->with('character')->orderByDesc('initiative')->get();
            $standing = [];
            $down = [];
            foreach ($combatants as $c) {
                $name = $c->character->name ?? $c->name;
                if ($c->status === 'down' || (int) $c->hp <= 0) {
                    $down[] = $name;
                } else {
                    $standing[] = $name . ' (' . max(0, (int) $c->hp) . '/' . (int) $c->max_hp . ' HP)';
                }
            }

            $title = $enc->name ?: 'The encounter';
            $rounds = (int) $enc->round;
            $parts = ['⚔️ ' . $title . ' has ended' . ($rounds > 0 ? ' after ' . $rounds . ' round' . ($rounds === 1 ? '' : 's') : '') . '.'];
            if ($standing) {
                $parts[] = 'Still standing: ' . implode(', ', $standing);
            }
            if ($down) {
                $parts[] = 'Defeated: ' . implode(', ', $down);
            }

            $discussion = Discussion::find($enc->discussion_id);
            if (! $discussion) {
                return;
            }

            $post = new CommentPost();
            $post->user_id = $actor->id;
            $post->discussion_id = $discussion->id;
            $post->ip_address = $request->getAttribute('ipAddress');
            $post->created_at = Carbon::now();
            $post->setContentAttribute(implode("\n\n", $parts), $actor);
            $post->save();

            $discussion->refreshLastPost();
            $discussion->refreshCommentCount();
            $discussion->save();
        } catch (\Throwable $e) {
            // ignore — the encounter still ends cleanly
        }
    }
}
