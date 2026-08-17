from pathlib import Path

p = Path('resources/assets/js/views/payments/Create.vue')
s = p.read_text()

old = '''          <sw-input-group
            :label="$t('payments.customer')"
            :error="customerError"
            required
          >
            <sw-select
              v-model="customer"
              :options="customers"
              :searchable="true"
              :show-labels="false"
              :allow-empty="false"
              :disabled="isEdit"
              :placeholder="$t('customers.select_a_customer')"
              label="name"
              class="mt-1"
              track-by="id"
            />
          </sw-input-group>

          <sw-input-group :label="$t('payments.invoice')">
            <sw-select
              v-model="invoice"
              :options="invoiceList"
              :searchable="true"
              :show-labels="false"
              :allow-empty="false"
              :disabled="isEdit"
              :placeholder="$t('invoices.select_invoice')"
              :custom-label="invoiceWithAmount"
              class="mt-1"
              track-by="invoice_number"
            />
          </sw-input-group>'''
new = '''          <sw-input-group label="Alumno" required>
            <select v-model="formData.student_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" :disabled="isEdit" @change="onStudentChange">
              <option :value="null">Seleccionar alumno</option>
              <option v-for="student in billingStudents" :key="student.id" :value="student.id">
                {{ student.full_name }} · {{ student.course || 'Sin curso' }}
              </option>
            </select>
          </sw-input-group>

          <sw-input-group label="Cuota o comprobante">
            <select v-model="formData.invoice_id" class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" :disabled="isEdit" @change="onInvoiceChange">
              <option :value="null">Cobro sin comprobante previo</option>
              <option v-for="row in studentInvoices" :key="row.id" :value="row.id">
                {{ row.invoice_number }} · {{ row.items && row.items.length ? row.items[0].name : 'Cuota' }} · saldo {{ formatDue(row.due_amount) }}
              </option>
            </select>
          </sw-input-group>

          <sw-input-group label="Pagador / responsable financiero" required>
            <select v-model="formData.family_member_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" :disabled="isEdit" @change="onResponsibleChange">
              <option :value="null">Seleccionar responsable</option>
              <option v-for="member in billingResponsibles" :key="member.id" :value="member.id">
                {{ member.name }}{{ member.relationship ? ` · ${member.relationship}` : '' }}
              </option>
            </select>
          </sw-input-group>'''
if old not in s:
    raise SystemExit('legacy payment customer/invoice block not found')
s = s.replace(old, new, 1)

s = s.replace("        user_id: null,\n", "        user_id: null,\n        student_id: null,\n        family_member_id: null,\n")
s = s.replace("      invoiceList: [],\n", "      invoiceList: [],\n      billingStudents: [],\n")
s = s.replace("      customer: {\n        required,\n      },\n", '')
s = s.replace("    ...mapGetters('customer', ['customers']),\n", '')

insert = '''    selectedBillingStudent() {
      return this.billingStudents.find((student) => Number(student.id) === Number(this.formData.student_id)) || null
    },
    billingResponsibles() {
      return this.selectedBillingStudent ? (this.selectedBillingStudent.financial_responsibles || []) : []
    },
    studentInvoices() {
      return this.invoiceList.filter((row) => Number(row.student_id) === Number(this.formData.student_id))
    },
'''
s = s.replace("    amount: {\n", insert + "    amount: {\n", 1)

# Currency no longer depends on a legacy customer account.
start = s.index('    customerCurrency() {')
end = s.index('    DateError() {', start)
s = s[:start] + '''    customerCurrency() {
      return this.defaultCurrencyForInput
    },
''' + s[end:]

# Remove old customer watcher, keep invoice watcher only if present.
watch_start = s.index('  watch: {')
cust_start = s.index('    customer(newValue) {', watch_start)
selected_note = s.index('    selectedNote() {', cust_start)
s = s[:cust_start] + s[selected_note:]

# Replace invoice watcher with simple assignment; canonical helpers drive the state.
old_watch = '''    invoice(newValue) {
      if (newValue) {
        this.formData.invoice_id = newValue.id
        if (!this.isEdit) {
          this.setPaymentAmountByInvoiceData(newValue.id)
        }
      }
    },'''
s = s.replace(old_watch, '', 1)

s = s.replace("    ...mapActions('customer', ['fetchCustomers']),\n\n", '')

