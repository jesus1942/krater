<template>
  <div>
    <sw-card variant="setting-card">
      <template slot="header">
        <h6 class="sw-section-title">Ciclos lectivos</h6>
        <p class="mt-2 text-sm leading-snug text-gray-500">
          El ciclo lectivo es la unidad de corte de toda la vida académica: sin ciclo no hay
          matrícula, ni nota, ni promoción. Cada nivel tiene los suyos, y se administran por
          separado usando el selector de nivel de la barra superior.
        </p>
      </template>

      <div v-if="loading" class="py-10 text-center text-gray-500">
        Cargando ciclos lectivos…
      </div>

      <div v-else-if="error" class="p-4 text-sm border rounded-lg border-red-200 bg-red-50 text-red-700">
        {{ error }}
      </div>

      <div v-else>
        <!-- estado vacío: la primera vez no hay nada, y conviene decir qué hacer -->
        <div
          v-if="!years.length && !creating"
          class="px-6 py-12 text-center border border-dashed rounded-lg border-gray-300"
        >
          <p class="text-base text-gray-700">Todavía no hay ningún ciclo lectivo en este nivel.</p>
          <p class="mt-1 mb-6 text-sm text-gray-500">
            Creá el primero para poder cargar cursos, divisiones y matrículas.
          </p>
          <sw-button variant="primary" @click="startCreate">Crear ciclo lectivo</sw-button>
        </div>

        <div v-else class="space-y-4">
          <div v-if="!creating" class="flex justify-end">
            <sw-button variant="primary-outline" @click="startCreate">Nuevo ciclo lectivo</sw-button>
          </div>

          <!-- formulario de alta o edición -->
          <form
            v-if="creating || editing"
            class="p-5 border rounded-lg border-primary-200 bg-primary-50"
            @submit.prevent="save"
          >
            <h4 class="mb-4 text-base font-semibold">
              {{ editing ? 'Editar ciclo lectivo' : 'Nuevo ciclo lectivo' }}
            </h4>

            <div class="grid gap-4 md:grid-cols-2">
              <label class="block text-sm text-gray-700">
                <span class="block mb-1">Año <span class="text-red-500">*</span></span>
                <sw-input v-model.number="form.year" type="number" min="2000" max="2100" required />
                <span v-if="fieldErrors.year" class="block mt-1 text-xs text-red-600">
                  {{ fieldErrors.year[0] }}
                </span>
              </label>

              <label class="block text-sm text-gray-700">
                <span class="block mb-1">Nombre <span class="text-red-500">*</span></span>
                <sw-input v-model="form.name" placeholder="Ciclo lectivo 2027" required />
                <span v-if="fieldErrors.name" class="block mt-1 text-xs text-red-600">
                  {{ fieldErrors.name[0] }}
                </span>
              </label>

              <label class="block text-sm text-gray-700">
                <span class="block mb-1">Inicio de clases <span class="text-red-500">*</span></span>
                <sw-input v-model="form.starts_on" type="date" required />
              </label>

              <label class="block text-sm text-gray-700">
                <span class="block mb-1">Fin de clases <span class="text-red-500">*</span></span>
                <sw-input v-model="form.ends_on" type="date" required />
                <span v-if="fieldErrors.ends_on" class="block mt-1 text-xs text-red-600">
                  {{ fieldErrors.ends_on[0] }}
                </span>
              </label>
            </div>

            <div class="flex justify-end gap-3 mt-6">
              <sw-button type="button" variant="primary-outline" @click="cancel">Cancelar</sw-button>
              <sw-button :loading="saving" variant="primary">
                {{ editing ? 'Guardar cambios' : 'Crear ciclo' }}
              </sw-button>
            </div>
          </form>

          <!-- listado -->
          <table v-if="years.length" class="w-full text-sm">
            <thead>
              <tr class="text-xs tracking-wide text-left text-gray-500 uppercase border-b">
                <th class="py-3">Año</th>
                <th class="py-3">Nombre</th>
                <th class="py-3">Cursada</th>
                <th class="py-3">Períodos</th>
                <th class="py-3">Estado</th>
                <th class="py-3 text-right">Acciones</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="year in years" :key="year.id" class="border-b last:border-0">
                <td class="py-3 font-semibold">{{ year.year }}</td>
                <td class="py-3">{{ year.name }}</td>
                <td class="py-3 text-gray-600">
                  {{ formatDate(year.starts_on) }} al {{ formatDate(year.ends_on) }}
                </td>
                <td class="py-3 text-gray-600">
                  {{ (year.terms || []).length || 'sin definir' }}
                </td>
                <td class="py-3">
                  <span class="px-2 py-1 text-xs rounded-full" :class="statusClass(year.status)">
                    {{ statusLabel(year.status) }}
                  </span>
                </td>
                <td class="py-3 text-right">
                  <button
                    v-if="year.status !== 'closed'"
                    type="button"
                    class="text-primary-500 hover:underline"
                    @click="startEdit(year)"
                  >
                    Editar
                  </button>
                  <!-- Un ciclo cerrado es historia: no se edita ni se borra. Para
                       tocarlo hay que reabrirlo, y eso pasa por doble control. -->
                  <span v-else class="text-xs text-gray-400">Cerrado</span>
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </div>
    </sw-card>
  </div>
