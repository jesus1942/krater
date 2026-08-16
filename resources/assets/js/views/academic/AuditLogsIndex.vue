<template>
  <div>
    <div class="flex items-start justify-between mb-6">
      <div>
        <h1 class="text-2xl font-semibold text-gray-900">Bitácora de auditoría</h1>
        <p class="mt-1 text-sm text-gray-500">Registro de accesos sensibles y cambios relevantes. Las entradas son de solo lectura.</p>
      </div>
    </div>

    <div class="grid grid-cols-2 gap-3 mb-5 md:grid-cols-4">
      <div v-for="t in tarjetas" :key="t.label" class="p-4 bg-white border rounded-lg" :class="t.clase">
        <div class="text-xs uppercase">{{ t.label }}</div>
        <div class="mt-1 text-2xl font-semibold">{{ t.valor }}</div>
      </div>
    </div>

    <sw-card>
      <div class="p-5">
        <div class="grid grid-cols-1 gap-3 mb-5 md:grid-cols-5">
          <sw-select v-model="filtros.severity" @change="buscar">
            <option value="">Toda gravedad</option>
            <option value="low">Baja</option>
            <option value="medium">Media</option>
            <option value="high">Alta</option>
            <option value="critical">Crítica</option>
          </sw-select>
          <sw-input v-model="filtros.action" placeholder="Acción" @input="buscarConEspera" />
          <sw-input v-model="filtros.desde" type="date" @change="buscar" />
          <sw-input v-model="filtros.hasta" type="date" @change="buscar" />
          <sw-button v-if="hayFiltros" variant="primary-outline" @click="limpiar">Limpiar</sw-button>
        </div>

        <p v-if="loading" class="py-8 text-sm text-center text-gray-500">Cargando bitácora…</p>
        <p v-else-if="error" class="py-8 text-sm text-center text-red-600">{{ error }}</p>

        <div v-else class="overflow-x-auto">
          <table v-if="logs.length" class="w-full text-sm">
            <thead>
              <tr class="text-left text-gray-500 border-b">
                <th class="py-2">Fecha</th>
                <th class="py-2">Usuario</th>
                <th class="py-2">Institución / nivel</th>
                <th class="py-2">Acción</th>
                <th class="py-2">Objeto</th>
                <th class="py-2">Gravedad</th>
                <th class="py-2">Origen</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="l in logs" :key="l.id" class="align-top border-b last:border-0">
                <td class="py-2 whitespace-nowrap text-gray-600">{{ fechaHora(l.created_at) }}</td>
                <td class="py-2"><span v-if="l.user">{{ l.user.name }}</span><span v-else class="text-gray-400">sistema</span></td>
                <td class="py-2 text-xs text-gray-600">
                  <div>{{ l.company ? l.company.name : '—' }}</div>
                  <div class="text-gray-400">{{ l.school_level ? l.school_level.name : 'institucional' }}</div>
                </td>
                <td class="py-2">
                  <span class="font-mono text-xs">{{ l.action }}</span>
                  <button v-if="l.old_values || l.new_values" type="button" class="block mt-1 text-xs text-primary-500 hover:underline" @click="detalle = detalle === l.id ? null : l.id">
                    {{ detalle === l.id ? 'Ocultar cambios' : 'Ver cambios' }}
                  </button>
                  <div v-if="detalle === l.id" class="p-2 mt-2 text-xs bg-gray-50 rounded">
                    <div v-for="(valor, campo) in (l.new_values || {})" :key="campo" class="mb-1">
                      <span class="font-medium">{{ campo }}:</span>
                      <span v-if="l.old_values && l.old_values[campo] !== undefined" class="text-gray-500"> {{ mostrar(l.old_values[campo]) }} → </span>
                      <span>{{ mostrar(valor) }}</span>
                    </div>
                  </div>
                </td>
                <td class="py-2 text-gray-600"><span v-if="l.auditable_type">{{ tipoCorto(l.auditable_type) }} #{{ l.auditable_id }}</span><span v-else class="text-gray-400">—</span></td>
                <td class="py-2"><span class="px-2 py-1 text-xs rounded-full" :class="claseGravedad(l.severity)">{{ gravedad(l.severity) }}</span></td>
                <td class="py-2 text-xs text-gray-500">{{ l.ip_address || '—' }}</td>
              </tr>
            </tbody>
          </table>
          <p v-else class="py-8 text-sm text-center text-gray-500">No hay registros que coincidan con los filtros.</p>
        </div>

        <div v-if="!loading && paginacion.last_page > 1" class="flex items-center justify-between mt-5">
          <span class="text-sm text-gray-500">Página {{ paginacion.current_page }} de {{ paginacion.last_page }} · {{ paginacion.total }} registros</span>
          <div class="flex gap-2">
            <sw-button variant="primary-outline" size="sm" :disabled="paginacion.current_page <= 1" @click="irA(paginacion.current_page - 1)">Anterior</sw-button>
            <sw-button variant="primary-outline" size="sm" :disabled="paginacion.current_page >= paginacion.last_page" @click="irA(paginacion.current_page + 1)">Siguiente</sw-button>
          </div>
        </div>
      </div>
    </sw-card>
  </div>
