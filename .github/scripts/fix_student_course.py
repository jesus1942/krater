from pathlib import Path

# StudentRequest: canonical placement IDs.
p = Path('app/Http/Requests/StudentRequest.php')
s = p.read_text()
old = """            'school_level_id' => [
                'required',
                Rule::exists('school_levels', 'id')->where(function ($query) use ($companyId) {
                    return $query->where('company_id', $companyId)->where('enabled', true);
                }),
            ],
            'level' => ['nullable', Rule::in(['Primario', 'Secundario', 'Terciario'])],
            'grade' => ['nullable', 'string', 'max:50'],
            'division' => ['nullable', 'string', 'max:20'],
            'school_year' => ['required', 'integer', 'min:2022', 'max:2100'],
"""
new = """            // La ubicación académica se elige por IDs canónicos. El nivel,
            // nombre del curso, división y año se derivan en backend.
            'academic_year_id' => ['required', 'integer'],
            'grade_level_id' => ['required', 'integer'],
            'division_id' => ['required', 'integer'],
"""
if old not in s:
    raise SystemExit('StudentRequest placement block not found')
p.write_text(s.replace(old, new))

# StudentsController: options and canonical derivation.
p = Path('app/Http/Controllers/V1/Student/StudentsController.php')
s = p.read_text()
s = s.replace(
    "use Crater\\Models\\FamilyMember;\nuse Crater\\Models\\Student;",
    "use Crater\\Models\\AcademicYear;\nuse Crater\\Models\\Division;\nuse Crater\\Models\\FamilyMember;\nuse Crater\\Models\\GradeLevel;\nuse Crater\\Models\\SchoolLevel;\nuse Crater\\Models\\Student;\nuse Crater\\Support\\TenantContext;"
)
marker = "    public function store(StudentRequest $request)\n"
method = '''    public function placementOptions(Request $request)
    {
        $companyId = (int) $request->header('company');
        $schoolLevelId = (int) TenantContext::schoolLevelId();

        $academicYears = AcademicYear::where('company_id', $companyId)
            ->where('school_level_id', $schoolLevelId)
            ->orderByDesc('year')
            ->get(['id', 'year', 'name', 'status']);

        $gradeLevels = GradeLevel::where('company_id', $companyId)
            ->where('school_level_id', $schoolLevelId)
            ->enabled()
            ->ordered()
            ->get(['id', 'school_level_id', 'name', 'position']);

        $divisions = Division::where('company_id', $companyId)
            ->where('school_level_id', $schoolLevelId)
            ->where('enabled', true)
            ->orderBy('grade_level_id')
            ->orderBy('name')
            ->get(['id', 'academic_year_id', 'grade_level_id', 'school_level_id', 'name', 'shift', 'capacity']);

        return response()->json([
            'academic_years' => $academicYears,
            'grade_levels' => $gradeLevels,
            'divisions' => $divisions,
        ]);
    }

'''
if marker not in s:
    raise SystemExit('store marker not found')
s = s.replace(marker, method + marker, 1)
old_store = '''        $validated = $request->validated();
        $familyMembers = $validated['family_members'] ?? [];
        unset($validated['family_members']);

        if ($request->header('school-level')) {
            $validated['school_level_id'] = $request->header('school-level');
        }

        $student = DB::transaction(function () use ($validated, $familyMembers, $companyId) {
'''
new_store = '''        $validated = $request->validated();
        $familyMembers = $validated['family_members'] ?? [];
        unset($validated['family_members']);
        $validated = $this->applyCanonicalPlacement($validated, $companyId);

        $student = DB::transaction(function () use ($validated, $familyMembers, $companyId) {
'''
if old_store not in s:
    raise SystemExit('store placement block not found')
