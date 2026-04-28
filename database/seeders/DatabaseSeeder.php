<?php

namespace Database\Seeders;

use App\Models\InviteCode;
use App\Models\Room;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Hash;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        // Admin user
        $admin = User::firstOrCreate(['email' => 'admin@kank.com'], [
            'username' => 'admin',
            'email'    => 'admin@kank.com',
            'password' => Hash::make('admin123'),
            'role'     => 'admin',
        ]);

        // Default global room
        $room = Room::firstOrCreate(['name' => 'Genel Sohbet'], [
            'name'       => 'Genel Sohbet',
            'type'       => 'global',
            'created_by' => $admin->id,
        ]);

        $room->members()->syncWithoutDetaching([$admin->id => ['role' => 'owner']]);

        // Announcements room
        $ann = Room::firstOrCreate(['name' => 'Duyurular'], [
            'name'       => 'Duyurular',
            'type'       => 'announcements',
            'created_by' => $admin->id,
        ]);

        $ann->members()->syncWithoutDetaching([$admin->id => ['role' => 'owner']]);

        // Initial invite codes
        $codes = ['KNK-ALPHA', 'KNK-BETA0', 'KNK-GAMMA'];
        foreach ($codes as $code) {
            InviteCode::firstOrCreate(['code' => $code], [
                'code'       => $code,
                'expires_at' => now()->addYear(),
            ]);
        }
    }
}
