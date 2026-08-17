<?php

namespace Crater\Http\Controllers\V1\Billing;

use Crater\Http\Controllers\Controller;
use Crater\Models\Student;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;

class SchoolBillingOptionsController extends Controller
{
    public function __invoke(Request $request)
    {
        $companyId = (int) TenantContext::companyId();
        $schoolLevelId = (int) TenantContext::schoolLevelId();

        $students = Student::where('company_id', $companyId)
            ->where('school_level_id', $schoolLevelId)
            ->whereIn('status', ['active', 'pending'])
            ->with([
                'familyMembers' => function ($query) {
                    $query->select('family_members.id', 'family_members.name', 'family_members.dni', 'family_members.user_id')
                        ->wherePivot('is_financial_responsible', true);
                },
            ])
            ->orderBy('last_name')
            ->orderBy('first_name')
            ->get()
            ->map(function ($student) {
                $enrollment = \Crater\Models\Enrollment::where('company_id', $student->company_id)
                    ->where('school_level_id', $student->school_level_id)
                    ->where('student_id', $student->id)
                    ->active()
                    ->with(['division.gradeLevel', 'academicYear'])
                    ->orderByDesc('academic_year_id')
                    ->first();

                return [
                    'id' => $student->id,
                    'full_name' => $student->full_name,
                    'dni' => $student->dni,
                    'enrollment_id' => optional($enrollment)->id,
                    'course' => $enrollment && $enrollment->division
                        ? trim(optional($enrollment->division->gradeLevel)->name.' '.$enrollment->division->name)
                        : trim(($student->grade ?: '').' '.($student->division ?: '')),
                    'academic_year' => optional(optional($enrollment)->academicYear)->year ?: $student->school_year,
                    'financial_responsibles' => $student->familyMembers->map(function ($member) {
                        return [
                            'id' => $member->id,
                            'name' => $member->name,
                            'dni' => $member->dni,
                            'user_id' => $member->user_id,
                            'relationship' => optional($member->pivot)->relationship,
                            'is_primary_contact' => (bool) optional($member->pivot)->is_primary_contact,
                        ];
                    })->values(),
                ];
            });

        return response()->json(['students' => $students]);
    }
}
