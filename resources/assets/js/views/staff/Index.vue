<template>
  <base-page class="staff-page">
    <sw-page-header title="Personal">
      <sw-breadcrumb slot="breadcrumbs">
        <sw-breadcrumb-item title="Inicio" to="/admin/dashboard" />
        <sw-breadcrumb-item title="Personal" active />
      </sw-breadcrumb>
      <template slot="actions">
        <sw-button v-if="canOnboard" variant="primary" @click="startNew">Nueva persona</sw-button>
      </template>
    </sw-page-header>

    <div class="mt-6 space-y-6">
      <!-- Alta unificada: datos, cargos, docencia y acceso -->
      <sw-card v-if="creating" variant="setting-card">
        <template slot="header"><h6 class="sw-section-title">Alta de personal</h6></template>
        <form class="space-y-6" @submit.prevent="createMember">
          <member-fields v-model="newMember" />

          <section>
            <h3 class="section-title">Cargos</h3>
            <p class="hint">Cada cargo es un rol con su alcance. Los permisos salen solo del cargo.</p>
            <cargo-fields v-for="(cargo, i) in newCargos" :key="'c' + i" v-model="newCargos[i]" :options="options" removable @remove="newCargos.splice(i, 1)" />
            <button type="button" class="link" @click="newCargos.push(blankCargo())">+ Agregar cargo</button>
          </section>

          <section>
            <h3 class="section-title">Asignaciones docentes</h3>
            <p class="hint">Materia y curso que dicta. El docente ve solo a los alumnos de esos cursos.</p>
            <teaching-fields v-for="(t, i) in newTeaching" :key="'t' + i" v-model="newTeaching[i]" :options="options" removable @remove="newTeaching.splice(i, 1)" />
            <button type="button" class="link" @click="newTeaching.push(blankTeaching())">+ Agregar materia</button>
          </section>

          <section>
            <h3 class="section-title">Acceso</h3>
            <label class="flex items-center gap-2 text-sm"><input v-model="newAccess.enabled" type="checkbox" /> Habilitar acceso al sistema</label>
            <div v-if="newAccess.enabled" class="grid gap-4 mt-3 md:grid-cols-2">
              <label class="field"><span>Correo de ingreso *</span><input v-model.trim="newAccess.email" type="email" class="input" :placeholder="newMember.email" /></label>
              <label class="field"><span>Contraseña inicial * (debe cambiarla al ingresar)</span><input v-model="newAccess.password" type="password" minlength="8" autocomplete="new-password" class="input" /></label>
            </div>
          </section>

          <p v-if="formError" class="error-box">{{ formError }}</p>
          <div class="flex flex-wrap justify-end gap-3">
            <sw-button type="button" variant="gray" @click="creating = false">Cancelar</sw-button>
            <sw-button type="submit" variant="primary" :loading="saving">Dar de alta</sw-button>
          </div>
        </form>
      </sw-card>

      <!-- Ficha de la persona -->
      <sw-card v-if="file" variant="setting-card">
        <template slot="header">
          <div class="flex items-center justify-between gap-3">
            <h6 class="sw-section-title">{{ file.member.full_name }}</h6>
            <sw-button size="sm" variant="gray" @click="file = null">Cerrar ficha</sw-button>
          </div>
        </template>
        <div class="space-y-6">
          <section>
            <h3 class="section-title">Datos</h3>
            <p class="text-sm text-gray-600">
              {{ file.member.document_type || 'Documento' }} {{ file.member.document_number || 'sin cargar' }}
              · {{ file.member.email || 'sin correo' }} · {{ file.member.phone || 'sin teléfono' }}
            </p>
            <button v-if="canManageHr" type="button" class="link" @click="editMember(file.member)">Editar datos</button>
          </section>

          <section>
            <h3 class="section-title">Cargos</h3>
            <p v-if="!file.cargos.length" class="hint">Sin cargos.</p>
            <div v-for="c in file.cargos" :key="c.id" class="row">
              <div>
                <strong>{{ c.role || c.position_title }}</strong>
                <span class="hint"> · {{ c.school_level || 'Institucional' }}<template v-if="c.division"> · {{ c.division }}</template>
                  <template v-if="c.weekly_hours"> · {{ c.weekly_hours }} h/sem</template></span>
              </div>
              <div class="flex items-center gap-3">
                <span class="text-xs" :class="c.active ? 'text-green-600' : 'text-gray-400'">{{ c.active ? (c.role && !c.granted ? 'Vigente, sin acceso' : 'Vigente') : 'Finalizado' }}</span>
                <button v-if="canOnboard && c.active && c.role_id" type="button" class="link" @click="act(`cargos/${c.id}/end`, 'Cargo finalizado.')">Finalizar</button>
              </div>
            </div>
            <div v-if="canOnboard" class="mt-3">
              <cargo-fields v-model="extraCargo" :options="options" />
              <sw-button size="sm" variant="primary-outline" @click="addTo('cargos', extraCargo, 'Cargo agregado.')">Agregar cargo</sw-button>
            </div>
          </section>

          <section>
            <h3 class="section-title">Asignaciones docentes</h3>
            <p v-if="!file.teaching.length" class="hint">Sin asignaciones docentes.</p>
            <div v-for="t in file.teaching" :key="t.id" class="row">
              <div>
                <strong>{{ t.subject }}</strong>
                <span class="hint"> · {{ t.course }}<template v-if="t.weekly_hours"> · {{ t.weekly_hours }} h/sem</template>
                  · desde {{ t.starts_on || '-' }}<template v-if="t.ends_on"> hasta {{ t.ends_on }}</template></span>
              </div>
              <div class="flex items-center gap-3">
                <span class="text-xs" :class="t.revoked_at ? 'text-gray-400' : 'text-green-600'">{{ t.revoked_at ? 'Revocada' : 'Vigente' }}</span>
                <button v-if="canOnboard && !t.revoked_at" type="button" class="link" @click="act(`teaching/${t.id}/revoke`, 'Asignación revocada; el acceso a ese curso se retiró.')">Revocar</button>
              </div>
            </div>
            <div v-if="canOnboard" class="mt-3">
              <teaching-fields v-model="extraTeaching" :options="options" />
              <sw-button size="sm" variant="primary-outline" @click="addTo('teaching', extraTeaching, 'Asignación agregada.')">Agregar materia</sw-button>
            </div>
          </section>

          <section>
            <h3 class="section-title">Acceso</h3>
            <template v-if="file.access && file.access.is_active">
              <p class="text-sm text-gray-600">Ingresa con {{ file.access.email }}<template v-if="file.access.must_change_password"> · debe cambiar la contraseña inicial</template>.</p>
              <sw-button v-if="canOnboard" size="sm" variant="gray" class="mt-2" @click="act('access/disable', 'Acceso desactivado.')">Desactivar acceso</sw-button>
            </template>
            <template v-else-if="canOnboard">
              <p class="hint">{{ file.access ? 'El acceso está desactivado.' : 'Sin acceso al sistema.' }}</p>
              <div class="grid gap-4 mt-2 md:grid-cols-2">
                <label v-if="!file.access" class="field"><span>Correo de ingreso</span><input v-model.trim="accessForm.email" type="email" class="input" :placeholder="file.member.email" /></label>
                <label class="field"><span>Contraseña inicial{{ file.access ? ' (opcional)' : ' *' }}</span><input v-model="accessForm.password" type="password" minlength="8" autocomplete="new-password" class="input" /></label>
              </div>
              <sw-button size="sm" variant="primary-outline" class="mt-2" @click="enableAccess">Habilitar acceso</sw-button>
            </template>
            <p v-else class="hint">Sin acceso al sistema.</p>
          </section>
          <p v-if="formError" class="error-box">{{ formError }}</p>
        </div>
      </sw-card>

      <!-- Edicion de datos personales (RR.HH.) -->
      <sw-card v-if="editing" variant="setting-card">
        <template slot="header"><h6 class="sw-section-title">Editar datos</h6></template>
        <form class="space-y-5" @submit.prevent="saveMember">
          <member-fields v-model="form" with-status />
          <div class="flex flex-wrap justify-end gap-3">
            <sw-button type="button" variant="gray" @click="editing = false">Cancelar</sw-button>
            <sw-button type="submit" variant="primary" :loading="saving">Guardar</sw-button>
          </div>
        </form>
      </sw-card>

      <div v-if="loading" class="py-12 text-center text-gray-500">Cargando personal…</div>
      <div v-else-if="!members.length" class="p-8 text-center bg-white border rounded-lg text-gray-500">Todavía no hay personal cargado.</div>
      <div v-else class="grid gap-4 lg:grid-cols-2">
        <article v-for="member in members" :key="member.id" class="p-5 bg-white border rounded-lg shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div>
              <h3 class="text-lg font-semibold">{{ member.full_name }}</h3>
              <p class="text-sm text-gray-500">{{ member.document_type || 'Documento' }} {{ member.document_number || 'sin cargar' }}</p>
            </div>
            <sw-button size="sm" variant="primary-outline" @click="openFile(member)">Ver ficha</sw-button>
          </div>
          <div class="flex flex-wrap gap-2 mt-3 text-xs">
            <span class="badge">{{ statusLabel(member.employment_status) }}</span>
            <span class="badge">{{ member.user_id ? 'Con usuario' : 'Sin usuario' }}</span>
          </div>
          <p v-for="a in (member.assignments || []).filter(x => x.active)" :key="a.id" class="mt-2 text-sm text-gray-600">
            {{ a.position_title }} · {{ a.school_level ? a.school_level.name : 'Institucional' }}
          </p>
        </article>
      </div>
    </div>
  </base-page>
