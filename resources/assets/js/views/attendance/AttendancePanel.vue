<template>
  <base-page class="asistencia">
    <sw-page-header title="Panel de asistencia">
      <sw-breadcrumb slot="breadcrumbs">
        <sw-breadcrumb-item title="Inicio" to="/admin/dashboard" />
        <sw-breadcrumb-item title="Panel de asistencia" active />
      </sw-breadcrumb>
    </sw-page-header>

    <p v-if="error" class="mt-6 aviso aviso--error">{{ error }}</p>

    <template v-else-if="data">
      <div class="flex flex-wrap items-end justify-between gap-4 mt-6">
        <p class="text-xs font-semibold tracking-widest text-gray-500 uppercase">Nivel {{ data.school_level }}</p>
        <label class="campo">
          <span>Fecha</span>
          <input v-model="date" type="date" :max="today" class="entrada" @change="load" />
        </label>
      </div>

      <!-- Hoy -->
      <div class="grid gap-4 mt-4 sm:grid-cols-2 xl:grid-cols-4">
        <div v-if="hoy.students" class="tarjeta">
          <span class="tarjeta__valor" :class="{ 'tarjeta__valor--alerta': hoy.students.absent_total }">{{ hoy.students.absent_total }}</span>
          <span class="tarjeta__rotulo">Alumnos ausentes</span>
          <span class="tarjeta__nota">{{ formatDate(data.date) }}</span>
        </div>
        <div v-if="hoy.students" class="tarjeta">
          <span class="tarjeta__valor" :class="{ 'tarjeta__valor--alerta': hoy.students.pending }">{{ hoy.students.pending }}</span>
          <span class="tarjeta__rotulo">{{ data.rules.mode === 'subject' ? 'Cursos con materias sin lista' : 'Divisiones sin lista tomada' }}</span>
          <span class="tarjeta__nota">de {{ hoy.students.divisions.length }}</span>
        </div>
        <div v-if="hoy.staff" class="tarjeta">
          <span class="tarjeta__valor" :class="{ 'tarjeta__valor--alerta': hoy.staff.absent.length + hoy.staff.leave.length }">{{ hoy.staff.absent.length + hoy.staff.leave.length }}</span>
          <span class="tarjeta__rotulo">Personal ausente o de licencia</span>
          <span class="tarjeta__nota">{{ hoy.staff.late.length }} tarde · {{ hoy.staff.unrecorded }} sin registrar</span>
        </div>
        <div v-if="hoy.uncovered_sections" class="tarjeta">
          <span class="tarjeta__valor" :class="{ 'tarjeta__valor--alerta': hoy.uncovered_sections.length }">{{ hoy.uncovered_sections.length }}</span>
          <span class="tarjeta__rotulo">Materias sin docente presente</span>
          <span class="tarjeta__nota">todos sus docentes faltan</span>
        </div>
      </div>

      <div class="grid gap-6 mt-6 xl:grid-cols-2">
        <section v-if="hoy.students" class="panel">
          <h2 class="titulo">El día por curso</h2>
          <table class="w-full mt-3 text-sm">
            <thead><tr class="cabeza"><th>Curso</th><th>Lista</th><th class="text-right">Ausentes</th><th class="text-right">Tarde</th></tr></thead>
            <tbody>
              <tr v-for="d in hoy.students.divisions" :key="d.division_id" class="fila">
                <td class="font-medium text-gray-900">{{ d.label }}</td>
                <td>
                  <span v-if="data.rules.mode === 'subject'" :class="d.taken < d.sections ? 'text-red-700' : 'text-green-700'">{{ d.taken }} de {{ d.sections }} materias</span>
                  <span v-else :class="d.taken ? 'text-green-700' : 'text-red-700'">{{ d.taken ? 'Tomada' : 'Pendiente' }}</span>
                </td>
                <td class="text-right">{{ d.absent }}</td>
                <td class="text-right">{{ d.late }}</td>
              </tr>
            </tbody>
          </table>
          <template v-if="hoy.students.absent.length">
            <h3 class="mt-5 subtitulo">Ausentes</h3>
            <p class="mt-1 text-sm text-gray-700">
              <span v-for="(s, i) in hoy.students.absent" :key="s.enrollment_id">{{ s.name }} <span class="nota">({{ s.division }})</span><template v-if="i < hoy.students.absent.length - 1">, </template></span>
            </p>
          </template>
        </section>

        <section v-if="hoy.staff" class="panel">
          <h2 class="titulo">El plantel en el día</h2>
          <p v-if="!hoy.staff.absent.length && !hoy.staff.leave.length && !hoy.staff.late.length" class="mt-3 nota">Sin ausencias registradas.</p>
          <ul class="mt-3 divide-y">
            <li v-for="p in hoy.staff.leave" :key="'l' + p.staff_member_id" class="flex justify-between py-2"><span>{{ p.name }}</span><span class="etiqueta etiqueta--licencia">Licencia: {{ p.leave_type }}</span></li>
            <li v-for="p in hoy.staff.absent" :key="'a' + p.staff_member_id" class="flex justify-between py-2"><span>{{ p.name }}</span><span class="etiqueta etiqueta--ausente">Ausente</span></li>
            <li v-for="p in hoy.staff.late" :key="'t' + p.staff_member_id" class="flex justify-between py-2"><span>{{ p.name }}</span><span class="etiqueta etiqueta--tarde">Tarde</span></li>
          </ul>
          <template v-if="hoy.uncovered_sections && hoy.uncovered_sections.length">
            <h3 class="mt-5 subtitulo">Materias sin docente presente</h3>
            <ul class="mt-1 text-sm text-gray-700">
              <li v-for="s in hoy.uncovered_sections" :key="s.course_section_id">{{ s.subject }} · {{ s.division }}</li>
            </ul>
          </template>
          <router-link to="/admin/attendance/staff" class="inline-block mt-4 enlace">Registrar asistencia del personal</router-link>
        </section>
      </div>

      <div class="grid gap-6 mt-6 xl:grid-cols-2">
        <section v-if="data.by_division" class="panel">
          <h2 class="titulo">Asistencia por curso</h2>
          <table class="w-full mt-3 text-sm">
            <thead><tr class="cabeza"><th>Curso</th><th>En {{ mesActual }}</th><th>En el ciclo</th></tr></thead>
            <tbody>
              <tr v-for="d in data.by_division.divisions" :key="d.division_id" class="fila">
                <td class="font-medium text-gray-900 whitespace-nowrap">{{ d.label }}</td>
                <td><porcentaje :valor="d.month" :minimo="minimo" /></td>
                <td><porcentaje :valor="d.year" :minimo="minimo" /></td>
              </tr>
            </tbody>
          </table>
          <template v-if="data.by_division.sections && data.by_division.sections.length">
            <h3 class="mt-5 subtitulo">Por materia, en el ciclo</h3>
            <table class="w-full mt-2 text-sm">
              <tbody>
                <tr v-for="s in data.by_division.sections" :key="s.course_section_id" class="fila">
                  <td class="text-gray-900">{{ s.subject }} <span class="nota">· {{ s.division }}</span></td>
                  <td class="w-2/5"><porcentaje :valor="s.year" :minimo="minimo" /></td>
                </tr>
              </tbody>
            </table>
          </template>
        </section>

        <section v-if="data.at_risk" class="panel">
          <h2 class="titulo">Alumnos en riesgo por inasistencias</h2>
          <p class="mt-1 nota">
            <template v-if="data.at_risk.mode === 'daily'">Límite del nivel: {{ data.at_risk.limit }} inasistencias en el ciclo (la llegada tarde cuenta media). Aparecen desde el 80 %.</template>
            <template v-else>Mínimo del nivel: {{ data.at_risk.limit }} % de asistencia por materia. Aparecen desde {{ data.at_risk.limit + 10 }} %, con al menos 4 clases.</template>
          </p>
          <p v-if="!data.at_risk.students.length" class="mt-3 nota">Ningún alumno cerca del límite.</p>
          <ul class="mt-3 divide-y">
            <li v-for="s in data.at_risk.students" :key="s.enrollment_id + '-' + (s.subject || '')" class="flex items-center justify-between gap-3 py-2">
              <div class="min-w-0">
                <span class="font-medium text-gray-900">{{ s.name }}</span>
                <span class="block nota">{{ s.division }}<template v-if="s.subject"> · {{ s.subject }}</template><template v-if="s.justified"> · {{ s.justified }} justificadas</template></span>
              </div>
              <span class="etiqueta" :class="s.level === 'exceeded' ? 'etiqueta--ausente' : 'etiqueta--tarde'">
                <template v-if="data.at_risk.mode === 'daily'">{{ s.absences }} / {{ data.at_risk.limit }}</template>
                <template v-else>{{ s.percentage }} %</template>
                · {{ s.level === 'exceeded' ? 'Superó el límite' : 'Cerca del límite' }}
              </span>
            </li>
          </ul>
        </section>
      </div>

      <section v-if="data.staff_absenteeism" class="mt-6 panel">
        <h2 class="titulo">Ausentismo del personal en {{ mesActual }}</h2>
        <dl class="grid grid-cols-2 gap-4 mt-3 sm:grid-cols-4">
          <div><dt class="nota">Personal del nivel</dt><dd class="cifra">{{ data.staff_absenteeism.staff_total }}</dd></div>
          <div><dt class="nota">Inasistencias</dt><dd class="cifra">{{ data.staff_absenteeism.absent_days }}</dd></div>
          <div><dt class="nota">Días de licencia</dt><dd class="cifra">{{ data.staff_absenteeism.leave_days }}</dd></div>
          <div><dt class="nota">Llegadas tarde</dt><dd class="cifra">{{ data.staff_absenteeism.late }}</dd></div>
        </dl>
        <div class="grid gap-6 mt-5 lg:grid-cols-3">
          <div>
            <h3 class="subtitulo">Licencias por tipo</h3>
            <p v-if="!data.staff_absenteeism.by_type.length" class="mt-1 nota">Sin licencias en el mes.</p>
            <ul class="mt-2 text-sm">
              <li v-for="t in data.staff_absenteeism.by_type" :key="t.type" class="flex justify-between py-1"><span>{{ t.type }}</span><span class="font-medium">{{ t.days }} días</span></li>
            </ul>
          </div>
          <div class="lg:col-span-2">
            <h3 class="subtitulo">Por persona</h3>
            <p v-if="!data.staff_absenteeism.people.length" class="mt-1 nota">Nadie faltó en el mes.</p>
            <table v-else class="w-full mt-2 text-sm">
              <thead><tr class="cabeza"><th>Persona</th><th class="text-right">Faltas</th><th class="text-right">Licencia</th><th class="text-right">Tarde</th></tr></thead>
              <tbody>
                <tr v-for="p in data.staff_absenteeism.people" :key="p.staff_member_id" class="fila">
                  <td class="text-gray-900">{{ p.name }}</td><td class="text-right">{{ p.absent }}</td><td class="text-right">{{ p.leave_days }}</td><td class="text-right">{{ p.late }}</td>
                </tr>
              </tbody>
            </table>
          </div>
        </div>
      </section>

      <section v-if="canManageRules && data.by_division" class="mt-6 panel">
        <h2 class="titulo">Reglas de asistencia del nivel</h2>
        <form class="grid gap-4 mt-3 md:grid-cols-4" @submit.prevent="saveRules">
          <label class="campo"><span>Cómo se toma</span>
            <select v-model="rules.mode" class="entrada"><option value="daily">Por día y división</option><option value="subject">Por materia</option></select>
          </label>
          <label class="campo"><span>Límite de inasistencias en el ciclo</span><input v-model.number="rules.max_absences" type="number" min="1" max="200" step="0.5" class="entrada" /></label>
          <label class="campo"><span>Asistencia mínima por materia (%)</span><input v-model.number="rules.min_percentage" type="number" min="1" max="100" class="entrada" /></label>
          <div class="flex items-end"><sw-button type="submit" variant="primary-outline" :loading="savingRules">Guardar reglas</sw-button></div>
        </form>
      </section>
    </template>
  </base-page>
