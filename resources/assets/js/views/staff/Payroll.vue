<template>
  <base-page class="payroll-page">
    <sw-page-header title="Liquidaciones y pagos">
      <sw-breadcrumb slot="breadcrumbs">
        <sw-breadcrumb-item title="Inicio" to="/admin/dashboard" />
        <sw-breadcrumb-item title="Recursos Humanos" to="/admin/staff" />
        <sw-breadcrumb-item title="Liquidaciones y pagos" active />
      </sw-breadcrumb>
      <template slot="actions">
        <sw-button variant="primary" @click="showPeriodForm = !showPeriodForm">Nuevo período</sw-button>
      </template>
    </sw-page-header>

    <div class="mt-6 space-y-5">
      <div class="p-4 border rounded-lg bg-blue-50 text-sm text-blue-900">
        Este módulo registra liquidaciones internas y pagos al personal. Los importes se guardan sin destruir historial: los pagos erróneos se revierten, no se eliminan.
      </div>

      <sw-card v-if="showPeriodForm" variant="setting-card">
        <template slot="header"><h6 class="sw-section-title">Abrir período de liquidación</h6></template>
        <div class="grid gap-4 md:grid-cols-4">
          <label class="field"><span>Nivel *</span><select v-model.number="periodForm.school_level_id" class="input" required><option disabled :value="null">Seleccionar</option><option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option></select></label>
          <label class="field"><span>Año *</span><input v-model.number="periodForm.year" type="number" min="2020" max="2100" class="input" /></label>
          <label class="field"><span>Mes *</span><select v-model.number="periodForm.month" class="input"><option v-for="(m,i) in months" :key="m" :value="i+1">{{ m }}</option></select></label>
          <div class="flex items-end"><sw-button variant="primary" :loading="saving" @click="createPeriod">Crear período</sw-button></div>
        </div>
      </sw-card>

      <div v-if="loading" class="py-12 text-center text-gray-500">Cargando liquidaciones…</div>
      <div v-else-if="!periods.length" class="p-8 text-center bg-white border rounded-lg text-gray-500">Todavía no hay períodos de liquidación.</div>

      <section v-for="period in periods" :key="period.id" class="p-5 bg-white border rounded-lg shadow-sm">
        <div class="flex flex-wrap items-start justify-between gap-4">
          <div>
            <h2 class="text-lg font-semibold">{{ monthLabel(period.month) }} {{ period.year }}</h2>
            <p class="text-sm text-gray-500">{{ period.school_level ? period.school_level.name : 'Nivel' }} · {{ statusLabel(period.status) }}</p>
          </div>
          <sw-button size="sm" variant="primary" @click="openSlip(period)">Cargar liquidación</sw-button>
        </div>

        <div v-if="slipPeriodId === period.id" class="p-4 mt-4 border rounded-lg bg-gray-50">
          <div class="grid gap-4 md:grid-cols-2 lg:grid-cols-4">
            <label class="field lg:col-span-2">
              <span>Personal *</span>
              <select v-model.number="slipForm.staff_member_id" class="input">
                <option disabled :value="null">Seleccionar</option>
                <option v-for="s in eligibleStaff(period)" :key="s.id" :value="s.id">{{ s.last_name }}, {{ s.first_name }} · {{ assignmentLabel(s, period.school_level_id) }}</option>
              </select>
            </label>
            <label class="field"><span>Bruto *</span><input v-model="slipForm.gross" type="number" min="0" step="0.01" class="input" /></label>
            <label class="field"><span>Descuentos *</span><input v-model="slipForm.deductions" type="number" min="0" step="0.01" class="input" /></label>
          </div>
          <div class="mt-3 text-sm"><strong>Neto:</strong> {{ money(toMinor(slipForm.gross) - toMinor(slipForm.deductions)) }}</div>
          <div class="flex justify-end gap-2 mt-3"><sw-button size="sm" variant="gray" @click="slipPeriodId = null">Cancelar</sw-button><sw-button size="sm" variant="primary" :loading="saving" @click="saveSlip(period)">Guardar liquidación</sw-button></div>
        </div>

        <div v-if="!period.slips.length" class="mt-4 text-sm text-gray-500">No hay liquidaciones cargadas en este período.</div>
        <div v-else class="mt-4 overflow-x-auto">
          <table class="w-full text-sm">
            <thead><tr class="text-left border-b"><th class="py-2">Personal</th><th>Bruto</th><th>Descuentos</th><th>Neto</th><th>Pagado</th><th>Estado</th><th></th></tr></thead>
            <tbody>
              <template v-for="slip in period.slips">
                <tr :key="`slip-${slip.id}`" class="border-b align-top">
                  <td class="py-3"><strong>{{ slip.staff_member ? slip.staff_member.last_name + ', ' + slip.staff_member.first_name : 'Personal' }}</strong><div class="text-xs text-gray-500">{{ slip.staff_member ? assignmentLabel(slip.staff_member, period.school_level_id) : '' }}</div></td>
                  <td>{{ money(slip.gross_amount) }}</td><td>{{ money(slip.deductions_amount) }}</td><td>{{ money(slip.net_amount) }}</td><td>{{ money(activePaid(slip)) }}</td><td>{{ statusLabel(slip.status) }}</td>
                  <td class="text-right whitespace-nowrap"><button v-if="slip.status === 'draft'" class="action" @click="approveSlip(slip)">Aprobar</button><button v-if="['approved','partially_paid'].includes(slip.status)" class="action ml-3" @click="openPayment(slip)">Registrar pago</button></td>
                </tr>
                <tr v-if="paymentSlipId === slip.id" :key="`pay-${slip.id}`" class="border-b bg-gray-50"><td colspan="7" class="p-4"><div class="grid gap-3 md:grid-cols-4"><label class="field"><span>Importe *</span><input v-model="paymentForm.amount" type="number" min="0.01" step="0.01" class="input" /></label><label class="field"><span>Fecha *</span><input v-model="paymentForm.paid_at" type="date" class="input" /></label><label class="field"><span>Medio *</span><select v-model="paymentForm.payment_method" class="input"><option value="transfer">Transferencia</option><option value="cash">Efectivo</option><option value="check">Cheque</option><option value="other">Otro</option></select></label><label class="field"><span>Referencia</span><input v-model.trim="paymentForm.reference" class="input" /></label></div><div class="mt-2 text-sm text-gray-600">Saldo pendiente: {{ money(Math.max(0, slip.net_amount - activePaid(slip))) }}</div><div class="flex justify-end gap-2 mt-3"><sw-button size="sm" variant="gray" @click="paymentSlipId = null">Cancelar</sw-button><sw-button size="sm" variant="primary" :loading="saving" @click="savePayment(slip)">Registrar pago</sw-button></div></td></tr>
                <tr v-for="p in slip.payments" :key="`payment-row-${p.id}`" class="text-xs border-b"><td class="py-2 pl-4 text-gray-500">Pago {{ p.paid_at }} · {{ paymentMethodLabel(p.payment_method) }}</td><td colspan="3" class="text-gray-500">{{ p.reference || 'Sin referencia' }}</td><td :class="p.reversed_at ? 'line-through text-gray-400' : 'text-green-700'">{{ money(p.amount) }}</td><td>{{ p.reversed_at ? 'Revertido' : 'Registrado' }}</td><td class="text-right"><button v-if="!p.reversed_at" class="text-red-500" @click="reversePayment(p)">Revertir</button></td></tr>
              </template>
            </tbody>
          </table>
        </div>
      </section>
    </div>
  </base-page>
