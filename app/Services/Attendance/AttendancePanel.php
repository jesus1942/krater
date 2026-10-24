<?php

namespace Crater\Services\Attendance;

use Carbon\Carbon;
use Carbon\CarbonPeriod;
use Crater\Enums\Permission;
use Crater\Models\AttendanceRecord;
use Crater\Models\CourseSection;
use Crater\Models\Division;
use Crater\Models\Enrollment;
use Crater\Models\StaffAttendance;
use Crater\Models\StaffLeave;
use Crater\Models\User;
use Crater\Services\Access\AccessManager;
use Crater\Support\TenantContext;
use Illuminate\Support\Facades\DB;

/**
 * Panel de asistencia de la direccion: el dia, porcentajes por curso, alumnos
 * en riesgo por inasistencias y ausentismo del personal. Lo de alumnos exige
 * ver toda la asistencia del nivel (no alcanza el alcance de una division);
 * lo del personal, ver la asistencia del personal.
 */
class AttendancePanel
{
    protected AccessManager $access;
    protected AttendanceRules $rules;
    protected StudentAttendance $students;
    protected StaffAttendanceBook $staff;

    public function __construct(AccessManager $access, AttendanceRules $rules, StudentAttendance $students, StaffAttendanceBook $staff)
    {
        $this->access = $access;
        $this->rules = $rules;
        $this->students = $students;
        $this->staff = $staff;
    }

    public function canSeeStudents(User $user, int $level): bool
    {
        return $this->access->allows($user, Permission::ATTENDANCE_VIEW_ALL, $level)
            && $this->access->hasLevelWideScope($user, $level, Permission::ATTENDANCE_VIEW_ALL);
    }

    public function build(User $user, string $date): array
    {
        $level = $this->students->level();
        $students = $this->canSeeStudents($user, $level->id);
        $staff = $this->access->allows($user, Permission::HR_ATTENDANCE_VIEW, $level->id);
        abort_unless($students || $staff, 403);
        $rules = $this->rules->forLevel($level);
        $divisions = Division::with('gradeLevel:id,name,position')->where('enabled', true)
            ->whereIn('academic_year_id', $this->students->openYears())->get(['id', 'name', 'grade_level_id', 'academic_year_id'])
            ->sortBy(fn ($d) => sprintf('%04d %s', optional($d->gradeLevel)->position ?? 999, $d->name))->values();
        $yearStart = DB::table('academic_years')->whereIn('id', $divisions->pluck('academic_year_id')->unique())->min('starts_on')
            ?? Carbon::parse($date)->startOfYear()->toDateString();
        $monthStart = Carbon::parse($date)->startOfMonth()->toDateString();

        return [
            'date' => $date, 'school_level' => $level->name, 'rules' => $rules,
            'today' => [
                'students' => $students ? $this->studentsToday($divisions, $rules, $date) : null,
                'staff' => $staff ? $this->staffToday($level->id, $date) : null,
                'uncovered_sections' => ($students && $staff) ? $this->uncoveredSections($divisions, $level->id, $date) : null,
            ],
            'by_division' => $students ? $this->byDivision($divisions, $rules, $monthStart, $yearStart, $date) : null,
            'at_risk' => $students ? $this->atRisk($divisions, $rules, $yearStart, $date) : null,
            'staff_absenteeism' => $staff ? $this->staffAbsenteeism($level->id, $monthStart, $date) : null,
        ];
    }

