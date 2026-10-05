<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;

/** A character's combat sheet: HP, four attributes and an equipped hand. */
class Sheet extends AbstractModel
{
    protected $table = 'rp_sheets';
    protected $casts = ['attributes' => 'array', 'equipped' => 'array'];

    public function character()
    {
        return $this->belongsTo(Character::class, 'character_id');
    }
}