</template>

<script>
export default {
  data() {
    const now = new Date()
    return {
      periods: [], levels: [], staff: [], loading: true, saving: false, showPeriodForm: false,
      slipPeriodId: null, paymentSlipId: null,
      months: ['Enero','Febrero','Marzo','Abril','Mayo','Junio','Julio','Agosto','Septiembre','Octubre','Noviembre','Diciembre'],
      periodForm: { school_level_id: null, year: now.getFullYear(), month: now.getMonth() + 1 },
      slipForm: { staff_member_id: null, gross: '', deductions: '0' },
      paymentForm: { amount: '', paid_at: now.toISOString().slice(0,10), payment_method: 'transfer', reference: '' },
    }
  },
  async created() { await this.load() },
  methods: {
    async load() { this.loading = true; try { const r = await window.axios.get('/api/v1/payroll'); this.periods = r.data.data || []; this.levels = r.data.levels || []; this.staff = r.data.staff || []; if (!this.periodForm.school_level_id && this.levels.length === 1) this.periodForm.school_level_id = this.levels[0].id } finally { this.loading = false } },
    toMinor(v) { const n = Number(v || 0); return Math.round(n * 100) },
    money(v) { return new Intl.NumberFormat('es-AR', { style: 'currency', currency: 'ARS' }).format(Number(v || 0) / 100) },
    monthLabel(m) { return this.months[Number(m)-1] || m },
    statusLabel(v) { return { draft:'Borrador', approved:'Aprobada', partially_paid:'Pago parcial', paid:'Pagada', closed:'Cerrado', void:'Anulada' }[v] || v },
    categoryLabel(v) { return { teaching:'Docente', administrative:'Administrativo', management:'Dirección / gestión', maintenance:'Mantenimiento', cleaning:'Limpieza', support:'Apoyo', other:'Otro' }[v] || v },
    paymentMethodLabel(v) { return { transfer:'Transferencia', cash:'Efectivo', check:'Cheque', other:'Otro' }[v] || v },
    activePaid(slip) { return (slip.payments || []).filter(p => !p.reversed_at).reduce((s,p) => s + Number(p.amount || 0), 0) },
    // Devuelve sólo personal con un cargo activo en el nivel del período.
    eligibleStaff(period) { return (this.staff || []).filter(s => this.assignmentForLevel(s, period.school_level_id)) },
    // La función se toma del cargo activo del nivel, no de StaffMember.staff_category.
    assignmentForLevel(member, levelId) { return (member.assignments || []).find(a => a.active && Number(a.school_level_id) === Number(levelId)) || null },
    assignmentLabel(member, levelId) { const a = this.assignmentForLevel(member, levelId); if (!a) return 'Sin cargo activo'; const category = this.categoryLabel(a.function_category); return a.position_title ? `${a.position_title} · ${category}` : category },
    async createPeriod() { if (!this.periodForm.school_level_id) return; this.saving = true; try { await window.axios.post('/api/v1/payroll/periods', this.periodForm); this.showPeriodForm = false; await this.load() } finally { this.saving = false } },
    openSlip(period) { this.slipPeriodId = period.id; this.paymentSlipId = null; this.slipForm = { staff_member_id: null, gross: '', deductions: '0' } },
    async saveSlip(period) { if (!this.slipForm.staff_member_id) return; this.saving = true; try { await window.axios.post(`/api/v1/payroll/periods/${period.id}/slips`, { staff_member_id: this.slipForm.staff_member_id, gross_amount: this.toMinor(this.slipForm.gross), deductions_amount: this.toMinor(this.slipForm.deductions) }); this.slipPeriodId = null; await this.load() } finally { this.saving = false } },
    async approveSlip(slip) { await window.axios.post(`/api/v1/payroll/slips/${slip.id}/approve`); await this.load() },
    openPayment(slip) { this.paymentSlipId = slip.id; this.slipPeriodId = null; this.paymentForm = { amount: ((slip.net_amount - this.activePaid(slip)) / 100).toFixed(2), paid_at: new Date().toISOString().slice(0,10), payment_method: 'transfer', reference: '' } },
    async savePayment(slip) { this.saving = true; try { await window.axios.post(`/api/v1/payroll/slips/${slip.id}/payments`, { ...this.paymentForm, amount: this.toMinor(this.paymentForm.amount) }); this.paymentSlipId = null; await this.load() } finally { this.saving = false } },
    async reversePayment(payment) { const reason = window.prompt('Motivo de la reversión'); if (!reason) return; await window.axios.post(`/api/v1/payroll/payments/${payment.id}/reverse`, { reason }); await this.load() },
  },
}
</script>

<style scoped>
.payroll-page .field { display:block; font-size:.875rem; color:#4a5568; }
.payroll-page .field > span { display:block; margin-bottom:.35rem; }
.payroll-page .input { width:100%; min-height:42px; padding:.6rem .75rem; border:1px solid #d9e2ec; border-radius:.375rem; background:white; }
.payroll-page .action { color:#2563eb; font-weight:600; }
</style>
