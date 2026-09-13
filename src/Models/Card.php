<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;
use Flarum\User\User;

/** A player-built card: an ability, item, spell or enemy with dice formulas. */
class Card extends AbstractModel
{
    protected $table = 'rp_cards';
    protected $casts = ['is_public' => 'boolean'];

    public function user()
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
