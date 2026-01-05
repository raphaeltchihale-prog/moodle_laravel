<?php

namespace App\Http\Controllers;

use App\Models\Module;
use App\Models\AssignmentFile;
use App\Models\Grade;
use App\Models\Assignment;
use App\Models\Submission; 
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class AssignmentController extends Controller
{
    public function show(Module $module)
    {
        return view('assignments.show', compact('module'));
    }

    public function createGrade(Request $request, Module $module)
    {
        $validated = $request->validate([
            'grade' => 'required|integer|min:0|max:100',
            'comment' => 'nullable|string',
            'submission_id' => 'required|exists:submissions,id',
        ]);

        $validated['teacher_id'] = Auth::id();

        Grade::create($validated);

        return redirect()->route('assignments.show', $module)
            ->with('success', 'Note attribuée avec succès.');
    }

    public function composeTest(Request $request, Module $module)
    {
        $validated = $request->validate([
            'selected_files' => 'required|array',
            'selected_files.*' => 'exists:assignment_files,id',
            'instructions' => 'required|string',
        ]);

        // Logique pour composer l'épreuve
        // ...

        return redirect()->route('assignments.show', $module)
            ->with('success', 'Épreuve composée avec succès.');
    }

    public function submissions($moduleId)
    {
        $module = Module::findOrFail($moduleId);
        return view('assignment-submissions', compact('module'));
    }

    // Méthode pour afficher tous les assignments et leurs submissions
    public function index()
    {
        $assignments = Assignment::all(); 
        $submissions = Submission::with(['student', 'assignment', 'grade'])->get();

        // 🔹 Passe les deux variables à la vue
        return view('assignments.index', compact('assignments', 'submissions'));
    }
}
