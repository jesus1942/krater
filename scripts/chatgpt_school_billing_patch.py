from pathlib import Path


def replace(path, old, new, count=1):
    p = Path(path)
    s = p.read_text()
    if old not in s:
        raise SystemExit(f'Missing pattern in {path}: {old[:80]!r}')
    s = s.replace(old, new, count)
    p.write_text(s)

# Routes
replace('routes/api.php',
    "use Crater\\Http\\Controllers\\V1\\Customer\\CustomerStatsController;\n",
    "use Crater\\Http\\Controllers\\V1\\Customer\\CustomerStatsController;\nuse Crater\\Http\\Controllers\\V1\\Billing\\SchoolBillingOptionsController;\n")
replace('routes/api.php',
    "        // Invoices\n        //-------------------------------------------------\n",
    "        // School billing options\n        //----------------------------------\n        Route::get('/school-billing/options', SchoolBillingOptionsController::class)->middleware('tenant');\n\n        // Invoices\n        //-------------------------------------------------\n")

# Invoice request
replace('app/Http/Requests/InvoicesRequest.php',
    "            'user_id' => [\n                'required',\n            ],",
    "            'user_id' => ['nullable', 'integer'],\n            'student_id' => ['nullable', 'integer'],\n            'enrollment_id' => ['nullable', 'integer'],\n            'family_member_id' => ['nullable', 'integer'],")

# Payment request
replace('app/Http/Requests/PaymentRequest.php',
    "            'user_id' => [\n                'required',\n            ],",
    "            'user_id' => ['nullable', 'integer'],\n            'student_id' => ['nullable', 'integer'],\n            'family_member_id' => ['nullable', 'integer'],")

# Invoice controller
replace('app/Http/Controllers/V1/Invoice/InvoicesController.php',
    "use Crater\\Models\\Invoice;\n",
    "use Crater\\Models\\Invoice;\nuse Crater\\Services\\Billing\\SchoolBillingAssignment;\n")
replace('app/Http/Controllers/V1/Invoice/InvoicesController.php',
    "Invoice::with(['items', 'user', 'creator', 'taxes'])\n            ->join('users', 'users.id', '=', 'invoices.user_id')",
    "Invoice::with(['items', 'user', 'student', 'familyMember', 'creator', 'taxes'])\n            ->leftJoin('users', 'users.id', '=', 'invoices.user_id')")
replace('app/Http/Controllers/V1/Invoice/InvoicesController.php',
    "    public function store(Requests\\InvoicesRequest $request)\n    {\n        $invoice = Invoice::createInvoice($request);",
    "    public function store(Requests\\InvoicesRequest $request, SchoolBillingAssignment $billing)\n    {\n        $request->merge($billing->resolveInvoice($request));\n        $invoice = Invoice::createInvoice($request);")
replace('app/Http/Controllers/V1/Invoice/InvoicesController.php',
    "            'user',\n            'taxes.taxType',",
    "            'user',\n            'student',\n            'enrollment',\n            'familyMember',\n            'taxes.taxType',")
replace('app/Http/Controllers/V1/Invoice/InvoicesController.php',
    "    public function update(Requests\\InvoicesRequest $request, Invoice $invoice)\n    {\n        $invoice = $invoice->updateInvoice($request);",
    "    public function update(Requests\\InvoicesRequest $request, Invoice $invoice, SchoolBillingAssignment $billing)\n    {\n        $request->merge($billing->resolveInvoice($request));\n        $invoice = $invoice->updateInvoice($request);")

# Payment controller
replace('app/Http/Controllers/V1/Payment/PaymentsController.php',
    "use Crater\\Models\\Payment;\n",
    "use Crater\\Models\\Payment;\nuse Crater\\Services\\Billing\\SchoolBillingAssignment;\n")
replace('app/Http/Controllers/V1/Payment/PaymentsController.php',
    "Payment::with(['user', 'invoice', 'paymentMethod', 'creator'])\n            ->join('users', 'users.id', '=', 'payments.user_id')",
    "Payment::with(['user', 'student', 'familyMember', 'invoice', 'paymentMethod', 'creator'])\n            ->leftJoin('users', 'users.id', '=', 'payments.user_id')")
replace('app/Http/Controllers/V1/Payment/PaymentsController.php',
    "    public function store(PaymentRequest $request)\n    {\n        $payment = Payment::createPayment($request);",
    "    public function store(PaymentRequest $request, SchoolBillingAssignment $billing)\n    {\n        $request->merge($billing->resolvePayment($request));\n        $payment = Payment::createPayment($request);")
replace('app/Http/Controllers/V1/Payment/PaymentsController.php',
    "            'user',\n            'invoice',",
    "            'user',\n            'student',\n            'familyMember',\n            'invoice',", 1)
replace('app/Http/Controllers/V1/Payment/PaymentsController.php',
    "    public function update(PaymentRequest $request, Payment $payment)\n    {\n        $payment = $payment->updatePayment($request);",
    "    public function update(PaymentRequest $request, Payment $payment, SchoolBillingAssignment $billing)\n    {\n        $request->merge($billing->resolvePayment($request));\n        $payment = $payment->updatePayment($request);")

# Model relations
replace('app/Models/Invoice.php',
    "    public function user()\n    {\n        return $this->belongsTo('Crater\\Models\\User', 'user_id');\n    }\n",
    "    public function user()\n    {\n        return $this->belongsTo('Crater\\Models\\User', 'user_id');\n    }\n\n    public function student()\n    {\n        return $this->belongsTo(Student::class);\n    }\n\n    public function enrollment()\n    {\n        return $this->belongsTo(Enrollment::class);\n    }\n\n    public function familyMember()\n    {\n        return $this->belongsTo(FamilyMember::class);\n    }\n")