</template>

<script>
import { mapActions } from 'vuex'
import { can } from '../../helpers/access'

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto', 'septiembre', 'octubre', 'noviembre', 'diciembre']
const iso = d => `${d.getFullYear()}-${String(d.getMonth() + 1).padStart(2, '0')}-${String(d.getDate()).padStart(2, '0')}`

const Porcentaje = {
  props: ['valor', 'minimo'],
  template: `<div class="flex items-center gap-2">
    <template v-if="valor !== null && valor !== undefined">
      <div style="flex:1;height:.5rem;min-width:2.5rem;background:#eef0f4;border-radius:999px;overflow:hidden">
        <div :style="{ width: valor + '%', height: '100%', borderRadius: '999px', background: valor < minimo ? '#a5121c' : '#102340' }" />
      </div>
      <span class="whitespace-nowrap" :class="valor < minimo ? 'text-red-700 font-medium' : 'text-gray-700'">{{ valor }} %</span>
    </template>
    <span v-else style="font-size:.8rem;color:#6b7280">Sin datos</span>
  </div>`,
}

export default {
  components: { Porcentaje },
  data() {
    const today = iso(new Date())
    return { data: null, error: '', date: today, today, rules: {}, savingRules: false }
  },
  computed: {
    hoy() { return this.data.today },
    mesActual() { return MESES[Number(this.date.split('-')[1]) - 1] },
    minimo() { return this.data.rules.mode === 'subject' ? this.data.rules.min_percentage : 85 },
    canManageRules() { return can(this.$store.state.user.currentUser, 'academic.year.manage') },
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
    async load() {
      try {
        const { data } = await window.axios.get('/api/v1/attendance/panel', { params: { date: this.date } })
        this.data = data
        this.rules = { mode: data.rules.mode, max_absences: data.rules.max_absences, min_percentage: data.rules.min_percentage }
        this.error = ''
      } catch (e) { this.error = this.messageOf(e, 'No se pudo cargar el panel de asistencia.') }
    },
    async saveRules() {
      this.savingRules = true
      try {
        await window.axios.put('/api/v1/attendance/rules', this.rules)
        this.showNotification({ type: 'success', message: 'Reglas guardadas.' })
        await this.load()
      } catch (e) { this.showNotification({ type: 'error', message: this.messageOf(e, 'No se pudieron guardar las reglas.') }) } finally { this.savingRules = false }
    },
  },
}
</script>