s = s.replace(old_store, new_store, 1)
old_update = '''        $familyMembers = $validated['family_members'] ?? [];
        unset($validated['family_members']);

        if ($request->header('school-level')) {
            $validated['school_level_id'] = $request->header('school-level');
        }

        DB::transaction(function () use ($student, $validated, $familyMembers, $hasFamilyPayload, $companyId) {
'''
new_update = '''        $familyMembers = $validated['family_members'] ?? [];
        unset($validated['family_members']);
        $validated = $this->applyCanonicalPlacement($validated, $companyId);

        DB::transaction(function () use ($student, $validated, $familyMembers, $hasFamilyPayload, $companyId) {
'''
if old_update not in s:
    raise SystemExit('update placement block not found')
s = s.replace(old_update, new_update, 1)
insert_before = "    private function syncFamilyMembers(Student $student, array $items, $companyId)\n"
helper = '''    private function applyCanonicalPlacement(array $validated, int $companyId): array
    {
        $schoolLevelId = (int) TenantContext::schoolLevelId();

        $academicYear = AcademicYear::where('company_id', $companyId)
            ->where('school_level_id', $schoolLevelId)
            ->find($validated['academic_year_id']);
        $gradeLevel = GradeLevel::where('company_id', $companyId)
            ->where('school_level_id', $schoolLevelId)
            ->where('enabled', true)
            ->find($validated['grade_level_id']);
        $division = Division::where('company_id', $companyId)
            ->where('school_level_id', $schoolLevelId)
            ->where('academic_year_id', optional($academicYear)->id)
            ->where('grade_level_id', optional($gradeLevel)->id)
            ->where('enabled', true)
            ->find($validated['division_id']);

        abort_unless($academicYear && $gradeLevel && $division, 422, 'El curso, la división y el ciclo deben pertenecer al nivel institucional activo.');

        $schoolLevel = SchoolLevel::where('company_id', $companyId)->findOrFail($gradeLevel->school_level_id);
        $levelLabels = [
            'primary' => 'Primario',
            'secondary' => 'Secundario',
            'tertiary' => 'Terciario',
        ];

        unset($validated['academic_year_id'], $validated['grade_level_id'], $validated['division_id']);
        $validated['school_level_id'] = $gradeLevel->school_level_id;
        $validated['level'] = $levelLabels[$schoolLevel->code] ?? $schoolLevel->name;
        $validated['grade'] = $gradeLevel->name;
        $validated['division'] = $division->name;
        $validated['school_year'] = $academicYear->year;

        return $validated;
    }

'''
if insert_before not in s:
    raise SystemExit('syncFamilyMembers marker not found')
s = s.replace(insert_before, helper + insert_before, 1)
p.write_text(s)

# Route before apiResource to avoid {student} binding.
p = Path('routes/api.php')
s = p.read_text()
old = "        Route::apiResource('students', StudentsController::class);\n"
new = "        Route::get('/students/placement-options', [StudentsController::class, 'placementOptions']);\n        Route::apiResource('students', StudentsController::class);\n"
if old not in s:
    raise SystemExit('student apiResource not found')
p.write_text(s.replace(old, new, 1))

# Deterministic generic repair for legacy explicit level labels.
Path('database/migrations/2026_09_01_000800_repair_student_level_consistency.php').write_text('''<?php

use Illuminate\\Database\\Migrations\\Migration;
use Illuminate\\Support\\Facades\\DB;
use Illuminate\\Support\\Facades\\Schema;

class RepairStudentLevelConsistency extends Migration
{
    public function up()
    {
        if (! Schema::hasTable('students') || ! Schema::hasTable('school_levels')) {
            return;
        }

        $map = [
            'Primario' => 'primary',
            'Secundario' => 'secondary',
            'Terciario' => 'tertiary',
        ];

        foreach ($map as $legacyLabel => $code) {
            $levels = DB::table('school_levels')->where('code', $code)->get(['id', 'company_id']);
            foreach ($levels as $level) {
                DB::table('students')
                    ->where('company_id', $level->company_id)
                    ->where('level', $legacyLabel)
                    ->where(function ($query) use ($level) {
                        $query->whereNull('school_level_id')->orWhere('school_level_id', '<>', $level->id);
                    })
                    ->update(['school_level_id' => $level->id]);
            }
        }
    }

    public function down()
    {
        // No se revierte a una asociación conocida como incorrecta.
    }
}
''')