    protected function studentsToday($divisions, array $rules, string $date): array
    {
        $records = AttendanceRecord::query()->whereDate('date', $date)->whereIn('division_id', $divisions->pluck('id'))
            ->get(['id', 'enrollment_id', 'division_id', 'course_section_id', 'status', 'is_justified']);
        $daily = $rules['mode'] === AttendanceRules::DAILY;
        $sections = $daily ? collect() : CourseSection::where('status', 'active')->whereIn('division_id', $divisions->pluck('id'))
            ->get(['id', 'division_id']);
        $rows = $divisions->map(function ($d) use ($records, $daily, $sections) {
            $own = $records->where('division_id', $d->id);
            $mine = $sections->where('division_id', $d->id);

            return ['division_id' => $d->id, 'label' => trim(optional($d->gradeLevel)->name.' '.$d->name),
                'taken' => $daily ? $own->isNotEmpty() : $own->pluck('course_section_id')->unique()->count(),
                'sections' => $daily ? null : $mine->count(),
                'absent' => $own->where('status', AttendanceRecord::ABSENT)->pluck('enrollment_id')->unique()->count(),
                'late' => $own->where('status', AttendanceRecord::LATE)->pluck('enrollment_id')->unique()->count()];
        })->values();
        $absentIds = $records->where('status', AttendanceRecord::ABSENT)->pluck('enrollment_id')->unique();
        $names = $this->names($absentIds);

        return [
            'divisions' => $rows,
            'pending' => $rows->filter(fn ($r) => $daily ? ! $r['taken'] : $r['taken'] < $r['sections'])->count(),
            'absent_total' => $absentIds->count(),
            'absent' => $absentIds->take(60)->map(fn ($id) => ['enrollment_id' => $id, 'name' => $names[$id]['name'] ?? '',
                'division' => $names[$id]['division'] ?? ''])->sortBy('name')->values(),
        ];
    }

    protected function staffToday(int $level, string $date): array
    {
        $day = $this->staffDay($level, $date);
        $by = fn ($status) => collect($day)->where('status', $status)->values();

        return ['total' => count($day), 'absent' => $by('absent'), 'late' => $by('late'), 'leave' => $by('leave'),
            'unrecorded' => collect($day)->whereNull('status')->count()];
    }

    /** Estado de cada persona del nivel en un dia, contando licencias aunque no haya fila. */
    protected function staffDay(int $level, string $date): array
    {
        $members = $this->staff->membersQuery($level)->get(['id', 'first_name', 'last_name']);
        $records = StaffAttendance::whereDate('date', $date)->whereIn('staff_member_id', $members->modelKeys())->get()->keyBy('staff_member_id');
        $leaves = StaffLeave::active()->covering($date)->whereIn('staff_member_id', $members->modelKeys())->get()->keyBy('staff_member_id');

        return $members->map(fn ($m) => ['staff_member_id' => $m->id, 'name' => trim($m->last_name.', '.$m->first_name, ', '),
            'status' => isset($leaves[$m->id]) ? 'leave' : optional($records[$m->id] ?? null)->status,
            'leave_type' => isset($leaves[$m->id]) ? (StaffLeave::TYPES[$leaves[$m->id]->type] ?? $leaves[$m->id]->type) : null])->all();
    }

    /** Materias del dia sin docente presente: todos sus docentes estan ausentes o de licencia. */
    protected function uncoveredSections($divisions, int $level, string $date): array
    {
        $out = collect($this->staffDay($level, $date))->whereIn('status', ['absent', 'leave'])->pluck('staff_member_id')->all();
        $sections = CourseSection::with('subject:id,name')->where('status', 'active')->whereIn('division_id', $divisions->pluck('id'))
            ->get(['id', 'division_id', 'subject_id']);
        $teaching = DB::table('teaching_assignments')->whereNull('revoked_at')->whereIn('course_section_id', $sections->modelKeys())
            ->where(fn ($q) => $q->whereNull('starts_on')->orWhereDate('starts_on', '<=', $date))
            ->where(fn ($q) => $q->whereNull('ends_on')->orWhereDate('ends_on', '>=', $date))
            ->get(['course_section_id', 'staff_member_id'])->groupBy('course_section_id');
        $labels = $divisions->keyBy('id');

        return $sections->filter(fn ($s) => isset($teaching[$s->id])
                && $teaching[$s->id]->pluck('staff_member_id')->diff($out)->isEmpty())
            ->map(fn ($s) => ['course_section_id' => $s->id, 'subject' => optional($s->subject)->name,
                'division' => trim(optional($labels[$s->division_id]->gradeLevel)->name.' '.$labels[$s->division_id]->name),
                'teachers' => $teaching[$s->id]->count()])->values()->all();
    }

