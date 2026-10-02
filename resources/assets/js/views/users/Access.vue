<template>
  <base-page>
    <sw-page-header title="Usuarios y roles">
      <sw-breadcrumb slot="breadcrumbs">
        <sw-breadcrumb-item title="Usuarios y roles" to="/admin/users" />
        <sw-breadcrumb-item :title="user.name || 'Ficha de acceso'" to="#" active />
      </sw-breadcrumb>
    </sw-page-header>
    <p v-if="error" role="alert" class="p-4 my-4 text-red-800 bg-red-100 rounded">{{ error }}</p>
    <p v-if="notice" role="status" class="p-4 my-4 text-green-800 bg-green-100 rounded">{{ notice }}</p>
    <p v-if="loading" class="p-6" role="status">Cargando acceso…</p>
    <div v-else class="grid gap-6 mt-6 lg:grid-cols-4">
      <aside class="p-5 bg-white border rounded-lg">
        <h2 class="text-lg font-semibold">{{ user.name }}</h2>
        <p class="mt-2 text-sm break-all">{{ user.email }}</p>
        <p class="mt-3 text-sm">{{ user.is_active ? 'Cuenta activa' : 'Cuenta desactivada' }}</p>
        <nav aria-label="Ficha del usuario" class="flex flex-col gap-2 mt-6">
          <button v-for="item in tabs" :key="item.id" type="button" class="p-3 text-left rounded" :class="tab === item.id ? 'bg-primary-100 text-primary-700' : 'text-gray-700'" :aria-current="tab === item.id ? 'page' : null" @click="tab = item.id">{{ item.label }}</button>
        </nav>
        <router-link v-if="canEdit" :to="`/admin/users/${user.id}/edit`" class="block mt-6 text-primary-600">Editar datos de la cuenta</router-link>
      </aside>
      <section class="lg:col-span-3">
        <template v-if="tab === 'assignments'">
          <sw-card v-if="canAssign" title="Asignar un rol">
            <p class="mb-5 text-sm text-gray-600">Elegí el rol y su alcance. Las fechas se interpretan por día, inclusive. Cada asignación conserva su historial.</p>
            <form @submit.prevent="grant">
              <div class="grid gap-4 md:grid-cols-2">
                <label class="block text-sm">Rol
                  <select v-model="form.role_id" required class="w-full p-3 mt-1 border rounded" @change="resetScope">
                    <option value="">Seleccionar rol</option>
                    <option v-for="role in grantableRoles" :key="role.id" :value="role.id">{{ role.label }}</option>
                  </select>
                </label>
                <label v-if="selectedRole && selectedRole.scope_type !== 'global'" class="block text-sm">Nivel
                  <select v-model="form.school_level_id" required class="w-full p-3 mt-1 border rounded" @change="form.division_id = ''; form.course_section_id = ''">
                    <option value="">Seleccionar nivel</option>
                    <option v-for="level in allowedLevels" :key="level.id" :value="level.id">{{ level.name }}</option>
                  </select>
                </label>
                <p v-else-if="selectedRole" class="p-3 text-sm bg-gray-100 rounded">Alcance: toda la institución.</p>
                <label v-if="selectedRole && selectedRole.scope_type === 'division'" class="block text-sm">División
                  <select v-model="form.division_id" required class="w-full p-3 mt-1 border rounded">
                    <option value="">Seleccionar división</option>
                    <option v-for="division in divisionsForLevel" :key="division.id" :value="division.id">{{ division.name }} · ciclo {{ division.year || division.academic_year_id }}</option>
                  </select>
                </label>
                <label v-if="selectedRole && selectedRole.scope_type === 'section'" class="block text-sm">Sección de materia
                  <select v-model="form.course_section_id" required class="w-full p-3 mt-1 border rounded">
                    <option value="">Seleccionar sección</option>
                    <option v-for="section in sectionsForLevel" :key="section.id" :value="section.id">{{ section.subject_name || 'Materia ' + section.subject_id }} · {{ section.division_name || 'División ' + section.division_id }}</option>
                  </select>
                </label>
                <label class="block text-sm">Desde
                  <input v-model="form.starts_at" required type="date" class="w-full p-3 mt-1 border rounded" />
                </label>
                <label v-if="!selectedRole || selectedRole.name !== 'total_admin'" class="block text-sm">Hasta (opcional)
                  <input v-model="form.ends_at" type="date" :min="form.starts_at" class="w-full p-3 mt-1 border rounded" />
                </label>
              </div>
              <p v-if="selectedRole" class="mt-4 text-sm text-gray-600">{{ selectedRole.description }}</p>
              <sw-button class="mt-5" variant="primary" type="submit" :loading="saving" :disabled="saving || !selectedRole">Guardar asignación</sw-button>
            </form>
          </sw-card>
          <sw-card class="mt-6" title="Asignaciones e historial">
            <p v-if="!assignments.length" class="text-gray-600">Esta cuenta todavía no tiene roles asignados.</p>
            <article v-for="assignment in assignments" :key="assignment.id" class="py-4 border-b last:border-b-0">
              <div class="flex flex-wrap items-start justify-between gap-3">
                <div>
                  <h3 class="font-semibold">{{ assignment.label }}</h3>
                  <p class="mt-1 text-sm">{{ assignment.level_name || 'Toda la institución' }}<span v-if="assignment.division_name"> · {{ assignment.division_name }}</span><span v-if="assignment.course_section_id"> · Sección {{ assignment.course_section_id }}</span></p>
                  <p class="mt-1 text-sm text-gray-600">{{ assignment.starts_on || 'Sin fecha de inicio' }} → {{ assignment.ends_on || 'Sin vencimiento' }}</p>
                  <p class="mt-1 text-sm font-medium">{{ status(assignment) }}</p>
                  <p v-if="assignment.revoked_at" class="text-xs text-gray-500">Revocada el {{ assignment.revoked_at }}</p>
                </div>
                <sw-button v-if="canAssign && !assignment.revoked_at && (!assignment.ends_on || assignment.ends_on >= today)" variant="danger-outline" :disabled="saving" @click="revoke(assignment)">Revocar acceso</sw-button>
              </div>
            </article>
          </sw-card>
        </template>
        <sw-card v-else title="Permisos efectivos">
          <p class="mb-5 text-sm text-gray-600">Permisos del nivel seleccionado en la cabecera. Cambiá el nivel para inspeccionar otro alcance. Un permiso por división o sección conserva esa limitación.</p>
          <p v-if="!permissions.length">Sin permisos efectivos en este contexto.</p>
          <article v-for="permission in permissions" :key="permission.name" class="py-3 border-b">
            <h3 class="font-medium">{{ permission.label }}</h3>
            <p class="text-xs text-gray-500">{{ permission.name }}</p>
            <p v-if="permission.total_admin" class="mt-1 text-sm">Administración total · alcance institucional</p>
            <ul v-else class="mt-2 text-sm">
              <li v-for="source in permission.sources" :key="source.id">{{ source.label }} · asignación #{{ source.id }}<span v-if="source.division_id"> · división #{{ source.division_id }}</span><span v-if="source.course_section_id"> · sección #{{ source.course_section_id }}</span></li>
            </ul>
          </article>
        </sw-card>
      </section>
    </div>
  </base-page>
