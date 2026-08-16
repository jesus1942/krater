<template>
  <div>
    <sw-card variant="setting-card">
      <template slot="header">
        <h6 class="sw-section-title">Matrículas</h6>
        <p class="mt-2 text-sm leading-snug text-gray-500">
          Quién cursa en cada división. La matrícula es el registro que se promociona al
          cerrar el año, así que no se borra: se le cambia el estado. Un alumno que se fue en
          mayo tiene que seguir figurando en el ciclo, con su fecha de baja.
        </p>
      </template>

      <div v-if="loading" class="py-10 text-center text-gray-500">Cargando…</div>

      <div v-else-if="error" class="p-4 text-sm border rounded-lg border-red-200 bg-red-50 text-red-700">
        {{ error }}
      </div>

      <div v-else-if="!divisions.length" class="px-6 py-12 text-center border border-dashed rounded-lg border-gray-300">
        <p class="text-base text-gray-700">No hay divisiones en este nivel.</p>
        <p class="mt-1 text-sm text-gray-500">
          Armá la estructura en
          <router-link class="underline" :to="{ name: 'academic.structure' }">Estructura académica</router-link>
          antes de matricular.
        </p>
      </div>

      <div v-else>
        <div class="flex flex-wrap items-end gap-4 mb-5">
          <label class="text-sm text-gray-700">
            <span class="block mb-1">Ciclo lectivo</span>
            <select v-model="cicloId" class="base-input" @change="alCambiarCiclo">
              <option v-for="a in years" :key="a.id" :value="a.id">{{ a.name }}</option>
            </select>
          </label>

          <label class="text-sm text-gray-700">
            <span class="block mb-1">División</span>
            <select v-model="divisionId" class="base-input" @change="cargarMatriculas">
              <option value="">Elegí una división</option>
              <option v-for="d in divisionesDelCiclo" :key="d.id" :value="d.id">
                {{ d.grade_level ? d.grade_level.name : '' }} {{ d.name }}
              </option>
            </select>
          </label>

          <div v-if="meta.cupo_disponible !== null && meta.cupo_disponible !== undefined" class="text-sm">
            <span class="block mb-1 text-gray-500">Cupo</span>
            <span
              class="px-2 py-1 text-xs rounded-full"
              :class="meta.cupo_disponible > 0 ? 'bg-green-100 text-green-800' : 'bg-red-100 text-red-800'"
            >
              {{ meta.cupo_disponible }} {{ meta.cupo_disponible === 1 ? 'lugar' : 'lugares' }}
            </span>
          </div>
        </div>

        <div v-if="divisionId">
          <table v-if="enrollments.length" class="w-full mb-6 text-sm">
            <thead>
              <tr class="text-xs tracking-wide text-left text-gray-500 uppercase border-b">
                <th class="py-2">Estudiante</th>
                <th class="py-2">DNI</th>
                <th class="py-2">Desde</th>
                <th class="py-2">Condición</th>
                <th class="py-2">Estado</th>
                <th class="py-2 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="m in enrollments"
                :key="m.id"
                class="border-b last:border-0"
                :class="{ 'opacity-60': m.status !== 'active' }"
              >
                <td class="py-2 font-medium">
                  {{ m.student ? m.student.last_name + ', ' + m.student.first_name : '—' }}
                  <span v-if="m.has_curricular_adaptation" class="ml-1 text-xs text-blue-700">
                    con adecuación
                  </span>
                </td>
                <td class="py-2 text-gray-600">{{ m.student ? m.student.dni || '—' : '—' }}</td>
                <td class="py-2 text-gray-600">{{ fecha(m.enrolled_on) }}</td>
                <td class="py-2 text-gray-600">{{ condicion(m.attendance_condition) }}</td>
                <td class="py-2">
                  <span class="px-2 py-1 text-xs rounded-full" :class="claseEstado(m.status)">
                    {{ estado(m.status) }}
                  </span>
                  <span v-if="m.left_on" class="block mt-1 text-xs text-gray-500">
                    {{ fecha(m.left_on) }}
                  </span>
                </td>
                <td class="py-2 text-right">
                  <button
                    v-if="m.status === 'active'"
                    type="button"
                    class="text-primary-500 hover:underline"
                    @click="abrirBaja(m)"
                  >
                    Dar de baja
                  </button>
                  <button
                    v-else
                    type="button"
                    class="text-primary-500 hover:underline"
                    @click="reincorporar(m)"
                  >
                    Reincorporar
                  </button>
                </td>
              </tr>
            </tbody>
          </table>

          <p v-else class="mb-6 text-sm text-gray-500">
            Todavía no hay nadie matriculado en esta división.
          </p>

          <form class="p-4 border rounded-lg border-primary-200 bg-primary-50" @submit.prevent="matricular">
            <h5 class="mb-3 text-sm font-semibold">Matricular un estudiante</h5>

            <div class="grid gap-4 md:grid-cols-3">
              <label class="block text-sm text-gray-700 md:col-span-2">
                <span class="block mb-1">Buscar por nombre o DNI</span>
                <sw-input v-model="busqueda" placeholder="Escribí al menos 2 letras…" @input="buscar" />
                <span class="block mt-1 text-xs text-gray-500">
                  Solo aparecen quienes todavía no están matriculados en este ciclo.
                </span>
              </label>

              <label class="block text-sm text-gray-700">
                <span class="block mb-1">Fecha de ingreso</span>
                <sw-input v-model="formAlta.enrolled_on" type="date" />
              </label>
            </div>

            <div v-if="candidatos.length" class="mt-3 overflow-y-auto border rounded max-h-48 bg-white">
              <button
                v-for="c in candidatos"
                :key="c.id"
                type="button"
                class="block w-full px-3 py-2 text-sm text-left hover:bg-gray-50"
                :class="{ 'bg-primary-100': formAlta.student_id === c.id }"
                @click="formAlta.student_id = c.id"
              >
                {{ c.last_name }}, {{ c.first_name }}
                <span class="text-xs text-gray-500">{{ c.dni ? '· ' + c.dni : '' }}</span>
              </button>
            </div>

            <p v-else-if="busqueda.length >= 2 && !buscando" class="mt-3 text-sm text-gray-500">
              No hay estudiantes sin matricular que coincidan.
            </p>

            <label class="flex items-center gap-2 mt-4 text-sm">
              <input v-model="formAlta.has_curricular_adaptation" type="checkbox" />
              Tiene adecuación curricular
              <span class="text-xs text-gray-500">
                (el detalle pedagógico va en el legajo, no acá)
              </span>
            </label>

            <p v-if="errorAlta" class="mt-3 text-sm text-red-600">{{ errorAlta }}</p>

            <div class="flex justify-end mt-4">
              <sw-button :loading="saving" :disabled="!formAlta.student_id" variant="primary" size="sm">
                Matricular
              </sw-button>
            </div>
          </form>
        </div>
      </div>
    </sw-card>

    <div v-if="bajaDe" class="fixed inset-0 z-50 flex items-center justify-center p-4 bg-black bg-opacity-40">
      <div class="w-full max-w-md p-6 bg-white rounded-lg">
        <h4 class="mb-1 text-base font-semibold">Dar de baja la matrícula</h4>
        <p class="mb-4 text-sm text-gray-600">
          {{ bajaDe.student ? bajaDe.student.last_name + ', ' + bajaDe.student.first_name : '' }}
        </p>

        <label class="block mb-4 text-sm text-gray-700">
          <span class="block mb-1">Motivo</span>
          <select v-model="formBaja.status" class="w-full base-input">
            <option value="transferred_out">Pase a otra escuela</option>
            <option value="withdrawn">Baja</option>
          </select>
          <span v-if="formBaja.status === 'transferred_out'" class="block mt-1 text-xs text-gray-500">
            El pase requiere permiso específico y queda registrado en la auditoría.
          </span>
        </label>

        <label class="block mb-4 text-sm text-gray-700">
          <span class="block mb-1">Fecha</span>
          <sw-input v-model="formBaja.left_on" type="date" />
          <span class="block mt-1 text-xs text-gray-500">Si la dejás vacía se usa hoy.</span>
        </label>

        <p v-if="errorBaja" class="mb-3 text-sm text-red-600">{{ errorBaja }}</p>

        <div class="flex justify-end gap-3">
          <sw-button type="button" variant="primary-outline" size="sm" @click="bajaDe = null">
            Cancelar
          </sw-button>
          <sw-button :loading="saving" variant="primary" size="sm" @click="confirmarBaja">
            Confirmar
          </sw-button>
        </div>
      </div>
    </div>
  </div>
