import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import SessionDropdown from 'flarum/forum/components/SessionDropdown';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import LinkButton from 'flarum/common/components/LinkButton';
import CharactersPage from './components/CharactersPage';
import DeckPage from './components/DeckPage';
import CombatTracker from './components/CombatTracker';
import inCharacter from './inCharacter';
import composerPicker from './composerPicker';

app.initializers.add('ernestdefoe-roleplay', () => {
  app.routes['rp.characters'] = { path: '/characters', component: CharactersPage } as any;
  app.routes['rp.deck'] = { path: '/deck', component: DeckPage } as any;

  // "My Characters" + "My Deck" entries in the account dropdown.
  extend(SessionDropdown.prototype, 'items', function (items: any) {
    if (!app.session.user) return;
    items.add(
      'rp-characters',
      LinkButton.component(
        { href: app.route('rp.characters'), icon: 'fas fa-dragon' },
        app.translator.trans('ernestdefoe-roleplay.forum.my_characters')
      ),
      50
    );
    items.add(
      'rp-deck',
      LinkButton.component(
        { href: app.route('rp.deck'), icon: 'fas fa-layer-group' },
        app.translator.trans('ernestdefoe-roleplay.forum.my_deck')
      ),
      49
    );
  });

  // The combat tracker lives at the top of a discussion's sidebar.
  extend(DiscussionPage.prototype, 'sidebarItems', function (items: any) {
    const discussion = (this as any).discussion;
    if (!discussion) return;
    items.add('rp-combat', CombatTracker.component({ discussionId: Number(discussion.id()) }), 100);
  });

  inCharacter();
  composerPicker();
});
