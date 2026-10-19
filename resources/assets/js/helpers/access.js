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

/** Inicio escolar: finanzas o direccion del nivel; el servidor lo calcula en bootstrap. */
export function canSeeSchoolDashboard(user) {
  if (!user) return false
  if (user.is_total_admin === true) return true
  const home = user.school_dashboard || {}
  const level = window.Ls.get('selectedSchoolLevel')
  return Boolean(level ? (home.levels || {})[level] : home.all)
}

/** Inicio util para los permisos del nivel activo. */
export function landingPath(user) {
  if (canSeeSchoolDashboard(user)) return '/admin/dashboard'
  if (can(user, 'students.view_basic')) return '/admin/students'
  if (can(user, 'hr.staff.view') || can(user, 'hr.staff.onboard')) return '/admin/staff'
  if (can(user, 'hr.payroll.view')) return '/admin/payroll'
  return '/admin/settings/user-profile'
}

/** Evita montar una pantalla que inmediatamente pediria endpoints prohibidos. */
export function canVisit(user, path) {
  if (path === '/admin/settings' || path === '/admin/settings/user-profile') return true
  const parts = path.split('/').filter(Boolean)
  const domain = parts[1]
  const creating = parts.includes('create') || parts.includes('edit')
  if (domain === 'settings') {
    const academic = {
      'academic-years': ['academic.year.view'],
      'academic-structure': ['academic.year.view', 'academic.division.view', 'academic.study_plan.view'],
      'enrollments': ['academic.year.view', 'academic.division.view', 'academic.enrollment.view'],
      'audit-logs': ['system.audit.view'], 'data-reconciliation': ['data.reconcile'],
      'school-levels': ['system.school_level.manage'], 'backup': ['system.backup.manage'],
      'course-sections': ['academic.section.view'],
    }
    return (academic[parts[2]] || ['system.settings.manage']).every(permission => can(user, permission))
  }
  if (!domain || domain === 'dashboard') return canSeeSchoolDashboard(user)
  const permission = {
    students: 'students.view_basic',
    customers: creating ? 'finance.invoice.manage' : 'finance.view',
    items: creating ? 'finance.invoice.manage' : 'finance.view',
    invoices: creating ? 'finance.invoice.manage' : 'finance.view',
    estimates: creating ? 'finance.invoice.manage' : 'finance.view',
    payments: creating ? 'finance.payment.manage' : 'finance.view',
    expenses: creating ? 'finance.expense.manage' : 'finance.view',
    staff: ['hr.staff.view', 'hr.staff.onboard'], payroll: 'hr.payroll.view',
    reports: 'finance.report.view',
  }[domain]
  return Boolean(permission && [].concat(permission).some(p => can(user, p)))
}
