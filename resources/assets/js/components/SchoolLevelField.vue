<template>
  <label v-if="isInstitutionView" class="block mb-4 text-sm">
    Nivel institucional <span v-if="!editing">*</span>
    <select v-model="selected" :disabled="editing" :required="!editing" class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded">
      <option value="">{{ editing ? 'Sin nivel asignado' : 'Seleccioná un nivel' }}</option>
      <option v-if="editing && value && !levels.some(level => String(level.id) === String(value))" :value="value">Nivel {{ value }} (no habilitado)</option>
      <option v-for="level in levels" :key="level.id" :value="level.id">{{ level.name }}</option>
    </select>
    <span v-if="editing" class="block mt-1 text-gray-500">El nivel se conserva. Para cambiarlo, usá reubicación.</span>
  </label>
</template>
<script>
/** Selector local al formulario; nunca modifica el nivel del encabezado. */
export default {
  props: { value: { default: null }, editing: Boolean },
  data() { return { levels: [], isInstitutionView: !window.Ls.get('selectedSchoolLevel') } },
  computed: {
    selected: {
      get() { return this.value || '' },
      set(value) { this.$emit('input', Number(value) || null) },
    },
  },
  async created() {
    if (this.isInstitutionView) {
      const response = await window.axios.get('/api/v1/school-levels')
      this.levels = response.data.levels.filter((level) => level.enabled)
    }
  },
}
</script>
