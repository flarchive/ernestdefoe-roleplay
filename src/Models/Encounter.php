<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;

/** A turn-based encounter run by a storyteller inside an RP discussion. */
class Encounter extends AbstractModel
{
    protected $table = 'rp_encounters';
    protected $casts = ['order' => 'array'];

    public function combatants()
    {
        return $this->hasMany(Combatant::class, 'encounter_id');
    }
}
