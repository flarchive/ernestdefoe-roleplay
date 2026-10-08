<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A fighter in an encounter, with live HP, initiative and side.
 *
 * @property int $id
 * @property int $encounter_id
 * @property int|null $character_id
 * @property int|null $card_id
 * @property string $name
 * @property int $max_hp
 * @property int $hp
 * @property int $initiative
 * @property string $team
 * @property bool $is_down
 * @property array<string, int>|null $meta
 * @property-read Encounter|null $encounter
 * @property-read Character|null $character
 */
class Combatant extends AbstractModel
{
    protected $table = 'rp_combatants';
    protected $casts = ['meta' => 'array', 'is_down' => 'boolean'];

    /** @return BelongsTo<Encounter, $this> */
    public function encounter(): BelongsTo
    {
        return $this->belongsTo(Encounter::class, 'encounter_id');
    }

    /** @return BelongsTo<Character, $this> */
    public function character(): BelongsTo
    {
        return $this->belongsTo(Character::class, 'character_id');
    }
}
