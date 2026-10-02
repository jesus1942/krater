/* Ensayo DOM de los componentes Vue reales. Falla ante llamadas no previstas.
 * npm ci --legacy-peer-deps && node tests/smoke/role-ui.cjs
 * No reemplaza la recorrida visual autenticada en staging.
 */
const fs = require('fs')
const path = require('path')
const vm = require('vm')
const assert = require('assert')
const { JSDOM } = require('jsdom')
const dom = new JSDOM('<html><body></body></html>', { url: 'http://suiteena.test' })
global.window = dom.window; global.document = window.document; global.navigator = window.navigator
const Vue = require('vue/dist/vue.common.js')
const Vuex = require('vuex')
const Router = require('vue-router')
const compiler = require('vue-template-compiler')
const babel = require('@babel/core')
Vue.use(require('vue-i18n')); Vue.use(Vuex); Vue.use(Router); Vue.use(require('vuelidate').default)
Vue.config.productionTip = false; Vue.config.devtools = false
const errors = []
Vue.config.errorHandler = error => errors.push(error)
Vue.prototype.$notification = () => {}
const wrapper = { functional: true, render: (h, c) => h('div', c.data, Object.values(c.slots()).flat()) }
const icon = { functional: true, render: h => h('span') }
for (const name of ['sw-transition', 'sw-list', 'sw-card', 'sw-page-header', 'sw-breadcrumb',
  'sw-breadcrumb-item', 'sw-dropdown', 'sw-dropdown-item', 'base-page', 'base-modal', 'base-notification', 'sw-select', 'sw-input-group', 'sw-avatar']) Vue.component(name, wrapper)
Vue.component('sw-button', { functional: true, render: (h, c) => h('button', c.data, c.children) })
Vue.component('sw-input', { props: ['value', 'placeholder'], template: '<input :value="value" :placeholder="placeholder" @input="$emit(\'input\', $event.target.value)" />' })
Vue.component('sw-list-item', { props: ['to', 'title'], template: '<router-link :to="to">{{title}}</router-link>' })
// Si el buscador se monta para estos roles, la llamada prohibida hace fallar el ensayo.
Vue.component('global-search', { created() { window.axios.get('/api/v1/search') }, render: h => h('input') })
Vue.component('save-icon', icon)
const root = path.resolve(__dirname, '../..')
const cache = new Map()
function load(file) {
  file = path.resolve(file)
  if (cache.has(file)) return cache.get(file)
  const source = fs.readFileSync(file, 'utf8')
  if (file.endsWith('.json')) return { __esModule: true, default: JSON.parse(source) }
  const sfc = file.endsWith('.vue') ? compiler.parseComponent(source) : null
  if (sfc) {
    const compiled = compiler.compile(sfc.template.content)
    assert.deepStrictEqual(compiled.errors, [], file + ': template invalido')
  }
  const code = babel.transformSync(sfc ? (sfc.script ? sfc.script.content : 'export default {}') : source, {
    babelrc: false, configFile: false, plugins: ['@babel/plugin-transform-modules-commonjs'],
  }).code
  const module = { exports: {} }
  const localRequire = name => {
    if (name.startsWith('@vue-hero-icons')) return new Proxy({}, { get: () => icon })
    if (name.includes('TheSiteFooter') || name.includes('BaseModal')) return { default: wrapper, __esModule: true }
    if (name.startsWith('.')) {
      let target = path.resolve(path.dirname(file), name)
      if (!path.extname(target)) target += fs.existsSync(target + '.js') ? '.js' : '.vue'
      return load(target)
    }
    return require(name)
  }
  vm.runInThisContext('(function(require,module,exports){' + code + '\n})', { filename: file })(localRequire, module, module.exports)
  if (sfc) module.exports.default.template = sfc.template.content
  cache.set(file, module.exports)
  return module.exports
}
const component = name => load(path.join(root, 'resources/assets/js/views', name + '.vue')).default
const layout = component('layouts/LayoutBasic')
const students = component('students/Index')
const settings = component('settings/SettingsIndex')
const profile = component('settings/UserProfileSetting')
const years = component('academic/AcademicYearsIndex')
const structure = component('academic/AcademicStructureIndex')
const enrollments = component('academic/EnrollmentsIndex')
// El catalogo PHP es la fuente de permisos: el ensayo detecta si falta uno.
const definitions = JSON.parse(require('child_process').execFileSync(process.env.PHP || 'php', ['-r',
  'require "vendor/autoload.php"; echo json_encode(Crater\\Enums\\RoleName::definitions());'], { cwd: root, encoding: 'utf8' }))
