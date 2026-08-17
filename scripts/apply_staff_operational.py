from pathlib import Path

root = Path(__file__).resolve().parents[1]

def replace_once(path, old, new):
    p = root / path
    text = p.read_text()
    if old not in text:
        raise SystemExit(f'No se encontro ancla en {path}: {old[:80]!r}')
    p.write_text(text.replace(old, new, 1))

# API imports y rutas
replace_once('routes/api.php',
"use Crater\\Http\\Controllers\\V1\\Family\\FamilyMembersController;\n",
"use Crater\\Http\\Controllers\\V1\\Family\\FamilyMembersController;\nuse Crater\\Http\\Controllers\\V1\\Staff\\StaffMembersController;\n")
replace_once('routes/api.php',
"    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {\n",
"    Route::middleware(['auth:sanctum', 'tenant'])->group(function () {\n        // --- Personal ---\n        Route::get('/staff', [StaffMembersController::class, 'index'])\n            ->middleware('permission:system.user.view');\n        Route::post('/staff', [StaffMembersController::class, 'store'])\n            ->middleware('permission:system.user.manage');\n        Route::put('/staff/{staffMember}', [StaffMembersController::class, 'update'])\n            ->middleware('permission:system.user.manage');\n        Route::post('/staff/{staffMember}/assignments', [StaffMembersController::class, 'storeAssignment'])\n            ->middleware('permission:system.user.manage');\n        Route::put('/staff/{staffMember}/assignments/{staffAssignment}', [StaffMembersController::class, 'updateAssignment'])\n            ->middleware('permission:system.user.manage');\n\n")

# Router
replace_once('resources/assets/js/router.js',
"// Students\nimport StudentIndex from './views/students/Index.vue'\n",
"// Students\nimport StudentIndex from './views/students/Index.vue'\n\n// Personal\nimport StaffIndex from './views/staff/Index.vue'\n")
replace_once('resources/assets/js/router.js',
"      // Items\n",
"      // Personal\n      {\n        path: 'staff',\n        name: 'staff.index',\n        component: StaffIndex,\n      },\n\n      // Items\n")

# Sidebar: visible para administracion heredada; la API sigue siendo la autoridad.
replace_once('resources/assets/js/views/layouts/partials/TheSiteSidebar.vue',
"      if (this.currentUser.role == 'super admin') {\n",
"      if (['super admin', 'admin'].includes(this.currentUser.role)) {\n        menu[0].push({\n          title: 'Personal',\n          icon: 'users-icon',\n          route: '/admin/staff',\n        })\n      }\n\n      if (this.currentUser.role == 'super admin') {\n")

