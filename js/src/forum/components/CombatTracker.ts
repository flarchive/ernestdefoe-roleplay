import app from 'flarum/forum/app';
import Component from 'flarum/common/Component';
import Button from 'flarum/common/components/Button';
import LoadingIndicator from 'flarum/common/components/LoadingIndicator';
import { RpApi, RpEncounter, RpCombatant, RpCard, RpCharacter, RpPlayResult } from '../api';

declare const m: import('mithril').Static;

const t = (k: string, v?: any) => app.translator.trans(`ernestdefoe-roleplay.forum.${k}`, v);

/**
 * The live combat tracker shown in a role-play discussion's sidebar: the
 * initiative order with HP bars, whose turn it is, and the controls to run the
 * fight (GM) or act on your turn (player). Backed by the /rp/encounters API.
 */
export default class CombatTracker extends Component<{ discussionId: number }> {
  loading = true;
  busy = false;
  enc: RpEncounter | null = null;
  cards: RpCard[] = [];
  characters: RpCharacter[] = [];
  log: RpPlayResult[] = [];
  add = { name: '', team: 'party' as 'party' | 'foe', hp: '10', defense: '' };
  play = { cardId: 0, targetId: 0 };
  joinCharId = 0;
  spawnCardId = 0;
  private pollTimer: any = null;
  private boundChannel: any = null;
  private rtHandler: any = null;

  oninit(vnode: any) {
    super.oninit(vnode);
    this.refresh(true);
    if (app.session.user) {
      RpApi.listCards().then((c) => { this.cards = c; m.redraw(); }).catch(() => {});
      RpApi.listCharacters().then((c) => { this.characters = c; m.redraw(); }).catch(() => {});
    }

    // Live updates: when an action elsewhere touches this discussion's encounter,
    // the server broadcasts `rp.encounter.touched` on flarum/realtime's public
    // channel — bind it and refetch instantly. The poll below is a fallback (for
    // when realtime is absent / the daemon is down), so it runs slowly.
    this.bindRealtime();
    this.pollTimer = setInterval(() => {
      this.bindRealtime(); // (re)bind after a reconnect swaps the channel object
      if (document.hidden || this.busy) return;
      RpApi.showEncounter(this.discussionId)
        .then((e) => {
          if (JSON.stringify(e) !== JSON.stringify(this.enc)) { this.enc = e; m.redraw(); }
        })
        .catch(() => {});
    }, 15000);
  }

  /** Subscribe to flarum/realtime's public channel and bind our event. Realtime
   *  only auto-subscribes logged-in users to their private channel, so we join
   *  the (auth-free) public channel ourselves via the shared Pusher instance.
   *  Idempotent + re-binds after a reconnect swaps the Pusher. No-op without
   *  flarum/realtime. */
  bindRealtime() {
    const pusher = (app as any).websocket;
    if (!pusher || typeof pusher.subscribe !== 'function') return;
    const channel = pusher.subscribe('public');
    if (!channel || channel === this.boundChannel) return;
    this.boundChannel = channel;
    this.rtHandler = (data: any) => {
      if (Number(data?.discussionId) === this.discussionId && !this.busy) this.refresh();
    };
    channel.bind('rp.encounter.touched', this.rtHandler);
  }

  onremove() {
    if (this.pollTimer) clearInterval(this.pollTimer);
    if (this.boundChannel && this.rtHandler) {
      try { this.boundChannel.unbind('rp.encounter.touched', this.rtHandler); } catch (e) { /* channel gone */ }
    }
  }

  get discussionId(): number {
    return this.attrs.discussionId;
  }

  refresh(initial = false) {
    if (initial) this.loading = true;
    RpApi.showEncounter(this.discussionId)
      .then((e) => { this.enc = e; this.loading = false; m.redraw(); })
      .catch(() => { this.loading = false; m.redraw(); });
  }

  act(p: Promise<any>, onEnc?: (e: RpEncounter) => void) {
    this.busy = true;
    p.then((res) => {
      this.busy = false;
      if (onEnc) onEnc(res);
      m.redraw();
    }).catch((e) => {
      this.busy = false;
      app.alerts.show({ type: 'error' }, e.message);
      m.redraw();
    });
  }

  // ── actions ──────────────────────────────────────────────────────
  start() { this.act(RpApi.encounterAction(this.enc!.id, 'start'), (e) => (this.enc = e)); }
  next() { this.act(RpApi.encounterAction(this.enc!.id, 'next'), (e) => (this.enc = e)); }
  end() { if (confirm(t('confirm_end_encounter') as any)) this.act(RpApi.encounterAction(this.enc!.id, 'end'), () => (this.enc = null)); }
  create() { this.act(RpApi.createEncounter(this.discussionId), (e) => (this.enc = e)); }
  remove(c: RpCombatant) { this.act(RpApi.removeCombatant(c.id), () => this.refresh()); }