    protected function byDivision($divisions, array $rules, string $monthStart, string $yearStart, string $date): array
    {
        $stats = fn (string $from) => AttendanceRecord::query()->whereIn('division_id', $divisions->pluck('id'))
            ->whereDate('date', '>=', $from)->whereDate('date', '<=', $date)
            ->groupBy('division_id')->selectRaw('division_id, count(*) as total, sum(absence_weight) as weight')
            ->get()->keyBy('division_id');
        $month = $stats($monthStart);
        $year = $stats($yearStart);
        $pct = fn ($row) => $row && $row->total > 0 ? round(100 * (1 - $row->weight / $row->total), 1) : null;
        $rows = $divisions->map(fn ($d) => ['division_id' => $d->id, 'label' => trim(optional($d->gradeLevel)->name.' '.$d->name),
            'month' => $pct($month[$d->id] ?? null), 'year' => $pct($year[$d->id] ?? null),
            'records_month' => (int) optional($month[$d->id] ?? null)->total])->values()->all();
        if ($rules['mode'] !== AttendanceRules::SUBJECT) {
            return ['divisions' => $rows, 'sections' => null];
        }
        $sections = AttendanceRecord::query()->whereIn('division_id', $divisions->pluck('id'))->whereNotNull('course_section_id')
            ->whereDate('date', '>=', $yearStart)->whereDate('date', '<=', $date)
            ->groupBy('course_section_id')->selectRaw('course_section_id, count(*) as total, sum(absence_weight) as weight')->get();
        $names = CourseSection::with('subject:id,name')->whereIn('id', $sections->pluck('course_section_id'))->get()->keyBy('id');
        $labels = $divisions->keyBy('id');

        return ['divisions' => $rows, 'sections' => $sections->map(function ($s) use ($names, $labels, $pct) {
            $section = $names[$s->course_section_id] ?? null;
            $division = $section ? ($labels[$section->division_id] ?? null) : null;

            return ['course_section_id' => $s->course_section_id, 'subject' => optional(optional($section)->subject)->name,
                'division' => $division ? trim(optional($division->gradeLevel)->name.' '.$division->name) : null, 'year' => $pct($s)];
        })->sortBy('year')->values()->all()];
    }

    /** Alumnos que se acercan o superan el limite del nivel en el ciclo. */
    protected function atRisk($divisions, array $rules, string $yearStart, string $date): array
    {
        $base = AttendanceRecord::query()->whereIn('division_id', $divisions->pluck('id'))
            ->whereDate('date', '>=', $yearStart)->whereDate('date', '<=', $date);
        if ($rules['mode'] === AttendanceRules::DAILY) {
            $limit = $rules['max_absences'];
            $rows = (clone $base)->whereNull('course_section_id')->groupBy('enrollment_id')
                ->selectRaw('enrollment_id, sum(absence_weight) as weight, sum(case when is_justified = 1 then absence_weight else 0 end) as justified')
                ->get()->filter(fn ($r) => (float) $r->weight >= $limit * 0.8)->values();
            $names = $this->names($rows->pluck('enrollment_id'));

            return ['mode' => 'daily', 'limit' => $limit, 'students' => $rows->map(fn ($r) => [
                'enrollment_id' => (int) $r->enrollment_id, 'name' => $names[$r->enrollment_id]['name'] ?? '',
                'division' => $names[$r->enrollment_id]['division'] ?? '', 'absences' => (float) $r->weight,
                'justified' => (float) $r->justified, 'level' => $r->weight >= $limit ? 'exceeded' : 'warning',
            ])->sortByDesc('absences')->values()->all()];
        }

        $min = $rules['min_percentage'];
        $rows = (clone $base)->whereNotNull('course_section_id')->groupBy('enrollment_id', 'course_section_id')
            ->selectRaw('enrollment_id, course_section_id, count(*) as total, sum(absence_weight) as weight')
            ->havingRaw('count(*) >= 4')->get()
            ->map(fn ($r) => $r->setAttribute('pct', round(100 * (1 - $r->weight / $r->total), 1)))
            ->filter(fn ($r) => $r->pct < $min + 10);
        $names = $this->names($rows->pluck('enrollment_id')->unique());
        $subjects = CourseSection::with('subject:id,name')->whereIn('id', $rows->pluck('course_section_id')->unique())->get()->keyBy('id');

        return ['mode' => 'subject', 'limit' => $min, 'students' => $rows->map(fn ($r) => [
            'enrollment_id' => (int) $r->enrollment_id, 'name' => $names[$r->enrollment_id]['name'] ?? '',
            'division' => $names[$r->enrollment_id]['division'] ?? '',
            'subject' => optional(optional($subjects[$r->course_section_id] ?? null)->subject)->name,
            'percentage' => $r->pct, 'classes' => (int) $r->total, 'level' => $r->pct < $min ? 'exceeded' : 'warning',
        ])->sortBy('percentage')->values()->all()];
    }