controller = r'''<?php

namespace Crater\Http\Controllers\V1\Staff;

use Crater\Enums\Permission;
use Crater\Http\Controllers\Controller;
use Crater\Models\SchoolLevel;
use Crater\Models\StaffAssignment;
use Crater\Models\StaffMember;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

class StaffMembersController extends Controller
{
    protected AccessManager $access;

    public function __construct(AccessManager $access)
    {
        $this->access = $access;
    }

    public function index(Request $request)
    {
        $user = $request->user();
        $global = $this->access->allows($user, Permission::USER_VIEW, null);
        $levelId = TenantContext::schoolLevelId();

        if (! $global && ! $this->access->allows($user, Permission::USER_VIEW, $levelId)) {
            abort(403);
        }

        $query = StaffMember::query()
            ->where('company_id', TenantContext::companyId())
            ->with(['assignments' => function ($q) use ($global, $levelId) {
                $q->with('schoolLevel:id,name,code')->orderByDesc('active')->orderByDesc('start_date');
                if (! $global) {
                    $q->where('school_level_id', $levelId);
                }
            }]);

        if (! $global) {
            $query->whereHas('assignments', fn ($q) => $q->where('school_level_id', $levelId));
        }

        $members = $query->orderBy('last_name')->orderBy('first_name')->get();

        $levels = SchoolLevel::where('company_id', TenantContext::companyId())
            ->where('enabled', true)
            ->when(! $global, fn ($q) => $q->whereKey($levelId))
            ->orderBy('id')
            ->get(['id', 'name', 'code']);

        return response()->json(['data' => $members, 'levels' => $levels, 'global' => $global]);
    }

    public function store(Request $request)
    {
        $this->assertCanManage($request);
        $data = $this->validateMember($request);
        $assignment = $this->validateAssignment($request, true);
        $this->assertLevelAllowed($request, $assignment['school_level_id'] ?? null);
        $this->assertUserCompany($data['user_id'] ?? null);

        $member = DB::transaction(function () use ($data, $assignment) {
            $data['company_id'] = TenantContext::companyId();
            $data['document_number_normalized'] = $this->normalizeDocument($data['document_number'] ?? null);
            $member = StaffMember::create($data);
            $assignment['company_id'] = TenantContext::companyId();
            $member->assignments()->create($assignment);
            return $member;
        });

        return response()->json(['data' => $member->fresh('assignments.schoolLevel')], 201);
    }

    public function update(Request $request, StaffMember $staffMember)
    {
        $this->assertCompany($staffMember);
        $this->assertCanManage($request);
        $data = $this->validateMember($request, $staffMember->id);
        $this->assertUserCompany($data['user_id'] ?? null);
        $data['document_number_normalized'] = $this->normalizeDocument($data['document_number'] ?? null);
        $staffMember->update($data);
        return response()->json(['data' => $staffMember->fresh('assignments.schoolLevel')]);
    }

    public function storeAssignment(Request $request, StaffMember $staffMember)
    {
        $this->assertCompany($staffMember);
        $this->assertCanManage($request);
        $data = $this->validateAssignment($request, true);
        $this->assertLevelAllowed($request, $data['school_level_id'] ?? null);
        $data['company_id'] = TenantContext::companyId();
        $assignment = $staffMember->assignments()->create($data);
        return response()->json(['data' => $assignment->load('schoolLevel')], 201);
    }

    public function updateAssignment(Request $request, StaffMember $staffMember, StaffAssignment $staffAssignment)
    {
        $this->assertCompany($staffMember);
        abort_unless((int) $staffAssignment->staff_member_id === (int) $staffMember->id, 404);
        abort_unless((int) $staffAssignment->company_id === (int) TenantContext::companyId(), 404);
        $this->assertCanManage($request);
        $data = $this->validateAssignment($request, false);
        $this->assertLevelAllowed($request, $data['school_level_id'] ?? $staffAssignment->school_level_id);
        $staffAssignment->update($data);
        return response()->json(['data' => $staffAssignment->fresh('schoolLevel')]);
    }

    protected function assertCanManage(Request $request): void
    {
        $user = $request->user();
        if ($this->access->allows($user, Permission::USER_MANAGE, null)) {
            return;
        }
        if (! $this->access->allows($user, Permission::USER_MANAGE, TenantContext::schoolLevelId())) {
            abort(403);
        }
    }

    protected function assertLevelAllowed(Request $request, ?int $levelId): void
    {
        if ($levelId === null) {
            if (! $this->access->allows($request->user(), Permission::USER_MANAGE, null)) {
                throw ValidationException::withMessages(['school_level_id' => ['Solo la administración global puede crear cargos institucionales sin nivel.']]);
            }
            return;
        }

        $level = SchoolLevel::where('company_id', TenantContext::companyId())->findOrFail($levelId);
        if ($this->access->allows($request->user(), Permission::USER_MANAGE, null)) {
            return;
        }
        if ((int) $level->id !== (int) TenantContext::schoolLevelId()) {
            abort(403);
        }
    }

    protected function assertCompany(StaffMember $staffMember): void
    {
        abort_unless((int) $staffMember->company_id === (int) TenantContext::companyId(), 404);
    }

    protected function assertUserCompany(?int $userId): void
    {
        if ($userId === null) return;
        if (! User::where('company_id', TenantContext::companyId())->whereKey($userId)->exists()) {
            throw ValidationException::withMessages(['user_id' => ['La cuenta de acceso no pertenece a esta institución.']]);
        }
    }

    protected function validateMember(Request $request, ?int $ignoreId = null): array
    {
        return $request->validate([
            'user_id' => ['nullable', 'integer'],
            'document_type' => ['nullable', 'string', 'max:30'],
            'document_number' => ['nullable', 'string', 'max:50'],
            'first_name' => ['required', 'string', 'max:120'],
            'last_name' => ['required', 'string', 'max:120'],
            'email' => ['nullable', 'email', 'max:150'],
            'phone' => ['nullable', 'string', 'max:60'],
            'staff_category' => ['required', Rule::in(['teaching', 'administrative', 'maintenance', 'cleaning', 'management', 'support', 'other'])],
            'employment_status' => ['required', Rule::in(['active', 'leave', 'inactive', 'terminated'])],
            'hire_date' => ['nullable', 'date'],
            'termination_date' => ['nullable', 'date', 'after_or_equal:hire_date'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    protected function validateAssignment(Request $request, bool $required): array
    {
        $prefix = $required ? 'required' : 'sometimes';
        return $request->validate([
            'school_level_id' => ['nullable', 'integer'],
            'position_code' => ['nullable', 'string', 'max:60'],
            'position_title' => [$prefix, 'string', 'max:150'],
            'function_category' => [$prefix, Rule::in(['teaching', 'administrative', 'maintenance', 'cleaning', 'management', 'support', 'other'])],
            'start_date' => ['nullable', 'date'],
            'end_date' => ['nullable', 'date', 'after_or_equal:start_date'],
            'active' => ['sometimes', 'boolean'],
            'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            'notes' => ['nullable', 'string'],
        ]);
    }

    protected function normalizeDocument(?string $document): ?string
    {
        if ($document === null || trim($document) === '') return null;
        return strtoupper(preg_replace('/[^A-Za-z0-9]/', '', $document));
    }
}
'''
(root / 'app/Http/Controllers/V1/Staff').mkdir(parents=True, exist_ok=True)
(root / 'app/Http/Controllers/V1/Staff/StaffMembersController.php').write_text(controller)