</template>

<script>
import { can } from '../../helpers/access'

/** Ficha con guardado por responsabilidad; toda autoridad la valida la API. */
export default {
  data() {
    const date = new Date()
    const today = `${date.getFullYear()}-${String(date.getMonth() + 1).padStart(2, '0')}-${String(date.getDate()).padStart(2, '0')}`
    return {
      user: {}, assignments: [], permissions: [], catalog: { roles: [], levels: [], divisions: [], sections: [] },
      tab: 'assignments', tabs: [{ id: 'assignments', label: 'Roles y alcances' }, { id: 'permissions', label: 'Permisos efectivos' }],
      loading: true, saving: false, error: '', notice: '', manageable: false, today,
      form: { role_id: '', school_level_id: '', division_id: '', course_section_id: '', starts_at: today, ends_at: '' },
    }
  },
  computed: {
    canAssign() { return this.manageable && this.user.is_active && can(this.$store.state.user.currentUser, 'system.role.assign') },
    canEdit() { return this.manageable && can(this.$store.state.user.currentUser, 'system.user.manage') },
    grantableRoles() { return this.catalog.roles.filter(role => role.grantable) },
    selectedRole() { return this.catalog.roles.find(role => role.id === this.form.role_id) },
    allowedLevels() { return this.catalog.levels.filter(level => this.selectedRole && this.selectedRole.grantable_level_ids.includes(level.id)) },
    divisionsForLevel() { return this.catalog.divisions.filter(division => division.school_level_id === this.form.school_level_id) },
    sectionsForLevel() { return this.catalog.sections.filter(section => section.school_level_id === this.form.school_level_id) },
  },
  created() { this.load() },
  watch: { '$route.params.id'() { this.load() } },
  methods: {
    /** Recarga datos y permisos luego de cualquier cambio confirmado. */
    async load() {
      this.loading = true
      try {
        const base = `/api/v1/users/${this.$route.params.id}`
        const [user, assignments, permissions, catalog] = await Promise.all([
          window.axios.get(base), window.axios.get(`${base}/role-assignments`),
          window.axios.get(`${base}/effective-permissions`), window.axios.get('/api/v1/roles'),
        ])
        this.user = user.data.user
        this.assignments = assignments.data.assignments
        this.manageable = assignments.data.can_manage
        this.permissions = permissions.data.permissions
        this.catalog = catalog.data
      } catch (error) { this.showError(error) } finally { this.loading = false }
    },
    /** Descarta selecciones incompatibles cuando cambia el tipo de rol. */
    resetScope() { this.form.school_level_id = ''; this.form.division_id = ''; this.form.course_section_id = ''; this.form.ends_at = '' },
    /** Indica vigencia, incluyendo revocaciones anteriores al vencimiento. */
    status(assignment) {
      if (assignment.revoked_at) return 'Revocada'
      if (assignment.starts_on && assignment.starts_on > this.today) return 'Programada'
      if (assignment.ends_on && assignment.ends_on < this.today) return 'Vencida'
      return 'Vigente'
    },
    /** Envía solo los identificadores y las fechas elegidas. */
    async grant() {
      if (this.saving) return
      this.saving = true; this.error = ''; this.notice = ''
      try {
        const payload = Object.fromEntries(Object.entries(this.form).map(([key, value]) => [key, value === '' ? null : value]))
        await window.axios.post(`/api/v1/users/${this.user.id}/role-assignments`, payload)
        this.notice = 'Asignación guardada.'
        await this.load()
      } catch (error) { this.showError(error) } finally { this.saving = false }
    },
    /** La confirmación identifica el acceso que se retira de inmediato. */
    async revoke(assignment) {
      if (this.saving) return
      const confirmation = await this.$swal({ title: 'Revocar acceso', text: `Se retirará ${assignment.label} de ${this.user.name}. El historial se conserva.`, icon: 'warning', showCancelButton: true, confirmButtonText: 'Revocar', cancelButtonText: 'Cancelar' })
      if (!confirmation.isConfirmed) return
      this.saving = true; this.error = ''; this.notice = ''
      try {
        await window.axios.post(`/api/v1/role-assignments/${assignment.id}/revoke`)
        this.notice = 'Acceso revocado.'
        await this.load()
      } catch (error) { this.showError(error) } finally { this.saving = false }
    },
    /** Presenta errores de validacion sin perder los datos del formulario. */
    showError(error) {
      const response = error.response || {}
      const data = response.data || {}
      this.error = response.status === 403 ? 'No tenés permiso para realizar esta acción en este alcance.' : Object.values(data.errors || {}).flat().join(' ') || data.message || 'No se pudo completar la operación.'
    },
  },
}
</script>
