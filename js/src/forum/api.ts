import app from 'flarum/forum/app';

/** Thin fetch wrapper around the extension's /api/rp/* routes (CSRF-aware). */
async function req(method: string, path: string, body?: any): Promise<any> {
  const res = await fetch(app.forum.attribute('apiUrl') + path, {
    method,
    headers: {
      'Content-Type': 'application/json',
      'X-CSRF-Token': (app.session as any).csrfToken,
    },
    credentials: 'same-origin',
    body: body ? JSON.stringify(body) : undefined,
  });
  if (!res.ok) {
    let detail = '';
    try {
      const j = await res.json();
      detail = j?.errors?.[0]?.detail || j?.errors?.[0]?.name || '';
    } catch {
      /* non-JSON error */
    }
    throw new Error(detail || `Request failed (${res.status})`);
  }
  return res.status === 204 ? null : res.json();
}

export interface RpCharacter {
  id: number;
  name: string;
  slug: string;
  avatarUrl: string | null;
  color: string | null;
  bio: string | null;
  status: string;
  postCount: number;
}

export interface RpCard {
  id: number;
  name: string;
  icon: string | null;
  type: 'ability' | 'item' | 'spell' | 'enemy';
  description: string | null;
  attackExpr: string | null;
  damageExpr: string | null;
  defense: number | null;
  hp: number | null;
  cost: number;
  isPublic: boolean;
  mine: boolean;
}

export interface RpCombatant {
  id: number;
  name: string;
  team: 'party' | 'foe';
  maxHp: number;
  hp: number;
  initiative: number;
  isDown: boolean;
  characterId: number | null;
  cardId: number | null;
  meta: { defense?: number; agility?: number };
  character: { name: string; color: string | null; avatarUrl: string | null; userId: number } | null;
}

export interface RpEncounter {
  id: number;
  discussionId: number;
  gmUserId: number;
  isGm: boolean;
  name: string | null;
  status: 'setup' | 'active' | 'ended';
  round: number;
  turnIndex: number;
  order: number[];
  activeId: number | null;
  combatants: RpCombatant[];
}

export interface RpPlayResult {
  actor: string;
  card: string;
  icon: string | null;
  type: string;
  target: string | null;
  attack: { expr: string; dice: number[]; mod: number; total: number } | null;
  hit: boolean;
  crit: boolean;
  damage: { expr: string; dice: number[]; mod: number; total: number } | null;
  amount: number;
  targetHp: number | null;
  targetMaxHp: number | null;
  down: boolean;
}

export const RpApi = {
  listCharacters: (): Promise<RpCharacter[]> => req('GET', '/rp/characters').then((r) => r.data),
  saveCharacter: (data: Partial<RpCharacter>, id?: number): Promise<RpCharacter> =>
    req(id ? 'PATCH' : 'POST', '/rp/characters' + (id ? '/' + id : ''), data).then((r) => r.data),
  deleteCharacter: (id: number): Promise<void> => req('DELETE', '/rp/characters/' + id),

  listCards: (): Promise<RpCard[]> => req('GET', '/rp/cards').then((r) => r.data),
  saveCard: (data: Partial<RpCard>, id?: number): Promise<RpCard> =>
    req(id ? 'PATCH' : 'POST', '/rp/cards' + (id ? '/' + id : ''), data).then((r) => r.data),
  deleteCard: (id: number): Promise<void> => req('DELETE', '/rp/cards/' + id),

  showEncounter: (discussionId: number): Promise<RpEncounter | null> =>
    req('GET', '/rp/encounters?discussionId=' + discussionId).then((r) => r.data),
  createEncounter: (discussionId: number, name?: string): Promise<RpEncounter> =>
    req('POST', '/rp/encounters', { discussionId, name }).then((r) => r.data),
  addCombatant: (encId: number, data: any): Promise<RpCombatant> =>
    req('POST', '/rp/encounters/' + encId + '/combatants', data).then((r) => r.data),
  joinEncounter: (encId: number, characterId: number, maxHp?: number): Promise<RpCombatant> =>
    req('POST', '/rp/encounters/' + encId + '/join', { characterId, maxHp }).then((r) => r.data),
  removeCombatant: (id: number): Promise<void> => req('DELETE', '/rp/combatants/' + id),
  encounterAction: (encId: number, action: 'start' | 'next' | 'end'): Promise<RpEncounter> =>
    req('POST', '/rp/encounters/' + encId + '/' + action).then((r) => r.data),
  playCard: (encId: number, data: { cardId: number; actorCombatantId: number; targetCombatantId?: number }): Promise<{ result: RpPlayResult; encounter: RpEncounter }> =>
    req('POST', '/rp/encounters/' + encId + '/play', data).then((r) => r.data),
};
