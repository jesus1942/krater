<template>
  <base-page>
    <sw-page-header title="Alumnos">
      <sw-breadcrumb slot="breadcrumbs">
        <sw-breadcrumb-item title="Inicio" to="/admin/dashboard" />
        <sw-breadcrumb-item title="Alumnos" to="#" active />
      </sw-breadcrumb>
      <template slot="actions">
        <sw-button size="lg" variant="primary" @click="openCreate">
          <plus-sm-icon class="h-6 mr-1 -ml-2" /> Nuevo alumno
        </sw-button>
      </template>
    </sw-page-header>

    <div class="grid gap-4 mb-6 sm:grid-cols-3">
      <div class="p-4 bg-white rounded shadow">
        <div class="text-3xl font-semibold text-primary-500">{{ summary.total }}</div>
        <div class="text-sm text-gray-500">Alumnos registrados</div>
      </div>
      <div class="p-4 bg-white rounded shadow">
        <div class="text-3xl font-semibold text-green-600">{{ summary.active }}</div>
        <div class="text-sm text-gray-500">Matrícula activa</div>
      </div>
      <div class="p-4 bg-white rounded shadow">
        <div class="text-3xl font-semibold text-yellow-600">{{ summary.pending }}</div>
        <div class="text-sm text-gray-500">Pendientes</div>
      </div>
    </div>

    <div class="p-4 mb-5 bg-white rounded shadow">
      <div class="grid gap-3 md:grid-cols-4">
        <sw-input v-model="filters.search" placeholder="Buscar por nombre o DNI" @input="debouncedFetch" />
        <select v-model="filters.level" class="h-10 px-3 bg-white border border-gray-300 rounded" @change="fetchStudents">
          <option value="">Todos los niveles</option>
          <option v-for="level in levels" :key="level" :value="level">{{ level }}</option>
        </select>
        <select v-model="filters.status" class="h-10 px-3 bg-white border border-gray-300 rounded" @change="fetchStudents">
          <option value="">Todos los estados</option>
          <option value="active">Activo</option>
          <option value="pending">Pendiente</option>
          <option value="withdrawn">Retirado</option>
          <option value="graduated">Egresado</option>
        </select>
        <sw-input v-model="filters.school_year" type="number" placeholder="Ciclo lectivo" @input="debouncedFetch" />
      </div>
    </div>

    <div v-if="loading" class="p-8 text-center text-gray-500">Cargando alumnos…</div>
    <div v-else-if="!students.length" class="p-10 text-center bg-white rounded shadow">
      <h3 class="mb-2 text-lg font-semibold">Todavía no hay alumnos cargados</h3>
      <p class="mb-5 text-gray-500">Creá el primer legajo y vinculalo con su familia y responsables.</p>
      <sw-button variant="primary-outline" @click="openCreate">Agregar alumno</sw-button>
    </div>
    <div v-else class="grid gap-4 lg:grid-cols-2">
      <article v-for="student in students" :key="student.id" class="p-5 bg-white rounded shadow">
        <div class="flex items-start justify-between">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ student.full_name }}</h3>
            <p class="text-sm text-gray-500">DNI {{ student.dni || 'sin registrar' }}</p>
          </div>
          <span :class="statusClass(student.status)" class="px-2 py-1 text-xs font-semibold rounded-full">
            {{ statusLabel(student.status) }}
          </span>
        </div>
        <div class="grid grid-cols-2 gap-3 mt-4 text-sm">
          <div><span class="block text-gray-400">Curso</span>{{ courseLabel(student) }}</div>
          <div><span class="block text-gray-400">Ciclo</span>{{ student.school_year }}</div>
          <div class="col-span-2">
            <span class="block text-gray-400">Familia y responsables</span>
            {{ familySummary(student) }}
          </div>
        </div>
        <div class="flex justify-end gap-3 mt-4">
          <button class="text-sm font-medium text-primary-500" @click="openEdit(student)">Editar</button>
          <button class="text-sm font-medium text-red-500" @click="remove(student)">Eliminar</button>
        </div>
      </article>
    </div>

    <div v-if="showForm" class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto bg-black bg-opacity-50">
      <form class="w-full max-w-4xl p-6 my-8 bg-white rounded shadow-xl" @submit.prevent="save">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-xl font-semibold">{{ form.id ? 'Editar alumno' : 'Nuevo alumno' }}</h2>
          <button type="button" class="text-2xl text-gray-400" @click="closeForm">×</button>
        </div>

        <h3 class="mb-3 text-sm font-semibold tracking-wide text-gray-600 uppercase">Datos del alumno</h3>
        <div class="grid gap-4 md:grid-cols-2">
          <label class="text-sm">Nombre *<sw-input v-model="form.first_name" class="mt-1" required /></label>
          <label class="text-sm">Apellido *<sw-input v-model="form.last_name" class="mt-1" required /></label>
          <label class="text-sm">DNI<sw-input v-model="form.dni" class="mt-1" /></label>
          <label class="text-sm">Fecha de nacimiento<sw-input v-model="form.birth_date" type="date" class="mt-1" /></label>
          <label class="text-sm">Ciclo lectivo *
            <select v-model="form.academic_year_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" @change="onAcademicYearChange">
              <option value="">Seleccionar ciclo</option>
              <option v-for="year in academicYears" :key="year.id" :value="year.id">{{ year.name || year.year }}</option>
            </select>
          </label>
          <label class="text-sm">Curso/Año *
            <select v-model="form.grade_level_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" @change="onGradeLevelChange">
              <option value="">Seleccionar curso</option>
              <option v-for="grade in gradeLevels" :key="grade.id" :value="grade.id">{{ grade.name }}</option>
            </select>
          </label>
          <label class="text-sm">División *
            <select v-model="form.division_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded">
              <option value="">Seleccionar división</option>
              <option v-for="division in availableDivisions" :key="division.id" :value="division.id">{{ division.name }}</option>
            </select>
          </label>
          <div class="text-sm">
            <span class="block">Nivel institucional</span>
            <div class="flex items-center h-10 px-3 mt-1 text-gray-600 bg-gray-50 border border-gray-200 rounded">{{ activeLevelName }}</div>
          </div>
          <label class="text-sm">Estado
            <select v-model="form.status" class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded">
              <option value="active">Activo</option>
              <option value="pending">Pendiente</option>
              <option value="withdrawn">Retirado</option>
              <option value="graduated">Egresado</option>
            </select>
          </label>
          <label class="text-sm md:col-span-2">Observaciones<textarea v-model="form.notes" rows="3" class="w-full px-3 py-2 mt-1 border border-gray-300 rounded"></textarea></label>
        </div>

        <div class="pt-6 mt-6 border-t border-gray-200">
          <div class="flex flex-wrap items-start justify-between gap-3 mb-4">
            <div>
              <h3 class="text-base font-semibold text-gray-800">Familia y responsables</h3>
              <p class="mt-1 text-sm text-gray-500">
                El DNI se coteja dentro de la institución. Si ya existe, se reutiliza el mismo familiar y se vincula a este alumno.
              </p>
            </div>
            <sw-button type="button" variant="primary-outline" @click="addFamilyMember">Agregar familiar</sw-button>
          </div>

          <div v-if="!form.family_members.length" class="p-4 text-sm text-center text-gray-500 border border-dashed border-gray-300 rounded">
            No hay familiares vinculados.
          </div>

          <div v-for="(member, index) in form.family_members" :key="member.local_key" class="p-4 mb-4 border border-gray-200 rounded">
            <div class="flex items-center justify-between mb-3">
              <strong class="text-sm text-gray-700">Familiar {{ index + 1 }}</strong>
              <button type="button" class="text-sm font-medium text-red-500" @click="removeFamilyMember(index)">Quitar vínculo</button>
            </div>

            <div class="grid gap-3 md:grid-cols-2 lg:grid-cols-4">
              <label class="text-sm lg:col-span-2">Nombre y apellido *<sw-input v-model="member.name" class="mt-1" required /></label>
              <label class="text-sm">DNI<sw-input v-model="member.dni" class="mt-1" placeholder="Se usa para cotejar" /></label>
              <label class="text-sm">Parentesco
                <select v-model="member.relationship" class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded">
                  <option value="">Seleccionar</option>
                  <option value="Madre">Madre</option>
                  <option value="Padre">Padre</option>
                  <option value="Tutor/a">Tutor/a</option>
                  <option value="Abuelo/a">Abuelo/a</option>
                  <option value="Hermano/a">Hermano/a</option>
                  <option value="Responsable">Responsable</option>
                  <option value="Otro">Otro</option>
                </select>
              </label>
              <label class="text-sm lg:col-span-2">Correo<sw-input v-model="member.email" type="email" class="mt-1" /></label>
              <label class="text-sm lg:col-span-2">Teléfono<sw-input v-model="member.phone" class="mt-1" /></label>
            </div>

            <div class="flex flex-wrap gap-5 mt-4 text-sm text-gray-700">
              <label class="flex items-center gap-2"><input v-model="member.is_responsible" type="checkbox" /> Responsable</label>
              <label class="flex items-center gap-2"><input v-model="member.is_financial_responsible" type="checkbox" /> Responsable financiero</label>
              <label class="flex items-center gap-2"><input v-model="member.is_primary_contact" type="checkbox" /> Contacto principal</label>
            </div>
          </div>
        </div>

        <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>
        <div class="flex justify-end gap-3 mt-6">
          <sw-button type="button" variant="primary-outline" @click="closeForm">Cancelar</sw-button>
          <sw-button :loading="saving" :disabled="saving" variant="primary">Guardar alumno</sw-button>
        </div>
      </form>
    </div>
  </base-page>
