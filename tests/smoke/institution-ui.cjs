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
  'sw-breadcrumb-item', 'sw-dropdown', 'sw-dropdown-item', 'base-page', 'base-modal', 'base-notification', 'sw-select', 'sw-input-group', 'sw-avatar', 'base-date-picker']) Vue.component(name, wrapper)
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
    if (name.startsWith('@/')) {
      let target = path.join(root, 'resources/assets/js', name.slice(2))
      if (!path.extname(target)) target += fs.existsSync(target + '.js') ? '.js' : '.vue'
      return load(target)
    }
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

window._ = require('lodash')
const levels = [1, 2, 3].map(id => ({ id, name: ['Primario', 'Secundario', 'Terciario'][id - 1], enabled: true }))
const storage = new Map()
window.Ls = { get: k => storage.get(k), set: (k, v) => storage.set(k, String(v)), remove: k => storage.delete(k) }
const calls = []
window.axios = { get: async url => {
  calls.push(url)
  assert.strictEqual(url, '/api/v1/school-levels', 'Petición no prevista / posible 403: ' + url)
  return { data: { levels } }
} }
Vue.prototype.$t = key => key
Vue.prototype.$tc = key => key
async function flush() { for (let i = 0; i < 6; i++) { await new Promise(resolve => setImmediate(resolve)); await Vue.nextTick() } }
const field = load(path.join(root, 'resources/assets/js/components/SchoolLevelField.vue')).default
async function fieldCase(selected, editing) {
  calls.length = 0
  storage.clear(); if (selected) storage.set('selectedSchoolLevel', String(selected))
  const parent = new Vue({ components: { Field: field }, data: () => ({ value: editing ? 2 : null }),
    template: '<Field v-model="value" :editing="' + editing + '" />',
  }).$mount()
  await flush()
  const select = parent.$el.querySelector ? parent.$el.querySelector('select') : null
  if (selected) { assert.strictEqual(select, null); assert.deepStrictEqual(calls, []) }
  else {
    assert(select); assert.strictEqual(select.disabled, editing); assert.strictEqual(select.required, !editing)
    assert.strictEqual(select.options.length, 4)
    if (editing) assert.strictEqual(select.value, '2')
    else { select.value = '3'; select.dispatchEvent(new window.Event('change')); await flush(); assert.strictEqual(parent.value, 3) }
  }
  parent.$destroy()
}
async function main() {
  for (const selected of [null, 1]) for (const editing of [false, true]) await fieldCase(selected, editing)
  storage.clear()
  // Opciones reales de cada formulario: detecta mixins sobreescritos y validación perdida.
  for (const domain of ['items', 'invoices', 'estimates', 'payments', 'expenses']) {
    console.log('Verificando formulario ' + domain)
    const options = component(domain + '/Create')
    const merged = Vue.extend(options).options
    assert.strictEqual(typeof merged.methods.requireRecordLevel, 'function', domain + ': falta validación de nivel')
    assert(merged.components.SchoolLevelField, domain + ': falta selector real')
    const source = fs.readFileSync(path.join(root, 'resources/assets/js/views', domain, 'Create.vue'), 'utf8')
    assert(source.includes('<school-level-field'))
    const data = options.data.call({})
    const record = data.newInvoice || data.newEstimate || data.formData
    const notices = []
    const context = { isEdit: false, showNotification: notice => notices.push(notice) }
    assert.strictEqual(merged.methods.requireRecordLevel.call(context, record), false)
    record.school_level_id = 3
    assert.strictEqual(merged.methods.requireRecordLevel.call(context, record), true)
    context.isEdit = true; record.school_level_id = null
    assert.strictEqual(merged.methods.requireRecordLevel.call(context, record), true, domain + ': histórico sin nivel no debe reclasificarse')
    storage.set('selectedSchoolLevel', '1'); context.isEdit = false
    assert.strictEqual(merged.methods.requireRecordLevel.call(context, record), true)
    storage.clear()
  }
  // Los cuatro informes montan el filtro global y generan URL consolidada/filtrada.
  const store = new Vuex.Store({ modules: { company: { namespaced: true, getters: {
    getSelectedCompany: () => ({ unique_hash: 'ficticia' }),
  } } } })
  for (const name of ['ExpensesReport', 'TaxReport', 'ProfitLossReport', 'SalesReports']) {
    const options = component('reports/' + name)
    const instance = new Vue({ ...options, store }).$mount()
    await flush()
    const select = instance.$el.querySelector('select')
    assert(select, name + ': falta filtro')
    assert.strictEqual(instance.reportLevelId, '')
    const urlProperty = name === 'SalesReports' ? 'customerDateRangeUrl' : 'dateRangeUrl'
    assert(instance[urlProperty].endsWith('school_level_id='))
    select.value = '2'; select.dispatchEvent(new window.Event('change')); await flush()
    assert.strictEqual(String(instance.reportLevelId), '2'); assert(instance[urlProperty].endsWith('school_level_id=2'))
    instance.$destroy()
  }
  const students = component('students/Index')
  assert(students.template.includes('v-if="student.can_edit"'), 'La edición global de alumnos sigue oculta')
  assert(students.components.SchoolLevelField, 'Alta de alumnos sin selector')
  assert.deepStrictEqual(errors, [])
  console.log('UI institución: seis formularios, selector obligatorio/fijo, mixins efectivos y cuatro informes verificados.')
}
main().catch(error => { console.error(error); process.exitCode = 1 })
