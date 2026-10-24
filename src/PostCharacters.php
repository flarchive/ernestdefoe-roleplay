<?php

namespace Ernestdefoe\Roleplay;

use Closure;
use Flarum\Post\Post;
use Illuminate\Database\Eloquent\Collection;

/**
 * The character a post was written as, loaded for a whole page at once.
 *
 * The post endpoints eager-load the link, but posts also arrive included in
 * other resources (a discussion's posts, an excerpt extension's firstPost or
 * lastPost on the discussion list), where reading the relation in the getter
 * was one query per post, and a second per linked character.
 *
 * The getter therefore notes the post and hands the serializer a closure. The
 * serializer resolves closures only after visiting the whole document, so the
 * first one to run loads every noted post's link and character together.
 */
final class PostCharacters
{
    /** @var Post[] */
    private static array $pending = [];

    public static function for(Post $post): array|Closure|null
    {
        if ($post->relationLoaded('rpCharacterLink')) {
            return self::present($post);
        }

        self::$pending[] = $post;

        return function () use ($post): ?array {
            if (self::$pending !== []) {
                $batch = new Collection(self::$pending);
                self::$pending = [];
                $batch->loadMissing('rpCharacterLink.character');
            }

            return self::present($post);
        };
    }

    private static function present(Post $post): ?array
    {
        $c = $post->rpCharacterLink?->character;

        return $c ? [
            'id' => (int) $c->id,
            'name' => $c->name,
            'slug' => $c->slug,
            'avatarUrl' => $c->avatar_url,
            'color' => $c->color,
        ] : null;
    }
}
