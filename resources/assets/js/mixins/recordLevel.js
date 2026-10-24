import SchoolLevelField from '../components/SchoolLevelField.vue'

/** Alta: selección obligatoria. Edición: conservar el nivel recibido de la API. */
export default {
  components: { SchoolLevelField },
  provide() { return { recordSchoolLevel: () => this.recordLevelId } },
  computed: {
    recordLevelId() {
      const record = this.newInvoice || this.newEstimate || this.formData || {}
      return record.school_level_id || window.Ls.get('selectedSchoolLevel') || null
    },
    levelBillingStudents() {
      return (this.billingStudents || []).filter(row => String(row.school_level_id) === String(this.recordLevelId))
    },
    levelInvoiceList() {
      return (this.invoiceList || []).filter(row => String(row.school_level_id) === String(this.recordLevelId))
    },
  },
  methods: {
    onRecordLevelChange() {
      if (this.isEdit) return
      const record = this.newInvoice || this.newEstimate || this.formData
      for (const field of ['student_id', 'family_member_id', 'enrollment_id', 'invoice_id', 'user_id']) {
        if (Object.prototype.hasOwnProperty.call(record, field)) record[field] = null
      }
      if (record.items) record.items = record.items.map(item => ({ ...item, item_id: null, name: '', price: 0, total: 0, valid: false }))
      if (this.newInvoice || this.newEstimate) this.selectedCustomer = null
      if (this.invoiceList) { this.invoice = null; record.amount = 0; this.maxPayableAmount = Number.MAX_SAFE_INTEGER }
    },
    requireRecordLevel(record) {
      if (!this.isEdit && !window.Ls.get('selectedSchoolLevel') && !record.school_level_id) {
        this.showNotification({ type: 'error', message: 'Seleccioná un nivel institucional antes de guardar.' })
        return false
      }
      return true
    },
  },
}
