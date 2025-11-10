<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\User;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\Hash;

class UserRoleSeeder extends Seeder
{
    public function run(): void
    {
        // --- Create Roles ---
        $roles = ['admin', 'mr', 'kabag', 'viewer'];

        foreach ($roles as $r) {
            Role::firstOrCreate(['name' => $r]);
        }

        // --- User list ---
        $userList = [
            'direktur@peroniks.com'       => 'admin',
            'MR@peroniks.com'             => 'mr',
            'managerppic@peroniks.com'    => 'kabag',
            'managerhr@peroniks.com'      => 'kabag',
            'managermarketing@peroniks.com' => 'kabag',
            'managerpurchasing@peroniks.com' => 'kabag',
            'kabagqc@peroniks.com'        => 'kabag',
            'managerACC@peroniks.com'     => 'kabag',
            'managertax@peroniks.com'     => 'kabag',
            'kabagcorflange@peroniks.com' => 'kabag',
            'kabagcorfitting@peronik.com' => 'kabag',
            'kabagflange@peroniks.com'    => 'kabag',
            'kabagfitting@peroniks.com'   => 'kabag',
            'kabagnettoflange@peronik.com' => 'kabag',
            'kabagnettofitting@peroniks.com' => 'kabag',
            'kabagbubutflange@peroniks.com' => 'kabag',
            'kabagbubutfitting@peroniks.com' => 'kabag',
            'kabagmaintenance@peroniks.com' => 'kabag',
            'kabagga@peroniks.com'        => 'kabag',
            'adminflange@peroniks.com'    => 'viewer',
            'adminfitting@peroniks.com'   => 'viewer',
            'adminqcflange@peroniks.com'  => 'viewer',
            'adminqcfitting@peroniks.com' => 'viewer',
            'adminmarketing@peroniks.com' => 'viewer',
        ];

        // --- Create Users + Assign Roles ---
        foreach ($userList as $email => $roleName) {
            $user = User::firstOrCreate(
                ['email' => $email],
                [
                    'name' => explode('@', $email)[0],
                    'password' => Hash::make('password'),
                ]
            );

            if ($roleName) {
                $user->assignRole($roleName);
            }
        }
    }
}
