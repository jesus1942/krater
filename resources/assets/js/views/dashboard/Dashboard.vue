<template>
  <base-page class="ena-inicio">
    <div class="flex flex-wrap items-end justify-between gap-2 mb-6">
      <div>
        <p class="text-xs font-semibold tracking-widest text-gray-500 uppercase">
          {{ subtitulo }}
        </p>
        <h1 class="text-2xl font-semibold text-gray-900">{{ saludo }}</h1>
      </div>
    </div>

    <p v-if="error" class="p-4 text-sm text-red-700 bg-red-50 rounded">
      No se pudo cargar el inicio. Volvé a intentar en unos minutos.
    </p>

    <template v-else-if="data">
      <div class="grid gap-4 mb-8 sm:grid-cols-2 xl:grid-cols-4">
        <router-link v-if="data.academic" to="/admin/students" class="ena-tarjeta">
          <span class="ena-tarjeta__valor">{{ data.academic.students }}</span>
          <span class="ena-tarjeta__rotulo">Alumnos con matrícula vigente</span>
          <span v-if="data.academic.year" class="ena-tarjeta__nota">Ciclo lectivo {{ data.academic.year }}</span>
        </router-link>

        <router-link v-if="data.academic" to="/admin/settings/academic-structure" class="ena-tarjeta">
          <span class="ena-tarjeta__valor">{{ data.academic.divisions_count }}</span>
          <span class="ena-tarjeta__rotulo">Divisiones</span>
          <span v-if="ocupacionTotal !== null" class="ena-tarjeta__nota">{{ ocupacionTotal }} % de ocupación</span>
        </router-link>

        <router-link v-if="data.staff" to="/admin/staff" class="ena-tarjeta">
          <span class="ena-tarjeta__valor">{{ data.staff.active }}</span>
          <span class="ena-tarjeta__rotulo">Personal en actividad</span>
        </router-link>

        <router-link v-if="data.finance" to="/admin/invoices" class="ena-tarjeta">
          <span class="ena-tarjeta__valor" :class="{ 'ena-tarjeta__valor--alerta': data.finance.overdue_total > 0 }"
            v-html="dinero(data.finance.overdue_total)" />
          <span class="ena-tarjeta__rotulo">Cuotas vencidas</span>
          <span class="ena-tarjeta__nota">
            {{ data.finance.families_overdue }} {{ data.finance.families_overdue === 1 ? 'familia' : 'familias' }} con deuda
          </span>
        </router-link>
      </div>

      <div class="grid gap-6 xl:grid-cols-5">
        <section v-if="data.academic" class="ena-panel" :class="data.finance ? 'xl:col-span-3' : 'xl:col-span-5'">
          <header class="ena-panel__cabecera">
            <h2>Matrícula por curso</h2>
            <router-link to="/admin/students" class="ena-enlace">Ver alumnos</router-link>
          </header>
          <p v-if="!data.academic.divisions.length" class="ena-vacio">
            Todavía no hay divisiones en el ciclo lectivo vigente.
          </p>
          <table v-else class="w-full text-sm">
            <thead>
              <tr class="text-xs tracking-wide text-left text-gray-500 uppercase border-b">
                <th v-if="institucional" class="py-2 font-semibold">Nivel</th>
                <th class="py-2 font-semibold">Curso y división</th>
                <th class="hidden py-2 font-semibold sm:table-cell">Turno</th>
                <th class="py-2 font-semibold w-2/5">Inscriptos</th>
              </tr>
            </thead>
            <tbody>
              <tr v-for="d in data.academic.divisions" :key="d.id" class="border-b last:border-0">
                <td v-if="institucional" class="py-2 pr-3 text-gray-600">{{ d.level }}</td>
                <td class="py-2 pr-3 font-medium text-gray-900 whitespace-nowrap">{{ d.grade }} {{ d.division }}</td>
                <td class="hidden py-2 text-gray-600 sm:table-cell">{{ turnos[d.shift] || '—' }}</td>
                <td class="py-2">
                  <div class="flex items-center gap-3">
                    <div v-if="d.capacity" class="ena-barra">
                      <div class="ena-barra__relleno" :class="{ 'ena-barra__relleno--lleno': d.enrolled >= d.capacity }"
                        :style="{ width: Math.min(100, Math.round(d.enrolled * 100 / d.capacity)) + '%' }" />
                    </div>
                    <span class="text-gray-700 whitespace-nowrap">
                      {{ d.enrolled }}<template v-if="d.capacity"> de {{ d.capacity }}</template>
                    </span>
                  </div>
                </td>
              </tr>
            </tbody>
          </table>
        </section>

        <div v-if="data.finance" class="grid content-start gap-6" :class="data.academic ? 'xl:col-span-2' : 'xl:col-span-5'">
          <section class="ena-panel">
            <header class="ena-panel__cabecera">
              <h2>Cobranza de {{ mesActual }}</h2>
              <router-link to="/admin/payments" class="ena-enlace">Ver cobros</router-link>
            </header>
            <dl class="grid grid-cols-2 gap-4">
              <div>
                <dt class="text-xs text-gray-500">Cuotas emitidas</dt>
                <dd class="text-lg font-semibold text-gray-900" v-html="dinero(data.finance.month.billed)" />
              </div>
              <div>
                <dt class="text-xs text-gray-500">Cobrado</dt>
                <dd class="text-lg font-semibold text-gray-900" v-html="dinero(data.finance.month.collected)" />
              </div>
            </dl>
            <div v-if="data.finance.month.rate !== null" class="mt-4">
              <div class="ena-barra ena-barra--ancha">
                <div class="ena-barra__relleno" :style="{ width: data.finance.month.rate + '%' }" />
              </div>
              <p class="mt-1 text-xs text-gray-500">{{ data.finance.month.rate }} % de lo emitido en el mes</p>
            </div>
            <p class="mt-4 text-xs text-gray-500">
              Saldo total pendiente: <span class="font-semibold text-gray-700" v-html="dinero(data.finance.due_total)" />
            </p>
          </section>

          <section class="ena-panel">
            <header class="ena-panel__cabecera">
              <h2>Familias con cuotas vencidas</h2>
            </header>
            <p v-if="!data.finance.top_overdue.length" class="ena-vacio">
              No hay cuotas vencidas.
            </p>
            <ul v-else class="divide-y">
              <li v-for="f in data.finance.top_overdue" :key="f.customer_id" class="flex items-center justify-between gap-3 py-2">
                <router-link :to="`/admin/customers/${f.customer_id}/view`" class="min-w-0">
                  <span class="block font-medium text-gray-900 truncate">{{ f.name }}</span>
                  <span class="block text-xs text-gray-500">
                    {{ f.fees }} {{ f.fees === 1 ? 'cuota' : 'cuotas' }} · desde {{ fecha(f.oldest) }}
                  </span>
                </router-link>
                <span class="font-semibold text-red-700 whitespace-nowrap" v-html="dinero(f.amount)" />
              </li>
            </ul>
          </section>
        </div>
      </div>
    </template>

    <div v-else class="grid gap-4 sm:grid-cols-2 xl:grid-cols-4">
      <div v-for="n in 4" :key="n" class="h-24 bg-white rounded shadow animate-pulse" />
    </div>
  </base-page>