# Make label formatter independent from a customer currency.
s = s.replace('''    invoiceWithAmount({ invoice_number, due_amount }) {
      return `${invoice_number} (${this.$utils.formatGraphMoney(
        due_amount,
        this.customer.currency
      )})`
    },
''', '''    invoiceWithAmount({ invoice_number, due_amount }) {
      return `${invoice_number} (${due_amount})`
    },
    formatDue(amount) {
      return this.$utils.formatGraphMoney(amount, this.defaultCurrencyForInput)
    },
''')

# Load billing options and all due invoices for current level instead of customers.
s = s.replace("        await this.fetchCustomers({ limit: 'all' })\n", "        await this.fetchSchoolBillingOptions()\n        await this.fetchLevelInvoices()\n")
s = s.replace("        if (this.$route.query.customer) {\n          this.setPaymentCustomer(parseInt(this.$route.query.customer))\n        }\n", '')
s = s.replace("        if (this.formData.user_id) {\n          await this.fetchCustomers({ limit: 'all' })\n        }\n", "        await this.fetchSchoolBillingOptions()\n        await this.fetchLevelInvoices()\n")

# Editing no longer requires a user object.
s = s.replace("        this.customer = response.data.payment.user\n", '')

# Replace helper methods that depended on customers.
old_methods = '''    setPaymentCustomer(id) {
      this.customer = this.customers.find((c) => {
        return c.id === id
      })
    },
    async setInvoicePaymentData() {
      let data = await this.fetchInvoice(this.$route.params.id)
      this.customer = data.data.invoice.user
      this.invoice = data.data.invoice
    },
    async setPaymentAmountByInvoiceData(id) {
      let data = await this.fetchInvoice(id)
      this.formData.amount = data.data.invoice.due_amount
      this.maxPayableAmount = data.data.invoice.due_amount
    },
    async fetchCustomerInvoices(userId) {
      let data = {
        customer_id: userId,
        status: 'DUE',
      }
      let response = await this.fetchInvoices(data)
      this.invoiceList = response.data.invoices.data
    },'''
new_methods = '''    async fetchSchoolBillingOptions() {
      const response = await window.axios.get('/api/v1/school-billing/options')
      this.billingStudents = response.data.students || []
    },
    async fetchLevelInvoices() {
      const response = await this.fetchInvoices({ status: 'DUE', limit: 'all' })
      this.invoiceList = response.data.invoices.data || []
    },
    onStudentChange() {
      this.formData.invoice_id = null
      this.formData.family_member_id = null
      this.formData.user_id = null
      this.invoice = null
      this.formData.amount = 0
      this.maxPayableAmount = Number.MAX_SAFE_INTEGER
      if (this.billingResponsibles.length === 1) {
        this.formData.family_member_id = this.billingResponsibles[0].id
        this.formData.user_id = this.billingResponsibles[0].user_id || null
      }
    },
    onResponsibleChange() {
      const member = this.billingResponsibles.find((row) => Number(row.id) === Number(this.formData.family_member_id))
      this.formData.user_id = member ? (member.user_id || null) : null
    },
    async onInvoiceChange() {
      if (!this.formData.invoice_id) return
      const row = this.invoiceList.find((item) => Number(item.id) === Number(this.formData.invoice_id))
      if (!row) return
      this.formData.student_id = row.student_id
      this.formData.family_member_id = row.family_member_id
      this.formData.user_id = row.user_id || null
      this.formData.amount = row.due_amount
      this.maxPayableAmount = row.due_amount
    },
    async setInvoicePaymentData() {
      const data = await this.fetchInvoice(this.$route.params.id)
      const row = data.data.invoice
      this.formData.invoice_id = row.id
      this.formData.student_id = row.student_id
      this.formData.family_member_id = row.family_member_id
      this.formData.user_id = row.user_id || null
      this.invoice = row
      this.formData.amount = row.due_amount
      this.maxPayableAmount = row.due_amount
    },
    async setPaymentAmountByInvoiceData(id) {
      const data = await this.fetchInvoice(id)
      this.formData.amount = data.data.invoice.due_amount
      this.maxPayableAmount = data.data.invoice.due_amount
    },'''
if old_methods not in s:
    raise SystemExit('legacy payment helper methods not found')
s = s.replace(old_methods, new_methods, 1)

# Validation touches canonical form only.
s = s.replace("      this.$v.customer.$touch()\n", '')

p.write_text(s)
