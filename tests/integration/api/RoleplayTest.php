<?php

namespace Ernestdefoe\Roleplay\Tests\integration\api;

use Carbon\Carbon;
use Flarum\Discussion\Discussion;
use Flarum\Post\Post;
use Flarum\Tags\Tag;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use Flarum\User\User;
use PHPUnit\Framework\Attributes\Test;
use Psr\Http\Message\ResponseInterface;

class RoleplayTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->extension('flarum-tags', 'ernestdefoe-roleplay');

        $posts = [];
        for ($n = 1; $n <= 8; $n++) {
            $posts[] = ['id' => $n, 'discussion_id' => 1, 'number' => $n, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Post '.$n.'</p></t>'];
        }
        $posts[] = ['id' => 20, 'discussion_id' => 2, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Secret</p></t>', 'is_private' => 1];
        $posts[] = ['id' => 30, 'discussion_id' => 3, 'number' => 1, 'created_at' => Carbon::now(), 'user_id' => 2, 'type' => 'comment', 'content' => '<t><p>Chat</p></t>'];

        $this->prepareDatabase([
            User::class => [
                $this->normalUser(),
                ['id' => 3, 'username' => 'player', 'email' => 'player@machine.local', 'is_email_confirmed' => 1],
            ],
            Tag::class => [
                ['id' => 1, 'name' => 'Role-play', 'slug' => 'rp', 'position' => 0],
                ['id' => 2, 'name' => 'General', 'slug' => 'general', 'position' => 1],
            ],
            Discussion::class => [
                ['id' => 1, 'title' => 'The tavern', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 1, 'comment_count' => 8],
                ['id' => 2, 'title' => 'Private', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 20, 'comment_count' => 1, 'is_private' => 1],
                ['id' => 3, 'title' => 'Off topic', 'created_at' => Carbon::now(), 'user_id' => 2, 'first_post_id' => 30, 'comment_count' => 1],
            ],
            'discussion_tag' => [
                ['discussion_id' => 1, 'tag_id' => 1],
                ['discussion_id' => 3, 'tag_id' => 2],
            ],
            Post::class => $posts,
            'rp_characters' => [
                ['id' => 1, 'user_id' => 2, 'name' => 'Aria', 'slug' => 'aria', 'status' => 'approved'],
                ['id' => 2, 'user_id' => 3, 'name' => 'Bram', 'slug' => 'bram', 'status' => 'approved'],
                ['id' => 3, 'user_id' => 2, 'name' => 'Old', 'slug' => 'old', 'status' => 'archived'],
            ],
            'rp_cards' => [
                ['id' => 1, 'user_id' => 2, 'name' => 'Slash', 'attack_expr' => '1d20+50', 'damage_expr' => '1d4', 'is_public' => 0],
                ['id' => 2, 'user_id' => 3, 'name' => 'Secret move', 'is_public' => 0],
                ['id' => 3, 'user_id' => 3, 'name' => 'Shared bolt', 'is_public' => 1],
            ],
        ]);
    }

    private function call(string $method, string $path, ?int $actor, array $body = [], array $query = []): ResponseInterface
    {
        $request = $this->request($method, $path, array_filter(['authenticatedAs' => $actor, 'json' => $body ?: null]))->withQueryParams($query);

        // A guest has no session to carry a CSRF token, and the post tests
        // reply several times a second; neither check is what is tested here.
        return $this->send($request->withAttribute('bypassCsrfToken', true)->withAttribute('bypassThrottling', true));
    }

    private function data(ResponseInterface $response): mixed
    {
        return json_decode((string) $response->getBody(), true)['data'] ?? null;
    }

    #[Test]
    public function a_member_sees_and_changes_only_their_own_characters()
    {
        $this->assertSame(401, $this->call('GET', '/api/rp/characters', null)->getStatusCode());

        $this->assertSame(['Aria'], array_column($this->data($this->call('GET', '/api/rp/characters', 2)), 'name'), 'Not Bram, and not the archived one');

        $this->assertSame(404, $this->call('PATCH', '/api/rp/characters/2', 2, ['name' => 'Stolen'])->getStatusCode());
        $this->assertSame(404, $this->call('DELETE', '/api/rp/characters/2', 2)->getStatusCode());
        $this->assertSame('Bram', $this->database()->table('rp_characters')->where('id', 2)->value('name'));

        $created = $this->data($this->call('POST', '/api/rp/characters', 2, ['name' => 'Aria', 'color' => 'red', 'avatarUrl' => 'javascript:alert(1)']));
        $this->assertSame('aria-2', $created['slug'], 'A unique slug');
        $this->assertNull($created['color']);
        $this->assertNull($created['avatarUrl'], 'Only an http(s) avatar');

        $this->assertSame(204, $this->call('DELETE', '/api/rp/characters/1', 2)->getStatusCode());
        $this->assertSame('archived', $this->database()->table('rp_characters')->where('id', 1)->value('status'), 'Archived, so old posts keep their speaker');
    }

    #[Test]
    public function the_deck_is_your_own_cards_and_the_public_ones()
    {
        $this->assertSame(['Shared bolt', 'Slash'], array_column($this->data($this->call('GET', '/api/rp/cards', 2)), 'name'));

        $this->assertSame(404, $this->call('PATCH', '/api/rp/cards/3', 2, ['name' => 'Mine now'])->getStatusCode(), 'Public is not shared ownership');
        $this->assertSame(404, $this->call('DELETE', '/api/rp/cards/2', 2)->getStatusCode());
        $this->assertSame(422, $this->call('POST', '/api/rp/cards', 2, ['name' => 'Bad', 'damageExpr' => '1d6; drop table'])->getStatusCode());
        $this->assertSame(3, $this->database()->table('rp_cards')->count());
    }

    #[Test]
    public function an_encounter_lives_only_where_its_discussion_can_be_seen()
    {
        $this->assertSame(404, $this->call('GET', '/api/rp/encounters', 2, [], ['discussionId' => 2])->getStatusCode(), 'Core hides private discussions from everyone here');
        $this->assertSame(404, $this->call('POST', '/api/rp/encounters', 2, ['discussionId' => 2])->getStatusCode());

        $this->database()->table('rp_encounters')->insert(['id' => 9, 'discussion_id' => 2, 'gm_user_id' => 2, 'status' => 'setup']);
        $this->assertSame(404, $this->call('POST', '/api/rp/encounters/9/join', 2, ['characterId' => 1])->getStatusCode());
        $this->assertSame(404, $this->call('POST', '/api/rp/encounters/9/combatants', 2, ['name' => 'Goblin'])->getStatusCode(), 'Even its own GM, once the discussion is out of reach');
    }

    #[Test]
    public function encounters_run_only_in_role_play_tags()
    {
        $this->setting('ernestdefoe-roleplay.tags', 'rp');

        $this->assertSame(403, $this->call('POST', '/api/rp/encounters', 2, ['discussionId' => 3])->getStatusCode());

        $response = $this->call('POST', '/api/rp/encounters', 2, ['discussionId' => 1, 'name' => 'Ambush']);
        $this->assertSame(200, $response->getStatusCode(), (string) $response->getBody());
        $this->assertTrue($this->data($response)['isGm']);
    }

    #[Test]
    public function only_the_gm_runs_an_encounter_and_players_join_as_their_own_characters()
    {
        $id = $this->data($this->call('POST', '/api/rp/encounters', 2, ['discussionId' => 1]))['id'];

        $this->assertSame(403, $this->call('POST', "/api/rp/encounters/$id/combatants", 3, ['name' => 'Dragon'])->getStatusCode());
        $this->assertSame(403, $this->call('POST', "/api/rp/encounters/$id/start", 3)->getStatusCode());

        $this->assertSame(404, $this->call('POST', "/api/rp/encounters/$id/join", 3, ['characterId' => 1])->getStatusCode(), 'Someone else\'s character');
        $joined = $this->data($this->call('POST', "/api/rp/encounters/$id/join", 3, ['characterId' => 2]));
        $this->assertSame('Bram', $joined['name']);
        $again = $this->data($this->call('POST', "/api/rp/encounters/$id/join", 3, ['characterId' => 2]));
        $this->assertSame($joined['id'], $again['id'], 'Joining twice is one combatant');

        $goblin = $this->data($this->call('POST', "/api/rp/encounters/$id/combatants", 2, ['name' => 'Goblin', 'team' => 'foe', 'maxHp' => 3]));
        $this->assertSame(403, $this->call('DELETE', '/api/rp/combatants/'.$goblin['id'], 3)->getStatusCode());

        $this->assertSame(200, $this->call('POST', "/api/rp/encounters/$id/start", 2)->getStatusCode());
        $this->assertSame(422, $this->call('POST', "/api/rp/encounters/$id/join", 3, ['characterId' => 2])->getStatusCode(), 'Too late once it has started');
    }

    #[Test]
    public function a_card_is_played_by_the_gm_or_by_the_owner_on_their_turn()
    {
        $id = $this->data($this->call('POST', '/api/rp/encounters', 2, ['discussionId' => 1]))['id'];
        $bram = $this->data($this->call('POST', "/api/rp/encounters/$id/join", 3, ['characterId' => 2]))['id'];
        $goblin = $this->data($this->call('POST', "/api/rp/encounters/$id/combatants", 2, ['name' => 'Goblin', 'team' => 'foe', 'maxHp' => 3]))['id'];
        $this->call('POST', "/api/rp/encounters/$id/start", 2);

        // Make it the goblin's turn: Bram's owner may not act now.
        $this->database()->table('rp_encounters')->where('id', $id)->update(['order' => json_encode([$goblin, $bram]), 'turn_index' => 0]);
        $this->assertSame(403, $this->call('POST', "/api/rp/encounters/$id/play", 3, ['actorCombatantId' => $bram, 'cardId' => 3, 'targetCombatantId' => $goblin])->getStatusCode());

        $this->database()->table('rp_encounters')->where('id', $id)->update(['turn_index' => 1]);
        $this->assertSame(404, $this->call('POST', "/api/rp/encounters/$id/play", 3, ['actorCombatantId' => $bram, 'cardId' => 1])->getStatusCode(), 'Not a card Bram\'s owner may use');
        $this->assertSame(200, $this->call('POST', "/api/rp/encounters/$id/play", 3, ['actorCombatantId' => $bram, 'cardId' => 3, 'targetCombatantId' => $goblin])->getStatusCode());

        // The GM can act for anyone, any time.
        $played = $this->data($this->call('POST', "/api/rp/encounters/$id/play", 2, ['actorCombatantId' => $goblin, 'cardId' => 1, 'targetCombatantId' => $bram]));
        $this->assertTrue($played['result']['hit'] ?? false, '1d20+50 always hits');
    }

    #[Test]
    public function a_post_is_written_as_your_own_character_only()
    {
        $reply = fn (int $character) => $this->call('POST', '/api/posts', 2, ['data' => ['type' => 'posts', 'attributes' => ['content' => 'In character', 'characterId' => $character], 'relationships' => ['discussion' => ['data' => ['type' => 'discussions', 'id' => '1']]]]]);

        $mine = $reply(1);
        $this->assertSame(201, $mine->getStatusCode(), (string) $mine->getBody());
        $this->assertSame('Aria', $this->data($mine)['attributes']['rpCharacter']['name'] ?? null);
        $this->assertSame(1, (int) $this->database()->table('rp_characters')->where('id', 1)->value('post_count'));

        $this->assertNull($this->data($reply(2))['attributes']['rpCharacter'], 'Someone else\'s character');
        $this->assertNull($this->data($reply(3))['attributes']['rpCharacter'], 'An archived character');
    }

    #[Test]
    public function the_speakers_of_a_page_of_posts_load_in_one_query()
    {
        $this->app();
        $links = [];
        for ($post = 1; $post <= 8; $post++) {
            $links[] = ['post_id' => $post, 'character_id' => 1 + $post % 2];
        }
        $this->database()->table('rp_post_character')->insert($links);

        // The repeated-query detector fails the request on an N+1.
        $response = $this->call('GET', '/api/posts', 2, [], ['filter' => ['discussion' => 1]]);
        $this->assertSame(200, $response->getStatusCode());
        $names = array_map(fn ($p) => $p['attributes']['rpCharacter']['name'] ?? null, $this->data($response));
        $this->assertSame(['Bram', 'Aria', 'Bram', 'Aria', 'Bram', 'Aria', 'Bram', 'Aria'], $names);
    }
}
