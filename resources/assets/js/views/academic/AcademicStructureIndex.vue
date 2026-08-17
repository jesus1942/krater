<template>
  <div>
    <sw-card variant="setting-card">
      <template slot="header">
        <h6 class="sw-section-title">Estructura académica</h6>
        <p class="mt-2 text-sm leading-snug text-gray-500">
          Los cursos son la progresión del nivel (1.º, 2.º, 3.º…), las divisiones son cada
          grupo concreto de un ciclo lectivo, y las materias son el diseño curricular. Todo
          es propio de este nivel: usá el selector de la barra superior para cambiar.
        </p>
      </template>

      <div
        v-if="!loading && !years.length"
        class="p-4 mb-5 text-sm border rounded-lg border-yellow-200 bg-yellow-50 text-yellow-800"
      >
        Todavía no hay ningún ciclo lectivo en este nivel. Creá uno en
        <router-link class="underline" :to="{ name: 'academic.years' }">Ciclos lectivos</router-link>
        antes de armar las divisiones.
      </div>

      <div class="flex gap-1 mb-6 border-b border-gray-200">
        <button
          v-for="t in tabs"
          :key="t.id"
          type="button"
          class="px-4 py-2 -mb-px text-sm font-medium border-b-2"
          :class="tab === t.id
            ? 'border-primary-500 text-primary-600'
            : 'border-transparent text-gray-500 hover:text-gray-700'"
          @click="tab = t.id"
        >
          {{ t.label }}
          <span class="ml-1 text-xs text-gray-400">{{ t.count }}</span>
        </button>
      </div>

      <div v-if="loading" class="py-10 text-center text-gray-500">Cargando…</div>

      <div v-else-if="error" class="p-4 text-sm border rounded-lg border-red-200 bg-red-50 text-red-700">
        {{ error }}
      </div>

      <div v-else>
        <section v-show="tab === 'cursos'">
          <p class="mb-4 text-sm text-gray-600">
            El orden define la progresión, y el curso siguiente es lo que hace posible la
            promoción automática. El último curso se deja sin destino: quien lo aprueba egresa.
          </p>

          <table v-if="gradeLevels.length" class="w-full mb-5 text-sm">
            <thead>
              <tr class="text-xs tracking-wide text-left text-gray-500 uppercase border-b">
                <th class="py-2">Orden</th>
                <th class="py-2">Curso</th>
                <th class="py-2">Promociona a</th>
                <th class="py-2">Unidad pedagógica</th>
                <th class="py-2 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="c in gradeLevels" :key="c.id" class="border-b last:border-0">
                <td class="py-2 font-mono text-gray-500">{{ c.position }}</td>
                <td class="py-2 font-medium">{{ c.name }}</td>
                <td class="py-2 text-gray-600">
                  <span v-if="c.promotes_to">{{ c.promotes_to.name }}</span>
                  <span v-else class="text-xs text-blue-700">Último — egresa</span>
                </td>
                <td class="py-2 text-gray-600">{{ c.pedagogical_unit || '—' }}</td>
                <td class="py-2 text-right">
                  <button type="button" class="text-primary-500 hover:underline" @click="editarCurso(c)">
                    Editar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <p v-else class="mb-5 text-sm text-gray-500">
            Todavía no hay cursos en este nivel.
          </p>

          <form class="p-4 border rounded-lg border-primary-200 bg-primary-50" @submit.prevent="guardarCurso">
            <h5 class="mb-3 text-sm font-semibold">
              {{ cursoEnEdicion ? 'Editar curso' : 'Nuevo curso' }}
            </h5>

            <div class="grid gap-4 md:grid-cols-4">
              <campo label="Nombre" :error="err.name">
                <sw-input v-model="formCurso.name" placeholder="3.er año" required />
              </campo>
              <campo label="Orden" :error="err.position">
                <sw-input v-model.number="formCurso.position" type="number" min="1" required />
              </campo>
              <campo label="Promociona a" :error="err.promotes_to_id">
                <select v-model="formCurso.promotes_to_id" class="w-full base-input">
                  <option value="">Ninguno — es el último</option>
                  <option
                    v-for="c in gradeLevels"
                    :key="c.id"
                    :value="c.id"
                    :disabled="cursoEnEdicion && c.id === cursoEnEdicion.id"
                  >
                    {{ c.name }}
                  </option>
                </select>
              </campo>
              <campo label="Unidad pedagógica">
                <sw-input v-model="formCurso.pedagogical_unit" placeholder="1.º y 2.º" />
              </campo>
            </div>

            <div class="flex justify-end gap-3 mt-4">
              <sw-button v-if="cursoEnEdicion" type="button" variant="primary-outline" size="sm" @click="cancelarCurso">
                Cancelar
              </sw-button>
              <sw-button :loading="saving" variant="primary" size="sm">
                {{ cursoEnEdicion ? 'Guardar' : 'Crear curso' }}
              </sw-button>
            </div>
          </form>
        </section>

        <section v-show="tab === 'divisiones'">
          <div class="flex items-center gap-3 mb-4">
            <label class="text-sm text-gray-700">Ciclo lectivo</label>
            <select v-model="cicloSeleccionado" class="base-input" @change="cargarDivisiones">
              <option v-for="a in years" :key="a.id" :value="a.id">
                {{ a.name }}
              </option>
            </select>
          </div>

          <table v-if="divisions.length" class="w-full mb-5 text-sm">
            <thead>
              <tr class="text-xs tracking-wide text-left text-gray-500 uppercase border-b">
                <th class="py-2">División</th>
                <th class="py-2">Turno</th>
                <th class="py-2">Capacidad</th>
                <th class="py-2 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in divisions" :key="d.id" class="border-b last:border-0">
                <td class="py-2 font-medium">
                  {{ d.grade_level ? d.grade_level.name : '' }} {{ d.name }}
                </td>
                <td class="py-2 text-gray-600">{{ d.shift || '—' }}</td>
                <td class="py-2 text-gray-600">{{ d.capacity || '—' }}</td>
                <td class="py-2 text-right">
                  <button type="button" class="text-primary-500 hover:underline" @click="editarDivision(d)">
                    Editar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <p v-else-if="cicloSeleccionado" class="mb-5 text-sm text-gray-500">
            Todavía no hay divisiones para este ciclo.
          </p>

          <form
            v-if="years.length && gradeLevels.length"
            class="p-4 border rounded-lg border-primary-200 bg-primary-50"
            @submit.prevent="guardarDivision"
          >
            <h5 class="mb-3 text-sm font-semibold">
              {{ divisionEnEdicion ? 'Editar división' : 'Nueva división' }}
            </h5>

            <div class="grid gap-4 md:grid-cols-4">
              <campo label="Curso" :error="err.grade_level_id">
                <select v-model="formDivision.grade_level_id" class="w-full base-input" required>
                  <option value="" disabled>Elegí un curso</option>
                  <option v-for="c in gradeLevels" :key="c.id" :value="c.id">
                    {{ c.name }}
                  </option>
                </select>
              </campo>
              <campo label="Nombre" :error="err.name">
                <sw-input v-model="formDivision.name" placeholder="A" required />
              </campo>
              <campo label="Turno" :error="err.shift">
                <sw-input v-model="formDivision.shift" placeholder="Mañana" />
              </campo>
              <campo label="Capacidad" :error="err.capacity">
                <sw-input v-model.number="formDivision.capacity" type="number" min="1" />
              </campo>
            </div>

            <div class="flex justify-end gap-3 mt-4">
              <sw-button v-if="divisionEnEdicion" type="button" variant="primary-outline" size="sm" @click="cancelarDivision">
                Cancelar
              </sw-button>
              <sw-button :loading="saving" variant="primary" size="sm">
                {{ divisionEnEdicion ? 'Guardar' : 'Crear división' }}
              </sw-button>
            </div>
          </form>

          <p v-else-if="!gradeLevels.length" class="text-sm text-gray-500">
            Creá al menos un curso antes de armar divisiones.
          </p>
        </section>

        <section v-show="tab === 'materias'">
          <table v-if="subjects.length" class="w-full mb-5 text-sm">
            <thead>
              <tr class="text-xs tracking-wide text-left text-gray-500 uppercase border-b">
                <th class="py-2">Materia</th>
                <th class="py-2">Año</th>
                <th class="py-2">Duración</th>
                <th class="py-2">Horas</th>
                <th class="py-2">Promoción</th>
                <th class="py-2 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="m in subjects"
                :key="m.id"
                class="border-b last:border-0"
                :class="{ 'opacity-50': !m.enabled }"
              >
                <td class="py-2 font-medium">
                  {{ m.name }}
                  <span v-if="!m.enabled" class="ml-1 text-xs text-gray-500">(deshabilitada)</span>
                </td>
                <td class="py-2 text-gray-600">{{ m.year_of_plan || '—' }}</td>
                <td class="py-2 text-gray-600">{{ duracion(m.duration) }}</td>
                <td class="py-2 text-gray-600">{{ m.weekly_hours ? m.weekly_hours + ' semanales' : '—' }}</td>
                <td class="py-2 text-gray-600">
                  {{ m.counts_for_promotion ? 'Computa' : 'No computa' }}
                </td>
                <td class="py-2 text-right">
                  <button type="button" class="text-primary-500 hover:underline" @click="editarMateria(m)">
                    Editar
                  </button>
                  <button
                    v-if="m.enabled"
                    type="button"
                    class="ml-3 text-red-600 hover:underline"
                    @click="deshabilitarMateria(m)"
                  >
                    Deshabilitar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <p v-else class="mb-5 text-sm text-gray-500">
            Todavía no hay materias en este nivel.
          </p>

          <form class="p-4 border rounded-lg border-primary-200 bg-primary-50" @submit.prevent="guardarMateria">
            <h5 class="mb-3 text-sm font-semibold">
              {{ materiaEnEdicion ? 'Editar materia' : 'Nueva materia' }}
            </h5>

            <div class="grid gap-4 md:grid-cols-4">
              <campo label="Nombre" :error="err.name">
                <sw-input v-model="formMateria.name" placeholder="Matemática" required />
              </campo>
              <campo label="Año del plan" :error="err.year_of_plan">
                <sw-input v-model.number="formMateria.year_of_plan" type="number" min="1" max="10" />
              </campo>
              <campo label="Duración">
                <select v-model="formMateria.duration" class="w-full base-input">
                  <option value="annual">Anual</option>
                  <option value="first_semester">1.er cuatrimestre</option>
                  <option value="second_semester">2.º cuatrimestre</option>
                  <option value="modular">Modular</option>
                </select>
              </campo>
              <campo label="Horas semanales" :error="err.weekly_hours">
                <sw-input v-model.number="formMateria.weekly_hours" type="number" min="1" max="40" />
              </campo>
            </div>

            <label class="flex items-center gap-2 mt-4 text-sm">
              <input v-model="formMateria.counts_for_promotion" type="checkbox" />
              Computa para la promoción
              <span class="text-xs text-gray-500">
                (destildado, se evalúa igual pero no bloquea el pase de curso)
              </span>
            </label>

            <div class="flex justify-end gap-3 mt-4">
              <sw-button v-if="materiaEnEdicion" type="button" variant="primary-outline" size="sm" @click="cancelarMateria">
                Cancelar
              </sw-button>
              <sw-button :loading="saving" variant="primary" size="sm">
                {{ materiaEnEdicion ? 'Guardar' : 'Crear materia' }}
              </sw-button>
            </div>
          </form>
        </section>
      </div>
    </sw-card>
  </div>
