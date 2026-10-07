import es from '../plugins/es.json'
import { can } from './access'

/** La navegacion institucional conserva sus nombres en español aun con locale en. */
export function navigationLabel(key) {
  return key.split('.').reduce((value, part) => value && value[part], es) || key
}

/** La entrada describe el acceso disponible, sin modificar sus permisos o rutas. */
export function settingsLabel(user) {
  return navigationLabel(can(user, 'system.settings.manage')
    ? 'navigation.settings' : 'navigation.my_profile')
}
