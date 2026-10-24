<template>
  <base-page class="asistencia">
    <sw-page-header title="Tomar asistencia">
      <sw-breadcrumb slot="breadcrumbs">
        <sw-breadcrumb-item title="Inicio" to="/admin/dashboard" />
        <sw-breadcrumb-item title="Tomar asistencia" active />
      </sw-breadcrumb>
    </sw-page-header>

    <p v-if="error" class="mt-6 aviso aviso--error">{{ error }}</p>

    <template v-else-if="loaded">
      <div class="flex flex-wrap items-end gap-4 mt-6">
        <label class="campo">
          <span>{{ mode === 'subject' ? 'Materia y curso' : 'Curso y división' }}</span>
          <select v-model="targetKey" class="entrada" @change="loadSheet">
            <option value="">Elegir</option>
            <option v-for="t in targets" :key="t.type + t.id" :value="t.type + ':' + t.id">{{ t.label }}</option>
          </select>
        </label>
        <label class="campo">
          <span>Fecha</span>
          <input v-model="date" type="date" :max="today" class="entrada" @change="loadSheet" />
        </label>
      </div>
      <p class="mt-2 nota">
        {{ mode === 'subject' ? 'En este nivel la asistencia se toma por materia, en cada clase.' : 'En este nivel la asistencia se toma una vez por día y por división.' }}
      </p>
      <p v-if="!targets.length" class="mt-6 aviso">No tenés cursos asignados para tomar asistencia en este nivel.</p>

      <section v-if="sheet" class="mt-6 panel">
        <header class="flex flex-wrap items-center justify-between gap-3 mb-4">
          <h2 class="titulo">
            {{ sheet.taken ? 'Lista tomada' : 'Lista sin tomar' }} · {{ formatDate(sheet.date) }}
          </h2>
          <div class="flex gap-3">
            <button v-if="sheet.can_edit" type="button" class="enlace" @click="allPresent">Marcar el resto presente</button>
          </div>
        </header>
        <p v-if="!sheet.can_edit" class="mb-4 aviso">Corregir días anteriores requiere autorización de la dirección.</p>
        <p v-if="!sheet.students.length" class="nota">No hay alumnos con matrícula vigente en esta fecha.</p>

        <ul class="divide-y">
          <li v-for="s in sheet.students" :key="s.enrollment_id" class="flex flex-wrap items-center justify-between gap-3 py-3">
            <div class="min-w-0">
              <span class="font-medium text-gray-900">{{ s.name }}</span>
              <span v-if="s.is_justified" class="ml-2 etiqueta etiqueta--ok">Justificada</span>
              <div v-if="sheet.can_justify && s.record_id && statuses[s.enrollment_id] !== 'present' && s.status !== 'present'" class="flex gap-2 mt-2">
                <input v-model="justifications[s.enrollment_id]" class="entrada entrada--chica" maxlength="255" placeholder="Motivo (certificado, aviso de la familia...)" />
                <button type="button" class="enlace" @click="justify(s)">{{ s.is_justified ? 'Actualizar' : 'Justificar' }}</button>
              </div>
            </div>
            <div class="estados" role="group" :aria-label="'Asistencia de ' + s.name">
              <button v-for="opt in options" :key="opt.value" type="button" :disabled="!sheet.can_edit"
                class="estado" :class="{ ['estado--' + opt.value]: statuses[s.enrollment_id] === opt.value }"
                @click="set(s.enrollment_id, opt.value)">{{ opt.label }}</button>
            </div>
          </li>
        </ul>

        <div v-if="sheet.can_edit && sheet.students.length" class="flex flex-wrap items-center justify-end gap-4 mt-5">
          <span class="nota">{{ pending }} sin marcar</span>
          <sw-button variant="primary" :loading="saving" :disabled="!marked" @click="save">Guardar asistencia</sw-button>
        </div>
      </section>
    </template>
  </base-page>
</template>

<script>
import { mapActions } from 'vuex'

const localToday = () => {
  const d = new Date()
  return `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`
}

