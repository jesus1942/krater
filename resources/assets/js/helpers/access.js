/** Permisos del nivel activo, calculados por el servidor en bootstrap. */
export function can(user, permission) {
  if (!user) return false
  if (user.is_total_admin === true) return true
  const level = window.Ls.get('selectedSchoolLevel')
  const permissions = level
    ? (user.permissions_by_level || {})[level] || []
    : user.permissions || []
  return permissions.includes(permission)
}
