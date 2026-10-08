<?php

namespace Ernestdefoe\Roleplay\Models;

use Flarum\Database\AbstractModel;
use Flarum\User\User;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasOne;

/**
 * A role-play character owned by a member. Posts can be authored "as" a
 * character (see rp_post_character); the character carries its own name,
 * avatar and accent colour for in-character display.
 *
 * @property int $id
 * @property int $user_id
 * @property string $name
 * @property string $slug
 * @property string|null $avatar_url
 * @property string|null $color
 * @property string|null $bio
 * @property string $status
 * @property int $post_count
 * @property \Carbon\Carbon|null $last_posted_at
 */
class Character extends AbstractModel
{
    protected $table = 'rp_characters';
    protected $casts = ['last_posted_at' => 'datetime', 'post_count' => 'integer'];

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class, 'user_id');
    }

    /** @return HasOne<Sheet, $this> */
    public function sheet(): HasOne
    {
        return $this->hasOne(Sheet::class, 'character_id');
    }
}