</template>

<script>
import { PlusSmIcon } from '@vue-hero-icons/solid'

let familyKey = 0
const newFamilyMember = () => ({
  id: null,
  user_id: null,
  name: '',
  dni: '',
  email: '',
  phone: '',
  relationship: '',
  is_responsible: false,
  is_financial_responsible: false,
  is_primary_contact: false,
  local_key: `family-${Date.now()}-${familyKey++}`,
})

const emptyForm = () => ({
  id: null,
  school_level_id: window.Ls.get('selectedSchoolLevel') || '',
  academic_year_id: '',
  grade_level_id: '',
  division_id: '',
  first_name: '',
  last_name: '',
  dni: '',
  birth_date: '',
  level: '',
  grade: '',
  division: '',
  school_year: new Date().getFullYear(),
  status: 'active',
  guardian_id: null,
  notes: '',
  family_members: [],
})

export default {
  components: { PlusSmIcon },
  data() {
    return {
      students: [],
      schoolLevels: [],
      academicYears: [],
      gradeLevels: [],
      divisions: [],
      summary: { total: 0, active: 0, pending: 0 },
      filters: { search: '', level: '', status: '', school_year: new Date().getFullYear() },
      levels: ['Primario', 'Secundario', 'Terciario'],
      form: emptyForm(),
      showForm: false,
      loading: false,
      saving: false,
      error: '',
      timer: null,
    }
  },
  created() {
    this.fetchSchoolLevels()
    this.fetchPlacementOptions()
    this.fetchStudents()
  },
  computed: {
    availableDivisions() {
      return this.divisions.filter((division) =>
        Number(division.academic_year_id) === Number(this.form.academic_year_id) &&
        Number(division.grade_level_id) === Number(this.form.grade_level_id)
      )
    },
    activeLevelName() {
      const selectedId = window.Ls.get('selectedSchoolLevel')
      const level = this.schoolLevels.find((item) => String(item.id) === String(selectedId))
      return level ? level.name : 'Nivel activo'
    },
  },
  methods: {
    async fetchSchoolLevels() {
      const response = await window.axios.get('/api/v1/school-levels')
      this.schoolLevels = response.data.levels.filter((level) => level.enabled)
    },
    async fetchPlacementOptions() {
      const response = await window.axios.get('/api/v1/students/placement-options')
      this.academicYears = response.data.academic_years || []
      this.gradeLevels = response.data.grade_levels || []
      this.divisions = response.data.divisions || []
      if (!this.form.academic_year_id && this.academicYears.length) {
        const current = this.academicYears.find((year) => Number(year.year) === new Date().getFullYear()) || this.academicYears[0]
        this.form.academic_year_id = current.id
      }
    },
    async fetchStudents() {
      this.loading = true
      try {
        const response = await window.axios.get('/api/v1/students', { params: this.filters })
        this.students = response.data.students.data || response.data.students
        this.summary = response.data.summary
      } finally {
        this.loading = false
      }
    },
    debouncedFetch() {
      clearTimeout(this.timer)
      this.timer = setTimeout(this.fetchStudents, 350)
    },
    async openCreate() {
      this.form = emptyForm()
      await this.fetchPlacementOptions()
      this.error = ''
      this.showForm = true
    },
    async openEdit(student) {
      await this.fetchPlacementOptions()
      const members = (student.family_members || []).map((member) => ({
        ...newFamilyMember(),
        id: member.id,
        user_id: member.user_id || null,
        name: member.name || '',
        dni: member.dni || '',
        email: member.email || '',
        phone: member.phone || '',
        relationship: member.pivot ? (member.pivot.relationship || '') : '',
        is_responsible: Boolean(member.pivot && member.pivot.is_responsible),
        is_financial_responsible: Boolean(member.pivot && member.pivot.is_financial_responsible),
        is_primary_contact: Boolean(member.pivot && member.pivot.is_primary_contact),
      }))

      if (!members.length && student.guardian) {
        members.push({
          ...newFamilyMember(),
          user_id: student.guardian.id,
          name: student.guardian.name || '',
          email: student.guardian.email || '',
          phone: student.guardian.phone || '',
          relationship: 'Responsable',
          is_responsible: true,
          is_financial_responsible: true,
          is_primary_contact: true,
        })
      }

      const academicYear = this.academicYears.find((year) => Number(year.year) === Number(student.school_year))
      const gradeLevel = this.gradeLevels.find((grade) => grade.name === student.grade)
      const division = this.divisions.find((item) =>
        gradeLevel && academicYear &&
        Number(item.grade_level_id) === Number(gradeLevel.id) &&
        Number(item.academic_year_id) === Number(academicYear.id) &&
        item.name === student.division
      )
      this.form = {
        ...emptyForm(),
        ...student,
        academic_year_id: academicYear ? academicYear.id : '',
        grade_level_id: gradeLevel ? gradeLevel.id : '',
        division_id: division ? division.id : '',
        guardian_id: student.guardian_id || null,
        family_members: members,
      }
      this.error = ''
      this.showForm = true
    },
    closeForm() {
      this.showForm = false
    },
    addFamilyMember() {
      this.form.family_members.push(newFamilyMember())
    },
    removeFamilyMember(index) {
      this.form.family_members.splice(index, 1)
    },
    async save() {
      this.saving = true
      this.error = ''
      try {
        const payload = {
          ...this.form,
          family_members: this.form.family_members.map(({ local_key, ...member }) => member),
        }
        if (this.form.id) {
          await window.axios.put(`/api/v1/students/${this.form.id}`, payload)
        } else {
          await window.axios.post('/api/v1/students', payload)
        }
        this.closeForm()
        await this.fetchStudents()
      } catch (error) {
        const response = error.response && error.response.data
        if (response && response.errors) {
          const first = Object.values(response.errors)[0]
          this.error = Array.isArray(first) ? first[0] : first
        } else {
          this.error = (response && response.message) || 'No se pudo guardar el alumno.'
        }
      } finally {
        this.saving = false
      }
    },
    onAcademicYearChange() {
      this.form.division_id = ''
    },
    onGradeLevelChange() {
      this.form.division_id = ''
    },
    async remove(student) {
      if (!window.confirm(`¿Eliminar el legajo de ${student.full_name}?`)) return
      await window.axios.delete(`/api/v1/students/${student.id}`)
      await this.fetchStudents()
    },
    familySummary(student) {
      const members = student.family_members || []
      if (members.length) {
        return members.map((member) => {
          const relation = member.pivot && member.pivot.relationship ? ` (${member.pivot.relationship})` : ''
          return `${member.name}${relation}`
        }).join(' · ')
      }
      return student.guardian ? student.guardian.name : 'Sin vincular'
    },
    courseLabel(student) {
      return [student.level, student.grade, student.division].filter(Boolean).join(' · ') || 'Sin asignar'
    },
    statusLabel(status) {
      return { active: 'Activo', pending: 'Pendiente', withdrawn: 'Retirado', graduated: 'Egresado' }[status] || status
    },
    statusClass(status) {
      return { active: 'bg-green-100 text-green-700', pending: 'bg-yellow-100 text-yellow-700', withdrawn: 'bg-red-100 text-red-700', graduated: 'bg-blue-100 text-blue-700' }[status] || 'bg-gray-100 text-gray-700'
    },
  },
}
</script>
