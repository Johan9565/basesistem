<?php

namespace App\Http\Controllers;

use App\Models\ExamModel;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class DashboardController extends Controller
{
    public function index(Request $request): Response
    {
        $exams = ExamModel::accessibleTo($request->user())
            ->map(fn (ExamModel $exam) => $exam->toCardArray($request->user()))
            ->values()
            ->all();

        return Inertia::render('Dashboard', [
            'exams' => $exams,
        ]);
    }
}
