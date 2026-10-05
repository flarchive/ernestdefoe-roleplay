import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import { RpApi, RpCharacter } from '../api';

declare const m: import('mithril').Static;

/**
 * "My Characters" — a member manages their role-play characters: create one
 * (name, accent colour, bio), see their post counts, and archive them.
 */
export default class CharactersPage extends Page {
  loading = true;
  saving = false;
  characters: RpCharacter[] = [];
  form: { id?: number; name: string; color: string; bio: string } = { name: '', color: '#7c3aed', bio: '' };

  oninit(vnode: any) {
    super.oninit(vnode);
    if (!app.session.user) {
      m.route.set(app.route('index'));
      return;
    }
    this.load();
  }

  load() {
    this.loading = true;
    RpApi.listCharacters()
      .then((c) => {
        this.characters = c;
        this.loading = false;
        m.redraw();
      })
      .catch((e) => {
        this.loading = false;
        app.alerts.show({ type: 'error' }, e.message);
        m.redraw();
      });
  }

  reset() {
    this.form = { name: '', color: '#7c3aed', bio: '' };
  }

  edit(c: RpCharacter) {
    this.form = { id: c.id, name: c.name, color: c.color || '#7c3aed', bio: c.bio || '' };
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  save() {
    if (!this.form.name.trim() || this.saving) return;
    this.saving = true;
    RpApi.saveCharacter({ name: this.form.name.trim(), color: this.form.color, bio: this.form.bio }, this.form.id)
      .then(() => {
        this.reset();
        this.saving = false;
        this.load();
      })
      .catch((e) => {
        this.saving = false;
        app.alerts.show({ type: 'error' }, e.message);
        m.redraw();
      });
  }

  remove(c: RpCharacter) {
    if (!confirm(app.translator.trans('ernestdefoe-roleplay.forum.confirm_archive', { name: c.name }) as any)) return;
    RpApi.deleteCharacter(c.id).then(() => this.load());
  }

  view() {
    const t = (k: string, v?: any) => app.translator.trans(`ernestdefoe-roleplay.forum.${k}`, v);

    return m('div.RpPage', [
      m('div.container', [
        m('h2.RpPage-title', [m('i.icon.fas.fa-dragon'), ' ', t('my_characters')]),
        m('p.RpPage-sub', t('characters_intro')),

        m('div.RpCard.RpForm', [
          m('h3', this.form.id ? t('edit_character') : t('new_character')),
          m('input.FormControl', {
            placeholder: t('name_placeholder'),
            value: this.form.name,
            maxlength: 80,
            oninput: (e: any) => (this.form.name = e.target.value),
          }),
          m('div.RpForm-row', [
            m('label.RpForm-color', [
              t('accent'),
              m('input', { type: 'color', value: this.form.color, oninput: (e: any) => (this.form.color = e.target.value) }),
            ]),
          ]),
          m('textarea.FormControl', {
            placeholder: t('bio_placeholder'),
            rows: 3,
            value: this.form.bio,
            oninput: (e: any) => (this.form.bio = e.target.value),
          }),
          m('div.RpForm-actions', [
            Button.component(
              { className: 'Button Button--primary', loading: this.saving, disabled: !this.form.name.trim(), onclick: () => this.save() },
              this.form.id ? t('save') : t('create')
            ),
            this.form.id ? Button.component({ className: 'Button', onclick: () => this.reset() }, t('cancel')) : null,
          ]),
        ]),

        this.loading
          ? m(LoadingIndicator)
          : this.characters.length
          ? m('div.RpCharacterList', this.characters.map((c) => this.characterRow(c, t)))
          : m('p.RpPage-empty', t('no_characters')),
      ]),
    ]);
  }

  characterRow(c: RpCharacter, t: any) {
    const accent = c.color || '#7c3aed';
    return m('div.RpCard.RpCharacter', { key: c.id }, [
      m('span.RpCharacter-badge', { style: { background: accent } }, c.avatarUrl ? m('img', { src: c.avatarUrl, alt: '' }) : m('span', (c.name[0] || '?').toUpperCase())),
      m('div.RpCharacter-main', [
        m('div.RpCharacter-name', c.name),
        m('div.RpCharacter-meta', t('post_count', { count: c.postCount })),
        c.bio ? m('div.RpCharacter-bio', c.bio) : null,
      ]),
      m('div.RpCharacter-actions', [
        Button.component({ className: 'Button Button--icon Button--flat', icon: 'fas fa-pen', onclick: () => this.edit(c), title: t('edit') }),
        Button.component({ className: 'Button Button--icon Button--flat', icon: 'fas fa-box-archive', onclick: () => this.remove(c), title: t('archive') }),
      ]),
    ]);
  }
}
