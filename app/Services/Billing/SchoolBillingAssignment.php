<?php

namespace Crater\Services\Billing;

use Crater\Models\Enrollment;
use Crater\Models\FamilyMember;
use Crater\Models\Invoice;
use Crater\Models\Student;
use Crater\Support\TenantContext;
use Illuminate\Http\Request;

class SchoolBillingAssignment
{
    public function resolveInvoice(Request $request): array
    {
        if (! $request->filled('student_id')) {
            return [];
        }

        $companyId = (int) TenantContext::companyId();
        $schoolLevelId = (int) TenantContext::schoolLevelId();

        $student = Student::where('company_id', $companyId)
            ->where('school_level_id', $schoolLevelId)
            ->findOrFail((int) $request->student_id);

        $enrollment = null;
        if ($request->filled('enrollment_id')) {
            $enrollment = Enrollment::where('company_id', $companyId)
                ->where('school_level_id', $schoolLevelId)
                ->where('student_id', $student->id)
                ->findOrFail((int) $request->enrollment_id);
        } else {
            $enrollment = Enrollment::where('company_id', $companyId)
                ->where('school_level_id', $schoolLevelId)
                ->where('student_id', $student->id)
                ->active()
                ->orderByDesc('academic_year_id')
                ->first();
        }

        $familyMember = $this->resolveFinancialResponsible($student, $request->family_member_id);

        return [
            'student_id' => $student->id,
            'enrollment_id' => optional($enrollment)->id,
            'family_member_id' => $familyMember->id,
            // Compatibilidad con Crater. La identidad canónica es FamilyMember;
            // la cuenta User puede no existir todavía.
            'user_id' => $familyMember->user_id ?: null,
        ];
    }

    public function resolvePayment(Request $request): array
    {
        $companyId = (int) TenantContext::companyId();
        $schoolLevelId = (int) TenantContext::schoolLevelId();

        if ($request->filled('invoice_id')) {
            $invoice = Invoice::where('company_id', $companyId)
                ->where('school_level_id', $schoolLevelId)
                ->findOrFail((int) $request->invoice_id);

            if ($invoice->student_id) {
                return [
                    'student_id' => $invoice->student_id,
                    'family_member_id' => $invoice->family_member_id,
                    'user_id' => $invoice->user_id,
                ];
            }
        }

        if (! $request->filled('student_id')) {
            return [];
        }

        $student = Student::where('company_id', $companyId)
            ->where('school_level_id', $schoolLevelId)
            ->findOrFail((int) $request->student_id);
        $familyMember = $this->resolveFinancialResponsible($student, $request->family_member_id);

        return [
            'student_id' => $student->id,
            'family_member_id' => $familyMember->id,
            'user_id' => $familyMember->user_id ?: null,
        ];
    }

    private function resolveFinancialResponsible(Student $student, $requestedId): FamilyMember
    {
        $query = $student->familyMembers()
            ->where('family_members.company_id', $student->company_id)
            ->wherePivot('is_financial_responsible', true);

        if ($requestedId) {
            $member = (clone $query)->where('family_members.id', (int) $requestedId)->first();
            abort_unless($member, 422, 'El responsable seleccionado no es responsable financiero de este alumno.');

            return $member;
        }

        $member = $query
            ->orderByDesc('student_family_members.is_primary_contact')
            ->first();

        abort_unless($member, 422, 'El alumno debe tener al menos un responsable financiero antes de generar una cuota.');

        return $member;
    }
}
