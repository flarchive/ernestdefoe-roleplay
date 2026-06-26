<?php

namespace Ernestdefoe\Roleplay\Api;

use Ernestdefoe\Roleplay\Game;
use Ernestdefoe\Roleplay\Models\Card;
use Flarum\Foundation\ValidationException;
use Flarum\Http\RequestUtil;
use Illuminate\Support\Arr;
use Laminas\Diactoros\Response\JsonResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/** POST /api/rp/cards (create) and PATCH /api/rp/cards/{id} (update). */
class SaveCardController implements RequestHandlerInterface
{
    private const TYPES = ['ability', 'item', 'spell', 'enemy'];

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        $actor = RequestUtil::getActor($request);
        $actor->assertRegistered();

        $body = (array) $request->getParsedBody();
        $id = Arr::get($request->getQueryParams(), 'id');

        $name = trim((string) Arr::get($body, 'name', ''));
        if ($id === null && $name === '') {
            throw new ValidationException(['name' => 'A card name is required.']);
        }

        // Dice expressions must parse (Game::roll returns null on anything invalid).
        foreach (['attackExpr' => 'attack_expr', 'damageExpr' => 'damage_expr'] as $field => $col) {
            if (Arr::has($body, $field)) {
                $expr = trim((string) Arr::get($body, $field, ''));
                if ($expr !== '' && Game::roll($expr) === null) {
                    throw new ValidationException([$field => 'Not a valid dice expression (e.g. "2d6+1").']);
                }
            }
        }

        if ($id !== null) {
            $card = Card::where('user_id', $actor->id)->findOrFail((int) $id);
        } else {
            $card = new Card();
            $card->user_id = $actor->id;
        }

        if ($name !== '') {
            $card->name = mb_substr($name, 0, 80);
        }
        if (Arr::has($body, 'icon')) {
            $icon = trim((string) Arr::get($body, 'icon', ''));
            $card->icon = $icon !== '' ? mb_substr($icon, 0, 60) : null;
        }
        if (Arr::has($body, 'type')) {
            $type = (string) Arr::get($body, 'type', 'ability');
            $card->type = in_array($type, self::TYPES, true) ? $type : 'ability';
        }
        if (Arr::has($body, 'description')) {
            $card->description = mb_substr(trim((string) Arr::get($body, 'description', '')), 0, 1000) ?: null;
        }
        if (Arr::has($body, 'attackExpr')) {
            $expr = strtolower(trim((string) Arr::get($body, 'attackExpr', '')));
            $card->attack_expr = $expr !== '' ? $expr : null;
        }
        if (Arr::has($body, 'damageExpr')) {
            $expr = strtolower(trim((string) Arr::get($body, 'damageExpr', '')));
            $card->damage_expr = $expr !== '' ? $expr : null;
        }
        if (Arr::has($body, 'defense')) {
            $card->defense = self::clampInt(Arr::get($body, 'defense'), 0, 999);
        }
        if (Arr::has($body, 'hp')) {
            $card->hp = self::clampInt(Arr::get($body, 'hp'), 0, 9999);
        }
        if (Arr::has($body, 'cost')) {
            $card->cost = (int) self::clampInt(Arr::get($body, 'cost'), 0, 99);
        }
        if (Arr::has($body, 'isPublic')) {
            $card->is_public = (bool) Arr::get($body, 'isPublic');
        }

        $card->save();

        return new JsonResponse(['data' => Present::card($card, (int) $actor->id)]);
    }

    /** Clamp to a range, or null when blank (so optional numeric fields can clear). */
    private static function clampInt($value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max($min, min($max, (int) $value));
    }
}
