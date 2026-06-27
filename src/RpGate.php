<?php

namespace Ernestdefoe\Roleplay;

use Flarum\Discussion\Discussion;
use Flarum\Settings\SettingsRepositoryInterface;

/**
 * Where is role-play allowed? Admins can restrict the character picker, the deck
 * and combat encounters to discussions carrying one of a chosen set of tags
 * (by slug). An empty setting means role-play works in every discussion.
 */
class RpGate
{
    /** @return string[] configured role-play tag slugs (empty = everywhere) */
    public static function tagSlugs(): array
    {
        $raw = (string) resolve(SettingsRepositoryInterface::class)->get('ernestdefoe-roleplay.tags');

        return array_values(array_filter(array_map('trim', explode(',', $raw))));
    }

    public static function isRpDiscussion(Discussion $discussion): bool
    {
        $slugs = self::tagSlugs();
        if ($slugs === []) {
            return true;
        }

        try {
            return $discussion->tags()->whereIn('slug', $slugs)->exists();
        } catch (\Throwable) {
            // flarum/tags not installed but tags configured → nothing qualifies.
            return false;
        }
    }
}
