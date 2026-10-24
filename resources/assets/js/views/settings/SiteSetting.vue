<template>
  <div class="sitio-config">
    <sw-card variant="setting-card">
      <template slot="header">
        <h6 class="sw-section-title">Sitio público</h6>
        <p class="mt-2 text-sm leading-snug text-gray-500" style="max-width: 680px">
          Textos, contacto y fotos de la página pública de la escuela. Lo que dejes vacío muestra el texto
          predeterminado. Los cambios se ven al instante en el sitio.
        </p>
      </template>

      <p v-if="error" class="aviso aviso--error">{{ error }}</p>
      <p v-else-if="!data" class="text-sm text-gray-500">Cargando…</p>

      <template v-else>
        <nav class="pestanas" aria-label="Secciones">
          <button v-if="data.can_edit_institution" type="button" :class="{ activa: tab === 'institution' }" @click="tab = 'institution'">Institución</button>
          <button v-for="(level, slug) in data.levels" :key="slug" type="button" :class="{ activa: tab === slug }" @click="tab = slug">{{ level.name }}</button>
        </nav>

        <!-- Institucion -->
        <section v-if="tab === 'institution'" class="space-y-6">
          <foto-campo titulo="Foto principal de la portada" :imagen="data.hero_image" :max-mb="data.limits.max_image_mb"
            ayuda="Horizontal, de al menos 1920 px de ancho. Se ve detrás del nombre de la escuela."
            @subir="subir('hero', $event)" @quitar="quitar('hero')" />

          <form class="grid gap-4 md:grid-cols-2" @submit.prevent="guardarInstitucion">
            <campo-texto v-model="institution.tagline" class="md:col-span-2" etiqueta="Frase de la portada" :defecto="data.institution_defaults.tagline" />
            <campo-texto v-model="institution.about" class="md:col-span-2" etiqueta="Texto de «La institución»" :defecto="data.institution_defaults.about" largo />
            <campo-texto v-model="institution.address" etiqueta="Dirección" :defecto="data.institution_defaults.address" />
            <campo-texto v-model="institution.city" etiqueta="Ciudad" :defecto="data.institution_defaults.city" />
            <campo-texto v-model="institution.phone" etiqueta="Teléfono" :defecto="data.institution_defaults.phone" />
            <campo-texto v-model="institution.whatsapp" etiqueta="WhatsApp (con código de país)" :defecto="data.institution_defaults.whatsapp" />
            <campo-texto v-model="institution.email" etiqueta="Correo" :defecto="data.institution_defaults.email" tipo="email" />
            <campo-texto v-model="institution.instagram" etiqueta="Instagram" :defecto="data.institution_defaults.instagram" />
            <div class="flex justify-end md:col-span-2">
              <sw-button type="submit" variant="primary" :loading="guardando">Guardar institución</sw-button>
            </div>
          </form>
        </section>

        <!-- Un nivel -->
        <section v-for="(level, slug) in data.levels" v-show="tab === slug" :key="slug" class="space-y-6">
          <foto-campo :titulo="'Foto del nivel ' + level.name" :imagen="level.image" :max-mb="data.limits.max_image_mb"
            ayuda="Horizontal. Se ve en la portada de la página del nivel."
            @subir="subir('level.' + slug, $event)" @quitar="quitar('level.' + slug)" />

          <form class="grid gap-4 md:grid-cols-2" @submit.prevent="guardarNivel(slug)">
            <campo-texto v-model="levels[slug].tagline" class="md:col-span-2" etiqueta="Frase del nivel" :defecto="level.defaults.tagline" />
            <campo-texto v-model="levels[slug].summary" class="md:col-span-2" etiqueta="Presentación" :defecto="level.defaults.summary" largo />
            <template v-for="i in 3">
              <campo-texto :key="'t' + i" v-model="levels[slug]['highlight_' + i + '_title']" :etiqueta="'Destacado ' + i"
                :defecto="level.defaults.highlights[i - 1].title" />
              <campo-texto :key="'x' + i" v-model="levels[slug]['highlight_' + i + '_text']" :etiqueta="'Detalle del destacado ' + i"
                :defecto="level.defaults.highlights[i - 1].text" />
            </template>
            <p class="text-sm text-gray-500 md:col-span-2">Contacto del nivel. Si queda vacío se muestra el de la institución.</p>
            <campo-texto v-model="levels[slug].phone" etiqueta="Teléfono" />
            <campo-texto v-model="levels[slug].whatsapp" etiqueta="WhatsApp (con código de país)" />
            <campo-texto v-model="levels[slug].email" class="md:col-span-2" etiqueta="Correo" tipo="email" />
            <div class="flex items-center justify-between gap-3 md:col-span-2">
              <a :href="'/' + slug" target="_blank" rel="noopener" class="text-sm text-primary-500 hover:underline">Ver la página del nivel</a>
              <sw-button type="submit" variant="primary" :loading="guardando">Guardar {{ level.name }}</sw-button>
            </div>
          </form>
        </section>
      </template>
    </sw-card>
  </div>
</template>

<script>
import { mapActions } from 'vuex'

const CampoTexto = {
  props: { value: { type: String, default: '' }, etiqueta: String, defecto: String, largo: Boolean, tipo: { type: String, default: 'text' } },
  template: `<label class="block text-sm text-gray-700">
    <span class="block mb-1 font-medium">{{ etiqueta }}</span>
    <textarea v-if="largo" :value="value" rows="3" maxlength="1200" class="entrada" :placeholder="defecto" @input="$emit('input', $event.target.value)" />
    <input v-else :value="value" :type="tipo" maxlength="255" class="entrada" :placeholder="defecto" @input="$emit('input', $event.target.value)" />
  </label>`,
}