</template>

<script>
export default {
  data() {
    return { loading: true, error: null, logs: [], paginacion: {}, resumen: {}, detalle: null, temporizador: null, filtros: { severity: '', action: '', desde: '', hasta: '' } }
  },
  computed: {
    hayFiltros() { return Object.values(this.filtros).some((v) => v) },
    tarjetas() {
      return [
        { label: 'Total', valor: this.resumen.total || 0, clase: 'border-gray-200 text-gray-700' },
        { label: 'Últimos 7 días', valor: this.resumen.ultimos_7_dias || 0, clase: 'border-gray-200 text-gray-700' },
        { label: 'Altos (7 días)', valor: this.resumen.altos_7_dias || 0, clase: 'border-yellow-200 bg-yellow-50 text-yellow-800' },
        { label: 'Críticos (7 días)', valor: this.resumen.criticos_7_dias || 0, clase: 'border-red-200 bg-red-50 text-red-800' },
      ]
    },
  },
  async created() { await this.cargar() },
  beforeDestroy() { clearTimeout(this.temporizador) },
  methods: {
    async cargar(pagina = 1) {
      this.loading = true; this.error = null
      try {
        const lista = await window.axios.get('/api/v1/audit-logs', { params: { ...this.filtros, page: pagina } })
        this.logs = lista.data.data
        this.paginacion = { current_page: lista.data.current_page, last_page: lista.data.last_page, total: lista.data.total }
        this.resumen = lista.data.audit_summary || {}
      } catch (e) {
        this.error = e.response && e.response.status === 403 ? 'No tenés permiso para consultar la bitácora.' : 'No se pudo cargar la bitácora.'
      } finally { this.loading = false }
    },
    buscar() { this.cargar(1) },
    buscarConEspera() { clearTimeout(this.temporizador); this.temporizador = setTimeout(() => this.cargar(1), 350) },
    limpiar() { this.filtros = { severity: '', action: '', desde: '', hasta: '' }; this.cargar(1) },
    irA(pagina) { this.cargar(pagina) },
    fechaHora(v) { if (!v) return '—'; return new Date(v).toLocaleString('es-AR', { day: '2-digit', month: '2-digit', year: 'numeric', hour: '2-digit', minute: '2-digit' }) },
    tipoCorto(t) { return String(t).split('\\').pop() },
    mostrar(v) { if (v === null || v === undefined) return '—'; if (typeof v === 'boolean') return v ? 'sí' : 'no'; return String(v) },
    gravedad(s) { return { low: 'Baja', medium: 'Media', high: 'Alta', critical: 'Crítica' }[s] || s },
    claseGravedad(s) { return { low: 'bg-gray-100 text-gray-700', medium: 'bg-blue-100 text-blue-800', high: 'bg-yellow-100 text-yellow-800', critical: 'bg-red-100 text-red-800' }[s] || 'bg-gray-100 text-gray-700' },
  },
}
</script>
