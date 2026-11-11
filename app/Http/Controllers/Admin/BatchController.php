<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Batch;
use App\Models\Setting;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function index(): View
    {
        return view('admin.batches.index', [
            'batches' => Batch::with('teacher:id,name')->withCount('members')->latest()->paginate(20),
        ]);
    }

    public function create(): View
    {
        return view('admin.batches.form', ['batch' => new Batch(['status' => 'upcoming']), 'teachers' => $this->teachers()]);
    }

    public function store(Request $request): RedirectResponse
    {
        $batch = Batch::create([...$this->validated($request), 'created_by' => $request->user()->id]);

        return redirect()->route('admin.batches.show', $batch)->with('success', 'Batch created. Now assign members to it.');
    }

    public function show(Request $request, Batch $batch): View
    {
        $search = trim((string) $request->query('q'));
        $enrolledIds = $batch->members()->pluck('users.id');

        // Only active members who paid the full fee can join a class batch.
        $eligible = User::role(User::ROLE_MEMBER)
            ->where('status', 'active')
            ->fullyPaid()
            ->whereNotIn('id', $enrolledIds)
            ->when($search, fn ($q) => $q->where(fn ($q) => $q
                ->where('name', 'like', "%{$search}%")
                ->orWhere('username', 'like', "%{$search}%")))
            ->withCount('batches')
            ->orderBy('batches_count')
            ->orderBy('name')
            ->limit(100)
            ->get();

        return view('admin.batches.show', [
            'batch' => $batch->load(['teacher', 'members' => fn ($q) => $q->orderBy('name')]),
            'eligible' => $eligible,
            'search' => $search,
            'fullFee' => Setting::money('full_fee'),
        ]);
    }

    public function edit(Batch $batch): View
    {
        return view('admin.batches.form', ['batch' => $batch, 'teachers' => $this->teachers()]);
    }

    public function update(Request $request, Batch $batch): RedirectResponse
    {
        $batch->update($this->validated($request));

        return redirect()->route('admin.batches.show', $batch)->with('success', 'Batch updated.');
    }

    public function destroy(Batch $batch): RedirectResponse
    {
        $batch->delete();

        return redirect()->route('admin.batches.index')->with('success', 'Batch deleted.');
    }

    public function assign(Request $request, Batch $batch): RedirectResponse
    {
        $data = $request->validate(['members' => ['required', 'array'], 'members.*' => ['integer']]);

        $ids = User::role(User::ROLE_MEMBER)->where('status', 'active')->fullyPaid()
            ->whereIn('id', $data['members'])->pluck('id');

        $batch->members()->syncWithoutDetaching($ids->mapWithKeys(fn ($id) => [$id => ['assigned_by' => $request->user()->id]])->all());

        $skipped = count($data['members']) - $ids->count();

        return back()->with('success', $ids->count().' member(s) assigned.'.($skipped ? " {$skipped} skipped (not fully paid or inactive)." : ''));
    }

    public function unassign(Batch $batch, User $member): RedirectResponse
    {
        $batch->members()->detach($member->id);

        return back()->with('success', "{$member->username} removed from the batch.");
    }

    private function validated(Request $request): array
    {
        $data = $request->validate([
            'name' => ['required', 'string', 'max:120'],
            'teacher_id' => ['nullable', 'integer', function ($attribute, $value, $fail) {
                if (! User::role(User::ROLE_TEACHER)->whereKey($value)->exists()) {
                    $fail('Please choose a valid teacher.');
                }
            }],
            'start_date' => ['nullable', 'date'],
            'days' => ['nullable', 'array'],
            'days.*' => [Rule::in(['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'])],
            'class_time' => ['nullable', 'date_format:H:i'],
            'duration_minutes' => ['nullable', 'integer', 'min:15', 'max:600'],
            'meeting_link' => ['nullable', 'url', 'max:255'],
            'description' => ['nullable', 'string', 'max:2000'],
            'status' => ['required', Rule::in(['upcoming', 'running', 'completed'])],
        ]);

        $data['days'] = ! empty($data['days']) ? implode(', ', $data['days']) : null;

        return $data;
    }

    private function teachers()
    {
        return User::role(User::ROLE_TEACHER)->orderBy('name')->get(['id', 'name']);
    }
}