  addCombatant() {
    if (!this.add.name.trim()) return;
    const data = { name: this.add.name.trim(), team: this.add.team, maxHp: Number(this.add.hp) || 10, defense: this.add.defense === '' ? undefined : Number(this.add.defense) };
    this.act(RpApi.addCombatant(this.enc!.id, data), () => { this.add.name = ''; this.add.defense = ''; this.refresh(); });
  }

  spawnFoe(cardId: number) {
    if (!cardId) return;
    this.act(RpApi.addCombatant(this.enc!.id, { cardId, team: 'foe' }), () => { this.spawnCardId = 0; this.refresh(); });
  }

  join() {
    if (!this.joinCharId) return;
    this.act(RpApi.joinEncounter(this.enc!.id, this.joinCharId), () => { this.joinCharId = 0; this.refresh(); });
  }

  playCard() {
    const e = this.enc!;
    if (!this.play.cardId || !e.activeId) return;
    this.act(
      RpApi.playCard(e.id, { cardId: this.play.cardId, actorCombatantId: e.activeId, targetCombatantId: this.play.targetId || undefined }),
      (res: { result: RpPlayResult; encounter: RpEncounter }) => {
        this.enc = res.encounter;
        this.log.unshift(res.result);
        this.log = this.log.slice(0, 6);
        if (res.result.crit) app.alerts.show({ type: 'success' }, t('crit_toast', { card: res.result.card }));
      }
    );
  }

  /** Once one whole side is down, the fight is decided — surface it. */
  outcome(): 'victory' | 'defeat' | null {
    const e = this.enc;
    if (!e || e.status !== 'active') return null;
    const party = e.combatants.filter((c) => c.team === 'party');
    const foes = e.combatants.filter((c) => c.team === 'foe');
    if (foes.length && foes.every((c) => c.isDown)) return 'victory';
    if (party.length && party.every((c) => c.isDown)) return 'defeat';
    return null;
  }

  // ── view ─────────────────────────────────────────────────────────
  canActNow(): boolean {
    const e = this.enc;
    if (!e || e.status !== 'active' || !e.activeId) return false;
    if (e.isGm) return true;
    const active = e.combatants.find((c) => c.id === e.activeId);
    return !!active?.character && active.character.userId === (app.session.user as any)?.id();
  }

  view() {
    if (this.loading) return m('div.RpTracker', m(LoadingIndicator, { display: 'block', size: 'small' }));

    if (!this.enc) {
      if (!app.session.user) return null;
      return m('div.RpTracker.RpTracker-empty', [
        Button.component({ className: 'Button Button--block', icon: 'fas fa-dice-d20', loading: this.busy, onclick: () => this.create() }, t('start_encounter')),
      ]);
    }

    const e = this.enc;
    const out = this.outcome();
    return m('div.RpTracker', { 'data-status': e.status }, [
      m('div.RpTracker-head', [
        m('span.RpTracker-title', [m('i.icon.fas.fa-dragon'), ' ', e.name || t('encounter')]),
        e.status === 'active' ? m('span.RpTracker-round', t('round_n', { n: e.round })) : m('span.RpTracker-badge', t('status_' + e.status)),
      ]),

      m('ul.RpTracker-list', e.combatants.map((c) => this.combatantRow(c, e))),

      out ? m('div.RpTracker-outcome', { class: 'is-' + out }, [m('i.icon.' + (out === 'victory' ? 'fas fa-trophy' : 'fas fa-skull')), ' ', t('outcome_' + out)]) : null,

      e.status === 'setup' && app.session.user ? this.joinControl() : null,
      e.isGm && e.status === 'setup' ? this.setupControls() : null,
      this.canActNow() ? this.playControls(e) : null,
      e.isGm && e.status === 'active'
        ? m('div.RpTracker-gm', [
            Button.component({ className: 'Button Button--primary Button--block', loading: this.busy, icon: 'fas fa-forward-step', onclick: () => this.next() }, t('next_turn')),
            Button.component({ className: 'Button Button--block RpTracker-end', onclick: () => this.end() }, t('end_encounter')),
          ])
        : null,

      this.log.length ? m('div.RpTracker-log', this.log.map((r) => this.logRow(r))) : null,
    ]);
  }

  combatantRow(c: RpCombatant, e: RpEncounter) {
    const pct = c.maxHp > 0 ? Math.max(0, Math.round((c.hp / c.maxHp) * 100)) : 0;
    const active = e.activeId === c.id;
    const accent = c.character?.color || (c.team === 'foe' ? '#dc2626' : '#2563eb');
    return m('li.RpCombatant', { key: c.id, class: (active ? 'is-active ' : '') + (c.isDown ? 'is-down' : '') }, [
      m('span.RpCombatant-turn', active ? m('i.icon.fas.fa-caret-right') : null),
      c.character?.avatarUrl
        ? m('img.RpCombatant-av', { src: c.character.avatarUrl, alt: '', style: { borderColor: accent } })
        : m('span.RpCombatant-dot', { style: { background: accent } }),
      m('div.RpCombatant-main', [
        m('div.RpCombatant-top', [
          m('span.RpCombatant-name', c.name),
          e.status !== 'setup' ? m('span.RpCombatant-init', c.initiative) : null,
        ]),
        m('div.RpCombatant-hpbar', m('span.RpCombatant-hpfill', { style: { width: pct + '%', background: c.team === 'foe' ? '#dc2626' : '#16a34a' } })),
        m('div.RpCombatant-hptext', c.isDown ? t('down') : `${c.hp}/${c.maxHp} HP`),
      ]),
      e.isGm && e.status === 'setup'
        ? Button.component({ className: 'Button Button--icon Button--flat RpCombatant-rm', icon: 'fas fa-xmark', onclick: () => this.remove(c) })
        : null,
    ]);
  }

