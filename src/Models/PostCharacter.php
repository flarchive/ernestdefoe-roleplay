<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;

/** Pivot: the character a post was authored as (rp_post_character). */
class PostCharacter extends AbstractModel
{
    protected $table = 'rp_post_character';
    protected $primaryKey = 'post_id';
    public $incrementing = false;
    public $timestamps = false;
    protected $casts = ['created_at' => 'datetime'];

    public function character()
    {
        return $this->belongsTo(Character::class, 'character_id');
    }
}