<style scoped>
.campo { display: block; font-size: .875rem; color: #4a5568; }
.campo > span { display: block; margin-bottom: .35rem; }
.entrada { width: 100%; min-height: 42px; padding: .55rem .75rem; border: 1px solid #d9e2ec; border-radius: .375rem; background: #fff; }
.nota { font-size: .8rem; color: #6b7280; }
.aviso { padding: .75rem 1rem; font-size: .875rem; color: #92400e; background: #fffbeb; border: 1px solid #fde68a; border-radius: .375rem; }
.aviso--error { color: #b91c1c; background: #fef2f2; border-color: #fecaca; }
.panel { min-width: 0; overflow-x: auto; padding: 1.25rem; background: #fff; border-radius: .5rem; box-shadow: 0 1px 3px rgba(16, 35, 64, .08); }
.titulo { font-size: 1.05rem; font-weight: 600; color: #102340; }
.subtitulo { font-size: .9rem; font-weight: 600; color: #374151; }
.enlace { font-size: .875rem; color: #a5121c; }
.enlace:hover { text-decoration: underline; }
.tarjeta { display: flex; flex-direction: column; padding: 1rem 1.25rem; background: #fff; border-radius: .5rem; border-top: 3px solid #102340; box-shadow: 0 1px 3px rgba(16, 35, 64, .08); }
.tarjeta__valor { font-size: 1.75rem; font-weight: 600; line-height: 1.2; color: #102340; }
.tarjeta__valor--alerta { color: #a5121c; }
.tarjeta__rotulo { margin-top: .25rem; font-size: .9rem; color: #4b5563; }
.tarjeta__nota { margin-top: .25rem; font-size: .75rem; color: #9ca3af; }
.cabeza th { padding: .5rem 0; font-size: .7rem; font-weight: 600; letter-spacing: .05em; text-align: left; text-transform: uppercase; color: #6b7280; border-bottom: 1px solid #e5e7eb; }
.cabeza th.text-right { text-align: right; }
.fila td { padding: .5rem .5rem .5rem 0; border-bottom: 1px solid #f1f5f9; }
.cifra { font-size: 1.35rem; font-weight: 600; color: #102340; }
.etiqueta { padding: .15rem .55rem; font-size: .75rem; border-radius: 999px; white-space: nowrap; }
.etiqueta--licencia { color: #1e3a8a; background: #dbeafe; }
.etiqueta--ausente { color: #991b1b; background: #fee2e2; }
.etiqueta--tarde { color: #92400e; background: #fef3c7; }
</style>
