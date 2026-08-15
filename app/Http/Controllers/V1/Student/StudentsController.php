<?php

namespace Crater\Http\Controllers\V1\Student;

use Crater\Http\Controllers\Controller;
use Crater\Http\Requests\StudentRequest;
use Crater\Models\Student;
use Illuminate\Http\Request;

class StudentsController extends Controller
{
    public function index(Request $request)
    {
        $companyId = $request->header('company');
        $limit = $request->get('limit', 15);

        $query = Student::with('guardian:id,name,email,phone')
            ->where('company_id', $companyId)
            ->when($request->search, function ($query, $search) {
                $query->where(function ($query) use ($search) {
                    $query->where('first_name', 'like', '%'.$search.'%')
                        ->orWhere('last_name', 'like', '%'.$search.'%')
                        ->orWhere('dni', 'like', '%'.$search.'%');
                });
            })
            ->when($request->level, function ($query, $level) {
                $query->where('level', $level);
            })
            ->when($request->status, function ($query, $status) {
                $query->where('status', $status);
            })
            ->when($request->school_year, function ($query, $schoolYear) {
                $query->where('school_year', $schoolYear);
            })
            ->orderBy('last_name')
            ->orderBy('first_name');

        $students = $limit === 'all' ? $query->get() : $query->paginate((int) $limit);

        return response()->json([
            'students' => $students,
            'summary' => [
                'total' => Student::where('company_id', $companyId)->count(),
                'active' => Student::where('company_id', $companyId)->where('status', 'active')->count(),
                'pending' => Student::where('company_id', $companyId)->where('status', 'pending')->count(),
            ],
        ]);
    }

    public function store(StudentRequest $request)
    {
        $data = $request->validated();
        if ($request->header('school-level')) {
            $data['school_level_id'] = $request->header('school-level');
        }

        $student = Student::create(array_merge($data, [
            'company_id' => $request->header('company'),
        ]));

        return response()->json(['student' => $student->load('guardian'), 'success' => true], 201);
    }

    public function show(Request $request, Student $student)
    {
        $this->ensureCompany($request, $student);

        return response()->json(['student' => $student->load('guardian')]);
    }

    public function update(StudentRequest $request, Student $student)
    {
        $this->ensureCompany($request, $student);
        $data = $request->validated();
        if ($request->header('school-level')) {
            $data['school_level_id'] = $request->header('school-level');
        }
        $student->update($data);

        return response()->json(['student' => $student->load('guardian'), 'success' => true]);
    }

    public function destroy(Request $request, Student $student)
    {
        $this->ensureCompany($request, $student);
        $student->delete();

        return response()->json(['success' => true]);
    }

    private function ensureCompany(Request $request, Student $student)
    {
        abort_unless((int) $student->company_id === (int) $request->header('company'), 404);
    }
}
