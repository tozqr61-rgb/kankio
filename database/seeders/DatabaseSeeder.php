<?php

namespace Database\Seeders;

use App\Models\InviteCode;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $admin = User::where('role', 'admin')->first();

        if (! $admin && app()->environment(['local', 'testing'])) {
            $admin = User::firstOrCreate(['email' => 'admin@kank.test'], [
                'username' => 'devadmin',
                'email'    => 'admin@kank.test',
                'password' => Hash::make(env('DEV_ADMIN_PASSWORD', Str::random(32))),
                'role'     => 'admin',
            ]);
        }

        // Default global room
        $room = Room::firstOrCreate(['name' => 'Genel Sohbet'], [
            'name'       => 'Genel Sohbet',
            'type'       => 'global',
            'created_by' => $admin?->id,
        ]);

        if ($admin) {
            $room->members()->syncWithoutDetaching([$admin->id => ['role' => 'owner']]);
        }

        // Announcements room
        $ann = Room::firstOrCreate(['name' => 'Duyurular'], [
            'name'       => 'Duyurular',
            'type'       => 'announcements',
            'created_by' => $admin?->id,
        ]);

        if ($admin) {
            $ann->members()->syncWithoutDetaching([$admin->id => ['role' => 'owner']]);
        }

        if (app()->environment(['local', 'testing'])) {
            for ($i = 0; $i < 3; $i++) {
                do {
                    $code = 'KNK-'.Str::upper(Str::random(10));
                } while (InviteCode::where('code', $code)->exists());

                InviteCode::create([
                    'code' => $code,
                    'expires_at' => now()->addYear(),
                ]);
            }
        }
    }
}