view = r'''<template>
  <base-page class="staff-page">
    <sw-page-header title="Personal">
      <sw-breadcrumb slot="breadcrumbs">
        <sw-breadcrumb-item title="Inicio" to="/admin/dashboard" />
        <sw-breadcrumb-item title="Personal" active />
      </sw-breadcrumb>
      <template slot="actions">
        <sw-button variant="primary" @click="startNew">Nuevo integrante</sw-button>
      </template>
    </sw-page-header>

    <div class="mt-6 space-y-6">
      <sw-card v-if="editing" variant="setting-card">
        <template slot="header"><h6 class="sw-section-title">{{ form.id ? 'Editar integrante' : 'Alta de personal' }}</h6></template>
        <form class="space-y-5" @submit.prevent="saveMember">
          <div class="grid gap-4 md:grid-cols-2">
            <label class="field"><span>Nombre *</span><input v-model.trim="form.first_name" required class="input" /></label>
            <label class="field"><span>Apellido *</span><input v-model.trim="form.last_name" required class="input" /></label>
            <label class="field"><span>Tipo de documento</span><select v-model="form.document_type" class="input"><option value="DNI">DNI</option><option value="CUIL">CUIL</option><option value="PASSPORT">Pasaporte</option></select></label>
            <label class="field"><span>Número de documento</span><input v-model.trim="form.document_number" class="input" /></label>
            <label class="field"><span>Correo</span><input v-model.trim="form.email" type="email" class="input" /></label>
            <label class="field"><span>Teléfono</span><input v-model.trim="form.phone" class="input" /></label>
            <label class="field"><span>Categoría *</span><select v-model="form.staff_category" required class="input"><option v-for="o in categories" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
            <label class="field"><span>Estado *</span><select v-model="form.employment_status" required class="input"><option value="active">Activo</option><option value="leave">Licencia</option><option value="inactive">Inactivo</option><option value="terminated">Baja</option></select></label>
            <label class="field"><span>Fecha de ingreso</span><input v-model="form.hire_date" type="date" class="input" /></label>
            <label class="field"><span>Fecha de baja</span><input v-model="form.termination_date" type="date" class="input" /></label>
          </div>
          <label class="field"><span>Notas</span><textarea v-model.trim="form.notes" rows="3" class="input"></textarea></label>

          <div v-if="!form.id" class="p-4 border rounded-lg bg-gray-50">
            <h3 class="mb-3 font-semibold">Primer cargo / función</h3>
            <assignment-fields :value="assignmentForm" :levels="levels" :categories="categories" @input="assignmentForm = $event" />
          </div>

          <div class="flex flex-wrap justify-end gap-3">
            <sw-button type="button" variant="gray" @click="cancelEdit">Cancelar</sw-button>
            <sw-button type="submit" variant="primary" :loading="saving">Guardar</sw-button>
          </div>
        </form>
      </sw-card>

      <div v-if="loading" class="py-12 text-center text-gray-500">Cargando personal…</div>
      <div v-else-if="!members.length" class="p-8 text-center bg-white border rounded-lg text-gray-500">Todavía no hay personal cargado.</div>
      <div v-else class="grid gap-4 lg:grid-cols-2">
        <article v-for="member in members" :key="member.id" class="p-5 bg-white border rounded-lg shadow-sm">
          <div class="flex items-start justify-between gap-4">
            <div><h3 class="text-lg font-semibold">{{ member.full_name }}</h3><p class="text-sm text-gray-500">{{ member.document_type || 'Documento' }} {{ member.document_number || 'sin cargar' }}</p></div>
            <sw-button size="sm" variant="gray" @click="editMember(member)">Editar</sw-button>
          </div>
          <div class="flex flex-wrap gap-2 mt-3 text-xs"><span class="badge">{{ categoryLabel(member.staff_category) }}</span><span class="badge">{{ statusLabel(member.employment_status) }}</span></div>
          <div class="mt-4 space-y-2">
            <div v-for="a in member.assignments" :key="a.id" class="p-3 border rounded-md">
              <div class="flex items-center justify-between gap-3"><strong>{{ a.position_title }}</strong><span class="text-xs" :class="a.active ? 'text-green-600' : 'text-gray-400'">{{ a.active ? 'Activo' : 'Inactivo' }}</span></div>
              <p class="mt-1 text-sm text-gray-500">{{ a.school_level ? a.school_level.name : 'Institucional' }} · {{ categoryLabel(a.function_category) }}<span v-if="a.weekly_hours"> · {{ a.weekly_hours }} h/sem</span></p>
              <button class="mt-2 text-sm text-primary-500" type="button" @click="toggleAssignment(member, a)">{{ a.active ? 'Finalizar cargo' : 'Reactivar cargo' }}</button>
            </div>
          </div>
          <button class="mt-4 text-sm font-medium text-primary-500" type="button" @click="openAssignment(member)">+ Agregar cargo o función</button>
          <div v-if="assignmentMemberId === member.id" class="p-4 mt-3 border rounded-lg bg-gray-50">
            <assignment-fields :value="assignmentForm" :levels="levels" :categories="categories" @input="assignmentForm = $event" />
            <div class="flex justify-end gap-2 mt-3"><sw-button size="sm" variant="gray" @click="assignmentMemberId = null">Cancelar</sw-button><sw-button size="sm" variant="primary" :loading="savingAssignment" @click="saveAssignment(member)">Guardar cargo</sw-button></div>
          </div>
        </article>
      </div>
    </div>
  </base-page>
</template>

<script>
const AssignmentFields = {
  props: ['value', 'levels', 'categories'],
  methods: { set(key, val) { this.$emit('input', { ...this.value, [key]: val }) } },
  template: `<div class="grid gap-4 md:grid-cols-2">
    <label class="field"><span>Nivel</span><select :value="value.school_level_id" class="input" @input="set('school_level_id', $event.target.value ? Number($event.target.value) : null)"><option :value="null">Institucional / todos</option><option v-for="l in levels" :key="l.id" :value="l.id">{{ l.name }}</option></select></label>
    <label class="field"><span>Cargo *</span><input :value="value.position_title" required class="input" @input="set('position_title', $event.target.value)" /></label>
    <label class="field"><span>Función *</span><select :value="value.function_category" required class="input" @input="set('function_category', $event.target.value)"><option v-for="o in categories" :key="o.value" :value="o.value">{{ o.label }}</option></select></label>
    <label class="field"><span>Horas semanales</span><input :value="value.weekly_hours" type="number" min="0" max="168" step="0.5" class="input" @input="set('weekly_hours', $event.target.value || null)" /></label>
    <label class="field"><span>Desde</span><input :value="value.start_date" type="date" class="input" @input="set('start_date', $event.target.value || null)" /></label>
  </div>`,
}

export default {
  components: { AssignmentFields },
  data() { return { members: [], levels: [], loading: true, saving: false, savingAssignment: false, editing: false, assignmentMemberId: null, form: {}, assignmentForm: {}, categories: [
    { value: 'teaching', label: 'Docente' }, { value: 'administrative', label: 'Administrativo' }, { value: 'management', label: 'Dirección / gestión' }, { value: 'maintenance', label: 'Mantenimiento' }, { value: 'cleaning', label: 'Limpieza' }, { value: 'support', label: 'Apoyo' }, { value: 'other', label: 'Otro' },
  ] } },
  async created() { await this.load() },
  methods: {
    blankMember() { return { id: null, user_id: null, document_type: 'DNI', document_number: '', first_name: '', last_name: '', email: '', phone: '', staff_category: 'teaching', employment_status: 'active', hire_date: '', termination_date: '', notes: '' } },
    blankAssignment() { return { school_level_id: this.levels.length === 1 ? this.levels[0].id : null, position_code: null, position_title: '', function_category: 'teaching', start_date: '', end_date: null, active: true, weekly_hours: null, notes: null } },
    async load() { this.loading = true; try { const r = await window.axios.get('/api/v1/staff'); this.members = r.data.data; this.levels = r.data.levels } finally { this.loading = false } },
    startNew() { this.form = this.blankMember(); this.assignmentForm = this.blankAssignment(); this.editing = true; window.scrollTo(0, 0) },
    editMember(m) { this.form = { ...this.blankMember(), ...m }; this.editing = true; window.scrollTo(0, 0) },
    cancelEdit() { this.editing = false; this.form = {} },
    payload(obj) { const p = { ...obj }; Object.keys(p).forEach(k => { if (p[k] === '') p[k] = null }); return p },
    async saveMember() { this.saving = true; try { const data = this.payload(this.form); if (this.form.id) await window.axios.put(`/api/v1/staff/${this.form.id}`, data); else await window.axios.post('/api/v1/staff', { ...data, ...this.payload(this.assignmentForm) }); this.editing = false; await this.load() } finally { this.saving = false } },
    openAssignment(m) { this.assignmentMemberId = m.id; this.assignmentForm = this.blankAssignment() },
    async saveAssignment(m) { if (!this.assignmentForm.position_title) return; this.savingAssignment = true; try { await window.axios.post(`/api/v1/staff/${m.id}/assignments`, this.payload(this.assignmentForm)); this.assignmentMemberId = null; await this.load() } finally { this.savingAssignment = false } },
    async toggleAssignment(m, a) { await window.axios.put(`/api/v1/staff/${m.id}/assignments/${a.id}`, { active: !a.active }); await this.load() },
    categoryLabel(v) { const o = this.categories.find(x => x.value === v); return o ? o.label : v },
    statusLabel(v) { return { active: 'Activo', leave: 'Licencia', inactive: 'Inactivo', terminated: 'Baja' }[v] || v },
  },
}
</script>

<style scoped>
.staff-page .field { display: block; font-size: .875rem; color: #4a5568; }
.staff-page .field > span { display: block; margin-bottom: .35rem; }
.staff-page .input { width: 100%; min-height: 42px; padding: .6rem .75rem; border: 1px solid #d9e2ec; border-radius: .375rem; background: white; }
.staff-page .badge { padding: .25rem .5rem; border-radius: 9999px; background: #f1f5f9; color: #475569; }
</style>
'''
(root / 'resources/assets/js/views/staff').mkdir(parents=True, exist_ok=True)
(root / 'resources/assets/js/views/staff/Index.vue').write_text(view)

