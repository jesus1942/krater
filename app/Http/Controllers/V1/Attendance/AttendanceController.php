<?php

namespace Crater\Http\Controllers\V1\Attendance;

use Crater\Enums\Permission;
use Crater\Http\Controllers\Controller;
use Crater\Models\AttendanceRecord;
use Crater\Models\StaffLeave;
use Crater\Services\Access\AccessManager;
use Crater\Services\Attendance\AttendancePanel;
use Crater\Services\Attendance\AttendanceRules;
use Crater\Services\Attendance\StaffAttendanceBook;
use Crater\Services\Attendance\StudentAttendance;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Asistencia de alumnos y del personal, y panel de la direccion. */
class AttendanceController extends Controller
{
    public function __construct()
    {
        $this->middleware('tenant');
    }

    public function targets(Request $request, StudentAttendance $students)
    {
        return response()->json($students->targets($request->user()));
    }

    public function sheet(Request $request, StudentAttendance $students)
    {
        $data = $request->validate($this->targetRules());

        return response()->json($students->sheet($request->user(), $data['type'], (int) $data['id'], $data['date']));
    }

    public function saveSheet(Request $request, StudentAttendance $students)
    {
        $data = $request->validate($this->targetRules() + [
            'records' => ['required', 'array', 'min:1', 'max:200'],
            'records.*.enrollment_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', Rule::in(AttendanceRecord::STATUSES)],
        ]);

        return response()->json($students->save($request->user(), $data['type'], (int) $data['id'], $data['date'], $data['records']));
    }

    public function justify(Request $request, AttendanceRecord $record, StudentAttendance $students)
    {
        $data = $request->validate(['justification' => ['nullable', 'string', 'max:255']]);
        $record = $students->justify($request->user(), $record, $data['justification'] ?? null);

        return response()->json(['id' => $record->id, 'is_justified' => $record->is_justified, 'justification' => $record->justification]);
    }

    public function panel(Request $request, AttendancePanel $panel)
    {
        $data = $request->validate(['date' => ['nullable', 'date_format:Y-m-d']]);

        return response()->json($panel->build($request->user(), $data['date'] ?? now()->toDateString()));
    }

    /** Reglas del nivel (modo y limites); las cambia quien administra el nivel. */
    public function rules(Request $request, StudentAttendance $students, AttendanceRules $rules, AccessManager $access)
    {
        $level = $students->level();
        if ($request->isMethod('put')) {
            abort_unless($access->allows($request->user(), Permission::ACADEMIC_YEAR_MANAGE, $level->id), 403);
            $data = $request->validate([
                'mode' => ['required', Rule::in([AttendanceRules::DAILY, AttendanceRules::SUBJECT])],
                'max_absences' => ['required', 'numeric', 'min:1', 'max:200'],
                'min_percentage' => ['required', 'numeric', 'min:1', 'max:100'],
            ]);

            return response()->json($rules->save($level, $data));
        }

        return response()->json($rules->forLevel($level));
    }

    public function staffDay(Request $request, StaffAttendanceBook $staff)
    {
        $data = $request->validate(['date' => ['required', 'date_format:Y-m-d']]);

        return response()->json($staff->day($request->user(), $data['date']));
    }

    public function saveStaffDay(Request $request, StaffAttendanceBook $staff)
    {
        $data = $request->validate([
            'date' => ['required', 'date_format:Y-m-d'],
            'records' => ['required', 'array', 'min:1', 'max:300'],
            'records.*.staff_member_id' => ['required', 'integer', 'distinct'],
            'records.*.status' => ['required', Rule::in(['present', 'absent', 'late'])],
            'records.*.notes' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($staff->save($request->user(), $data['date'], $data['records']));
    }

    public function leaves(Request $request, StaffAttendanceBook $staff)
    {
        $data = $request->validate(['from' => ['required', 'date_format:Y-m-d'], 'to' => ['required', 'date_format:Y-m-d']]);

        return response()->json(['data' => $staff->leaves($request->user(), $data['from'], $data['to']), 'types' => StaffLeave::TYPES]);
    }

    public function storeLeave(Request $request, StaffAttendanceBook $staff)
    {
        $data = $request->validate([
            'staff_member_id' => ['required', 'integer'],
            'type' => ['required', Rule::in(array_keys(StaffLeave::TYPES))],
            'starts_on' => ['required', 'date_format:Y-m-d'], 'ends_on' => ['required', 'date_format:Y-m-d'],
            'notes' => ['nullable', 'string', 'max:500'],
        ]);

        return response()->json($staff->createLeave($request->user(), $data), 201);
    }

    public function cancelLeave(Request $request, StaffLeave $leave, StaffAttendanceBook $staff)
    {
        return response()->json($staff->cancelLeave($request->user(), $leave));
    }

    protected function targetRules(): array
    {
        return [
            'type' => ['required', Rule::in(['division', 'course_section'])],
            'id' => ['required', 'integer'],
            'date' => ['required', 'date_format:Y-m-d'],
        ];
    }
}