    /** Inasistencias, tardanzas y dias de licencia del personal en el mes. */
    protected function staffAbsenteeism(int $level, string $from, string $to): array
    {
        $members = $this->staff->membersQuery($level)->get(['id', 'first_name', 'last_name']);
        $ids = $members->modelKeys();
        $records = StaffAttendance::whereIn('staff_member_id', $ids)->whereDate('date', '>=', $from)->whereDate('date', '<=', $to)
            ->get(['staff_member_id', 'date', 'status']);
        $leaves = StaffLeave::active()->whereIn('staff_member_id', $ids)->whereDate('starts_on', '<=', $to)->whereDate('ends_on', '>=', $from)->get();
        $byType = [];
        $people = $members->map(function ($m) use ($records, $leaves, $from, $to, &$byType) {
            $mine = $records->where('staff_member_id', $m->id);
            $leaveDays = 0;
            foreach ($leaves->where('staff_member_id', $m->id) as $leave) {
                $start = max($leave->starts_on->toDateString(), $from);
                $end = min($leave->ends_on->toDateString(), $to);
                $days = collect(CarbonPeriod::create($start, $end))->filter(fn ($d) => ! $d->isWeekend())->count();
                $leaveDays += $days;
                $label = StaffLeave::TYPES[$leave->type] ?? $leave->type;
                $byType[$label] = ($byType[$label] ?? 0) + $days;
            }

            return ['staff_member_id' => $m->id, 'name' => trim($m->last_name.', '.$m->first_name, ', '),
                'absent' => $mine->where('status', 'absent')->count(), 'late' => $mine->where('status', 'late')->count(),
                'leave_days' => $leaveDays];
        })->filter(fn ($p) => $p['absent'] + $p['late'] + $p['leave_days'] > 0)
            ->sortByDesc(fn ($p) => $p['absent'] + $p['leave_days'])->values();
        arsort($byType);

        return ['from' => $from, 'to' => $to, 'staff_total' => count($ids),
            'absent_days' => $people->sum('absent'), 'late' => $people->sum('late'), 'leave_days' => $people->sum('leave_days'),
            'by_type' => collect($byType)->map(fn ($days, $type) => ['type' => $type, 'days' => $days])->values(), 'people' => $people];
    }

    /** Nombre y curso de cada matricula, para las listas. */
    protected function names($enrollmentIds): array
    {
        return Enrollment::with(['student' => fn ($q) => $q->withoutGlobalScope('school_level')->select('id', 'first_name', 'last_name'),
            'division' => fn ($q) => $q->withoutGlobalScope('school_level')->select('id', 'name', 'grade_level_id'),
            'division.gradeLevel' => fn ($q) => $q->withoutGlobalScope('school_level')->select('id', 'name')])
            ->whereIn('id', collect($enrollmentIds)->all())->get()
            ->mapWithKeys(fn ($e) => [$e->id => ['name' => trim(optional($e->student)->last_name.', '.optional($e->student)->first_name, ', '),
                'division' => trim(optional(optional($e->division)->gradeLevel)->name.' '.optional($e->division)->name)]])->all();
    }
}
