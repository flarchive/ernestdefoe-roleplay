import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import SessionDropdown from 'flarum/forum/components/SessionDropdown';
import DiscussionPage from 'flarum/forum/components/DiscussionPage';
import LinkButton from 'flarum/common/components/LinkButton';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import inCharacter from './inCharacter';
import composerPicker from './composerPicker';
import { isRpDiscussion } from './rpTags';

declare const m: any;

/**
 * The combat tracker is a chunk fetched the first time a role-play discussion
 * opens. Until it arrives the sidebar shows the tracker's own loading state.
 */
let CombatTracker: any = null;
let trackerRequested = false;

function combatTracker(): any {
  if (!CombatTracker && !trackerRequested) {
    trackerRequested = true;
    import('./components/CombatTracker').then(
      (mod) => {
        CombatTracker = mod.default;
        m.redraw();
      },
      () => {
        trackerRequested = false;
      }
    );
  }

  return CombatTracker;
}

app.initializers.add('ernestdefoe-roleplay', () => {
  // The pages are chunks fetched on first visit, not part of every page.
  app.routes['rp.characters'] = { path: '/characters', component: () => import('./components/CharactersPage') } as any;
  app.routes['rp.deck'] = { path: '/roleplay/deck', component: () => import('./components/DeckPage') } as any;

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
      LinkButton.component({ href: app.route('rp.deck'), icon: 'fas fa-layer-group' }, app.translator.trans('ernestdefoe-roleplay.forum.my_deck')),
      49
    );
  });

  // The combat tracker lives at the top of a discussion's sidebar — but only in
  // discussions where role-play is enabled (admin-configured tags).
  extend(DiscussionPage.prototype, 'sidebarItems', function (items: any) {
    const discussion = (this as any).discussion;
    if (!discussion || !isRpDiscussion(discussion)) return;
    const Tracker = combatTracker();
    items.add(
      'rp-combat',
      Tracker
        ? Tracker.component({ discussionId: Number(discussion.id()) })
        : m('div.RpTracker', m(LoadingIndicator, { display: 'block', size: 'small' })),
      100
    );
  });

  inCharacter();
  composerPicker();
});
