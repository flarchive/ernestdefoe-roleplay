<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;

/** A fighter in an encounter, with live HP, initiative and side. */
class Combatant extends AbstractModel
{
    protected $table = 'rp_combatants';
    protected $casts = ['meta' => 'array', 'is_down' => 'boolean'];

    public function encounter()
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }

    public function character()
    {
        return $this->belongsTo(Character::class, 'character_id');
    }
}
