import app from 'flarum/forum/app';
import { override } from 'flarum/common/extend';
import CommentPost from 'flarum/forum/components/CommentPost';
import PostUser from 'flarum/forum/components/PostUser';

declare const m: import('mithril').Static;

/** The character a post was authored as, if any. */
function rpChar(post: any): any {
  return post && typeof post.attribute === 'function' ? post.attribute('rpCharacter') : null;
}

function badge(rp: any) {
  return m(
    'span.Avatar.RpPostAvatar',
    { style: { background: rp.color || '#7c3aed' } },
    rp.avatarUrl ? m('img', { src: rp.avatarUrl, alt: '' }) : String(rp.name || '?')[0].toUpperCase()
  );
}

/**
 * "Played by {user}", translatable, without calling app.translator.trans().
 * trans() with a param THROWS inside the PostUser override below — Flarum treats
 * the param as a user model and calls .displayName() on it (confirmed: a string
 * param -> "displayName is not a function", which blanks the post header). So we
 * read the raw locale template and interpolate the (already display) name by hand.
 */
function playedByLabel(user: string): string {
  const entry = (app.translator as any).translations?.['ernestdefoe-roleplay.forum.played_by'];
  const tpl = (typeof entry === 'string' ? entry : entry?.message) || 'Played by {user}';
  return tpl.replace('{user}', user);
}

/**
 * In-character display: when a post was authored as a character, swap the
 * author's avatar and name for the character's — but keep a visible "played by
 * {user}" line so the real author is never hidden (trust + moderation).
 */
export default function inCharacter() {
  override(CommentPost.prototype, 'avatar', function (this: any, original: () => any) {
    const rp = rpChar(this.attrs.post);
    return rp ? badge(rp) : original();
  });

  override(PostUser.prototype, 'view', function (this: any, original: () => any) {
    const post = this.attrs.post;
    const rp = rpChar(post);
    if (!rp) return original();

    const user = typeof post.user === 'function' ? post.user() : null;
    const by = user && typeof user.displayName === 'function' ? user.displayName() : '';
    return m('h3.PostUser-name.RpPostUser', [
      m('i.icon.fas.fa-theater-masks'),
      m('span.RpPostUser-name', { style: { color: rp.color || '#7c3aed' } }, rp.name),
      by ? m('span.RpPostUser-by', playedByLabel(by)) : null,
    ]);
  });
}
