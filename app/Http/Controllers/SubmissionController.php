<?php

namespace App\Http\Controllers;

use App\Models\Submission;
use App\Models\Assignment;
use App\Models\Grade;
use App\Models\Module;
use App\Services\MoodleSubmissionService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class SubmissionController extends Controller
{
    protected $submissionService;

    public function __construct(MoodleSubmissionService $submissionService)
    {
        $this->submissionService = $submissionService;
    }

    // Affiche les soumissions pour un module
    public function index($moduleId)
    {
        $module = Module::findOrFail($moduleId);

        $assignments = $module->assignments; // si plusieurs assignments
        $submissions = Submission::with(['student', 'assignment', 'grade'])
                                ->whereIn('assignment_id', $assignments->pluck('id'))
                                ->get();

        return view('assignment-submissions', [
            'module' => $module,
            'assignments' => $assignments,
            'submissions' => $submissions
        ]);
    }

    // Créer une soumission
    public function store(Request $request, $moduleId)
    {
        $module = Module::findOrFail($moduleId);
        $assignment = $module->assignment; // ou $module->assignments->first() si multiple

        $request->validate([
            'content' => 'required_without:file|string',
            'file' => 'required_without:content|file|mimes:pdf,txt|max:5120'
        ]);

        $filePath = $request->hasFile('file') ? $request->file('file')->store('submissions') : null;

        $submission = Submission::create([
            'assignment_id' => $assignment->id,
            'user_id' => Auth::id(),
            'content' => $request->content,
            'file_path' => $filePath,
            'status' => 'submitted',
            'submitted_at' => now()
        ]);

        // Synchroniser avec Moodle (optionnel)
        $this->submissionService->submitAssignment(
            $assignment->moodle_id,
            Auth::user()->moodle_id,
            $request->content,
            $request->file('file') ?? null
        );

        return redirect()->back()->with('success', 'Submission successful!');
    }

    // Noter une soumission
    public function grade(Request $request, Submission $submission)
    {
        $user = Auth::user();

        // Vérifier le rôle correctement
        if (!$user || !$user->hasRole('ROLE_TEACHER')) {
            return redirect()->back()->with('error', 'Unauthorized action');
        }

        $request->validate([
            'grade' => 'required|numeric|min:0|max:20',
            'feedback' => 'nullable|string'
        ]);

        // Sauvegarder la note localement
        Grade::updateOrCreate(
            ['submission_id' => $submission->id],
            [
                'grade' => $request->grade,
                'comment' => $request->feedback,
                'teacher_id' => $user->id
            ]
        );

        $submission->update(['status' => 'graded']);

        // Synchroniser avec Moodle
        $success = $this->submissionService->saveGrade(
            $submission->assignment->moodle_id,
            $submission->user->moodle_id,
            $request->grade * 5, // Convertir sur 100 si besoin
            $request->feedback
        );

        if ($success) {
            return redirect()->back()->with('success', 'Grade saved successfully');
        }

        return redirect()->back()->with('error', 'Grade saved locally but failed to sync with Moodle');
    }
}