replace('app/Models/Payment.php',
    "    public function user()\n    {\n        return $this->belongsTo(User::class, 'user_id');\n    }\n",
    "    public function user()\n    {\n        return $this->belongsTo(User::class, 'user_id');\n    }\n\n    public function student()\n    {\n        return $this->belongsTo(Student::class);\n    }\n\n    public function familyMember()\n    {\n        return $this->belongsTo(FamilyMember::class);\n    }\n")

# Items screen terminology
p = Path('resources/assets/js/views/items/Index.vue')
s = p.read_text()
s = s.replace(':title="$t(\'items.title\')"', 'title="Conceptos y aranceles"')
s = s.replace(':title="$tc(\'items.item\', 2)"', 'title="Conceptos y aranceles"')
s = s.replace("{{ $t('items.add_item') }}", 'Nuevo concepto')
s = s.replace(":title=\"$t('items.no_items')\"", 'title="Aún no hay conceptos ni aranceles"')
s = s.replace(":description=\"$t('items.list_of_items')\"", 'description="Creá cuotas, matrículas, materiales u otros conceptos cobrables para este nivel."')
s = s.replace("{{ $t('items.add_new_item') }}", 'Agregar concepto o arancel')
s = s.replace(":label=\"$t('items.name')\"", 'label="Concepto"')
s = s.replace(":label=\"$t('items.price')\"", 'label="Importe"')
p.write_text(s)

# Invoice school selector
p = Path('resources/assets/js/views/invoices/Create.vue')
s = p.read_text()
old = '''        <customer-select
          :valid="$v.selectedCustomer"
          :customer-id="customerId"
          class="col-span-5 pr-0"
        />'''
new = '''        <div class="col-span-5 p-5 bg-white border border-gray-200 rounded">
          <h3 class="mb-4 text-sm font-semibold tracking-wide text-gray-600 uppercase">Asignación escolar</h3>
          <label class="block mb-4 text-sm">Alumno *
            <select v-model="newInvoice.student_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" @change="onBillingStudentChange">
              <option :value="null">Seleccionar alumno</option>
              <option v-for="student in billingStudents" :key="student.id" :value="student.id">
                {{ student.full_name }} · {{ student.course || 'Sin curso' }}
              </option>
            </select>
          </label>
          <label class="block text-sm">Responsable financiero *
            <select v-model="newInvoice.family_member_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded">
              <option :value="null">Seleccionar responsable</option>
              <option v-for="member in billingResponsibles" :key="member.id" :value="member.id">
                {{ member.name }}{{ member.relationship ? ` · ${member.relationship}` : '' }}
              </option>
            </select>
          </label>
          <p v-if="selectedBillingStudent" class="mt-3 text-xs text-gray-500">
            Ciclo {{ selectedBillingStudent.academic_year || 'sin matrícula activa' }}. La deuda queda a nombre del alumno; el responsable indica quién administra/paga la obligación.
          </p>
        </div>'''
if old not in s: raise SystemExit('customer select template not found')
s = s.replace(old, new, 1)
s = s.replace("import CustomerSelect from './CustomerSelect'\n", '')
s = s.replace('    CustomerSelect,\n', '')
s = s.replace("        user_id: null,\n", "        user_id: null,\n        student_id: null,\n        enrollment_id: null,\n        family_member_id: null,\n")
s = s.replace("      customerId: null,\n", "      customerId: null,\n      billingStudents: [],\n")
s = s.replace("      selectedCustomer: {\n        required,\n      },\n", "      'newInvoice.student_id': { required },\n      'newInvoice.family_member_id': { required },\n")
s = s.replace("    selectedCustomer(newVal) {\n      if (newVal && newVal.currency) {\n        this.selectedCurrency = newVal.currency\n      } else {\n        this.selectedCurrency = this.defaultCurrency\n      }\n    },\n\n", '')
s = s.replace("    currency() {\n      return this.selectedCurrency\n    },\n", "    currency() {\n      return this.selectedCurrency\n    },\n\n    selectedBillingStudent() {\n      return this.billingStudents.find((student) => Number(student.id) === Number(this.newInvoice.student_id)) || null\n    },\n\n    billingResponsibles() {\n      return this.selectedBillingStudent ? (this.selectedBillingStudent.financial_responsibles || []) : []\n    },\n")
s = s.replace("    this.fetchInitialData()\n", "    this.fetchInitialData()\n    this.fetchSchoolBillingOptions()\n", 1)
s = s.replace("    selectFixed() {\n", "    async fetchSchoolBillingOptions() {\n      const response = await window.axios.get('/api/v1/school-billing/options')\n      this.billingStudents = response.data.students || []\n    },\n\n    onBillingStudentChange() {\n      const student = this.selectedBillingStudent\n      this.newInvoice.enrollment_id = student ? student.enrollment_id : null\n      this.newInvoice.family_member_id = null\n      this.newInvoice.user_id = null\n      if (student && student.financial_responsibles && student.financial_responsibles.length === 1) {\n        const member = student.financial_responsibles[0]\n        this.newInvoice.family_member_id = member.id\n        this.newInvoice.user_id = member.user_id || null\n      }\n    },\n\n    selectFixed() {\n")
s = s.replace("      'selectedCustomer',\n", '')
s = s.replace("      'selectCustomer',\n", '')
# Existing edit can still populate legacy customerId, but billing IDs are loaded from invoice.
p.write_text(s)

# Ensure frontend has no old Article label at the main concepts page.