const FotoCampo = {
  props: { titulo: String, imagen: Object, ayuda: String, maxMb: Number },
  data: () => ({ alt: '' }),
  template: `<div class="foto">
    <div class="foto__vista" :style="imagen ? { backgroundImage: 'url(' + imagen.url + ')' } : {}">
      <span v-if="!imagen">Sin foto: se usa el fondo institucional</span>
    </div>
    <div class="foto__datos">
      <p class="font-medium text-gray-800">{{ titulo }}</p>
      <p class="text-sm text-gray-500">{{ ayuda }} JPG, PNG o WebP, hasta {{ maxMb }} MB.</p>
      <input v-model="alt" class="mt-3 entrada" maxlength="255" placeholder="Descripción de la foto (para accesibilidad)" />
      <div class="flex flex-wrap gap-3 mt-3">
        <label class="boton-archivo">
          {{ imagen ? 'Cambiar foto' : 'Subir foto' }}
          <input type="file" accept="image/jpeg,image/png,image/webp" class="hidden" @change="elegir" />
        </label>
        <button v-if="imagen" type="button" class="text-sm text-red-600 hover:underline" @click="$emit('quitar')">Quitar foto</button>
      </div>
    </div>
  </div>`,
  methods: {
    elegir(e) {
      const file = e.target.files[0]
      e.target.value = ''
      if (file) this.$emit('subir', { file, alt: this.alt })
    },
  },
}

export default {
  components: { CampoTexto, FotoCampo },
  data() {
    return { data: null, error: '', tab: 'institution', institution: {}, levels: {}, guardando: false }
  },
  async created() { await this.cargar() },
  methods: {
    ...mapActions('notification', ['showNotification']),
    mensaje(e, fallback) {
      const d = e.response && e.response.data
      if (d && d.errors) { const first = Object.values(d.errors)[0]; if (first && first.length) return first[0] }
      return (d && d.message) || fallback
    },
    mostrar(data) {
      this.data = data
      this.institution = { ...data.institution }
      this.levels = Object.fromEntries(Object.entries(data.levels).map(([slug, l]) => [slug, { ...l.values }]))
      if (!data.can_edit_institution && this.tab === 'institution') this.tab = Object.keys(data.levels)[0]
    },
    async cargar() {
      try {
        const { data } = await window.axios.get('/api/v1/site/settings')
        this.mostrar(data)
      } catch (e) { this.error = this.mensaje(e, 'No se pudo cargar el sitio público.') }
    },
    async guardar(request, ok) {
      this.guardando = true
      try {
        const { data } = await request()
        this.mostrar(data)
        this.showNotification({ type: 'success', message: ok })
      } catch (e) {
        this.showNotification({ type: 'error', message: this.mensaje(e, 'No se pudo guardar.') })
      } finally { this.guardando = false }
    },
    guardarInstitucion() { return this.guardar(() => window.axios.put('/api/v1/site/institution', this.institution), 'Datos de la institución guardados.') },
    guardarNivel(slug) { return this.guardar(() => window.axios.put(`/api/v1/site/levels/${slug}`, this.levels[slug]), 'Página del nivel guardada.') },
    subir(key, { file, alt }) {
      const form = new FormData()
      form.append('image', file)
      if (alt) form.append('alt', alt)
      return this.guardar(() => window.axios.post(`/api/v1/site/images/${key}`, form), 'Foto actualizada.')
    },
    quitar(key) {
      if (!window.confirm('¿Quitar la foto? Se vuelve a ver el fondo institucional.')) return
      return this.guardar(() => window.axios.delete(`/api/v1/site/images/${key}`), 'Foto quitada.')
    },
  },
}
</script>

<style scoped>
.pestanas { display: flex; flex-wrap: wrap; gap: 6px; margin-bottom: 24px; border-bottom: 1px solid #e5e7eb; }
.pestanas button { padding: 10px 16px; font-size: .9rem; color: #6b7280; border-bottom: 2px solid transparent; margin-bottom: -1px; }
.pestanas button.activa { color: #0f2340; font-weight: 600; border-color: #a5121c; }
.aviso { padding: .75rem 1rem; font-size: .875rem; border-radius: .375rem; }
.aviso--error { color: #b91c1c; background: #fef2f2; border: 1px solid #fecaca; }
.sitio-config ::v-deep .entrada { width: 100%; min-height: 42px; padding: .55rem .75rem; border: 1px solid #d9e2ec; border-radius: .375rem; background: #fff; font-size: .9rem; }
.sitio-config ::v-deep .foto { display: grid; grid-template-columns: 260px 1fr; gap: 20px; align-items: start; padding: 16px; border: 1px solid #e5e7eb; border-radius: .5rem; background: #f9fafb; }
.sitio-config ::v-deep .foto__vista { display: grid; place-items: center; aspect-ratio: 16 / 9; border-radius: .375rem; background: #0f2340 center / cover no-repeat; color: rgba(255, 255, 255, .75); font-size: .8rem; text-align: center; padding: 8px; }
.sitio-config ::v-deep .boton-archivo { display: inline-flex; align-items: center; padding: .5rem 1rem; font-size: .875rem; font-weight: 600; color: #fff; background: #0f2340; border-radius: .375rem; cursor: pointer; }
@media (max-width: 720px) { .sitio-config ::v-deep .foto { grid-template-columns: 1fr; } }
</style>