</template>

<script>
export default {
  data() {
    return {
      years: [],
      loading: true,
      saving: false,
      creating: false,
      editing: null,
      error: null,
      fieldErrors: {},
      form: this.emptyForm(),
    }
  },

  async created() {
    await this.load()
  },

  methods: {
    emptyForm() {
      const proximoAnio = new Date().getFullYear() + 1

      return {
        year: proximoAnio,
        name: `Ciclo lectivo ${proximoAnio}`,
        starts_on: '',
        ends_on: '',
      }
    },

    async load() {
      this.loading = true
      this.error = null

      try {
        const { data } = await window.axios.get('/api/v1/academic-years')
        this.years = data.data
      } catch (e) {
        // Un 403 acá casi siempre significa que la persona no tiene el permiso
        // en el nivel seleccionado, no que algo se rompió.
        this.error =
          e.response && e.response.status === 403
            ? 'No tenés permiso para ver los ciclos lectivos de este nivel.'
            : 'No se pudieron cargar los ciclos lectivos.'
      } finally {
        this.loading = false
      }
    },

    startCreate() {
      this.editing = null
      this.form = this.emptyForm()
      this.fieldErrors = {}
      this.creating = true
    },

    startEdit(year) {
      this.creating = false
      this.editing = year
      this.fieldErrors = {}
      this.form = {
        year: year.year,
        name: year.name,
        starts_on: (year.starts_on || '').substring(0, 10),
        ends_on: (year.ends_on || '').substring(0, 10),
      }
    },

    cancel() {
      this.creating = false
      this.editing = null
      this.fieldErrors = {}
    },

    async save() {
      this.saving = true
      this.fieldErrors = {}

      try {
        if (this.editing) {
          await window.axios.put(`/api/v1/academic-years/${this.editing.id}`, this.form)
        } else {
          await window.axios.post('/api/v1/academic-years', this.form)
        }

        this.cancel()
        await this.load()
      } catch (e) {
        if (e.response && e.response.status === 422) {
          this.fieldErrors = e.response.data.errors || {}
        } else if (e.response && e.response.status === 403) {
          this.error = 'No tenés permiso para modificar ciclos lectivos en este nivel.'
        } else {
          this.error = 'No se pudo guardar el ciclo lectivo.'
        }
      } finally {
        this.saving = false
      }
    },

    formatDate(value) {
      if (!value) return '—'

      const [anio, mes, dia] = value.substring(0, 10).split('-')

      return `${dia}/${mes}/${anio}`
    },

    statusLabel(status) {
      return {
        draft: 'En preparación',
        active: 'En curso',
        closing: 'En cierre',
        closed: 'Cerrado',
      }[status] || status
    },

    statusClass(status) {
      return {
        draft: 'bg-gray-100 text-gray-700',
        active: 'bg-green-100 text-green-800',
        closing: 'bg-yellow-100 text-yellow-800',
        closed: 'bg-blue-100 text-blue-800',
      }[status] || 'bg-gray-100 text-gray-700'
    },
  },
}
</script>
