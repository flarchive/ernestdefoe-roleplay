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

    // Pass the user MODEL: the translator turns a `user` param into
    // {username} (a string there throws on .displayName()).
    const user = typeof post.user === 'function' ? post.user() : null;
    return m('h3.PostUser-name.RpPostUser', [
      m('i.icon.fas.fa-theater-masks'),
      m('span.RpPostUser-name', { style: { color: rp.color || '#7c3aed' } }, rp.name),
      user ? m('span.RpPostUser-by', app.translator.trans('ernestdefoe-roleplay.forum.played_by', { user })) : null,
    ]);
  });
}
