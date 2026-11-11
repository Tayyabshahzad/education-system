<?php

namespace App\Http\Controllers\Teacher;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function show(Request $request, Batch $batch): View
    {
        abort_unless($batch->teacher_id === $request->user()->id, 403);

        return view('teacher.batch', [
            'batch' => $batch->load(['members' => fn ($q) => $q->orderBy('name')]),
        ]);
    }
}