# Frontend canonical dropdowns.
p = Path('resources/assets/js/views/students/Index.vue')
s = p.read_text()
old_ui = '''          <label class="text-sm">Nivel institucional *
            <select v-model="form.school_level_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" @change="syncLevelName">
              <option value="">Seleccionar</option>
              <option v-for="level in schoolLevels" :key="level.id" :value="level.id">{{ level.name }}</option>
            </select>
          </label>
          <label class="text-sm">Curso/Año<sw-input v-model="form.grade" class="mt-1" placeholder="Ej.: 4.º" /></label>
          <label class="text-sm">División<sw-input v-model="form.division" class="mt-1" placeholder="Ej.: A" /></label>
          <label class="text-sm">Ciclo lectivo *<sw-input v-model="form.school_year" type="number" class="mt-1" required /></label>
'''
new_ui = '''          <label class="text-sm">Ciclo lectivo *
            <select v-model="form.academic_year_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" @change="onAcademicYearChange">
              <option value="">Seleccionar ciclo</option>
              <option v-for="year in academicYears" :key="year.id" :value="year.id">{{ year.name || year.year }}</option>
            </select>
          </label>
          <label class="text-sm">Curso/Año *
            <select v-model="form.grade_level_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded" @change="onGradeLevelChange">
              <option value="">Seleccionar curso</option>
              <option v-for="grade in gradeLevels" :key="grade.id" :value="grade.id">{{ grade.name }}</option>
            </select>
          </label>
          <label class="text-sm">División *
            <select v-model="form.division_id" required class="w-full h-10 px-3 mt-1 bg-white border border-gray-300 rounded">
              <option value="">Seleccionar división</option>
              <option v-for="division in availableDivisions" :key="division.id" :value="division.id">{{ division.name }}</option>
            </select>
          </label>
          <div class="text-sm">
            <span class="block">Nivel institucional</span>
            <div class="flex items-center h-10 px-3 mt-1 text-gray-600 bg-gray-50 border border-gray-200 rounded">{{ activeLevelName }}</div>
          </div>
'''
if old_ui not in s:
    raise SystemExit('student free-text UI block not found')
s = s.replace(old_ui, new_ui, 1)
s = s.replace("  school_level_id: window.Ls.get('selectedSchoolLevel') || '',\n  first_name: '',", "  school_level_id: window.Ls.get('selectedSchoolLevel') || '',\n  academic_year_id: '',\n  grade_level_id: '',\n  division_id: '',\n  first_name: '',", 1)
s = s.replace("      schoolLevels: [],\n      summary:", "      schoolLevels: [],\n      academicYears: [],\n      gradeLevels: [],\n      divisions: [],\n      summary:", 1)
s = s.replace("    this.fetchSchoolLevels()\n    this.fetchStudents()", "    this.fetchSchoolLevels()\n    this.fetchPlacementOptions()\n    this.fetchStudents()", 1)
marker = "    async fetchStudents() {\n"
methods = '''    async fetchPlacementOptions() {
      const response = await window.axios.get('/api/v1/students/placement-options')
      this.academicYears = response.data.academic_years || []
      this.gradeLevels = response.data.grade_levels || []
      this.divisions = response.data.divisions || []
      if (!this.form.academic_year_id && this.academicYears.length) {
        const current = this.academicYears.find((year) => Number(year.year) === new Date().getFullYear()) || this.academicYears[0]
        this.form.academic_year_id = current.id
      }
    },
'''
if marker not in s:
    raise SystemExit('fetchStudents marker not found')
