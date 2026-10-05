<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

/**
 * A role-play character owned by a member. Posts can be authored "as" a
 * character (see rp_post_character); the character carries its own name,
 * avatar and accent colour for in-character display.
 */
class Character extends AbstractModel
{
    protected $table = 'rp_characters';
    protected $casts = ['last_posted_at' => 'datetime', 'post_count' => 'integer'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    public function sheet()
    {
        return $this->hasOne(Sheet::class, 'character_id');
    }
}
