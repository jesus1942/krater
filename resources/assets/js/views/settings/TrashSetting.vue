<template>
  <div class="px-4 pb-8 sm:px-6">
    <div class="mb-6">
      <h2 class="text-xl font-semibold text-gray-800">Papelera</h2>
      <p class="mt-1 text-sm text-gray-500">
        Registros dados de baja. Se pueden restaurar; no se borran en forma definitiva.
      </p>
    </div>

    <div
      v-if="!isWholeInstitutionContext"
      class="p-4 mb-5 text-sm text-yellow-800 bg-yellow-50 border border-yellow-200 rounded"
    >
      La papelera sólo funciona en <strong>Toda la institución</strong>. Cambiá el selector de nivel antes de continuar.
    </div>

    <template v-else>
      <label class="block mb-5 text-sm sm:w-64">
        Tipo de registro
        <select
          v-model="type"
          class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded"
          @change="load(1)"
        >
          <option v-for="option in types" :key="option.value" :value="option.value">
            {{ option.label }}
          </option>
        </select>
      </label>

      <div
        v-if="error"
        class="p-4 mb-5 text-sm text-red-700 bg-red-50 border border-red-200 rounded"
      >
        {{ error }}
      </div>

      <div v-if="loading" class="p-6 text-sm text-center text-gray-500">
        Cargando la papelera…
      </div>

      <div
        v-else-if="!records.length"
        class="p-5 text-sm text-gray-600 bg-gray-50 border border-gray-200 rounded"
      >
        No hay {{ currentLabel.toLowerCase() }} dados de baja.
      </div>

      <div v-else class="overflow-x-auto bg-white border border-gray-200 rounded shadow-sm">
        <table class="w-full text-sm">
          <thead>
            <tr class="text-left text-gray-500 bg-gray-50">
              <th class="px-4 py-3">ID</th>
              <th class="px-4 py-3">Referencia</th>
              <th class="px-4 py-3">Nivel</th>
              <th class="px-4 py-3">Baja</th>
              <th class="px-4 py-3"></th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="record in records" :key="record.id" class="border-t border-gray-100">
              <td class="px-4 py-3 font-medium text-gray-700">#{{ record.id }}</td>
              <td class="px-4 py-3 text-gray-700">{{ reference(record) }}</td>
              <td class="px-4 py-3 text-gray-600">
                {{ record.school_level_id ? '#' + record.school_level_id : '-' }}
              </td>
              <td class="px-4 py-3 text-gray-600">{{ formatDate(record.deleted_at) }}</td>
              <td class="px-4 py-3 text-right">
                <sw-button
                  variant="primary-outline"
                  size="sm"
                  :loading="restoring === record.id"
                  :disabled="restoring !== null"
                  @click="restore(record)"
                >
                  Restaurar
                </sw-button>
              </td>
            </tr>
          </tbody>
        </table>
      </div>

      <div v-if="lastPage > 1" class="flex items-center justify-end gap-3 mt-4 text-sm">
        <sw-button variant="primary-outline" size="sm" :disabled="page <= 1" @click="load(page - 1)">
          Anterior
        </sw-button>
        <span>{{ page }} / {{ lastPage }}</span>
        <sw-button variant="primary-outline" size="sm" :disabled="page >= lastPage" @click="load(page + 1)">
          Siguiente
        </sw-button>
      </div>
    </template>
  </div>
</template>

<script>
import { mapActions } from 'vuex'

const TYPES = [
  { value: 'invoices', label: 'Facturas', field: 'invoice_number' },
  { value: 'payments', label: 'Cobros', field: 'payment_number' },
  { value: 'estimates', label: 'Presupuestos', field: 'estimate_number' },
  { value: 'expenses', label: 'Gastos', field: 'notes' },
  { value: 'items', label: 'Ítems', field: 'name' },
  { value: 'customers', label: 'Familias', field: 'name' },
]

export default {
  data() {
    return {
      types: TYPES,
      type: 'invoices',
      records: [],
      page: 1,
      lastPage: 1,
      loading: false,
      restoring: null,
      error: '',
    }
  },

  computed: {
    isWholeInstitutionContext() {
      return !window.Ls.get('selectedSchoolLevel')
    },
    currentLabel() {
      return TYPES.find(option => option.value === this.type).label
    },
  },

  created() {
    if (this.isWholeInstitutionContext) {
      this.load(1)
    }
  },

  methods: {
    ...mapActions('notification', ['showNotification']),

    async load(page) {
      this.loading = true
      this.error = ''
      try {
        const response = await window.axios.get(`/api/v1/trash/${this.type}`, { params: { page } })
        const records = response.data.records
        this.records = records.data || []
        this.page = records.current_page || 1
        this.lastPage = records.last_page || 1
      } catch (error) {
        this.records = []
        this.error = this.messageOf(error, 'No se pudo cargar la papelera.')
      } finally {
        this.loading = false
      }
    },

    async restore(record) {
      this.restoring = record.id
      this.error = ''
      try {
        await window.axios.post(`/api/v1/trash/${this.type}/${record.id}/restore`)
        this.showNotification({ type: 'success', message: 'Registro restaurado.' })
        await this.load(this.records.length === 1 && this.page > 1 ? this.page - 1 : this.page)
      } catch (error) {
        this.error = this.messageOf(error, 'No se pudo restaurar el registro.')
      } finally {
        this.restoring = null
      }
    },

    reference(record) {
      const field = TYPES.find(option => option.value === this.type).field
      return record[field] || '-'
    },

    formatDate(value) {
      return value ? new Date(value).toLocaleString('es-AR') : '-'
    },

    messageOf(error, fallback) {
      const data = error.response && error.response.data
      if (data && data.errors) {
        const first = Object.values(data.errors)[0]
        if (first && first.length) return first[0]
      }
      return (data && data.message) || fallback
    },
  },
}
</script>
