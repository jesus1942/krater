<template>
  <div>
    <sw-card variant="setting-card">
      <template slot="header">
        <h6 class="sw-section-title">Niveles institucionales</h6>
        <p class="mt-2 text-sm leading-snug text-gray-500">
          Configurá Primario, Secundario y Terciario de forma independiente. Estos datos se usarán en documentos, comunicaciones e informes de cada nivel.
        </p>
      </template>

      <div v-if="loading" class="py-10 text-center text-gray-500">Cargando niveles…</div>
      <div v-else class="space-y-5">
        <section v-for="level in levels" :key="level.id" class="overflow-hidden border border-gray-200 rounded-lg">
          <button type="button" class="flex items-center justify-between w-full px-5 py-4 text-left bg-gray-50" @click="toggle(level.id)">
            <div><strong class="text-base">{{ level.name }}</strong><span class="block text-xs text-gray-500">{{ codeLabel(level.code) }}</span></div>
            <span class="text-primary-500">{{ openId === level.id ? 'Cerrar' : 'Configurar' }}</span>
          </button>
          <form v-if="openId === level.id" class="p-5" @submit.prevent="save(level)">
            <div class="grid gap-4 md:grid-cols-2">
              <field label="Nombre visible"><sw-input v-model="level.name" required /></field>
              <field label="Nombre legal"><sw-input v-model="level.legal_name" /></field>
              <field label="CUE"><sw-input v-model="level.cue" /></field>
              <field label="Código jurisdiccional / registro"><sw-input v-model="level.jurisdiction_code" /></field>
              <field label="Resolución de autorización"><sw-input v-model="level.resolution_number" /></field>
              <field label="CUIT"><sw-input v-model="level.tax_id" /></field>
              <field label="Razón social para facturación"><sw-input v-model="level.billing_name" /></field>
              <field label="Correo institucional"><sw-input v-model="level.email" type="email" /></field>
              <field label="Teléfono"><sw-input v-model="level.phone" /></field>
              <field label="Sitio web"><sw-input v-model="level.website" /></field>
              <field label="Domicilio"><sw-input v-model="level.address" /></field>
              <field label="Ciudad"><sw-input v-model="level.city" /></field>
              <field label="Provincia"><sw-input v-model="level.province" /></field>
              <field label="Código postal"><sw-input v-model="level.postal_code" /></field>
              <field label="Director/a o rector/a"><sw-input v-model="level.director_name" /></field>
              <field label="Secretario/a"><sw-input v-model="level.secretary_name" /></field>
              <field label="Responsable administrativo/contable"><sw-input v-model="level.accounting_contact" /></field>
              <label class="flex items-center gap-2 mt-6 text-sm"><input v-model="level.enabled" type="checkbox" /> Nivel habilitado</label>
            </div>
            <div class="flex justify-end mt-6"><sw-button :loading="savingId === level.id" variant="primary">Guardar {{ level.name }}</sw-button></div>
          </form>
        </section>
      </div>
    </sw-card>
  </div>
</template>

<script>
const Field = { props: ['label'], template: '<label class="block text-sm text-gray-700"><span class="block mb-1">{{ label }}</span><slot /></label>' }
export default {
  components: { Field },
  data() { return { levels: [], openId: null, loading: true, savingId: null } },
  async created() { await this.load() },
  methods: {
    async load() { this.loading = true; const response = await window.axios.get('/api/v1/school-levels'); this.levels = response.data.levels; this.loading = false },
    toggle(id) { this.openId = this.openId === id ? null : id },
    codeLabel(code) { return { primary: 'Educación Primaria', secondary: 'Educación Secundaria', tertiary: 'Educación Superior' }[code] || code },
    async save(level) { this.savingId = level.id; await window.axios.put(`/api/v1/school-levels/${level.id}`, level); this.savingId = null },
  },
}
</script>