</template>

<script>
import { mapActions } from 'vuex'
import { can } from '../../helpers/access'

const MemberFields = {
  props: ['value', 'withStatus'],
  methods: { set(key, val) { this.$emit('input', { ...this.value, [key]: val }) } },
  template: `<div class="grid gap-4 md:grid-cols-2">
    <label class="field"><span>Nombre *</span><input :value="value.first_name" required class="input" @input="set('first_name', $event.target.value)" /></label>
    <label class="field"><span>Apellido *</span><input :value="value.last_name" required class="input" @input="set('last_name', $event.target.value)" /></label>
    <label class="field"><span>Tipo de documento</span><select :value="value.document_type" class="input" @input="set('document_type', $event.target.value)"><option value="DNI">DNI</option><option value="CUIL">CUIL</option><option value="PASSPORT">Pasaporte</option></select></label>
    <label class="field"><span>Número de documento</span><input :value="value.document_number" class="input" @input="set('document_number', $event.target.value)" /></label>
    <label class="field"><span>Correo</span><input :value="value.email" type="email" class="input" @input="set('email', $event.target.value)" /></label>
    <label class="field"><span>Teléfono</span><input :value="value.phone" class="input" @input="set('phone', $event.target.value)" /></label>
    <label class="field"><span>Ingreso a la institución</span><input :value="value.hire_date" type="date" class="input" @input="set('hire_date', $event.target.value)" /></label>
    <label v-if="withStatus" class="field"><span>Estado *</span><select :value="value.employment_status" class="input" @input="set('employment_status', $event.target.value)"><option value="active">Activo</option><option value="leave">Licencia</option><option value="inactive">Inactivo</option><option value="terminated">Baja</option></select></label>
  </div>`,
}