async function flush() { for (let i = 0; i < 8; i++) { await new Promise(resolve => setImmediate(resolve)); await Vue.nextTick() } await new Promise(resolve => setTimeout(resolve, 100)); await Vue.nextTick() }
function find(instance, options) {
  if (instance.$options.template === options.template) return instance
  for (const child of instance.$children) { const found = find(child, options); if (found) return found }
}
async function exercise(role, locale) {
  const calls = []; const storage = new Map([['selectedSchoolLevel', '999']])
  window.Ls = { get: k => storage.get(k), set: (k, v) => storage.set(k, String(v)), remove: k => storage.delete(k) }
  const permissions = [...definitions[role === 'registrar' ? 'preceptor' : role].permissions,
    ...(role === 'registrar' ? definitions.preceptor_registrar.permissions : [])]
  const user = { is_total_admin: false, permissions: [], permissions_by_level: { 1: permissions }, avatar: null, name: 'Cuenta ficticia', email: 'ficticio@example.invalid' }
  const year = { id: 1, year: 2026, name: 'Ciclo ficticio', status: 'active' }
  const student = { id: 1, full_name: 'Alumno ficticio', first_name: 'Alumno', last_name: 'Ficticio', status: 'active', school_level_id: 1, can_edit: role === 'registrar', family_members: [] }
  const responses = {
    '/api/v1/me': { user }, '/api/v1/me/settings': { language: locale || 'es' }, '/api/v1/languages': { languages: [{ code: 'es', name: 'Español' }, { code: 'en', name: 'English' }] },
    '/api/v1/school-levels': { levels: [{ id: 1, enabled: true, name: 'Primario' }] },
    '/api/v1/students': { students: { data: [student] }, summary: { total: 1, active: 1, pending: 0 }, can_view_all_levels: false },
    '/api/v1/academic-years': { data: [year] }, '/api/v1/grade-levels': { data: [{ id: 1, name: 'Curso' }] },
    '/api/v1/subjects': { data: [] }, '/api/v1/divisions': { data: [{ id: 1, academic_year_id: 1, name: 'A' }] },
    '/api/v1/enrollments': { data: [], meta: {} },
  }
  if (role === 'registrar') responses['/api/v1/students/placement-options'] = { academic_years: [year], grade_levels: [], divisions: [] }
  window.axios = { get: async url => {
    calls.push(url)
    if (!responses[url]) { const error = new Error(role + ': llamada prohibida ' + url); errors.push(error); throw error }
    return { data: responses[url] }
  } }
  const store = new Vuex.Store({
    getters: { isAppLoaded: () => true, isSidebarOpen: () => false, languages: () => responses['/api/v1/languages'].languages },
    actions: { bootstrap: () => userMutations.SET_DEFAULT_LANGUAGE({}, locale), toggleSidebar: () => {}, fetchLanguages: () => window.axios.get('/api/v1/languages') },
    modules: {
      user: { namespaced: true, state: { currentUser: user }, getters: { currentUser: s => s.currentUser }, actions: { fetchCurrentUser: () => window.axios.get('/api/v1/me'), fetchUserSettings: () => window.axios.get('/api/v1/me/settings') } },
      company: { namespaced: true, getters: { getSelectedCompany: () => ({ id: 1 }) }, actions: { setSelectedCompany: () => {} } },
      auth: { namespaced: true, actions: { logout: () => {} } }, modal: { namespaced: true, actions: { openModal: () => {} } },
    },
  })
  const router = new Router({ mode: 'abstract', routes: [{ path: '/admin', component: layout, children: [
    { path: 'dashboard', component: { created() { window.axios.get('/api/v1/dashboard') }, template: '<div />' } },
    { path: 'students', component: students }, { path: 'settings', component: settings, children: [
      { path: 'user-profile', component: profile },
      { path: 'academic-years', component: years }, { path: 'academic-structure', component: structure }, { path: 'enrollments', component: enrollments },
    ] },
  ] }] })
  router.push('/admin/dashboard')
  // Usa la instancia y mutacion reales: no simula el valor predeterminado del frontend.
  const i18n = load(path.join(root, 'resources/assets/js/plugins/i18n.js')).default
  const userMutations = load(path.join(root, 'resources/assets/js/store/modules/user/mutations.js')).default
  window.i18n = i18n
  assert.equal(i18n.fallbackLocale, 'es')
  const app = new Vue({ store, router, i18n, render: h => h('router-view') }).$mount()
  document.body.append(app.$el); await flush()
  assert.equal(i18n.locale, locale || 'es')
  if (!locale) {
    assert.equal(i18n.t('settings.preferences.select_language'), 'seleccione el idioma')
    assert.equal(i18n.t('validation.required'), 'Se requiere campo')
  }
  assert.equal(router.currentRoute.path, '/admin/students')
  assert.equal(storage.get('selectedSchoolLevel'), '1')
  assert(!calls.includes('/api/v1/dashboard')); assert(!calls.includes('/api/v1/search'))
  const sidebar = find(app, component('layouts/partials/TheSiteSidebar'))
  const links = sidebar.menuGroups.flatMap(group => group.items.map(item => item.route))
  assert.deepStrictEqual(links, ['/admin/students', '/admin/settings'])
  assert.deepStrictEqual(sidebar.menuGroups.flatMap(group => group.items.map(item => item.title)), ['Alumnos', 'Mi perfil'])
  assert(sidebar.$el.textContent.includes('Alumnos') && sidebar.$el.textContent.includes('Mi perfil'))
  assert(!sidebar.$el.textContent.includes('navigation.'))
  const header = find(app, component('layouts/partials/TheSiteHeader'))
  assert.equal(header.settingsTitle, 'Mi perfil')
  assert(header.$el.textContent.includes('Mi perfil'))
  const screen = find(app, students)
  assert.equal(screen.canCreate, role === 'registrar')
  assert(!screen.canRelocate); assert(!calls.includes('/api/v1/students/placement-options'))
  if (role === 'registrar') {
    await screen.openCreate(); await flush()
    assert(screen.$el.textContent.includes('Nuevo alumno'))
    screen.closeForm(); calls.length = 0
    await screen.openEdit(student); await flush()
    assert(!calls.includes('/api/v1/students/placement-options'))
    assert(!screen.$el.textContent.includes('Ciclo lectivo *'))
    assert(!screen.$el.textContent.includes('Estado'))
    screen.closeForm()
  }
  router.push('/admin/settings/user-profile'); await flush()
  assert(find(app, profile) && !find(app, profile).isRequestOnGoing)
  assert.equal(find(app, profile).language.code, locale || 'es')
  const settingsScreen = find(app, settings)
  assert.equal(settingsScreen.settingsTitle, 'Mi perfil')
  for (const anchor of settingsScreen.$el.querySelectorAll('a')) assert(!/settings\.menu_title\.|navigation\./.test(anchor.textContent), anchor.textContent)
  const menu = settingsScreen.visibleMenuItems.map(item => item.link)
  assert(menu.includes('/admin/settings/academic-years')); assert(menu.includes('/admin/settings/academic-structure')); assert(menu.includes('/admin/settings/enrollments'))
  assert(!menu.includes('/admin/settings/backup'))
  for (const [route, options] of [['academic-years', years], ['academic-structure', structure], ['enrollments', enrollments]]) {
    router.push('/admin/settings/' + route); await flush()
    const academic = find(app, options)
    assert(academic && !academic.error, role + ': ' + route)
    assert.equal(academic.$el.querySelectorAll('form').length, 0, role + ': escritura visible ' + route)
    if (route === 'enrollments') { academic.divisionId = 1; await academic.cargarMatriculas(); await flush() }
  }
  assert.deepStrictEqual(errors, [])
  // Tambien verifica todos los items visibles de administracion total.
  assert(!calls.includes('/api/v1/search'))
  responses['/api/v1/search'] = {} // La busqueda solo se habilita al pasar a total admin.
  user.is_total_admin = true
  await Vue.nextTick()
  assert.equal(header.settingsTitle, 'Configuración')
  assert.equal(settingsScreen.settingsTitle, 'Configuración')
  assert(sidebar.$el.textContent.includes('Configuración'))
  assert(!/navigation\./.test(sidebar.$el.textContent))
  for (const item of sidebar.menuGroups.flatMap(group => group.items)) assert(!item.title.startsWith('navigation.'))
  for (const anchor of settingsScreen.$el.querySelectorAll('a')) assert(!/settings\.menu_title\.|navigation\./.test(anchor.textContent), anchor.textContent)
  console.log(role + ' (' + locale + '): inicio, menus, lectura academica y acciones OK; sin llamadas prohibidas')
  app.$destroy(); app.$el.remove()
}
(async () => {
  const i18n = load(path.join(root, 'resources/assets/js/plugins/i18n.js')).default
  assert.equal(i18n.locale, 'es') // Pantallas anteriores al login/bootstrap.
  const login = component('auth/Login')
  const authStore = new Vuex.Store({ modules: { auth: { namespaced: true, actions: { login: () => {} } } } })
  const authRouter = new Router({ mode: 'abstract', routes: [] })
  const loginApp = new Vue({ i18n, store: authStore, router: authRouter, render: h => h(login) }).$mount()
  await flush()
  assert.equal(errors.length, 0)
  assert(loginApp.$el.textContent.includes('Iniciar'))
  assert.equal(loginApp.$el.querySelector('input[name="email"]').placeholder, 'correo@ejemplo.com')
  loginApp.$destroy()
  function verifyCatalog(source, prefix = '') {
    for (const [key, value] of Object.entries(source)) {
      const full = prefix + key
      if (value && typeof value === 'object') verifyCatalog(value, full + '.')
      else assert(i18n.te(full, 'es'), 'Falta traduccion española: ' + full)
    }
  }
  verifyCatalog(require('../../resources/assets/js/plugins/en.json'))
  for (const locale of ['en', 'es', undefined]) for (const role of ['preceptor', 'registrar', 'teacher']) await exercise(role, locale)
})().catch(error => { console.error(error); process.exitCode = 1 })
