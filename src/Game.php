<?php

namespace Ernestdefoe\Roleplay;

use Ernestdefoe\Roleplay\Models\Card;
use Ernestdefoe\Roleplay\Models\Combatant;
use Ernestdefoe\Roleplay\Models\Encounter;

/**
 * The tactical-game engine: dice evaluation, card-play resolution and encounter
 * turn management. Kept pure where possible — the controllers persist HP +
 * encounter state — so it stays simple to reason about and test.
 */
class Game
{
    /** Evaluate a dice expression like "2d6+1". Returns [expr,dice,mod,total] or null if blank/invalid. */
    public static function roll(?string $expr): ?array
    {
        $expr = strtolower(preg_replace('/\s+/', '', (string) $expr));
        if ($expr === '' || ! preg_match('/^(\d*)d(\d+)([+-]\d+)?$/', $expr, $p)) {
            return null;
        }
        $count = $p[1] === '' ? 1 : (int) $p[1];
        $sides = (int) $p[2];
        $mod = ($p[3] ?? '') !== '' ? (int) $p[3] : 0;
        if ($count < 1 || $count > 100 || $sides < 2 || $sides > 1000) {
            return null;
        }
        $dice = [];
        for ($i = 0; $i < $count; $i++) {
            $dice[] = random_int(1, $sides);
        }

        return ['expr' => $expr, 'dice' => $dice, 'mod' => $mod, 'total' => array_sum($dice) + $mod];
    }

    /**
     * Resolve an actor playing a card at an optional target. Rolls the card's
     * attack (a max natural die = a crit → double damage), checks it against the
     * target's defense, rolls damage on a hit and applies it. Returns a
     * structured result for rendering/broadcasting; the caller persists $target.
     */
    public static function play(Combatant $actor, Card $card, ?Combatant $target): array
    {
        $res = [
            'actor' => $actor->name,
            'card' => $card->name,
            'icon' => $card->icon,
            'type' => $card->type,
            'target' => $target?->name,
            'attack' => null,
            'hit' => true,
            'crit' => false,
            'damage' => null,
            'amount' => 0,
            'targetHp' => $target?->hp,
            'targetMaxHp' => $target?->max_hp,
            'down' => (bool) ($target?->is_down),
        ];

        if ($atk = self::roll($card->attack_expr)) {
            $res['attack'] = $atk;
            $sides = (int) (explode('d', $atk['expr'])[1] ?? 0);
            $res['crit'] = $sides >= 4 && in_array($sides, $atk['dice'], true);
            if ($target) {
                $defense = (int) ($target->meta['defense'] ?? 0);
                $res['hit'] = $defense === 0 || $atk['total'] >= $defense;
            }
        }

        if ($res['hit'] && $card->damage_expr && $dmg = self::roll($card->damage_expr)) {
            $amount = $dmg['total'] * ($res['crit'] ? 2 : 1);
            $res['damage'] = $dmg;
            $res['amount'] = $amount;
            if ($target) {
                $target->hp = max(0, (int) $target->hp - $amount);
                $target->is_down = $target->hp <= 0;
                $res['targetHp'] = $target->hp;
                $res['down'] = $target->is_down;
            }
        }

        return $res;
    }

    /** Roll initiative (1d20 + agility) for every combatant, set the order and activate. */
    public static function start(Encounter $enc): void
    {
        $combatants = $enc->combatants()->get();
        foreach ($combatants as $c) {
            $init = self::roll('1d20');
            $c->initiative = ($init['total'] ?? 0) + (int) ($c->meta['agility'] ?? 0);
            $c->save();
        }
        $enc->order = $combatants->sortByDesc('initiative')->pluck('id')->values()->all();
        $enc->status = 'active';
        $enc->round = 1;
        $enc->turn_index = 0;
        $enc->save();
    }

    /** Advance to the next living combatant; wrapping the order starts a new round. */
    public static function nextTurn(Encounter $enc): void
    {
        $order = $enc->order ?: [];
        if (! $order) {
            return;
        }
        $down = Combatant::whereIn('id', $order)->where('is_down', true)->pluck('id')->map(fn ($v) => (int) $v)->all();
        for ($i = 0; $i < count($order); $i++) {
            $enc->turn_index++;
            if ($enc->turn_index >= count($order)) {
                $enc->turn_index = 0;
                $enc->round++;
            }
            if (! in_array((int) $order[$enc->turn_index], $down, true)) {
                break;
            }
        }
        $enc->save();
    }

    /** The id of the combatant whose turn it currently is (or null). */
    public static function activeId(Encounter $enc): ?int
    {
        $order = $enc->order ?: [];

        return isset($order[$enc->turn_index]) ? (int) $order[$enc->turn_index] : null;
    }
}
