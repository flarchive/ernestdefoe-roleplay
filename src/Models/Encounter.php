<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A turn-based encounter run by a storyteller inside an RP discussion.
 *
 * @property int $id
 * @property int $discussion_id
 * @property int $gm_user_id
 * @property string|null $name
 * @property string $status
 * @property int $round
 * @property int $turn_index
 * @property list<int>|null $order
 */
class Encounter extends AbstractModel
{
    protected $table = 'rp_encounters';
    protected $casts = ['order' => 'array'];

    /** @return HasMany<Combatant, $this> */
    public function combatants(): HasMany
    {
        return $this->hasMany(Combatant::class, 'encounter_id');
    }
}