const CargoFields = {
  props: ['value', 'options', 'removable'],
  computed: {
    role() { return (this.options.roles || []).find(r => r.id === this.value.role_id) || null },
    divisions() { return (this.options.divisions || []).filter(d => d.school_level_id === this.value.school_level_id) },
  },
  methods: { set(key, val) { this.$emit('input', { ...this.value, [key]: val, ...(key === 'school_level_id' || key === 'role_id' ? { division_id: null } : {}) }) } },
  template: `<div class="grid gap-3 p-3 mb-2 border rounded md:grid-cols-4 bg-gray-50">
    <label class="field"><span>Rol</span><select :value="value.role_id" class="input" @input="set('role_id', Number($event.target.value) || null)"><option :value="null">Elegir</option><option v-for="r in options.roles" :key="r.id" :value="r.id">{{ r.label }}</option></select></label>
    <label v-if="!role || role.scope_type !== 'global'" class="field"><span>Nivel</span><select :value="value.school_level_id" class="input" @input="set('school_level_id', Number($event.target.value) || null)"><option :value="null">Elegir</option><option v-for="l in options.levels" :key="l.id" :value="l.id">{{ l.name }}</option></select></label>
    <label v-if="role && role.scope_type === 'division'" class="field"><span>División</span><select :value="value.division_id" class="input" @input="set('division_id', Number($event.target.value) || null)"><option :value="null">Elegir</option><option v-for="d in divisions" :key="d.id" :value="d.id">{{ (d.grade_level ? d.grade_level.name + ' ' : '') + d.name }}</option></select></label>
    <label class="field"><span>Horas semanales</span><input :value="value.weekly_hours" type="number" min="0" max="168" step="0.5" class="input" @input="set('weekly_hours', $event.target.value || null)" /></label>
    <button v-if="removable" type="button" class="self-end text-sm text-red-600" @click="$emit('remove')">Quitar</button>
  </div>`,
}

