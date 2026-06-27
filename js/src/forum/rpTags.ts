import app from 'flarum/forum/app';

/** Tag slugs where role-play is enabled (empty = every discussion). */
export function rpTagSlugs(): string[] {
  return (app.forum.attribute('rpTags') as string[]) || [];
}

/** Is role-play (picker / deck / encounters) allowed in this discussion? */
export function isRpDiscussion(discussion: any): boolean {
  const slugs = rpTagSlugs();
  if (!slugs.length) return true; // none configured → role-play works everywhere
  if (!discussion || typeof discussion.tags !== 'function') return false;
  const tags = discussion.tags() || [];
  return tags.some((t: any) => t && slugs.includes(t.slug()));
}
