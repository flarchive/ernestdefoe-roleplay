<?php

namespace Ernestdefoe\Roleplay\Forum;

use Flarum\Http\UrlGenerator;
use Laminas\Diactoros\Response\RedirectResponse;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Server\RequestHandlerInterface;

/**
 * The deck page lived at /deck until Flarum 2.0 shipped its own Deck there.
 * Old links and bookmarks land here (only while flarum/deck is disabled) and
 * move on to /roleplay/deck. A temporary redirect, so enabling flarum/deck
 * later is not shadowed by a browser-cached 301.
 */
class RedirectOldDeckPath implements RequestHandlerInterface
{
    public function __construct(
        protected UrlGenerator $url
    ) {
    }

    public function handle(ServerRequestInterface $request): ResponseInterface
    {
        return new RedirectResponse($this->url->to('forum')->route('rp.deck'), 302);
    }
}
