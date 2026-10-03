<template>
  <base-page class="staff-page">
    <sw-page-header title="Personal">
      <sw-breadcrumb slot="breadcrumbs">
        <sw-breadcrumb-item title="Inicio" to="/admin/dashboard" />
        <sw-breadcrumb-item title="Personal" active />
      </sw-breadcrumb>
      <template slot="actions">
        <sw-button variant="primary" @click="startNew">Nuevo integrante</sw-button>
      </template>
    </sw-page-header>

    <div class="mt-6 space-y-6">
      <sw-card v-if="editing" variant="setting-card">
        <template slot="header"><h6 class="sw-section-title">{{ form.id ? 'Editar integrante' : 'Alta de personal' }}</h6></template>
        <form class="space-y-5" @submit.prevent="saveMember">
          <div class="grid gap-4 md:grid-cols-2">
            <label class="field"><span>Nombre *</span><input v-model.trim="form.first_name" required class="input" /></label>
            <label class="field"><span>Apellido *</span><input v-model.trim="form.last_name" required class="input" /></label>
            <label class="field"><span>Tipo de documento</span><select v-model="form.document_type" class="input"><option value="DNI">DNI</option><option value="CUIL">CUIL</option><option value="PASSPORT">Pasaporte</option></select></label>
            <label class="field"><span>Número de documento</span><input v-model.trim="form.document_number" class="input" /></label>
            <label class="field"><span>Correo</span><input v-model.trim="form.email" type="email" class="input" /></label>
            <label class="field"><span>Teléfono</span><input v-model.trim="form.phone" class="input" /></label>
            <label class="field"><span>Estado *</span><select v-model="form.employment_status" required class="input"><option value="active">Activo</option><option value="leave">Licencia</option><option value="inactive">Inactivo</option><option value="terminated">Baja</option></select></label>
            <label class="field"><span>Ingreso a la institución</span><input v-model="form.hire_date" type="date" class="input" /></label>
            <label class="field"><span>Fecha de baja</span><input v-model="form.termination_date" type="date" class="input" /></label>
          </div>
          <label class="field"><span>Notas</span><textarea v-model.trim="form.notes" rows="3" class="input"></textarea></label>

          <div v-if="!form.id" class="p-4 border rounded-lg bg-gray-50">
            <h3 class="mb-1 font-semibold">Primer cargo / función</h3>
            <p class="mb-3 text-xs text-gray-500">La función se define en el cargo y es la que utiliza Liquidaciones. No hace falta cargar una categoría separada.</p>
            <assignment-fields :value="assignmentForm" :levels="levels" :categories="categories" @input="assignmentForm = $event" />
          </div>

          <div class="flex flex-wrap justify-end gap-3">
            <sw-button type="button" variant="gray" @click="cancelEdit">Cancelar</sw-button>
            <sw-button type="submit" variant="primary" :loading="saving">Guardar</sw-button>
          </div>
        </form>
      </sw-card>

      <div v-if="loading" class="py-12 text-center text-gray-500">Cargando personal…</div>
      <div v-else-if="!members.length" class="p-8 text-center bg-white border rounded-lg text-gray-500">Todavía no hay personal cargado.</div>
      <div v-else class="grid gap-4 lg:grid-cols-2">
        <article v-for="member in members" :key="member.id" class="p-5 bg-white border rounded-lg shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div><h3 class="text-lg font-semibold">{{ member.full_name }}</h3><p class="text-sm text-gray-500">{{ member.document_type || 'Documento' }} {{ member.document_number || 'sin cargar' }}</p></div>
            <sw-button size="sm" variant="gray" @click="editMember(member)">Editar</sw-button>
          </div>
          <div class="flex flex-wrap gap-2 mt-3 text-xs"><span v-if="memberFunction(member)" class="badge">{{ categoryLabel(memberFunction(member)) }}</span><span class="badge">{{ statusLabel(member.employment_status) }}</span></div>
          <div class="mt-4 space-y-2">
            <div v-for="a in member.assignments" :key="a.id" class="p-3 border rounded-md">
              <div class="flex items-center justify-between gap-3"><strong>{{ a.position_title }}</strong><span class="text-xs" :class="a.active ? 'text-green-600' : 'text-gray-400'">{{ a.active ? 'Activo' : 'Inactivo' }}</span></div>
              <p class="mt-1 text-sm text-gray-500">{{ a.school_level ? a.school_level.name : 'Institucional' }} · {{ categoryLabel(a.function_category) }}<span v-if="a.weekly_hours"> · {{ a.weekly_hours }} h/sem</span></p>
              <button class="mt-2 text-sm text-primary-500" type="button" @click="toggleAssignment(member, a)">{{ a.active ? 'Finalizar cargo' : 'Reactivar cargo' }}</button>
            </div>
          </div>
          <button class="mt-4 text-sm font-medium text-primary-500" type="button" @click="openAssignment(member)">+ Agregar cargo o función</button>
          <div v-if="assignmentMemberId === member.id" class="p-4 mt-3 border rounded-lg bg-gray-50">
            <assignment-fields :value="assignmentForm" :levels="levels" :categories="categories" @input="assignmentForm = $event" />
            <div class="flex justify-end gap-2 mt-3"><sw-button size="sm" variant="gray" @click="assignmentMemberId = null">Cancelar</sw-button><sw-button size="sm" variant="primary" :loading="savingAssignment" @click="saveAssignment(member)">Guardar cargo</sw-button></div>
          </div>
        </article>
      </div>
    </div>
  </base-page>
