import app from 'flarum/forum/app';
import Page from 'flarum/common/components/Page';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import Button from 'flarum/common/components/Button';
import Select from 'flarum/common/components/Select';
import { RpApi, RpCard } from '../api';

declare const m: import('mithril').Static;

type Form = {
  id?: number;
  name: string;
  icon: string;
  type: RpCard['type'];
  description: string;
  attackExpr: string;
  damageExpr: string;
  defense: string;
  hp: string;
  cost: string;
  isPublic: boolean;
};

const BLANK: Form = { name: '', icon: 'fas fa-bolt', type: 'ability', description: '', attackExpr: '', damageExpr: '', defense: '', hp: '', cost: '0', isPublic: false };

/**
 * "My Deck" — the card builder. A member crafts cards (abilities, items, spells,
 * enemies) with dice formulas (e.g. attack "1d20+3", damage "2d6+1") that the
 * tactical encounter engine resolves. Public cards are shared into everyone's deck.
 */
export default class DeckPage extends Page {
  loading = true;
  saving = false;
  cards: RpCard[] = [];
  form: Form = { ...BLANK };

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
    RpApi.listCards()
      .then((c) => {
        this.cards = c;
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
    this.form = { ...BLANK };
  }

  edit(c: RpCard) {
    this.form = {
      id: c.id,
      name: c.name,
      icon: c.icon || 'fas fa-bolt',
      type: c.type,
      description: c.description || '',
      attackExpr: c.attackExpr || '',
      damageExpr: c.damageExpr || '',
      defense: c.defense != null ? String(c.defense) : '',
      hp: c.hp != null ? String(c.hp) : '',
      cost: String(c.cost ?? 0),
      isPublic: c.isPublic,
    };
    window.scrollTo({ top: 0, behavior: 'smooth' });
  }

  save() {
    if (!this.form.name.trim() || this.saving) return;
    this.saving = true;
    const f = this.form;
    RpApi.saveCard(
      {
        name: f.name.trim(),
        icon: f.icon.trim(),
        type: f.type,
        description: f.description,
        attackExpr: f.attackExpr,
        damageExpr: f.damageExpr,
        defense: (f.defense === '' ? null : Number(f.defense)) as any,
        hp: (f.hp === '' ? null : Number(f.hp)) as any,
        cost: Number(f.cost) || 0,
        isPublic: f.isPublic,
      },
      f.id
    )
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

  remove(c: RpCard) {
    if (!confirm(app.translator.trans('ernestdefoe-roleplay.forum.confirm_delete_card', { name: c.name }) as any)) return;
    RpApi.deleteCard(c.id).then(() => this.load());
  }

  view() {
    const t = (k: string, v?: any) => app.translator.trans(`ernestdefoe-roleplay.forum.${k}`, v);
    const mine = this.cards.filter((c) => c.mine);
    const shared = this.cards.filter((c) => !c.mine);

    return m('div.RpPage', [
      m('div.container', [
        m('h2.RpPage-title', [m('i.icon.fas.fa-layer-group'), ' ', t('my_deck')]),
        m('p.RpPage-sub', t('deck_intro')),

        this.formView(t),

        this.loading
          ? m(LoadingIndicator)
          : [
              mine.length ? m('div.RpDeck-grid', mine.map((c) => this.cardTile(c, t))) : m('p.RpPage-empty', t('no_cards')),
              shared.length
                ? [m('h3.RpDeck-heading', t('shared_cards')), m('div.RpDeck-grid', shared.map((c) => this.cardTile(c, t)))]
                : null,
            ],
      ]),
    ]);
  }

  formView(t: any) {
    const f = this.form;
    const typeOptions = { ability: t('type_ability'), item: t('type_item'), spell: t('type_spell'), enemy: t('type_enemy') };

    return m('div.RpCard.RpForm.RpDeck-form', [
      m('h3', f.id ? t('edit_card') : t('new_card')),
      m('div.RpForm-row', [
        m('input.FormControl.RpForm-grow', { placeholder: t('card_name_placeholder'), value: f.name, maxlength: 80, oninput: (e: any) => (f.name = e.target.value) }),
        Select.component({ value: f.type, options: typeOptions, onchange: (v: any) => (f.type = v) }),
      ]),
      m('div.RpForm-row', [
        m('label.RpForm-field', [t('icon_label'), m('input.FormControl', { placeholder: 'fas fa-bolt', value: f.icon, oninput: (e: any) => (f.icon = e.target.value) })]),
        m('label.RpForm-field', [t('cost_label'), m('input.FormControl', { type: 'number', min: 0, max: 99, value: f.cost, oninput: (e: any) => (f.cost = e.target.value) })]),
      ]),
      m('div.RpForm-row', [
        m('label.RpForm-field', [t('attack_label'), m('input.FormControl', { placeholder: '1d20+3', value: f.attackExpr, oninput: (e: any) => (f.attackExpr = e.target.value) })]),
        m('label.RpForm-field', [t('damage_label'), m('input.FormControl', { placeholder: '2d6+1', value: f.damageExpr, oninput: (e: any) => (f.damageExpr = e.target.value) })]),
      ]),
      m('div.RpForm-row', [
        m('label.RpForm-field', [t('defense_label'), m('input.FormControl', { type: 'number', min: 0, value: f.defense, oninput: (e: any) => (f.defense = e.target.value) })]),
        m('label.RpForm-field', [t('hp_label'), m('input.FormControl', { type: 'number', min: 0, value: f.hp, oninput: (e: any) => (f.hp = e.target.value) })]),
      ]),
      m('textarea.FormControl', { placeholder: t('card_desc_placeholder'), rows: 2, value: f.description, oninput: (e: any) => (f.description = e.target.value) }),
      m('label.RpForm-check', [m('input', { type: 'checkbox', checked: f.isPublic, onchange: (e: any) => (f.isPublic = e.target.checked) }), ' ', t('share_card')]),
      m('div.RpForm-actions', [
        Button.component({ className: 'Button Button--primary', loading: this.saving, disabled: !f.name.trim(), onclick: () => this.save() }, f.id ? t('save') : t('create_card')),
        f.id ? Button.component({ className: 'Button', onclick: () => this.reset() }, t('cancel')) : null,
      ]),
    ]);
  }

  cardTile(c: RpCard, t: any) {
    const stat = (icon: string, val: any) => (val != null && val !== '' ? m('span.RpTile-stat', [m('i.icon.' + icon), ' ', val]) : null);
    return m('div.RpTile', { key: c.id, 'data-type': c.type }, [
      m('div.RpTile-head', [
        m('i.RpTile-icon.icon.' + (c.icon || 'fas fa-bolt')),
        c.cost ? m('span.RpTile-cost', c.cost) : null,
      ]),
      m('div.RpTile-name', c.name),
      m('div.RpTile-type', t('type_' + c.type)),
      c.description ? m('div.RpTile-desc', c.description) : null,
      m('div.RpTile-stats', [
        stat('fas fa-dice-d20', c.attackExpr),
        stat('fas fa-burst', c.damageExpr),
        stat('fas fa-shield', c.defense),
        stat('fas fa-heart', c.hp),
      ]),
      c.mine
        ? m('div.RpTile-actions', [
            Button.component({ className: 'Button Button--icon Button--flat', icon: 'fas fa-pen', onclick: () => this.edit(c), title: t('edit') }),
            Button.component({ className: 'Button Button--icon Button--flat', icon: 'fas fa-trash', onclick: () => this.remove(c), title: t('delete') }),
          ])
        : c.isPublic
        ? m('div.RpTile-shared', t('shared'))
        : null,
    ]);
  }
}
