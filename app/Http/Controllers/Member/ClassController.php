<?php

namespace App\Http\Controllers\Member;

use App\Http\Controllers\Controller;
use App\Models\Setting;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();

        return view('member.classes', [
            'batches' => $user->batches()->with('teacher:id,name,email,phone')->orderBy('start_date')->get(),
            'eligible' => $user->isFullyPaid(),
            'fullFee' => Setting::money('full_fee'),
        ]);
    }
}
