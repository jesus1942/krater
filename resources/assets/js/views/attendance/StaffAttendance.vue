<template>
  <base-page class="asistencia">
    <sw-page-header title="Asistencia del personal">
      <sw-breadcrumb slot="breadcrumbs">
        <sw-breadcrumb-item title="Inicio" to="/admin/dashboard" />
        <sw-breadcrumb-item title="Asistencia del personal" active />
      </sw-breadcrumb>
    </sw-page-header>

    <p v-if="error" class="mt-6 aviso aviso--error">{{ error }}</p>

    <template v-else-if="day">
      <div class="flex flex-wrap items-end gap-4 mt-6">
        <label class="campo">
          <span>Fecha</span>
          <input v-model="date" type="date" :max="today" class="entrada" @change="load" />
        </label>
      </div>

      <section class="mt-6 panel">
        <header class="flex flex-wrap items-center justify-between gap-3 mb-4">
          <h2 class="titulo">Plantel · {{ formatDate(day.date) }}</h2>
          <button v-if="day.can_record" type="button" class="enlace" @click="allPresent">Marcar el resto presente</button>
        </header>
        <p v-if="!day.members.length" class="nota">No hay personal con cargo o docencia vigente en este nivel.</p>
        <ul class="divide-y">
          <li v-for="m in day.members" :key="m.staff_member_id" class="flex flex-wrap items-center justify-between gap-3 py-3">
            <div class="min-w-0">
              <span class="font-medium text-gray-900">{{ m.name }}</span>
              <span v-if="m.roles.length" class="block nota">{{ m.roles.join(' · ') }}</span>
            </div>
            <div v-if="m.leave" class="text-right">
              <span class="etiqueta etiqueta--licencia">Licencia: {{ m.leave.type_label }}</span>
              <span class="block nota">{{ formatDate(m.leave.starts_on) }} al {{ formatDate(m.leave.ends_on) }}</span>
            </div>
            <div v-else class="flex flex-wrap items-center gap-2">
              <input v-if="statuses[m.staff_member_id] && statuses[m.staff_member_id] !== 'present'" v-model="notes[m.staff_member_id]"
                class="entrada entrada--chica" maxlength="500" placeholder="Observación (opcional)" :disabled="!day.can_record" />
              <div class="estados" role="group" :aria-label="'Asistencia de ' + m.name">
                <button v-for="opt in options" :key="opt.value" type="button" :disabled="!day.can_record" class="estado"
                  :class="{ ['estado--' + opt.value]: statuses[m.staff_member_id] === opt.value }" @click="$set(statuses, m.staff_member_id, opt.value)">
                  {{ opt.label }}
                </button>
              </div>
            </div>
          </li>
        </ul>
        <div v-if="day.can_record && day.members.length" class="flex justify-end mt-5">
          <sw-button variant="primary" :loading="saving" :disabled="!marked" @click="save">Guardar asistencia</sw-button>
        </div>
      </section>

      <section class="mt-6 panel">
        <header class="flex flex-wrap items-center justify-between gap-3 mb-4">
          <h2 class="titulo">Licencias de {{ monthName }}</h2>
        </header>
        <p v-if="!leaves.length" class="nota">No hay licencias cargadas en el mes.</p>
        <ul class="divide-y">
          <li v-for="l in leaves" :key="l.id" class="flex flex-wrap items-center justify-between gap-3 py-2">
            <div>
              <span class="font-medium" :class="l.cancelled ? 'text-gray-400 line-through' : 'text-gray-900'">{{ l.name }}</span>
              <span class="block nota">{{ l.type_label }} · {{ formatDate(l.starts_on) }} al {{ formatDate(l.ends_on) }}<template v-if="l.notes"> · {{ l.notes }}</template></span>
            </div>
            <button v-if="day.can_record && !l.cancelled" type="button" class="enlace" @click="cancelLeave(l)">Anular</button>
            <span v-else-if="l.cancelled" class="nota">Anulada</span>
          </li>
        </ul>

        <form v-if="day.can_record" class="grid gap-3 p-4 mt-5 border rounded md:grid-cols-5 bg-gray-50" @submit.prevent="createLeave">
          <label class="campo md:col-span-2"><span>Persona</span>
            <select v-model="leave.staff_member_id" class="entrada" required>
              <option :value="null">Elegir</option>
              <option v-for="m in day.members" :key="m.staff_member_id" :value="m.staff_member_id">{{ m.name }}</option>
            </select>
          </label>
          <label class="campo"><span>Tipo</span>
            <select v-model="leave.type" class="entrada" required>
              <option v-for="(label, code) in day.leave_types" :key="code" :value="code">{{ label }}</option>
            </select>
          </label>
          <label class="campo"><span>Desde</span><input v-model="leave.starts_on" type="date" class="entrada" required /></label>
          <label class="campo"><span>Hasta</span><input v-model="leave.ends_on" type="date" class="entrada" required /></label>
          <label class="campo md:col-span-4"><span>Observaciones</span><input v-model="leave.notes" class="entrada" maxlength="500" placeholder="Número de certificado, artículo, etc." /></label>
          <div class="flex items-end"><sw-button type="submit" variant="primary-outline" :loading="savingLeave">Cargar licencia</sw-button></div>
        </form>
      </section>
    </template>
  </base-page>
</template>

<script>
import { mapActions } from 'vuex'

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']
const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

