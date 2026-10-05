<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Models\Character;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Database\QueryException;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/rp/characters (create) and PATCH /api/rp/characters/{id} (update). */
class SaveCharacterController implements RequestHandlerInterface
{
    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $body = (array) $request->getParsedBody();
        $id = Arr::get($request->getQueryParams(), 'id');

        $name = trim((string) Arr::get($body, 'name', ''));
        if ($id === null && $name === '') {
            throw new ValidationException(['name' => 'A character name is required.']);
        }

        if ($id !== null) {
            $character = Character::where('user_id', $actor->id)->findOrFail((int) $id);
        } else {
            $character = new Character();
            $character->user_id = $actor->id;
            $character->status = 'approved';
            $character->slug = self::uniqueSlug($name);
        }

        if ($name !== '') {
            $character->name = mb_substr($name, 0, 80);
        }
        if (Arr::has($body, 'color')) {
            $color = (string) Arr::get($body, 'color', '');
            $character->color = preg_match('/^#[0-9a-f]{6}$/i', $color) ? $color : null;
        }
        if (Arr::has($body, 'bio')) {
            $character->bio = mb_substr(trim((string) Arr::get($body, 'bio', '')), 0, 2000) ?: null;
        }
        if (Arr::has($body, 'avatarUrl')) {
            $url = trim((string) Arr::get($body, 'avatarUrl', ''));
            $character->avatar_url = preg_match('#^https?://#i', $url) ? $url : null;
        }

        try {
            $character->save();
        } catch (QueryException $e) {
            // A concurrent create can claim the same slug between uniqueSlug()'s
            // existence check and this insert (UNIQUE violation, SQLSTATE 23000).
            // Only the create path generates a slug, so retry it once with a
            // random suffix; rethrow anything else.
            if ($id !== null || (string) $e->getCode() !== '23000') {
                throw $e;
            }
            $character->slug = (Str::slug($name) ?: 'character').'-'.Str::lower(Str::random(6));
            $character->save();
        }

        return new JsonResponse(['data' => Present::character($character)]);
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'character';
        $slug = $base;

        // Bounded probe: if base, base-2 … base-50 are all taken, fall back to a
        // random suffix instead of looping forever. A residual race between this
        // check and the insert is caught by the QueryException retry in handle().
        for ($n = 2; $n <= 50 && Character::where('slug', $slug)->exists(); $n++) {
            $slug = $base.'-'.$n;
        }
        if (Character::where('slug', $slug)->exists()) {
            $slug = $base.'-'.Str::lower(Str::random(6));
        }

        return $slug;
    }
}
