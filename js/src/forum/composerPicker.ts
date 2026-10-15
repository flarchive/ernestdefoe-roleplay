import app from 'flarum/forum/app';
import { extend } from 'flarum/common/extend';
import Select from 'flarum/common/components/Select';
import { RpApi, RpCharacter } from './api';
import { isRpDiscussion } from './rpTags';

declare const m: import('mithril').Static;

// The signed-in member's characters, fetched once per session for the picker.
let cache: RpCharacter[] | null = null;
let loading = false;

function ensureChars() {
  if (cache !== null || loading || !app.session.user) return;
  loading = true;
  RpApi.listCharacters()
    .then((c) => {
      cache = c;
      loading = false;
      m.redraw();
    })
    .catch(() => {
      cache = [];
      loading = false;
    });
}

/**
 * Adds a "Posting as …" picker to the reply composer. Choosing a character sets
 * `characterId` on the submitted post, so the reply is authored in-character.
 * The composer is a lazy-loaded chunk, so we extend it by STRING module path
 * (Flarum resolves it when the chunk loads) rather than by class reference.
 */
export default function composerPicker() {
  const ext = extend as any;

  ext('flarum/forum/components/ReplyComposer', 'headerItems', function (this: any, items: any) {
    if (!app.session.user) return;
    // Only offer the in-character picker where role-play is enabled.
    const discussion = (this.attrs && this.attrs.discussion) || null;
    if (discussion && !isRpDiscussion(discussion)) return;
    ensureChars();
    if (!cache || !cache.length) return;

    const options: Record<string, string> = {
      '': app.translator.trans('ernestdefoe-roleplay.forum.as_yourself') as any,
    };
    cache.forEach((c) => (options[String(c.id)] = c.name));

    items.add(
      'rp-character',
      m('span.RpComposerPicker', { title: app.translator.trans('ernestdefoe-roleplay.forum.posting_as') }, [
        m('i.icon.fas.fa-masks-theater'),
        Select.component({
          options,
          value: this.rpCharacterId ? String(this.rpCharacterId) : '',
          onchange: (v: string) => {
            this.rpCharacterId = v ? parseInt(v, 10) : null;
          },
        }),
      ]),
      10
    );
  });

  ext('flarum/forum/components/ReplyComposer', 'data', function (this: any, data: any) {
    if (this.rpCharacterId) data.characterId = this.rpCharacterId;
  });
}