</template>

<script>
export default {
  data() {
    return {
      loading: true,
      saving: false,
      buscando: false,
      error: null,
      errorAlta: null,
      errorBaja: null,
      years: [],
      divisions: [],
      cicloId: '',
      divisionId: '',
      enrollments: [],
      meta: {},
      busqueda: '',
      candidatos: [],
      formAlta: { student_id: null, enrolled_on: '', has_curricular_adaptation: false },
      bajaDe: null,
      formBaja: { status: 'transferred_out', left_on: '' },
      temporizador: null,
    }
  },

  computed: {
    divisionesDelCiclo() {
      return this.divisions.filter((d) => String(d.academic_year_id) === String(this.cicloId))
    },
  },

  async created() {
    await this.load()
  },

  methods: {
    async load() {
      this.loading = true
      this.error = null

      try {
        const { data: ciclos } = await window.axios.get('/api/v1/academic-years')
        this.years = ciclos.data

        if (this.years.length) {
          const enCurso = this.years.find((a) => a.status === 'active')
          this.cicloId = (enCurso || this.years[0]).id
          await this.cargarDivisiones()
        }
      } catch (e) {
        this.error =
          e.response && e.response.status === 403
            ? 'No tenés permiso para ver las matrículas de este nivel.'
            : 'No se pudieron cargar los datos.'
      } finally {
        this.loading = false
      }
    },

    async cargarDivisiones() {
      const { data } = await window.axios.get('/api/v1/divisions', {
        params: { academic_year_id: this.cicloId },
      })
      this.divisions = data.data
    },

    async alCambiarCiclo() {
      this.divisionId = ''
      this.enrollments = []
      this.meta = {}
      await this.cargarDivisiones()
    },

    async cargarMatriculas() {
      if (!this.divisionId) {
        this.enrollments = []
        this.meta = {}
        return
      }

      try {
        const { data } = await window.axios.get('/api/v1/enrollments', {
          params: { division_id: this.divisionId },
        })
        this.enrollments = data.data
        this.meta = data.meta
      } catch (e) {
        this.error =
          e.response && e.response.status === 403
            ? 'Esa división no está dentro de tu alcance.'
            : 'No se pudieron cargar las matrículas.'
      }
    },

    buscar() {
      clearTimeout(this.temporizador)
      if (this.busqueda.length < 2) {
        this.candidatos = []
        return
      }

      this.temporizador = setTimeout(async () => {
        this.buscando = true
        try {
          const { data } = await window.axios.get('/api/v1/enrollments/disponibles', {
            params: { academic_year_id: this.cicloId, buscar: this.busqueda },
          })
          this.candidatos = data.data
        } finally {
          this.buscando = false
        }
      }, 300)
    },

    async matricular() {
      this.saving = true
      this.errorAlta = null
      try {
        await window.axios.post('/api/v1/enrollments', {
          student_id: this.formAlta.student_id,
          division_id: this.divisionId,
          enrolled_on: this.formAlta.enrolled_on || null,
          has_curricular_adaptation: this.formAlta.has_curricular_adaptation,
        })
        this.formAlta = { student_id: null, enrolled_on: '', has_curricular_adaptation: false }
        this.busqueda = ''
        this.candidatos = []
        await this.cargarMatriculas()
      } catch (e) {
        this.errorAlta = this.mensaje(e, 'No se pudo matricular.')
      } finally {
        this.saving = false
      }
    },

    abrirBaja(m) {
      this.bajaDe = m
      this.errorBaja = null
      this.formBaja = { status: 'transferred_out', left_on: '' }
    },

    async confirmarBaja() {
      this.saving = true
      this.errorBaja = null
      try {
        await window.axios.put(`/api/v1/enrollments/${this.bajaDe.id}`, {
          status: this.formBaja.status,
          left_on: this.formBaja.left_on || null,
        })
        this.bajaDe = null
        await this.cargarMatriculas()
      } catch (e) {
        this.errorBaja = this.mensaje(e, 'No se pudo dar de baja.')
      } finally {
        this.saving = false
      }
    },

    async reincorporar(m) {
      const ok = window.confirm('¿Reincorporar esta matrícula? Vuelve a figurar como activa.')
      if (!ok) return

      try {
        await window.axios.put(`/api/v1/enrollments/${m.id}`, { status: 'active' })
        await this.cargarMatriculas()
      } catch (e) {
        this.error = this.mensaje(e, 'No se pudo reincorporar.')
      }
    },

    mensaje(e, porDefecto) {
      if (!e.response) return porDefecto
      if (e.response.status === 403) return 'No tenés permiso para esta acción en este nivel.'
      const d = e.response.data || {}
      if (d.errors) {
        const primero = Object.values(d.errors)[0]
        if (primero && primero.length) return primero[0]
      }
      return d.message || porDefecto
    },

    fecha(v) {
      if (!v) return '—'
      const [a, m, d] = String(v).substring(0, 10).split('-')
      return `${d}/${m}/${a}`
    },

    estado(s) {
      return {
        active: 'Activa',
        transferred_out: 'Pase',
        withdrawn: 'Baja',
        completed: 'Completó',
      }[s] || s
    },

    claseEstado(s) {
      return {
        active: 'bg-green-100 text-green-800',
        transferred_out: 'bg-yellow-100 text-yellow-800',
        withdrawn: 'bg-gray-200 text-gray-700',
        completed: 'bg-blue-100 text-blue-800',
      }[s] || 'bg-gray-100 text-gray-700'
    },

    condicion(c) {
      return { regular: 'Regular', libre: 'Libre', oyente: 'Oyente' }[c] || c || '—'
    },
  },
}
</script>
