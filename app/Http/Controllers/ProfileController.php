<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class ProfileController extends Controller
{
    public function uploadAvatar(Request $request)
    {
        $request->validate([
            'avatar' => 'required|image|mimes:jpeg,png,jpg,gif,webp',
        ], [
            'avatar.required' => 'Lütfen bir fotoğraf seçin.',
            'avatar.image'    => 'Dosya bir görsel olmalıdır.',
            'avatar.mimes'    => 'Desteklenen formatlar: JPEG, PNG, GIF, WebP.',
        ]);

        $user = Auth::user();

        /* Delete old avatar (public disk: storage/app/public) */
        if ($user->avatar_url && str_contains($user->avatar_url, '/storage/avatars/')) {
            $oldFile = 'avatars/' . basename($user->avatar_url);
            Storage::disk('public')->delete($oldFile);
        }

        $file     = $request->file('avatar');
        $filename = $user->id . '_' . time() . '.' . $file->getClientOriginalExtension();
        /* Must use 'public' disk so files land in storage/app/public (symlinked to public/storage) */
        $path     = $file->storeAs('avatars', $filename, 'public');
        $url      = '/storage/avatars/' . $filename;

        $user->update([
            'avatar_url'          => $url,
            'last_avatar_update'  => now(),
        ]);

        return response()->json(['avatar_url' => $url]);
    }

    public function toggleNotifications(Request $request)
    {
        $user    = Auth::user();
        $enabled = ! $user->notifications_enabled;

        $user->update(['notifications_enabled' => $enabled]);

        return response()->json(['notifications_enabled' => $enabled]);
    }
}