</template>

<script>
import { mapGetters } from 'vuex'

const MESES = ['enero', 'febrero', 'marzo', 'abril', 'mayo', 'junio', 'julio', 'agosto',
  'septiembre', 'octubre', 'noviembre', 'diciembre']

export default {
  data() {
    return { data: null, error: false }
  },

  computed: {
    ...mapGetters('company', ['defaultCurrency']),
    turnos: () => ({ morning: 'Mañana', afternoon: 'Tarde', evening: 'Noche' }),
    institucional() {
      return this.data && !this.data.school_level
    },
    subtitulo() {
      return this.data && this.data.school_level ? `Nivel ${this.data.school_level}` : 'Toda la institución'
    },
    saludo() {
      const user = this.$store.state.user.currentUser
      const nombre = user && user.name ? user.name.split(' ')[0] : ''
      return nombre ? `Hola, ${nombre}` : 'Inicio'
    },
    mesActual() {
      return MESES[new Date().getMonth()]
    },
    ocupacionTotal() {
      const a = this.data && this.data.academic
      return a && a.capacity ? Math.round(a.occupied_with_capacity * 100 / a.capacity) : null
    },
  },

  async created() {
    try {
      const response = await window.axios.get('/api/v1/dashboard/school')
      this.data = response.data
    } catch (e) {
      this.error = true
    }
  },

  methods: {
    dinero(amount) {
      return this.$utils.formatMoney(amount || 0, this.defaultCurrency)
    },
    fecha(iso) {
      if (!iso) return ''
      const [y, m, d] = iso.split('-')
      return `${d}/${m}/${y}`
    },
  },
}
</script>

<style scoped>
.ena-tarjeta {
  display: flex;
  flex-direction: column;
  padding: 1rem 1.25rem;
  background: #fff;
  border-radius: 0.5rem;
  border-top: 3px solid #102340;
  box-shadow: 0 1px 3px rgba(16, 35, 64, 0.08);
  transition: box-shadow 0.2s ease;
}
.ena-tarjeta:hover {
  box-shadow: 0 6px 18px rgba(16, 35, 64, 0.12);
}
.ena-tarjeta__valor {
  font-size: 1.75rem;
  font-weight: 600;
  line-height: 1.2;
  color: #102340;
}
.ena-tarjeta__valor--alerta {
  color: #a5121c;
}
.ena-tarjeta__rotulo {
  margin-top: 0.25rem;
  font-size: 0.9rem;
  color: #4b5563;
}
.ena-tarjeta__nota {
  margin-top: 0.25rem;
  font-size: 0.75rem;
  color: #9ca3af;
}
.ena-panel {
  padding: 1.25rem;
  background: #fff;
  border-radius: 0.5rem;
  box-shadow: 0 1px 3px rgba(16, 35, 64, 0.08);
}
.ena-panel__cabecera {
  display: flex;
  align-items: baseline;
  justify-content: space-between;
  gap: 1rem;
  margin-bottom: 1rem;
}
.ena-panel__cabecera h2 {
  font-size: 1.05rem;
  font-weight: 600;
  color: #102340;
}
.ena-enlace {
  font-size: 0.85rem;
  color: #a5121c;
}
.ena-enlace:hover {
  text-decoration: underline;
}
.ena-vacio {
  font-size: 0.9rem;
  color: #6b7280;
}
.ena-barra {
  flex: 1;
  height: 0.5rem;
  min-width: 4rem;
  background: #eef0f4;
  border-radius: 999px;
  overflow: hidden;
}
.ena-barra--ancha {
  height: 0.6rem;
}
.ena-barra__relleno {
  height: 100%;
  background: #102340;
  border-radius: 999px;
}
.ena-barra__relleno--lleno {
  background: #a5121c;
}
</style>