</template>

<script>
const AssignmentFields = {
  props: ['value', 'levels', 'categories'],
  methods: {
    // Emite siempre un objeto nuevo para mantener el formulario padre reactivo.
    set(key, val) { this.$emit('input', { ...this.value, [key]: val }) },
  },
  template: `<div class="grid gap-4 md:grid-cols-2">
    <label class="field"><span>Nivel</span><select :value="value.school_level_id" class="input" @input="set('school_level_id', $event.target.value ? Number($event.target.value) : null)"><option :value="null">Institucional / todos</option><option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option></select></label>
    <label class="field"><span>Cargo *</span><input :value="value.position_title" required class="input" @input="set('position_title', $event.target.value)" /></label>
    <label class="field"><span>Función *</span><select :value="value.function_category" required class="input" @input="set('function_category', $event.target.value)"><option v-for="o in categories" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
    <label class="field"><span>Horas semanales</span><input :value="value.weekly_hours" type="number" min="0" max="168" step="0.5" class="input" @input="set('weekly_hours', $event.target.value || null)" /></label>
    <label class="field"><span>Inicio del cargo</span><input :value="value.start_date" type="date" class="input" @input="set('start_date', $event.target.value || null)" /></label>
  </div>`,
}

export default {
  components: { AssignmentFields },
  data() { return { members: [], levels: [], loading: true, saving: false, savingAssignment: false, editing: false, assignmentMemberId: null, form: {}, assignmentForm: {}, categories: [
    { value: 'teaching', label: 'Docente' }, { value: 'administrative', label: 'Administrativo' }, { value: 'management', label: 'Dirección / gestión' }, { value: 'maintenance', label: 'Mantenimiento' }, { value: 'cleaning', label: 'Limpieza' }, { value: 'support', label: 'Apoyo' }, { value: 'other', label: 'Otro' },
  ] } },
  async created() { await this.load() },
  methods: {
    // Datos personales: la función ya no se duplica acá, vive en StaffAssignment.
    blankMember() { return { id: null, user_id: null, document_type: 'DNI', document_number: '', first_name: '', last_name: '', email: '', phone: '', employment_status: 'active', hire_date: '', termination_date: '', notes: '' } },
    blankAssignment() { return { school_level_id: this.levels.length === 1 ? this.levels[0].id : null, position_code: null, position_title: '', function_category: 'teaching', start_date: '', end_date: null, active: true, weekly_hours: null, notes: null } },
    async load() { this.loading = true; try { const r = await window.axios.get('/api/v1/staff'); this.members = r.data.data; this.levels = r.data.levels } finally { this.loading = false } },
    startNew() { this.form = this.blankMember(); this.assignmentForm = this.blankAssignment(); this.editing = true; window.scrollTo(0, 0) },
    editMember(m) { this.form = { ...this.blankMember(), ...m }; delete this.form.staff_category; delete this.form.assignments; delete this.form.full_name; this.editing = true; window.scrollTo(0, 0) },
    cancelEdit() { this.editing = false; this.form = {} },
    payload(obj) { const p = { ...obj }; Object.keys(p).forEach(k => { if (p[k] === '') p[k] = null }); return p },
    async saveMember() { this.saving = true; try { const data = this.payload(this.form); if (this.form.id) await window.axios.put(`/api/v1/staff/${this.form.id}`, data); else await window.axios.post('/api/v1/staff', { ...data, ...this.payload(this.assignmentForm) }); this.editing = false; await this.load() } finally { this.saving = false } },
    openAssignment(m) { this.assignmentMemberId = m.id; this.assignmentForm = this.blankAssignment() },
    async saveAssignment(m) { if (!this.assignmentForm.position_title) return; this.savingAssignment = true; try { await window.axios.post(`/api/v1/staff/${m.id}/assignments`, this.payload(this.assignmentForm)); this.assignmentMemberId = null; await this.load() } finally { this.savingAssignment = false } },
    async toggleAssignment(m, a) { await window.axios.put(`/api/v1/staff/${m.id}/assignments/${a.id}`, { active: !a.active }); await this.load() },
    // La insignia principal se deriva del cargo activo más reciente, nunca del campo legado staff_category.
    memberFunction(member) { const assignments = member.assignments || []; const active = assignments.find(a => a.active); return (active || assignments[0] || {}).function_category || null },
    categoryLabel(v) { const o = this.categories.find(x => x.value === v); return o ? o.label : v },
    statusLabel(v) { return { active: 'Activo', leave: 'Licencia', inactive: 'Inactivo', terminated: 'Baja' }[v] || v },
  },
}
</script>

<style scoped>
.staff-page .field { display: block; font-size: .875rem; color: #4a5568; }
.staff-page .field > span { display: block; margin-bottom: .35rem; }
.staff-page .input { width: 100%; min-height: 42px; padding: .6rem .75rem; border: 1px solid #d9e2ec; border-radius: .375rem; background: white; }
.staff-page .badge { padding: .25rem .5rem; border-radius: 9999px; background: #f1f5f9; color: #475569; }
</style>
