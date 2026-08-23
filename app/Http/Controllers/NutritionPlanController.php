<?php

namespace App\Http\Controllers;

use App\Models\Student;
use App\Services\NutritionAssessmentService;
use Illuminate\Http\RedirectResponse;

class NutritionPlanController extends Controller
{
    public function assess(Student $student, NutritionAssessmentService $assessmentService): RedirectResponse
    {
        $schoolId = auth()->user()?->school_id;
        if ($schoolId && $student->school_id !== $schoolId) {
            abort(403);
        }

        $assessment = $assessmentService->assess($student);

        return redirect(route('students.show', $student).'#nutritionPlan')
            ->with('nutritionAssessment', $assessment);
    }
}
