<?php

/*
 * Role-Play for Flarum 2 — play a character, post in-character, and run a
 * card-based tactical game inside discussions. MIT licensed. Companion tables
 * only; no core tables touched.
 */

use Carbon\Carbon;
use Ernestdefoe\Roleplay\Api;
use Ernestdefoe\Roleplay\Models;
use Flarum\Api\Resource\PostResource;
use Flarum\Api\Schema;
use Flarum\Extend;
use Flarum\Post\CommentPost;
use Flarum\Post\Post;

return [
    (new Extend\Frontend('forum'))
        ->js(__DIR__ . '/js/dist/forum.js')
        ->css(__DIR__ . '/less/forum.less')
        ->route('/characters', 'rp.characters')
        ->route('/deck', 'rp.deck'),

    (new Extend\Frontend('admin'))
        ->js(__DIR__ . '/js/dist/admin.js'),

    new Extend\Locales(__DIR__ . '/resources/locale'),

    // Which tags enable role-play (the picker, deck and encounters). Comma-separated
    // slugs on the admin side; exposed to the frontend as an array. Empty = everywhere.
    (new Extend\Settings())
        ->serializeToForum('rpTags', 'ernestdefoe-roleplay.tags', fn ($v) => array_values(array_filter(array_map('trim', explode(',', (string) $v))))),

    (new Extend\Routes('api'))
        ->get('/rp/characters', 'rp.characters.list', Api\ListCharactersController::class)
        ->post('/rp/characters', 'rp.characters.create', Api\SaveCharacterController::class)
        ->patch('/rp/characters/{id}', 'rp.characters.update', Api\SaveCharacterController::class)
        ->delete('/rp/characters/{id}', 'rp.characters.delete', Api\DeleteCharacterController::class)
        ->get('/rp/cards', 'rp.cards.list', Api\ListCardsController::class)
        ->post('/rp/cards', 'rp.cards.create', Api\SaveCardController::class)
        ->patch('/rp/cards/{id}', 'rp.cards.update', Api\SaveCardController::class)
        ->delete('/rp/cards/{id}', 'rp.cards.delete', Api\DeleteCardController::class)
        // Tactical encounters (combat runs inside a discussion). Static segments
        // (combatants/play) win over the {action} placeholder in FastRoute.
        ->get('/rp/encounters', 'rp.enc.show', Api\ShowEncounterController::class)
        ->post('/rp/encounters', 'rp.enc.create', Api\CreateEncounterController::class)
        ->post('/rp/encounters/{id}/combatants', 'rp.enc.add-combatant', Api\AddCombatantController::class)
        ->delete('/rp/combatants/{id}', 'rp.combatant.remove', Api\RemoveCombatantController::class)
        ->post('/rp/encounters/{id}/play', 'rp.enc.play', Api\PlayCardController::class)
        ->post('/rp/encounters/{id}/join', 'rp.enc.join', Api\JoinEncounterController::class)
        ->post('/rp/encounters/{id}/{action}', 'rp.enc.action', Api\EncounterActionController::class),

    // Register the persistent, user-owned entities as JSON:API resource *types*
    // (no endpoints — the custom /api/rp/* controllers own the HTTP surface). This
    // makes characters and cards observable/extendable by other extensions and
    // available as proper relationships, without changing the working API.
    (new Extend\ApiResource(Api\Resource\CharacterResource::class)),
    (new Extend\ApiResource(Api\Resource\CardResource::class)),

    // Post-in-character: link a post to the character it was authored as.
    (new Extend\Model(Post::class))
        ->hasOne('rpCharacterLink', Models\PostCharacter::class, 'post_id', 'id'),

    (new Extend\ApiResource(PostResource::class))
        ->fields(fn () => [
            // Accept the chosen character on creation (the composer sends it). The
            // setter persists the post→character link after save; characterId is
            // virtual (no posts column), so the closure handles the write entirely.
            Schema\Integer::make('characterId')
                ->writableOnCreate()
                ->set(function (Post $post, $value, $context) {
                    $cid = (int) $value;
                    if ($cid <= 0 || $post->exists || ! $post instanceof CommentPost) {
                        return;
                    }
                    $character = Models\Character::where('user_id', $context->getActor()->id)
                        ->where('status', 'approved')
                        ->find($cid);
                    if (! $character) {
                        return;
                    }
                    $post->afterSave(function ($post) use ($character) {
                        if (Models\PostCharacter::find($post->id)) {
                            return;
                        }
                        $link = new Models\PostCharacter();
                        $link->post_id = $post->id;
                        $link->character_id = $character->id;
                        $link->created_at = Carbon::now();
                        $link->save();

                        $character->post_count = (int) $character->post_count + 1;
                        $character->last_posted_at = Carbon::now();
                        $character->save();
                    });
                }),

            // Expose the character a post was authored as (for in-character display).
            Schema\Arr::make('rpCharacter')->get(function (Post $post) {
                $c = $post->rpCharacterLink?->character;

                return $c ? [
                    'id' => (int) $c->id,
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'avatarUrl' => $c->avatar_url,
                    'color' => $c->color,
                ] : null;
            }),
        ])
        ->endpoint(['index', 'show'], fn ($e) => $e->eagerLoad('rpCharacterLink.character')),
];
