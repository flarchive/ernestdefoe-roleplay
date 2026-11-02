<?php

namespace Ernestdefoe\Roleplay\Tests\integration\api;

use Flarum\Http\RouteCollection;
use Flarum\Testing\integration\RetrievesAuthorizedUsers;
use Flarum\Testing\integration\TestCase;
use PHPUnit\Framework\Attributes\Test;

/**
 * Flarum 2.0 ships flarum/deck at /deck. Role-Play's deck page lives at
 * /roleplay/deck so both can be enabled; two routes on one path 500 every
 * request. Old /deck links redirect only where flarum/deck does not own it.
 */
class DeckRouteTest extends TestCase
{
    use RetrievesAuthorizedUsers;

    protected function setUp(): void
    {
        parent::setUp();

        $this->prepareDatabase(['users' => [$this->normalUser()]]);
    }

    private function forumRoutes(): RouteCollection
    {
        return $this->app()->getContainer()->make('flarum.forum.routes');
    }

    #[Test]
    public function the_deck_page_lives_at_roleplay_deck()
    {
        $this->extension('flarum-tags', 'ernestdefoe-roleplay');

        $this->assertSame('/roleplay/deck', $this->forumRoutes()->getPath('rp.deck'));
    }

    #[Test]
    public function old_deck_links_redirect_when_flarum_deck_is_not_enabled()
    {
        $this->extension('flarum-tags', 'ernestdefoe-roleplay');

        $response = $this->send($this->request('GET', '/deck', ['authenticatedAs' => 2]));

        $this->assertEquals(302, $response->getStatusCode());
        $this->assertStringEndsWith('/roleplay/deck', $response->getHeaderLine('Location'));
    }

    #[Test]
    public function both_deck_pages_coexist_with_flarum_deck()
    {
        $this->extension('flarum-tags', 'flarum-deck', 'ernestdefoe-roleplay');

        $routes = $this->forumRoutes();

        // Building the dispatcher's route table is what threw "Cannot register
        // two routes matching /deck" and 500'd every request.
        $this->assertNotEmpty($routes->getRouteData());
        $this->assertSame('/deck', $routes->getPath('deck'));
        $this->assertSame('/roleplay/deck', $routes->getPath('rp.deck'));
        // flarum/deck owns /deck, so the legacy redirect stays out of its way.
        $this->assertArrayNotHasKey('rp.deck.legacy', $routes->getRoutes());
    }
}
