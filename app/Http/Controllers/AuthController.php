<?php

namespace App\Http\Controllers;

use App\Models\InviteCode;
use App\Models\Room;
use App\Models\User;
use Illuminate\Validation\ValidationException;
use Illuminate\Http\Request;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
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

        if ($user->is_banned || $user->isDeactivated()) {
            return back()->withErrors(['username' => 'Bu hesap artık aktif değil.'])->withInput();
        }

        if ($user->is_bot) {
            return back()->withErrors(['username' => 'Bu hesapla giriş yapılamaz.'])->withInput();
        }

        Auth::login($user, $request->boolean('remember'));
        $request->session()->regenerate();

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

        $email = $username . '@kank.com';

        if (User::where('username', $username)->exists()) {
            return back()->withErrors(['username' => 'Bu kullanıcı adı zaten alınmış.'])->withInput();
        }

        try {
            $user = DB::transaction(function () use ($request, $username, $email) {
                $invite = InviteCode::where('code', strtoupper($request->invite_code))
                    ->where('is_used', false)
                    ->lockForUpdate()
                    ->first();

                if (! $invite || ! $invite->isValid()) {
                    throw ValidationException::withMessages([
                        'invite_code' => 'Geçersiz veya süresi dolmuş davetiye kodu.',
                    ]);
                }

                $user = User::create([
                    'username' => $username,
                    'email'    => $email,
                    'password' => Hash::make($request->password),
                    'role'     => 'user',
                ]);

                $invite->update(['is_used' => true, 'used_by' => $user->id]);

                $globalRooms = Room::where('type', 'global')->get();
                foreach ($globalRooms as $room) {
                    $room->members()->syncWithoutDetaching([$user->id => ['role' => 'member']]);
                }

                return $user;
            });
        } catch (ValidationException $e) {
            throw $e;
        } catch (QueryException) {
            return back()->withErrors(['username' => 'Bu kullanıcı adı zaten alınmış.'])->withInput();
        }

        Auth::login($user);
        $request->session()->regenerate();

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
