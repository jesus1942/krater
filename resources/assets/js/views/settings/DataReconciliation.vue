<template>
  <div class="px-4 pb-8 sm:px-6">
    <div class="mb-6">
      <h2 class="text-xl font-semibold text-gray-800">Registros sin nivel</h2>
      <p class="mt-1 text-sm text-gray-500">
        Reconciliación manual de registros huérfanos. Ningún cambio se aplica sin revisión previa.
      </p>
    </div>

    <div
      v-if="!isWholeInstitutionContext"
      class="p-4 mb-5 text-sm text-yellow-800 bg-yellow-50 border border-yellow-200 rounded"
    >
      Esta herramienta sólo funciona en <strong>Toda la institución</strong>. Cambiá el selector de nivel antes de continuar.
    </div>

    <div
      v-else-if="loading"
      class="p-6 text-sm text-center text-gray-500"
    >
      Buscando registros sin nivel…
    </div>

    <template v-else-if="isWholeInstitutionContext">
      <div
        v-if="error"
        class="p-4 mb-5 text-sm text-red-700 bg-red-50 border border-red-200 rounded"
      >
        {{ error }}
      </div>

      <div
        v-if="!groups.length && !error"
        class="p-5 text-sm text-green-800 bg-green-50 border border-green-200 rounded"
      >
        No hay registros huérfanos para reconciliar.
      </div>

      <section
        v-for="group in groups"
        :key="group.model"
        class="mb-6 bg-white border border-gray-200 rounded shadow-sm"
      >
        <div class="flex flex-wrap items-center justify-between gap-3 px-4 py-3 border-b border-gray-200">
          <div>
            <h3 class="font-semibold text-gray-800">{{ group.label }}</h3>
            <p class="text-xs text-gray-500">
              {{ group.records.length }} registro{{ group.records.length === 1 ? '' : 's' }} pendiente{{ group.records.length === 1 ? '' : 's' }}
            </p>
          </div>
          <sw-button
            variant="primary"
            size="sm"
            :disabled="selectedIds(group.model).length === 0"
            @click="openReconcile(group)"
          >
            Reubicar seleccionados
          </sw-button>
        </div>

        <div class="overflow-x-auto">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-gray-500 bg-gray-50">
                <th class="w-12 px-4 py-3"></th>
                <th class="px-4 py-3">ID</th>
                <th class="px-4 py-3">Referencia</th>
                <th class="px-4 py-3">Problema</th>
                <th class="px-4 py-3">Nivel actual</th>
              </tr>
            </thead>
            <tbody>
              <tr
                v-for="record in group.records"
                :key="record.id"
                class="border-t border-gray-100"
              >
                <td class="px-4 py-3">
                  <input
                    type="checkbox"
                    :checked="isSelected(group.model, record.id)"
                    @change="toggleSelection(group.model, record.id)"
                  />
                </td>
                <td class="px-4 py-3 font-medium text-gray-700">#{{ record.id }}</td>
                <td class="px-4 py-3 text-gray-700">{{ referenceLabel(record.reference) }}</td>
                <td class="px-4 py-3">
                  <span class="px-2 py-1 text-xs text-yellow-800 bg-yellow-100 rounded">
                    {{ orphanReasonLabel(record.orphan_reason) }}
                  </span>
                </td>
                <td class="px-4 py-3 text-gray-600">
                  {{ record.current_school_level_id === null ? 'NULL' : '#' + record.current_school_level_id }}
                </td>
              </tr>
            </tbody>
          </table>
        </div>
      </section>
    </template>

    <div
      v-if="showModal"
      class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto bg-black bg-opacity-50"
    >
      <div class="w-full max-w-2xl p-6 my-8 bg-white rounded shadow-xl">
        <div class="flex items-center justify-between">
          <div>
            <h3 class="text-xl font-semibold">Reconciliar {{ activeGroup ? activeGroup.label : '' }}</h3>
            <p class="mt-1 text-sm text-gray-500">
              {{ activeIds.length }} registro{{ activeIds.length === 1 ? '' : 's' }} seleccionado{{ activeIds.length === 1 ? '' : 's' }}
            </p>
          </div>
          <button class="text-2xl text-gray-400" @click="closeModal">×</button>
        </div>

        <div class="p-3 mt-4 text-sm text-blue-800 bg-blue-50 rounded">
          Primero se simula. El botón Aplicar recién se habilita cuando la vista previa es válida.
        </div>

        <label class="block mt-5 text-sm">
          Nivel destino *
          <select
            v-model="form.school_level_id"
            class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded"
            @change="clearPreview"
          >
            <option value="">Seleccionar nivel</option>
            <option v-for="level in levels" :key="level.id" :value="level.id">
              {{ level.name }}
            </option>
          </select>
        </label>

        <label class="block mt-4 text-sm">
          Motivo *
          <textarea
            v-model="form.reason"
            rows="3"
            maxlength="500"
            class="w-full px-3 py-2 mt-1 bg-white border border-gray-300 rounded"
            placeholder="Ej.: Corrección de clasificación histórica confirmada por administración"
            @input="clearPreview"
          ></textarea>
        </label>

        <div
          v-if="modalError"
          class="p-3 mt-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded"
        >
          {{ modalError }}
        </div>

        <div v-if="previewRows.length" class="mt-5 overflow-x-auto border border-gray-200 rounded">
          <table class="w-full text-sm">
            <thead>
              <tr class="text-left text-gray-500 bg-gray-50">
                <th class="px-3 py-2">ID</th>
                <th class="px-3 py-2">Anterior</th>
                <th class="px-3 py-2">Destino</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="row in previewRows" :key="row.id" class="border-t border-gray-100">
                <td class="px-3 py-2">#{{ row.id }}</td>
                <td class="px-3 py-2">{{ row.from_school_level_id === null ? 'NULL' : '#' + row.from_school_level_id }}</td>
                <td class="px-3 py-2">{{ row.to_school_level_name }} (#{{ row.to_school_level_id }})</td>
              </tr>
            </tbody>
          </table>
        </div>

        <div class="flex flex-wrap justify-end gap-3 mt-6">
          <sw-button type="button" variant="primary-outline" @click="closeModal">
            Cancelar
          </sw-button>
          <sw-button
            type="button"
            variant="primary-outline"
            :loading="previewing"
            :disabled="previewing || !canPreview"
            @click="preview"
          >
            Revisar cambio
          </sw-button>
          <sw-button
            type="button"
            variant="primary"
            :loading="applying"
            :disabled="applying || previewRows.length === 0"
            @click="apply"
          >
            Aplicar reconciliación
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
      loading: false,
      error: '',
      groups: [],
      levels: [],
      selected: {},
      showModal: false,
      activeGroup: null,
      activeIds: [],
      form: {
        school_level_id: '',
        reason: '',
      },
      previewRows: [],
      previewing: false,
      applying: false,
      modalError: '',
    }
  },

  computed: {
    isWholeInstitutionContext() {
      return !window.Ls.get('selectedSchoolLevel')
    },
    canPreview() {
      return Boolean(
        this.activeGroup &&
          this.activeIds.length &&
          this.form.school_level_id &&
          this.form.reason.trim()
      )
    },
  },

  created() {
    if (this.isWholeInstitutionContext) {
      this.load()
    }
  },

  methods: {
    async load() {
      this.loading = true
      this.error = ''
      try {
        const response = await window.axios.get('/api/v1/data-reconciliation')
        this.groups = response.data.groups || []
        this.levels = response.data.levels || []
        this.selected = {}
      } catch (error) {
        const response = error.response && error.response.data
        this.error = (response && response.message) || 'No se pudieron cargar los registros sin nivel.'
      } finally {
        this.loading = false
      }
    },

    selectedIds(model) {
      return this.selected[model] || []
    },

    isSelected(model, id) {
      return this.selectedIds(model).includes(Number(id))
    },

    toggleSelection(model, id) {
      const current = [...this.selectedIds(model)]
      const numericId = Number(id)
      const index = current.indexOf(numericId)

      if (index >= 0) current.splice(index, 1)
      else current.push(numericId)

      this.$set(this.selected, model, current)
    },

    openReconcile(group) {
      this.activeGroup = group
      this.activeIds = [...this.selectedIds(group.model)]
      this.form = { school_level_id: '', reason: '' }
      this.previewRows = []
      this.modalError = ''
      this.showModal = true
    },

    closeModal() {
      this.showModal = false
      this.activeGroup = null
      this.activeIds = []
      this.previewRows = []
      this.modalError = ''
    },

    clearPreview() {
      this.previewRows = []
      this.modalError = ''
    },

    async preview() {
      if (!this.canPreview) return

      this.previewing = true
      this.modalError = ''
      try {
        const response = await window.axios.post('/api/v1/data-reconciliation/preview', {
          model: this.activeGroup.model,
          ids: this.activeIds,
          school_level_id: this.form.school_level_id,
        })
        this.previewRows = response.data.preview || []
      } catch (error) {
        const response = error.response && error.response.data
        this.modalError = (response && response.message) || 'La simulación no pudo validarse.'
        this.previewRows = []
      } finally {
        this.previewing = false
      }
    },

    async apply() {
      if (!this.previewRows.length) return

      this.applying = true
      this.modalError = ''
      try {
        await window.axios.post('/api/v1/data-reconciliation/apply', {
          model: this.activeGroup.model,
          ids: this.activeIds,
          school_level_id: this.form.school_level_id,
          reason: this.form.reason.trim(),
        })
        this.closeModal()
        await this.load()
      } catch (error) {
        const response = error.response && error.response.data
        this.modalError = (response && response.message) || 'No se pudo aplicar la reconciliación.'
      } finally {
        this.applying = false
      }
    },

    orphanReasonLabel(reason) {
      return {
        null_level: 'Sin nivel',
        missing_level: 'Nivel inexistente',
        foreign_company_level: 'Nivel de otra empresa',
      }[reason] || reason
    },

    referenceLabel(reference) {
      if (!reference) return '—'

      const parts = []
      if (reference.number) parts.push(reference.number)
      if (reference.name) parts.push(reference.name)
      if (reference.full_name) parts.push(reference.full_name)
      if (reference.date) parts.push(reference.date)

      return parts.length ? parts.join(' · ') : 'Sin referencia adicional'
    },
  },
}
</script>