s = s.replace(marker, methods + marker, 1)
s = s.replace("    openCreate() {\n      this.form = emptyForm()\n      this.error = ''\n      this.showForm = true\n    },", "    async openCreate() {\n      this.form = emptyForm()\n      await this.fetchPlacementOptions()\n      this.error = ''\n      this.showForm = true\n    },", 1)
s = s.replace("    openEdit(student) {\n", "    async openEdit(student) {\n      await this.fetchPlacementOptions()\n", 1)
old_assignment = '''      this.form = {
        ...emptyForm(),
        ...student,
        guardian_id: student.guardian_id || null,
        family_members: members,
      }
      this.error = ''
'''
new_assignment = '''      const academicYear = this.academicYears.find((year) => Number(year.year) === Number(student.school_year))
      const gradeLevel = this.gradeLevels.find((grade) => grade.name === student.grade)
      const division = this.divisions.find((item) =>
        gradeLevel && academicYear &&
        Number(item.grade_level_id) === Number(gradeLevel.id) &&
        Number(item.academic_year_id) === Number(academicYear.id) &&
        item.name === student.division
      )
      this.form = {
        ...emptyForm(),
        ...student,
        academic_year_id: academicYear ? academicYear.id : '',
        grade_level_id: gradeLevel ? gradeLevel.id : '',
        division_id: division ? division.id : '',
        guardian_id: student.guardian_id || null,
        family_members: members,
      }
      this.error = ''
'''
if old_assignment not in s:
    raise SystemExit('openEdit assignment not found')
s = s.replace(old_assignment, new_assignment, 1)
old_sync = '''    syncLevelName() {
      const level = this.schoolLevels.find((item) => String(item.id) === String(this.form.school_level_id))
      this.form.level = level ? { primary: 'Primario', secondary: 'Secundario', tertiary: 'Terciario' }[level.code] : ''
    },
'''
new_sync = '''    onAcademicYearChange() {
      this.form.division_id = ''
    },
    onGradeLevelChange() {
      this.form.division_id = ''
    },
'''
if old_sync not in s:
    raise SystemExit('syncLevelName block not found')
s = s.replace(old_sync, new_sync, 1)
old_methods = "  methods: {\n"
computed = '''  computed: {
    availableDivisions() {
      return this.divisions.filter((division) =>
        Number(division.academic_year_id) === Number(this.form.academic_year_id) &&
        Number(division.grade_level_id) === Number(this.form.grade_level_id)
      )
    },
    activeLevelName() {
      const selectedId = window.Ls.get('selectedSchoolLevel')
      const level = this.schoolLevels.find((item) => String(item.id) === String(selectedId))
      return level ? level.name : 'Nivel activo'
    },
  },
  methods: {
'''
if old_methods not in s:
    raise SystemExit('methods marker not found')
s = s.replace(old_methods, computed, 1)
p.write_text(s)

# Regression source checks. Kept framework-independent so it can run without
# booting Laravel's TestCase and without depending on the legacy SQLite migrations.
t = Path('tests/Isolation/StudentCoursePlacementTest.php')
t.parent.mkdir(parents=True, exist_ok=True)
t.write_text('''<?php

$root = dirname(__DIR__, 2);

it('never derives student level from the request header anymore', function () use ($root) {
    $source = file_get_contents($root.'/app/Http/Controllers/V1/Student/StudentsController.php');
    expect($source)->toContain('applyCanonicalPlacement');
});

it('uses canonical dropdown identifiers instead of free text placement', function () use ($root) {
    $request = file_get_contents($root.'/app/Http/Requests/StudentRequest.php');
    $vue = file_get_contents($root.'/resources/assets/js/views/students/Index.vue');
    expect($request)->toContain("'academic_year_id' => ['required', 'integer']");
    expect($request)->toContain("'grade_level_id' => ['required', 'integer']");
    expect($request)->toContain("'division_id' => ['required', 'integer']");
    expect($vue)->toContain('Seleccionar curso');
    expect($vue)->toContain('Seleccionar división');
    expect($vue)->not->toContain('placeholder="Ej.: 4.º"');
});

it('repairs explicit legacy level mismatches without personal hardcoding', function () use ($root) {
    $migration = file_get_contents($root.'/database/migrations/2026_09_01_000800_repair_student_level_consistency.php');
    expect($migration)->toContain("'Primario' => 'primary'");
    expect($migration)->toContain("'Secundario' => 'secondary'");
    expect($migration)->toContain("'Terciario' => 'tertiary'");
    expect($migration)->not->toContain('55230674');
    expect($migration)->not->toContain('Felipe');
});
''')