const TeachingFields = {
  props: ['value', 'options', 'removable'],
  methods: {
    set(key, val) { this.$emit('input', { ...this.value, [key]: val }) },
    label(s) { const d = s.division || {}; return (s.subject ? s.subject.name : 'Materia') + ' · ' + ((d.grade_level ? d.grade_level.name + ' ' : '') + (d.name || '')) },
  },
  template: `<div class="grid gap-3 p-3 mb-2 border rounded md:grid-cols-4 bg-gray-50">
    <label class="field md:col-span-2"><span>Materia y curso</span><select :value="value.course_section_id" class="input" @input="set('course_section_id', Number($event.target.value) || null)"><option :value="null">Elegir</option><option v-for="s in options.sections" :key="s.id" :value="s.id">{{ label(s) }}</option></select></label>
    <label class="field"><span>Horas semanales</span><input :value="value.weekly_hours" type="number" min="0" max="168" step="0.5" class="input" @input="set('weekly_hours', $event.target.value || null)" /></label>
    <label class="field"><span>Desde</span><input :value="value.starts_on" type="date" class="input" @input="set('starts_on', $event.target.value || null)" /></label>
    <label class="field"><span>Hasta</span><input :value="value.ends_on" type="date" class="input" @input="set('ends_on', $event.target.value || null)" /></label>
    <button v-if="removable" type="button" class="self-end text-sm text-red-600" @click="$emit('remove')">Quitar</button>
  </div>`,
}

