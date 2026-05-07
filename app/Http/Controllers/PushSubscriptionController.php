<?php

namespace App\Http\Controllers;

use App\Models\PushSubscription;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class PushSubscriptionController extends Controller
{
    public function publicKey()
    {
        return response()->json([
            'public_key' => config('services.webpush.public_key'),
        ]);
    }

    public function store(Request $request)
    {
        $data = $request->validate([
            'endpoint'                  => 'required|url|max:2048',
            'keys.p256dh'               => 'nullable|string|max:512',
            'keys.auth'                 => 'nullable|string|max:512',
        ]);

        $user = Auth::user();

        PushSubscription::updateOrCreate(
            ['endpoint' => $data['endpoint']],
            [
                'user_id'     => $user->id,
                'public_key'  => $data['keys']['p256dh'] ?? null,
                'auth_token'  => $data['keys']['auth'] ?? null,
            ],
        );

        return response()->json(['ok' => true]);
    }

    public function destroy(Request $request)
    {
        $data = $request->validate([
            'endpoint' => 'required|string',
        ]);

        PushSubscription::where('endpoint', $data['endpoint'])
            ->where('user_id', Auth::id())
            ->delete();

        return response()->json(['ok' => true]);
    }
}
