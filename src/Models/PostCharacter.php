<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Pivot: the character a post was authored as (rp_post_character).
 *
 * @property int $post_id
 * @property int $character_id
 * @property \Carbon\Carbon|null $created_at
 * @property-read Character|null $character
 */
class PostCharacter extends AbstractModel
{
    protected $table = 'rp_post_character';
    protected $primaryKey = 'post_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_id');
    }
}