export default {
  components: { MemberFields, CargoFields, TeachingFields },
  data() {
    return {
      members: [], loading: true, saving: false, creating: false, editing: false, formError: '',
      options: { roles: [], levels: [], divisions: [], sections: [] },
      newMember: {}, newCargos: [], newTeaching: [], newAccess: {},
      file: null, extraCargo: {}, extraTeaching: {}, accessForm: {}, form: {},
    }
  },
  computed: {
    user() { return this.$store.state.user.currentUser },
    canOnboard() { return can(this.user, 'hr.staff.onboard') },
    canManageHr() { return can(this.user, 'hr.staff.manage') },
  },
  async created() {
    await this.load()
    if (this.canOnboard) {
      const { data } = await window.axios.get('/api/v1/staff/onboarding-options')
      this.options = data
    }
  },
  methods: {
    ...mapActions('notification', ['showNotification']),
    blankMember() { return { document_type: 'DNI', document_number: '', first_name: '', last_name: '', email: '', phone: '', hire_date: '' } },
    blankCargo() { return { role_id: null, school_level_id: this.options.levels.length === 1 ? this.options.levels[0].id : null, division_id: null, weekly_hours: null } },
    blankTeaching() { return { course_section_id: null, weekly_hours: null, starts_on: null, ends_on: null } },
    clean(obj) { const p = { ...obj }; Object.keys(p).forEach(k => { if (p[k] === '') p[k] = null }); return p },
    messageOf(error, fallback) {
      const data = error.response && error.response.data
      if (data && data.errors) { const first = Object.values(data.errors)[0]; if (first && first.length) return first[0] }
      return (data && (data.message || (data.error === 'forbidden' && 'No tenés permiso para esta operación en este nivel.'))) || fallback
    },
    async load() {
      this.loading = true
      try {
        if (can(this.user, 'hr.staff.view')) {
          const r = await window.axios.get('/api/v1/staff')
          this.members = r.data.data
        }
      } finally { this.loading = false }
    },
    startNew() {
      this.file = null; this.editing = false; this.formError = ''
      this.newMember = this.blankMember(); this.newCargos = []; this.newTeaching = []; this.newAccess = { enabled: false, email: '', password: '' }
      this.creating = true; window.scrollTo(0, 0)
    },
    async createMember() {
      this.saving = true; this.formError = ''
      try {
        const payload = { ...this.clean(this.newMember),
          cargos: this.newCargos.filter(c => c.role_id).map(this.clean),
          teaching: this.newTeaching.filter(t => t.course_section_id).map(this.clean),
          access: this.newAccess.enabled ? this.clean({ ...this.newAccess, email: this.newAccess.email || this.newMember.email }) : { enabled: false } }
        const { data } = await window.axios.post('/api/v1/staff/onboard', payload)
        this.creating = false
        this.showNotification({ type: 'success', message: 'Alta registrada.' })
        await this.load()
        this.showFile(data)
      } catch (error) { this.formError = this.messageOf(error, 'No se pudo dar de alta.') } finally { this.saving = false }
    },
    showFile(data) {
      this.file = data; this.extraCargo = this.blankCargo(); this.extraTeaching = this.blankTeaching(); this.accessForm = { email: '', password: '' }; this.formError = ''
    },
    async openFile(member) {
      this.creating = false; this.editing = false
      try {
        const { data } = await window.axios.get(`/api/v1/staff/${member.id}/file`)
        this.showFile(data); window.scrollTo(0, 0)
      } catch (error) { this.showNotification({ type: 'error', message: this.messageOf(error, 'No se pudo abrir la ficha.') }) }
    },
    async request(method, path, payload, message) {
      this.formError = ''
      try {
        const { data } = await window.axios[method](`/api/v1/staff/${this.file.member.id}/${path}`, payload)
        this.showFile(data)
        this.showNotification({ type: 'success', message })
        await this.load()
      } catch (error) { this.formError = this.messageOf(error, 'No se pudo completar la operación.') }
    },
    act(path, message) { return this.request('post', path, {}, message) },
    addTo(kind, value, message) { return this.request('post', kind, this.clean(value), message) },
    enableAccess() { return this.request('post', 'access', this.clean({ ...this.accessForm, email: this.accessForm.email || this.file.member.email }), 'Acceso habilitado.') },
    editMember(member) {
      this.form = { employment_status: 'active', ...member }
      delete this.form.full_name
      this.editing = true
    },
    async saveMember() {
      this.saving = true
      try {
        await window.axios.put(`/api/v1/staff/${this.form.id}`, this.clean(this.form))
        this.editing = false
        await this.load()
        await this.openFile(this.form)
      } catch (error) { this.showNotification({ type: 'error', message: this.messageOf(error, 'No se pudieron guardar los datos.') }) } finally { this.saving = false }
    },
    statusLabel(v) { return { active: 'Activo', leave: 'Licencia', inactive: 'Inactivo', terminated: 'Baja' }[v] || v },
  },
}
</script>

<style scoped>
.staff-page .field { display: block; font-size: .875rem; color: #4a5568; }
.staff-page .field > span { display: block; margin-bottom: .35rem; }
.staff-page .input { width: 100%; min-height: 42px; padding: .6rem .75rem; border: 1px solid #d9e2ec; border-radius: .375rem; background: white; }
.staff-page .badge { padding: .25rem .5rem; border-radius: 9999px; background: #f1f5f9; color: #475569; }
.staff-page .section-title { font-weight: 600; color: #1f2937; margin-bottom: .25rem; }
.staff-page .hint { font-size: .8rem; color: #6b7280; }
.staff-page .link { margin-top: .5rem; font-size: .875rem; color: #5851d8; }
.staff-page .row { display: flex; flex-wrap: wrap; justify-content: space-between; gap: .75rem; padding: .75rem; margin-bottom: .5rem; border: 1px solid #e5e7eb; border-radius: .375rem; }
.staff-page .error-box { padding: .75rem; font-size: .875rem; color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; border-radius: .375rem; }
</style>
