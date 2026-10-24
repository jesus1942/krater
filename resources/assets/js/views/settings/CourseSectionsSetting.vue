<template>
  <div class="px-4 pb-8 sm:px-6">
    <div class="mb-6">
      <h2 class="text-xl font-semibold text-gray-800">Materias por curso</h2>
      <p class="mt-1 text-sm text-gray-500">
        Elegí qué materias del nivel se dictan en cada curso. Son la base de las asignaciones docentes.
      </p>
    </div>

    <div v-if="!levelSelected" class="p-4 text-sm text-yellow-800 bg-yellow-50 border border-yellow-200 rounded">
      Elegí un nivel en el selector de arriba para cargar sus materias por curso.
    </div>

    <template v-else>
      <div v-if="error" class="p-4 mb-5 text-sm text-red-700 bg-red-50 border border-red-200 rounded">{{ error }}</div>
      <div v-if="loading" class="p-6 text-sm text-center text-gray-500">Cargando cursos y materias…</div>

      <div v-else class="grid gap-6 md:grid-cols-12">
        <div class="md:col-span-4">
          <p class="mb-2 text-sm font-medium text-gray-700">Curso</p>
          <p v-if="!divisions.length" class="text-sm text-gray-500">Todavía no hay cursos en este nivel.</p>
          <button
            v-for="division in divisions"
            :key="division.id"
            type="button"
            class="block w-full px-3 py-2 mb-1 text-sm text-left border rounded"
            :class="division.id === divisionId ? 'border-primary-500 bg-primary-50' : 'border-gray-200 bg-white'"
            @click="selectDivision(division.id)"
          >
            {{ divisionLabel(division) }}
          </button>
        </div>

        <div v-if="divisionId" class="md:col-span-8">
          <p class="mb-2 text-sm font-medium text-gray-700">Materias que se dictan en {{ currentLabel }}</p>
          <p v-if="!subjects.length" class="text-sm text-gray-500">
            Cargá primero las materias del nivel en Estructura académica.
          </p>
          <label
            v-for="subject in subjects"
            :key="subject.id"
            class="flex items-start gap-3 p-3 mb-2 bg-white border border-gray-200 rounded"
          >
            <input v-model="selected" type="checkbox" :value="subject.id" :disabled="!canManage" class="mt-1" />
            <span class="flex-1">
              <span class="block text-sm text-gray-800">{{ subject.name }}</span>
              <span class="block text-xs text-gray-500">
                {{ subject.study_plan ? subject.study_plan.name : 'Sin plan' }}
                <template v-if="subject.year_of_plan"> · {{ subject.year_of_plan }}.º año del plan</template>
              </span>
              <span v-if="teachersOf(subject.id).length" class="block mt-1 text-xs text-gray-600">
                Docentes: {{ teachersOf(subject.id).join(', ') }}
              </span>
            </span>
          </label>
          <div v-if="canManage" class="flex justify-end mt-4">
            <sw-button variant="primary" :loading="saving" :disabled="saving" @click="save">Guardar materias del curso</sw-button>
          </div>
        </div>
      </div>
    </template>
  </div>
</template>

<script>
import { mapActions } from 'vuex'
import { can } from '../../helpers/access'

export default {
  data() {
    return { divisions: [], subjects: [], sections: [], selected: [], divisionId: null, loading: false, saving: false, error: '' }
  },

  computed: {
    levelSelected() {
      return Boolean(window.Ls.get('selectedSchoolLevel'))
    },
    canManage() {
      return can(this.$store.state.user.currentUser, 'academic.section.manage')
    },
    currentLabel() {
      const division = this.divisions.find(d => d.id === this.divisionId)
      return division ? this.divisionLabel(division) : ''
    },
  },

  created() {
    if (this.levelSelected) this.load()
  },

  methods: {
    ...mapActions('notification', ['showNotification']),

    async load() {
      this.loading = true
      this.error = ''
      try {
        const [divisions, subjects] = await Promise.all([
          window.axios.get('/api/v1/divisions'),
          window.axios.get('/api/v1/subjects', { params: { solo_habilitadas: 1 } }),
        ])
        this.divisions = divisions.data.data
        this.subjects = subjects.data.data
        if (this.divisions.length) await this.selectDivision(this.divisions[0].id)
      } catch (error) {
        this.error = this.messageOf(error, 'No se pudieron cargar los cursos del nivel.')
      } finally {
        this.loading = false
      }
    },

    async selectDivision(id) {
      this.divisionId = id
      const { data } = await window.axios.get('/api/v1/course-sections', { params: { division_id: id } })
      this.sections = data.data
      this.selected = this.sections.map(section => section.subject_id)
    },

    async save() {
      this.saving = true
      this.error = ''
      try {
        await window.axios.put(`/api/v1/divisions/${this.divisionId}/course-sections`, { subject_ids: this.selected })
        this.showNotification({ type: 'success', message: 'Materias del curso guardadas.' })
        await this.selectDivision(this.divisionId)
      } catch (error) {
        this.error = this.messageOf(error, 'No se pudieron guardar las materias.')
      } finally {
        this.saving = false
      }
    },

    teachersOf(subjectId) {
      const section = this.sections.find(s => s.subject_id === subjectId)
      return section ? (section.teaching || []).map(t => t.name) : []
    },

    divisionLabel(division) {
      return `${division.grade_level ? division.grade_level.name : ''} ${division.name}`.trim()
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