export default {
  data() {
    return {
      loaded: false, error: '', mode: 'daily', targets: [], targetKey: '', date: localToday(), today: localToday(),
      sheet: null, statuses: {}, justifications: {}, saving: false,
      options: [{ value: 'present', label: 'Presente' }, { value: 'absent', label: 'Ausente' }, { value: 'late', label: 'Tarde' }],
    }
  },
  computed: {
    marked() { return Object.values(this.statuses).filter(Boolean).length },
    pending() { return this.sheet ? this.sheet.students.length - this.marked : 0 },
  },
  async created() {
    try {
      const { data } = await window.axios.get('/api/v1/attendance/targets')
      this.mode = data.mode
      this.targets = data.targets
      if (this.targets.length === 1) {
        this.targetKey = this.targets[0].type + ':' + this.targets[0].id
        await this.loadSheet()
      }
    } catch (e) {
      this.error = this.messageOf(e, 'No se pudo cargar la asistencia.')
    } finally { this.loaded = true }
  },
  methods: {
    ...mapActions('notification', ['showNotification']),
    messageOf(error, fallback) {
      const data = error.response && error.response.data
      if (data && data.errors) { const first = Object.values(data.errors)[0]; if (first && first.length) return first[0] }
      return (data && data.message) || fallback
    },
    formatDate(iso) { const [y, m, d] = iso.split('-'); return `${d}/${m}/${y}` },
    async loadSheet() {
      this.sheet = null
      if (!this.targetKey || !this.date) return
      const [type, id] = this.targetKey.split(':')
      try {
        const { data } = await window.axios.get('/api/v1/attendance/sheet', { params: { type, id, date: this.date } })
        this.show(data)
      } catch (e) {
        this.showNotification({ type: 'error', message: this.messageOf(e, 'No se pudo abrir la lista.') })
      }
    },
    show(data) {
      this.sheet = data
      this.statuses = Object.fromEntries(data.students.map(s => [s.enrollment_id, s.status]))
      this.justifications = Object.fromEntries(data.students.map(s => [s.enrollment_id, s.justification || '']))
    },
    set(id, value) { this.$set(this.statuses, id, value) },
    allPresent() { this.sheet.students.forEach(s => { if (!this.statuses[s.enrollment_id]) this.set(s.enrollment_id, 'present') }) },
    async save() {
      const [type, id] = this.targetKey.split(':')
      const records = Object.entries(this.statuses).filter(([, status]) => status)
        .map(([enrollment, status]) => ({ enrollment_id: Number(enrollment), status }))
      this.saving = true
      try {
        const { data } = await window.axios.put('/api/v1/attendance/sheet', { type, id: Number(id), date: this.date, records })
        this.show(data)
        this.showNotification({ type: 'success', message: 'Asistencia guardada.' })
      } catch (e) {
        this.showNotification({ type: 'error', message: this.messageOf(e, 'No se pudo guardar la asistencia.') })
      } finally { this.saving = false }
    },
    async justify(student) {
      try {
        await window.axios.post(`/api/v1/attendance/records/${student.record_id}/justify`, { justification: this.justifications[student.enrollment_id] || null })
        this.showNotification({ type: 'success', message: 'Justificación guardada.' })
        await this.loadSheet()
      } catch (e) {
        this.showNotification({ type: 'error', message: this.messageOf(e, 'No se pudo justificar.') })
      }
    },
  },
}
</script>

<style scoped>
.campo { display: block; font-size: .875rem; color: #4a5568; min-width: 15rem; }
.campo > span { display: block; margin-bottom: .35rem; }
.entrada { width: 100%; min-height: 42px; padding: .55rem .75rem; border: 1px solid #d9e2ec; border-radius: .375rem; background: #fff; }
.entrada--chica { min-height: 34px; padding: .3rem .6rem; font-size: .85rem; min-width: 16rem; }
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
.etiqueta { padding: .1rem .5rem; font-size: .7rem; border-radius: 999px; }
.etiqueta--ok { color: #166534; background: #dcfce7; }
</style>
