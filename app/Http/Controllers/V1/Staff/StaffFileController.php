<?php

namespace Crater\Http\Controllers\V1\Staff;

use Crater\Http\Controllers\Controller;
use Crater\Models\CourseSection;
use Crater\Models\Division;
use Crater\Models\SchoolLevel;
use Crater\Models\StaffAssignment;
use Crater\Models\StaffMember;
use Crater\Models\Subject;
use Crater\Models\TeachingAssignment;
use Crater\Services\Access\AccessManager;
use Crater\Services\Access\StaffOnboarding;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Fila E1: la ficha de personal es la unica pantalla para datos, cargos,
 * docencia y acceso. Ver StaffOnboarding para las reglas.
 */
class StaffFileController extends Controller
{
    protected StaffOnboarding $staff;

    public function __construct(StaffOnboarding $staff)
    {
        $this->middleware('tenant');
        $this->staff = $staff;
    }

    /** Catalogos del formulario, limitados a lo que el usuario puede otorgar. */
    public function options(Request $request, AccessManager $access)
    {
        $actor = $request->user();
        $level = TenantContext::schoolLevelId();
        $total = $access->isTotalAdmin($actor);
        $levels = SchoolLevel::where('company_id', TenantContext::companyId())->where('enabled', true)
            ->when(! $total || $level, fn ($q) => $q->whereKey($level))->orderBy('id')->get(['id', 'name']);
        $roles = DB::table('roles')->where('company_id', TenantContext::companyId())
            ->whereNotIn('name', StaffOnboarding::NOT_A_CARGO)->orderBy('hierarchy_level')
            ->get(['id', 'name', 'label', 'scope_type', 'hierarchy_level'])
            ->filter(fn ($role) => $total || $access->canGrantRole($actor, $role->name, (int) $role->hierarchy_level, $level))
            ->values();
        // Solo divisiones de ciclos abiertos: un ciclo cerrado no recibe cargos ni docencia nueva.
        $divisions = Division::acrossLevels()->with(['gradeLevel' => fn ($q) => $q->withoutGlobalScope('school_level')->select('id', 'name', 'position')])
            ->where('company_id', TenantContext::companyId())
            ->whereIn('school_level_id', $levels->modelKeys())->where('enabled', true)
            ->whereIn('academic_year_id', DB::table('academic_years')->where('company_id', TenantContext::companyId())
                ->whereIn('status', ['draft', 'active', 'closing'])->select('id'))
            ->orderBy('grade_level_id')->orderBy('name')->get(['id', 'name', 'school_level_id', 'grade_level_id']);
        $subjects = Subject::acrossLevels()->where('company_id', TenantContext::companyId())->whereIn('school_level_id', $levels->modelKeys())
            ->where('enabled', true)->orderBy('name')->get(['id', 'name', 'school_level_id']);
        $levelIds = $levels->modelKeys();
        $canCreateSubjects = $total || ($level && $access->allows($actor, \Crater\Enums\Permission::STUDY_PLAN_MANAGE, $level));
        $sections = CourseSection::with(['subject:id,name', 'division:id,name,grade_level_id', 'division.gradeLevel:id,name'])
            ->where('company_id', TenantContext::companyId())->whereIn('school_level_id', $levels->modelKeys())
            ->where('status', 'active')->orderBy('division_id')->get(['id', 'division_id', 'subject_id', 'school_level_id']);

        return response()->json(['levels' => $levels, 'roles' => $roles, 'divisions' => $divisions, 'sections' => $sections,
            'subjects' => $subjects, 'can_create_subjects' => $canCreateSubjects && $levelIds !== []]);
    }

    public function show(Request $request, StaffMember $staffMember, AccessManager $access)
    {
        abort_unless((int) $staffMember->company_id === TenantContext::companyId(), 404);
        if (! $access->isTotalAdmin($request->user())) {
            abort_unless(in_array(TenantContext::schoolLevelId(), $this->staff->memberLevels($staffMember), true), 403);
        }

        return response()->json($this->staff->present($staffMember));
    }

    public function store(Request $request)
    {
        $data = $request->validate($this->memberRules() + $this->cargoRules('cargos.*.') + $this->teachingRules('teaching.*.') + [
            'cargos' => ['nullable', 'array', 'max:10'], 'teaching' => ['nullable', 'array', 'max:40'],
            'access' => ['nullable', 'array'], 'access.enabled' => ['nullable', 'boolean'],
            'access.user_id' => ['nullable', 'integer'], 'access.email' => ['nullable', 'email', 'max:150'],
            'access.password' => ['nullable', 'string', 'min:8', 'max:120',
                'required_if:access.enabled,true,1'],
        ]);
        if (! empty($data['access']['enabled']) && ! empty($data['access']['user_id'])) {
            unset($data['access']['password']);
        }
        $member = $this->staff->create($request->user(), $data);

        return response()->json($this->staff->present($member), 201);
    }

