<template>
  <div class="flex items-center justify-center min-h-screen px-4 bg-gray-100">
    <form class="w-full max-w-md p-6 bg-white rounded shadow" @submit.prevent="submit">
      <h1 class="text-xl font-semibold text-gray-800">Cambiá tu contraseña</h1>
      <p class="mt-1 text-sm text-gray-500">
        Ingresaste con una contraseña inicial. Antes de continuar, elegí una propia.
      </p>
      <label class="block mt-5 text-sm">
        Contraseña inicial
        <input v-model="form.current_password" type="password" autocomplete="current-password" required
          class="w-full h-10 px-3 mt-1 border border-gray-300 rounded" />
      </label>
      <label class="block mt-4 text-sm">
        Nueva contraseña (8 caracteres o más)
        <input v-model="form.password" type="password" autocomplete="new-password" minlength="8" required
          class="w-full h-10 px-3 mt-1 border border-gray-300 rounded" />
      </label>
      <label class="block mt-4 text-sm">
        Repetí la nueva contraseña
        <input v-model="form.password_confirmation" type="password" autocomplete="new-password" minlength="8" required
          class="w-full h-10 px-3 mt-1 border border-gray-300 rounded" />
      </label>
      <p v-if="error" class="p-3 mt-4 text-sm text-red-700 bg-red-50 border border-red-200 rounded">{{ error }}</p>
      <div class="flex justify-between gap-3 mt-6">
        <sw-button type="button" variant="primary-outline" @click="logout">Salir</sw-button>
        <sw-button type="submit" variant="primary" :loading="saving" :disabled="saving">Guardar y continuar</sw-button>
      </div>
    </form>
  </div>
</template>

<script>
export default {
  data() {
    return { form: { current_password: '', password: '', password_confirmation: '' }, saving: false, error: '' }
  },
  methods: {
    async submit() {
      this.error = ''
      if (this.form.password !== this.form.password_confirmation) {
        this.error = 'Las contraseñas nuevas no coinciden.'
        return
      }
      this.saving = true
      try {
        await window.axios.put('/api/v1/me/password', this.form)
        window.location.assign('/admin')
      } catch (error) {
        const data = error.response && error.response.data
        const first = data && data.errors ? Object.values(data.errors)[0] : null
        this.error = (first && first[0]) || (data && data.message) || 'No se pudo cambiar la contraseña.'
      } finally {
        this.saving = false
      }
    },
    logout() {
      this.$store.dispatch('auth/logout')
    },
  },
}
</script>
