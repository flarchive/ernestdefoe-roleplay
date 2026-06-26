<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Models\Character;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
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

        $character->save();

        return new JsonResponse(['data' => Present::character($character)]);
    }

    private static function uniqueSlug(string $name): string
    {
        $base = Str::slug($name) ?: 'character';
        $slug = $base;
        $n = 1;
        while (Character::where('slug', $slug)->exists()) {
            $slug = $base.'-'.(++$n);
        }

        return $slug;
    }
}
