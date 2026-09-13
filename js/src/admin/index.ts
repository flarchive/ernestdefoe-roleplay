import app from 'flarum/admin/app';
import Admin from 'flarum/common/extenders/Admin';

// Settings via Flarum 2's JS Admin extender (the 1.x app.extensionData API is
// removed). A single field: the tag slugs where role-play is enabled. Each
// .setting() takes a FUNCTION returning the field descriptor.
const K = 'ernestdefoe-roleplay';
const t = (k: string) => app.translator.trans(`${K}.admin.${k}`);

export default [
  new Admin().setting(() => ({
    setting: `${K}.tags`,
    type: 'text',
    label: t('tags_label'),
    help: t('tags_help'),
    placeholder: 'role-play, in-character',
  })),
];