export default {
  data() {
    const today = iso(new Date())
    return {
      error: '', day: null, date: today, today, statuses: {}, notes: {}, leaves: [], saving: false, savingLeave: false,
      leave: { staff_member_id: null, type: 'illness', starts_on: today, ends_on: today, notes: '' },
      options: [{ value: 'present', label: 'Presente' }, { value: 'absent', label: 'Ausente' }, { value: 'late', label: 'Tarde' }],
    }
  },
  computed: {
    marked() { return Object.values(this.statuses).filter(Boolean).length },
    monthName() { return MESES[Number(this.date.split('-')[1]) - 1] },
    monthRange() {
      const [y, m] = this.date.split('-').map(Number)
      return { from: iso(new Date(y, m - 1, 1)), to: iso(new Date(y, m, 0)) }
    },
  },
  created() { this.load() },
  methods: {
    ...mapActions('notification', ['showNotification']),
    messageOf(error, fallback) {
      const data = error.response && error.response.data
      if (data && data.errors) { const first = Object.values(data.errors)[0]; if (first && first.length) return first[0] }
      return (data && data.message) || fallback
    },
    formatDate(value) { const [y, m, d] = value.split('-'); return `${d}/${m}/${y}` },
    show(data) {
      this.day = data
      this.statuses = Object.fromEntries(data.members.filter(m => !m.leave).map(m => [m.staff_member_id, m.status]))
      this.notes = Object.fromEntries(data.members.map(m => [m.staff_member_id, m.notes || '']))
    },
    async load() {
      try {
        const [day, leaves] = await Promise.all([
          window.axios.get('/api/v1/staff-attendance', { params: { date: this.date } }),
          window.axios.get('/api/v1/staff-leaves', { params: this.monthRange }),
        ])
        this.show(day.data)
        this.leaves = leaves.data.data
      } catch (e) { this.error = this.messageOf(e, 'No se pudo cargar la asistencia del personal.') }
    },
    allPresent() { this.day.members.forEach(m => { if (!m.leave && !this.statuses[m.staff_member_id]) this.$set(this.statuses, m.staff_member_id, 'present') }) },
    async save() {
      const records = Object.entries(this.statuses).filter(([, s]) => s)
        .map(([id, status]) => ({ staff_member_id: Number(id), status, notes: status === 'present' ? null : (this.notes[id] || null) }))
      this.saving = true
      try {
        const { data } = await window.axios.put('/api/v1/staff-attendance', { date: this.date, records })
        this.show(data)
        this.showNotification({ type: 'success', message: 'Asistencia del personal guardada.' })
      } catch (e) { this.showNotification({ type: 'error', message: this.messageOf(e, 'No se pudo guardar.') }) } finally { this.saving = false }
    },
    async createLeave() {
      this.savingLeave = true
      try {
        await window.axios.post('/api/v1/staff-leaves', { ...this.leave, notes: this.leave.notes || null })
        this.showNotification({ type: 'success', message: 'Licencia cargada.' })
        this.leave = { ...this.leave, staff_member_id: null, notes: '' }
        await this.load()
      } catch (e) { this.showNotification({ type: 'error', message: this.messageOf(e, 'No se pudo cargar la licencia.') }) } finally { this.savingLeave = false }
    },
    async cancelLeave(l) {
      if (!window.confirm(`¿Anular la licencia de ${l.name}?`)) return
      try {
        await window.axios.post(`/api/v1/staff-leaves/${l.id}/cancel`)
        this.showNotification({ type: 'success', message: 'Licencia anulada.' })
        await this.load()
      } catch (e) { this.showNotification({ type: 'error', message: this.messageOf(e, 'No se pudo anular.') }) }
    },
  },
}
</script>

<style scoped>
.campo { display: block; font-size: .875rem; color: #4a5568; }
.campo > span { display: block; margin-bottom: .35rem; }
.entrada { width: 100%; min-height: 42px; padding: .55rem .75rem; border: 1px solid #d9e2ec; border-radius: .375rem; background: #fff; }
.entrada--chica { width: auto; min-height: 34px; padding: .3rem .6rem; font-size: .85rem; min-width: 14rem; }
.nota { font-size: .8rem; color: #6b7280; }
.aviso { padding: .75rem 1rem; font-size: .875rem; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: .375rem; }
.aviso--error { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
.panel { padding: 1.25rem; background: #fff; border-radius: .5rem; box-shadow: 0 1px 3px rgba(16, 35, 64, .08); }
.titulo { font-size: 1.05rem; font-weight: 600; color: #102340; }
.enlace { font-size: .875rem; color: #a5121c; }
.enlace:hover { text-decoration: underline; }
.estados { display: inline-flex; border: 1px solid #d9e2ec; border-radius: .5rem; overflow: hidden; }
.estado { padding: .45rem .9rem; font-size: .85rem; color: #374151; background: #fff; border-left: 1px solid #d9e2ec; }
.estado:first-child { border-left: 0; }
.estado:disabled { cursor: not-allowed; opacity: .7; }
.estado--present { color: #fff; background: #15803d; }
.estado--absent { color: #fff; background: #a5121c; }
.estado--late { color: #fff; background: #b45309; }
.etiqueta { padding: .15rem .55rem; font-size: .75rem; border-radius: 999px; }
.etiqueta--licencia { color: #1e3a8a; background: #dbeafe; }
</style>