test = r'''<?php

$root = dirname(__DIR__, 2);

it('makes Personal operational without destructive delete', function () use ($root) {
    $controller = file_get_contents($root.'/app/Http/Controllers/V1/Staff/StaffMembersController.php');
    $routes = file_get_contents($root.'/routes/api.php');
    $vue = file_get_contents($root.'/resources/assets/js/views/staff/Index.vue');
    expect($routes)->toContain("Route::get('/staff'");
    expect($routes)->toContain("Route::post('/staff'");
    expect($controller)->toContain('Permission::USER_MANAGE');
    expect($controller)->toContain('TenantContext::schoolLevelId()');
    expect($controller)->not->toContain('function destroy');
    expect($vue)->toContain('Alta de personal');
    expect($vue)->toContain('Agregar cargo o función');
});

it('keeps staff identity separate from access account and assignments level scoped', function () use ($root) {
    $controller = file_get_contents($root.'/app/Http/Controllers/V1/Staff/StaffMembersController.php');
    expect($controller)->toContain('assertUserCompany');
    expect($controller)->toContain('assertLevelAllowed');
    expect($controller)->toContain("StaffMember::create");
    expect($controller)->toContain("assignments()->create");
});
'''
(root / 'tests/Isolation/StaffOperationalTest.php').write_text(test)
