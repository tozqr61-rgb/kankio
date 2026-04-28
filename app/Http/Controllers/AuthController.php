<?php

namespace App\Http\Controllers;

use App\Models\InviteCode;
use App\Models\Room;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;

class AuthController extends Controller
{
    public function showLogin()
    {
        if (Auth::check()) {
            return redirect()->route('chat.index');
        }
        return view('auth.login');
    }

    public function login(Request $request)
    {
        $request->validate([
            'username' => 'required|string|min:3',
            'password' => 'required|string|min:6',
        ]);

        $username  = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $request->username));
        $email     = $username . '@kank.com';

        $user = User::where('email', $email)->first();

        if (! $user || ! Hash::check($request->password, $user->password)) {
            return back()->withErrors(['username' => 'Kullanıcı adı veya şifre hatalı.'])->withInput();
        }

        if ($user->is_banned) {
            return back()->withErrors(['username' => 'Hesabınız yasaklanmıştır.'])->withInput();
        }

        Auth::login($user, $request->boolean('remember'));

        $user->update(['last_seen_at' => now()]);

        return redirect()->route('chat.index');
    }

    public function register(Request $request)
    {
        $request->validate([
            'username'    => 'required|string|min:3',
            'password'    => 'required|string|min:6|confirmed',
            'invite_code' => 'required|string',
        ]);

        $username = strtolower(preg_replace('/[^a-zA-Z0-9]/', '', $request->username));

        if (strlen($username) < 3) {
            return back()->withErrors(['username' => 'Kullanıcı adı en az 3 harf/rakam içermelidir.'])->withInput();
        }

        $invite = InviteCode::where('code', strtoupper($request->invite_code))
            ->where('is_used', false)
            ->first();

        if (! $invite || ! $invite->isValid()) {
            return back()->withErrors(['invite_code' => 'Geçersiz veya süresi dolmuş davetiye kodu.'])->withInput();
        }

        $email = $username . '@kank.com';

        if (User::where('username', $username)->exists()) {
            return back()->withErrors(['username' => 'Bu kullanıcı adı zaten alınmış.'])->withInput();
        }

        $user = User::create([
            'username' => $username,
            'email'    => $email,
            'password' => Hash::make($request->password),
            'role'     => 'user',
        ]);

        $invite->update(['is_used' => true, 'used_by' => $user->id]);

        // Add to all global rooms
        $globalRooms = Room::where('type', 'global')->get();
        foreach ($globalRooms as $room) {
            $room->members()->syncWithoutDetaching([$user->id => ['role' => 'member']]);
        }

        Auth::login($user);

        return redirect()->route('chat.index');
    }

    public function logout(Request $request)
    {
        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return redirect()->route('landing');
    }
}
