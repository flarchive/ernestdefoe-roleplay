<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A character's combat sheet: HP, four attributes and an equipped hand.
 *
 * The `attributes` column shares its name with Eloquent's own attribute bag,
 * so read it with getAttribute('attributes'), never as a property.
 *
 * @property int $id
 * @property int $character_id
 * @property int $max_hp
 * @property int $hp
 * @property array<int>|null $equipped
 */
class Sheet extends AbstractModel
{
    protected $table = 'rp_sheets';
    protected $casts = ['attributes' => 'array', 'equipped' => 'array'];

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_id');
    }
}
