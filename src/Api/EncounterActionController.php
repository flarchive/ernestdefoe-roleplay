<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Game;
use Ernestdefoe\Roleplay\Models\Encounter;
use Flarum\Api\JsonApi;
use Flarum\Api\Resource\PostResource;
use Flarum\Discussion\Discussion;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Flarum\User\User;
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
    public function __construct(private Touch $touch, private JsonApi $api)
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
    private function postSummary(Encounter $enc, User $actor, ServerRequestInterface $request): void
    {
        try {
            $combatants = $enc->combatants()->with('character')->orderByDesc('initiative')->get();
            $standing = [];
            $down = [];
            foreach ($combatants as $c) {
                $name = $c->character->name ?? $c->name;
                if ($c->is_down || (int) $c->hp <= 0) {
                    $down[] = $name;
                } else {
                    $standing[] = $name.' ('.max(0, (int) $c->hp).'/'.(int) $c->max_hp.' HP)';
                }
            }

            $title = $enc->name ?: 'The encounter';
            $rounds = (int) $enc->round;
            $parts = ['⚔️ '.$title.' has ended'.($rounds > 0 ? ' after '.$rounds.' round'.($rounds === 1 ? '' : 's') : '').'.'];
            if ($standing) {
                $parts[] = 'Still standing: '.implode(', ', $standing);
            }
            if ($down) {
                $parts[] = 'Defeated: '.implode(', ', $down);
            }

            $discussion = Discussion::whereVisibleTo($actor)->find($enc->discussion_id);
            if (! $discussion || ! $actor->can('reply', $discussion)) {
                return; // locked, read-only or no longer reachable: end without a recap
            }

            // Post it the way a reply is posted, as the GM, so approval,
            // events and every other extension's rules apply to it.
            $this->api->forResource(PostResource::class)
                ->forEndpoint('create')
                ->withRequest($request)
                ->process([
                    'data' => [
                        'attributes' => ['content' => implode("\n\n", $parts)],
                        'relationships' => [
                            'discussion' => ['data' => ['type' => 'discussions', 'id' => (string) $discussion->id]],
                        ],
                    ],
                ], [], ['actor' => $actor]);
        } catch (\Throwable $e) {
            // ignore — the encounter still ends cleanly
        }
    }
}