    public function addCargo(Request $request, StaffMember $staffMember)
    {
        $this->staff->addCargo($request->user(), $staffMember, $request->validate($this->cargoRules()));

        return response()->json($this->staff->present($staffMember->fresh()), 201);
    }

    public function endCargo(Request $request, StaffMember $staffMember, int $assignment)
    {
        $cargo = StaffAssignment::where('company_id', TenantContext::companyId())->findOrFail($assignment);
        $this->staff->endCargo($request->user(), $staffMember, $cargo);

        return response()->json($this->staff->present($staffMember->fresh()));
    }

    public function addTeaching(Request $request, StaffMember $staffMember)
    {
        $this->staff->addTeaching($request->user(), $staffMember, $request->validate($this->teachingRules()));

        return response()->json($this->staff->present($staffMember->fresh()), 201);
    }

    public function revokeTeaching(Request $request, StaffMember $staffMember, int $teaching)
    {
        $assignment = TeachingAssignment::where('company_id', TenantContext::companyId())->findOrFail($teaching);
        $this->staff->revokeTeaching($request->user(), $staffMember, $assignment);

        return response()->json($this->staff->present($staffMember->fresh()));
    }

    public function update(Request $request, StaffMember $staffMember)
    {
        $this->staff->updateMember($request->user(), $staffMember, $request->validate($this->memberRules()));

        return response()->json($this->staff->present($staffMember->fresh()));
    }

    public function archive(Request $request, StaffMember $staffMember)
    {
        $this->staff->archive($request->user(), $staffMember);

        return response()->json($this->staff->present($staffMember->fresh()));
    }

    public function enableAccess(Request $request, StaffMember $staffMember)
    {
        $data = $request->validate(['user_id' => ['nullable', 'integer'], 'email' => ['nullable', 'email', 'max:150'],
            'password' => ['nullable', 'string', 'min:8', 'max:120']]);
        if (! $staffMember->user_id && empty($data['user_id']) && empty($data['password'])) {
            abort(422, 'Indicá una contraseña inicial o una cuenta existente para vincular.');
        }
        $this->staff->enableAccess($request->user(), $staffMember, $data);

        return response()->json($this->staff->present($staffMember->fresh()));
    }

    public function disableAccess(Request $request, StaffMember $staffMember)
    {
        $this->staff->disableAccess($request->user(), $staffMember);

        return response()->json($this->staff->present($staffMember->fresh()));
    }

    protected function memberRules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:120'], 'last_name' => ['required', 'string', 'max:120'],
            'document_type' => ['nullable', 'string', 'max:30'], 'document_number' => ['nullable', 'string', 'max:50'],
            'email' => ['nullable', 'email', 'max:150'], 'phone' => ['nullable', 'string', 'max:60'],
            'hire_date' => ['nullable', 'date'], 'notes' => ['nullable', 'string', 'max:2000'],
        ];
    }

    protected function cargoRules(string $prefix = ''): array
    {
        return [
            $prefix.'role_id' => ['required', 'integer'], $prefix.'school_level_id' => ['nullable', 'integer'],
            $prefix.'division_id' => ['nullable', 'integer'], $prefix.'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            $prefix.'starts_on' => ['nullable', 'date'], $prefix.'ends_on' => ['nullable', 'date'],
        ];
    }

    protected function teachingRules(string $prefix = ''): array
    {
        return [
            $prefix.'course_section_id' => ['nullable', 'integer', 'required_without:'.$prefix.'division_id'],
            $prefix.'division_id' => ['nullable', 'integer', 'required_without:'.$prefix.'course_section_id'],
            $prefix.'subject_id' => ['nullable', 'integer', 'required_without_all:'.$prefix.'course_section_id,'.$prefix.'subject_name'],
            $prefix.'subject_name' => ['nullable', 'string', 'min:2', 'max:255'],
            $prefix.'weekly_hours' => ['nullable', 'numeric', 'min:0', 'max:168'],
            $prefix.'starts_on' => ['nullable', 'date'], $prefix.'ends_on' => ['nullable', 'date'],
        ];
    }
}
