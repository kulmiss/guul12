<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\Mock;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * "Preview as student" from Nova: a mock exam the way a student sees it before answering. Content
 * staff only (signed in to Nova); never indexed.
 */
class PreviewController extends Controller
{
    public function mock(Request $request, Mock $mock): Response
    {
        $user = $request->user();
        abort_unless($user instanceof User && $user->can('manage-content'), 403);

        $questions = $mock->questions()->visibleToStudents()->with(['options', 'passage'])->get();

        return response()->view('staff.mock-preview', ['mock' => $mock->load('subject'), 'questions' => $questions])
            ->header('X-Robots-Tag', 'noindex')
            ->header('Cache-Control', 'no-store');
    }
}
