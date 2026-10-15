<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A player-built card: an ability, item, spell or enemy with dice formulas.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string|null $icon
 * @property string $type
 * @property string|null $description
 * @property string|null $attack_expr
 * @property string|null $damage_expr
 * @property int|null $defense
 * @property int|null $hp
 * @property int $cost
 * @property bool $is_public
 */
class Card extends AbstractModel
{
    protected $table = 'rp_cards';
    protected $casts = ['is_public' => 'boolean'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }
}