</template>

<script>
const Campo = {
  props: ['label', 'error'],
  template: `<label class="block text-sm text-gray-700">
      <span class="block mb-1">{{ label }}</span>
      <slot />
      <span v-if="error" class="block mt-1 text-xs text-red-600">{{ error[0] }}</span>
    </label>`,
}

export default {
  components: { Campo },

  data() {
    return {
      tab: 'cursos',
      loading: true,
      saving: false,
      error: null,
      err: {},

      years: [],
      cicloSeleccionado: '',
      gradeLevels: [],
      divisions: [],
      subjects: [],

      cursoEnEdicion: null,
      divisionEnEdicion: null,
      materiaEnEdicion: null,

      formCurso: this.cursoVacio(),
      formDivision: this.divisionVacia(),
      formMateria: this.materiaVacia(),
    }
  },

  computed: {
    tabs() {
      return [
        { id: 'cursos', label: 'Cursos', count: this.gradeLevels.length },
        { id: 'divisiones', label: 'Divisiones', count: this.divisions.length },
        { id: 'materias', label: 'Materias', count: this.subjects.length },
      ]
    },
  },

  async created() {
    await this.load()
  },

  methods: {
    cursoVacio: () => ({ name: '', position: null, promotes_to_id: '', pedagogical_unit: '' }),
    divisionVacia: () => ({ grade_level_id: '', name: '', shift: '', capacity: null }),
    materiaVacia: () => ({
      name: '', year_of_plan: null, duration: 'annual',
      weekly_hours: null, counts_for_promotion: true,
    }),

    async load() {
      this.loading = true
      this.error = null

      try {
        const [ciclos, cursos, materias] = await Promise.all([
          window.axios.get('/api/v1/academic-years'),
          window.axios.get('/api/v1/grade-levels'),
          window.axios.get('/api/v1/subjects'),
        ])

        this.years = ciclos.data.data
        this.gradeLevels = cursos.data.data
        this.subjects = materias.data.data

        if (this.years.length) {
          const enCurso = this.years.find((a) => a.status === 'active')
          this.cicloSeleccionado = (enCurso || this.years[0]).id
          await this.cargarDivisiones()
        }
      } catch (e) {
        this.error =
          e.response && e.response.status === 403
            ? 'No tenés permiso para ver la estructura académica de este nivel.'
            : 'No se pudo cargar la estructura académica.'
      } finally {
        this.loading = false
      }
    },

    async cargarDivisiones() {
      if (!this.cicloSeleccionado) return

      const { data } = await window.axios.get('/api/v1/divisions', {
        params: { academic_year_id: this.cicloSeleccionado },
      })

      this.divisions = data.data
    },

    editarCurso(c) {
      this.cursoEnEdicion = c
      this.err = {}
      this.formCurso = {
        name: c.name,
        position: c.position,
        promotes_to_id: c.promotes_to_id || '',
        pedagogical_unit: c.pedagogical_unit || '',
      }
    },
    cancelarCurso() {
      this.cursoEnEdicion = null
      this.formCurso = this.cursoVacio()
      this.err = {}
    },
    async guardarCurso() {
      await this.enviar(
        () => this.cursoEnEdicion
          ? window.axios.put(`/api/v1/grade-levels/${this.cursoEnEdicion.id}`, this.payloadCurso())
          : window.axios.post('/api/v1/grade-levels', this.payloadCurso()),
        () => { this.cancelarCurso() }
      )
    },
    payloadCurso() {
      return { ...this.formCurso, promotes_to_id: this.formCurso.promotes_to_id || null }
    },

    editarDivision(d) {
      this.divisionEnEdicion = d
      this.err = {}
      this.formDivision = {
        grade_level_id: d.grade_level_id,
        name: d.name,
        shift: d.shift || '',
        capacity: d.capacity,
      }
    },
    cancelarDivision() {
      this.divisionEnEdicion = null
      this.formDivision = this.divisionVacia()
      this.err = {}
    },
    async guardarDivision() {
      const payload = {
        ...this.formDivision,
        academic_year_id: this.cicloSeleccionado,
        shift: this.formDivision.shift || null,
      }

      await this.enviar(
        () => this.divisionEnEdicion
          ? window.axios.put(`/api/v1/divisions/${this.divisionEnEdicion.id}`, payload)
          : window.axios.post('/api/v1/divisions', payload),
        () => { this.cancelarDivision() }
      )
    },

    editarMateria(m) {
      this.materiaEnEdicion = m
      this.err = {}
      this.formMateria = {
        name: m.name,
        year_of_plan: m.year_of_plan,
        duration: m.duration,
        weekly_hours: m.weekly_hours,
        counts_for_promotion: m.counts_for_promotion,
      }
    },
    cancelarMateria() {
      this.materiaEnEdicion = null
      this.formMateria = this.materiaVacia()
      this.err = {}
    },
    async guardarMateria() {
      await this.enviar(
        () => this.materiaEnEdicion
          ? window.axios.put(`/api/v1/subjects/${this.materiaEnEdicion.id}`, this.formMateria)
          : window.axios.post('/api/v1/subjects', this.formMateria),
        () => { this.cancelarMateria() }
      )
    },

    async deshabilitarMateria(m) {
      const ok = window.confirm(
        `¿Deshabilitar "${m.name}"? Deja de ofrecerse para secciones nuevas, ` +
          'pero el historial de calificaciones se conserva.'
      )

      if (!ok) return

      await this.enviar(() => window.axios.delete(`/api/v1/subjects/${m.id}`), () => {})
    },

    async enviar(peticion, alTerminar) {
      this.saving = true
      this.err = {}

      try {
        await peticion()
        alTerminar()
        await this.load()
      } catch (e) {
        if (e.response && e.response.status === 422) {
          this.err = e.response.data.errors || {}
        } else if (e.response && e.response.status === 403) {
          this.error = 'No tenés permiso para modificar la estructura académica de este nivel.'
        } else {
          this.error = 'No se pudo guardar el cambio.'
        }
      } finally {
        this.saving = false
      }
    },

    duracion(v) {
      return {
        annual: 'Anual',
        first_semester: '1.er cuatrimestre',
        second_semester: '2.º cuatrimestre',
        modular: 'Modular',
      }[v] || v
    },
  },
}
</script>
