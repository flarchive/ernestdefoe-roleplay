<?php

namespace Ernestdefoe\Roleplay\Api;

/** Small input-coercion helpers shared across the role-play controllers. */
class Input
{
    /**
     * Clamp a nullable value into the [min, max] range, returning null for blank
     * input so optional numeric fields can be cleared.
     */
    public static function clampInt(mixed $value, int $min, int $max): ?int
    {
        if ($value === null || $value === '') {
            return null;
        }

        return max($min, min($max, (int) $value));
    }
}