  setupControls() {
    const a = this.add;
    return m('div.RpTracker-setup', [
      m('div.RpTracker-addrow', [
        m('input.FormControl', { placeholder: t('combatant_name'), value: a.name, oninput: (ev: any) => (a.name = ev.target.value) }),
      ]),
      m('div.RpTracker-addrow', [
        m('select.FormControl', { value: a.team, onchange: (ev: any) => (a.team = ev.target.value) }, [
          m('option', { value: 'party' }, t('team_party')),
          m('option', { value: 'foe' }, t('team_foe')),
        ]),
        m('input.FormControl', { type: 'number', min: 1, placeholder: t('hp_label'), value: a.hp, oninput: (ev: any) => (a.hp = ev.target.value), title: t('hp_label') }),
        m('input.FormControl', { type: 'number', min: 0, placeholder: t('defense_label'), value: a.defense, oninput: (ev: any) => (a.defense = ev.target.value), title: t('defense_label') }),
      ]),
      Button.component({ className: 'Button Button--block', icon: 'fas fa-user-plus', disabled: !a.name.trim(), loading: this.busy, onclick: () => this.addCombatant() }, t('add_combatant')),
      this.cards.some((c) => c.type === 'enemy' || c.hp != null)
        ? m('select.FormControl', { value: this.spawnCardId, onchange: (ev: any) => this.spawnFoe(Number(ev.target.value)) }, [
            m('option', { value: 0 }, t('spawn_foe_card')),
            this.cards.filter((c) => c.type === 'enemy' || c.hp != null).map((c) => m('option', { value: c.id }, c.name)),
          ])
        : null,
      Button.component({ className: 'Button Button--primary Button--block', icon: 'fas fa-flag-checkered', disabled: this.enc!.combatants.length < 1, loading: this.busy, onclick: () => this.start() }, t('start_combat')),
    ]);
  }

  joinControl() {
    const me = (app.session.user as any)?.id();
    if (!this.characters.length) return null;
    const joined = this.enc!.combatants.some((c) => c.character && c.character.userId === me);
    if (joined) return null;
    return m('div.RpTracker-join', [
      m('select.FormControl', { value: this.joinCharId, onchange: (ev: any) => (this.joinCharId = Number(ev.target.value)) }, [
        m('option', { value: 0 }, t('join_as')),
        this.characters.map((c) => m('option', { value: c.id }, c.name)),
      ]),
      Button.component({ className: 'Button Button--block RpTracker-joinbtn', icon: 'fas fa-hand-fist', disabled: !this.joinCharId, loading: this.busy, onclick: () => this.join() }, t('join_fray')),
    ]);
  }

  playControls(e: RpEncounter) {
    const active = e.combatants.find((c) => c.id === e.activeId);
    return m('div.RpTracker-play', [
      m('div.RpTracker-playwho', t('your_move', { name: active?.name || '' })),
      m('select.FormControl', { value: this.play.cardId, onchange: (ev: any) => (this.play.cardId = Number(ev.target.value)) }, [
        m('option', { value: 0 }, t('pick_card')),
        this.cards.map((c) => m('option', { value: c.id }, c.name)),
      ]),
      m('select.FormControl', { value: this.play.targetId, onchange: (ev: any) => (this.play.targetId = Number(ev.target.value)) }, [
        m('option', { value: 0 }, t('no_target')),
        e.combatants.filter((c) => !c.isDown).map((c) => m('option', { value: c.id }, c.name)),
      ]),
      Button.component({ className: 'Button Button--primary Button--block', icon: 'fas fa-wand-sparkles', disabled: !this.play.cardId, loading: this.busy, onclick: () => this.playCard() }, t('play_card')),
    ]);
  }

  logRow(r: RpPlayResult) {
    const hit = r.hit && r.amount > 0;
    return m('div.RpLog', { class: r.crit ? 'is-crit' : '' }, [
      m('i.icon.' + (r.icon || 'fas fa-bolt')),
      m('span', [
        m('strong', r.actor),
        ' ',
        r.card,
        r.target ? ' → ' + r.target : '',
        hit ? m('span.RpLog-dmg', ` −${r.amount}`) : m('span.RpLog-miss', ' ' + t('miss')),
        r.crit ? m('span.RpLog-crit', ' ' + t('crit')) : '',
      ]),
    ]);
  }
}
