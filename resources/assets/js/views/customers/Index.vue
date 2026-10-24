<template>
  <base-page>
    <sw-page-header title="Familias y responsables">
      <sw-breadcrumb slot="breadcrumbs">
        <sw-breadcrumb-item title="Inicio" to="/admin/dashboard" />
        <sw-breadcrumb-item title="Familias y responsables" to="#" active />
      </sw-breadcrumb>
      <template slot="actions">
        <sw-button size="lg" variant="primary" @click="openCreate">
          <plus-sm-icon class="h-6 mr-1 -ml-2" /> Nuevo responsable
        </sw-button>
      </template>
    </sw-page-header>

    <div class="p-4 mb-5 bg-white rounded shadow">
      <div class="flex flex-col gap-3 md:flex-row md:items-center md:justify-between">
        <div>
          <div class="text-2xl font-semibold text-primary-500">{{ members.length }}</div>
          <div class="text-sm text-gray-500">Familiares y responsables registrados</div>
        </div>
        <sw-input v-model="search" class="w-full md:w-80" placeholder="Buscar por nombre, DNI, correo o teléfono" @input="debouncedFetch" />
      </div>
    </div>

    <div v-if="loading" class="p-8 text-center text-gray-500">Cargando familias y responsables…</div>

    <div v-else-if="!members.length" class="p-10 text-center bg-white rounded shadow">
      <h3 class="mb-2 text-lg font-semibold">Aún no hay familias ni responsables</h3>
      <p class="mb-5 text-gray-500">Los responsables cargados desde el legajo de un alumno aparecerán automáticamente acá.</p>
      <sw-button variant="primary-outline" @click="openCreate">Agregar familia o responsable</sw-button>
    </div>

    <div v-else class="grid gap-4 lg:grid-cols-2">
      <article v-for="member in members" :key="member.id" class="p-5 bg-white rounded shadow">
        <div class="flex items-start justify-between gap-3">
          <div>
            <h3 class="text-lg font-semibold text-gray-900">{{ member.name }}</h3>
            <p class="text-sm text-gray-500">DNI {{ member.dni || 'sin registrar' }}</p>
          </div>
          <button class="text-sm font-medium text-primary-500" @click="openEdit(member)">Editar</button>
        </div>

        <div class="grid gap-3 mt-4 text-sm sm:grid-cols-2">
          <div><span class="block text-gray-400">Correo</span>{{ member.email || 'Sin registrar' }}</div>
          <div><span class="block text-gray-400">Teléfono</span>{{ member.phone || 'Sin registrar' }}</div>
        </div>

        <div class="pt-4 mt-4 border-t border-gray-100">
          <span class="block mb-2 text-sm text-gray-400">Alumnos vinculados</span>
          <div v-if="member.students && member.students.length" class="space-y-2">
            <div v-for="student in member.students" :key="student.id" class="p-3 text-sm bg-gray-50 rounded">
              <div class="font-medium text-gray-800">{{ student.first_name }} {{ student.last_name }}</div>
              <div class="text-gray-500">{{ placementLabel(student) }}</div>
              <div class="flex flex-wrap gap-2 mt-2">
                <span v-if="student.pivot && student.pivot.relationship" class="px-2 py-1 text-xs bg-white border rounded">{{ student.pivot.relationship }}</span>
                <span v-if="student.pivot && student.pivot.is_responsible" class="px-2 py-1 text-xs bg-blue-50 text-blue-700 rounded">Responsable</span>
                <span v-if="student.pivot && student.pivot.is_financial_responsible" class="px-2 py-1 text-xs bg-green-50 text-green-700 rounded">Responsable financiero</span>
                <span v-if="student.pivot && student.pivot.is_primary_contact" class="px-2 py-1 text-xs bg-yellow-50 text-yellow-700 rounded">Contacto principal</span>
              </div>
            </div>
          </div>
          <p v-else class="text-sm text-gray-500">Sin alumnos vinculados todavía.</p>
        </div>
      </article>
    </div>

    <div v-if="showForm" class="fixed inset-0 z-50 flex items-start justify-center p-4 overflow-y-auto bg-black bg-opacity-50">
      <form class="w-full max-w-2xl p-6 my-8 bg-white rounded shadow-xl" @submit.prevent="save">
        <div class="flex items-center justify-between mb-5">
          <h2 class="text-xl font-semibold">{{ form.id ? 'Editar familiar o responsable' : 'Nuevo familiar o responsable' }}</h2>
          <button type="button" class="text-2xl text-gray-400" @click="closeForm">×</button>
        </div>

        <div class="grid gap-4 md:grid-cols-2">
          <label class="text-sm md:col-span-2">Nombre y apellido *<sw-input v-model="form.name" class="mt-1" required /></label>
          <label class="text-sm">DNI<sw-input v-model="form.dni" class="mt-1" /></label>
          <label class="text-sm">Teléfono<sw-input v-model="form.phone" class="mt-1" /></label>
          <label class="text-sm md:col-span-2">Correo<sw-input v-model="form.email" type="email" class="mt-1" /></label>
          <label class="text-sm md:col-span-2">Observaciones<textarea v-model="form.notes" rows="3" class="w-full px-3 py-2 mt-1 border border-gray-300 rounded"></textarea></label>
        </div>

        <p class="mt-4 text-sm text-gray-500">Los vínculos, parentescos y responsabilidades sobre cada alumno se administran desde el legajo del alumno para preservar el historial.</p>
        <p v-if="error" class="mt-4 text-sm text-red-600">{{ error }}</p>

        <div class="flex justify-end gap-3 mt-6">
          <sw-button type="button" variant="primary-outline" @click="closeForm">Cancelar</sw-button>
          <sw-button :loading="saving" :disabled="saving" variant="primary">Guardar</sw-button>
        </div>
      </form>
    </div>
  </base-page>
