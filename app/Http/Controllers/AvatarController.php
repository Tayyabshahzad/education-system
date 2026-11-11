<?php

namespace App\Http\Controllers;

use App\Models\User;
use App\Services\AvatarService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class AvatarController extends Controller
{
    /** Served through Laravel (no storage:link needed on shared hosting). Cached by the browser for a week. */
    public function show(User $user): Response
    {
        if (! $user->avatar_path || ! Storage::exists($user->avatar_path)) {
            return redirect(asset('images/default-avatar.svg'));
        }

        return Storage::response($user->avatar_path, null, [
            'Cache-Control' => 'private, max-age=604800',
        ]);
    }

    public function update(Request $request, AvatarService $avatars): RedirectResponse
    {
        $request->validate([
            'avatar' => ['required', 'file', 'image', 'mimes:jpg,jpeg,png,webp', 'max:10240'],
        ], [
            'avatar.image' => 'Please upload a JPG, PNG or WEBP photo.',
            'avatar.mimes' => 'Please upload a JPG, PNG or WEBP photo.',
        ]);

        $avatars->update($request->user(), $request->file('avatar'));

        return back()->with('success', 'Profile picture updated.');
    }

    public function destroy(Request $request, AvatarService $avatars): RedirectResponse
    {
        $avatars->remove($request->user());

        return back()->with('success', 'Profile picture removed.');
    }
}