</template>

<script>
import { PlusSmIcon } from '@vue-hero-icons/solid'

const emptyForm = () => ({ id: null, name: '', dni: '', email: '', phone: '', notes: '' })

export default {
  components: { PlusSmIcon },
  data() {
    return {
      members: [],
      search: '',
      loading: false,
      saving: false,
      showForm: false,
      error: '',
      form: emptyForm(),
      timer: null,
    }
  },
  created() {
    this.fetchMembers()
  },
  methods: {
    async fetchMembers() {
      this.loading = true
      try {
        const response = await window.axios.get('/api/v1/family-members', { params: { search: this.search } })
        this.members = response.data.data || []
      } finally {
        this.loading = false
      }
    },
    debouncedFetch() {
      clearTimeout(this.timer)
      this.timer = setTimeout(this.fetchMembers, 300)
    },
    openCreate() {
      this.form = emptyForm()
      this.error = ''
      this.showForm = true
    },
    openEdit(member) {
      this.form = {
        id: member.id,
        name: member.name || '',
        dni: member.dni || '',
        email: member.email || '',
        phone: member.phone || '',
        notes: member.notes || '',
      }
      this.error = ''
      this.showForm = true
    },
    closeForm() {
      this.showForm = false
    },
    async save() {
      this.saving = true
      this.error = ''
      try {
        if (this.form.id) {
          await window.axios.put(`/api/v1/family-members/${this.form.id}`, this.form)
        } else {
          await window.axios.post('/api/v1/family-members', this.form)
        }
        this.closeForm()
        await this.fetchMembers()
      } catch (error) {
        const response = error.response && error.response.data
        if (response && response.errors) {
          const first = Object.values(response.errors)[0]
          this.error = Array.isArray(first) ? first[0] : first
        } else {
          this.error = (response && response.message) || 'No se pudo guardar el familiar.'
        }
      } finally {
        this.saving = false
      }
    },
    placementLabel(student) {
      return [student.level, student.grade, student.division].filter(Boolean).join(' · ') || 'Sin ubicación académica'
    },
  },
}
</script>
