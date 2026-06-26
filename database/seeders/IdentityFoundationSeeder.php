<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use App\Models\User;
use App\Models\Department;
use Spatie\Permission\Models\Role;

/**
 * IdentityFoundationSeeder — Phase D2A
 *
 * Performs exactly 4 operations:
 *   Task 1 — Fix 2 email typos (@peronik.com → @peroniks.com)
 *   Task 2 — Assign department_id to all 26 users
 *   Task 3 — Assign manager_id to 19 departments
 *   Task 4 — Create 'auditor' role (Spatie, no permissions)
 *
 * Safe to run multiple times (idempotent).
 */
class IdentityFoundationSeeder extends Seeder
{
    public function run(): void
    {
        // ── TASK 1: Fix email typos ────────────────────────────────────────────
        $this->command->info('Task 1: Fixing email typos...');

        $emailFixes = [
            'kabagcorfitting@peronik.com'  => 'kabagcorfitting@peroniks.com',
            'kabagnettoflange@peronik.com' => 'kabagnettoflange@peroniks.com',
        ];

        foreach ($emailFixes as $old => $new) {
            $affected = User::where('email', $old)->update(['email' => $new]);
            if ($affected) {
                $this->command->line("  ✓ Fixed: {$old} → {$new}");
            } else {
                $this->command->line("  – Not found (already fixed?): {$old}");
            }
        }

        // ── TASK 2: Assign department_id ──────────────────────────────────────
        $this->command->info('Task 2: Assigning department_id to users...');

        // Pre-build department code → id map for quick lookup
        $deptMap = Department::pluck('id', 'code')->toArray(); // ['QA-FL' => 13, ...]

        // Mapping: user.name → department.code (null = GLOBAL, no department)
        $userDeptMap = [
            'direktur'          => 'DIR',
            'MR'                => 'MR',
            'managerhr'         => 'PRS',
            'managerppic'       => 'PPIC',
            'managermarketing'  => 'MKT',
            'managerpurchasing' => 'PBL',
            'managerACC'        => 'ACC & FIN',
            'managertax'        => 'TAX',
            'kabagqc'           => 'QA-FL',
            'adminqcflange'     => 'QA-FL',
            'adminqcfitting'    => 'QA-PF',
            'adminmarketing'    => 'MKT',
            'kabagcorflange'    => 'COR-FL',
            'kabagcorfitting'   => 'COR-PF',
            'kabagflange'       => 'PRD-FL',
            'kabagfitting'      => 'PRD-PF',
            'kabagnettoflange'  => 'NT-FL',
            'kabagnettofitting' => 'NT-PF',
            'kabagbubutflange'  => 'BBT-FL',
            'kabagbubutfitting' => 'BBT-PF',
            'kabagmaintenance'  => 'MTC',
            'kabagga'           => 'MNJ',
            'adminflange'       => 'PRD-FL',
            'adminfitting'      => 'PRD-PF',
            'dc'                => null,    // GLOBAL
            'tuv'               => null,    // GLOBAL
        ];

        foreach ($userDeptMap as $userName => $deptCode) {
            $user = User::where('name', $userName)->first();
            if (! $user) {
                $this->command->line("  – User not found: {$userName}");
                continue;
            }

            $deptId = $deptCode ? ($deptMap[$deptCode] ?? null) : null;

            if ($deptCode && $deptId === null) {
                $this->command->warn("  ⚠ Department code not found: {$deptCode} (for user {$userName})");
                continue;
            }

            // Use DB::table to bypass fillable guard for safety, and allow null
            DB::table('users')->where('id', $user->id)->update(['department_id' => $deptId]);

            $label = $deptCode ?? 'NULL (GLOBAL)';
            $this->command->line("  ✓ {$userName} → {$label}");
        }

        // ── TASK 3: Assign manager_id to departments ──────────────────────────
        $this->command->info('Task 3: Assigning manager_id to departments...');

        // Mapping: department.code → user.name (the manager)
        $deptManagerMap = [
            'DIR'     => 'direktur',
            'MR'      => 'MR',
            'PRS'     => 'managerhr',
            'PPIC'    => 'managerppic',
            'MKT'     => 'managermarketing',
            'PBL'     => 'managerpurchasing',
            'ACC & FIN' => 'managerACC',
            'TAX'     => 'managertax',
            'QA-FL'   => 'kabagqc',
            'COR-FL'  => 'kabagcorflange',
            'COR-PF'  => 'kabagcorfitting',
            'PRD-FL'  => 'kabagflange',
            'PRD-PF'  => 'kabagfitting',
            'NT-FL'   => 'kabagnettoflange',
            'NT-PF'   => 'kabagnettofitting',
            'BBT-FL'  => 'kabagbubutflange',
            'BBT-PF'  => 'kabagbubutfitting',
            'MTC'     => 'kabagmaintenance',
            'MNJ'     => 'kabagga',
        ];

        foreach ($deptManagerMap as $deptCode => $managerName) {
            $dept = Department::where('code', $deptCode)->first();
            if (! $dept) {
                $this->command->line("  – Department not found: {$deptCode}");
                continue;
            }

            $manager = User::where('name', $managerName)->first();
            if (! $manager) {
                $this->command->line("  – Manager user not found: {$managerName} (for dept {$deptCode})");
                continue;
            }

            DB::table('departments')->where('id', $dept->id)->update(['manager_id' => $manager->id]);
            $this->command->line("  ✓ {$deptCode} → manager: {$managerName} (user_id={$manager->id})");
        }

        // ── TASK 4: Create 'auditor' role ─────────────────────────────────────
        $this->command->info('Task 4: Creating auditor role...');

        $role = Role::firstOrCreate(
            ['name' => 'auditor', 'guard_name' => 'web'],
        );

        if ($role->wasRecentlyCreated) {
            $this->command->line('  ✓ Role created: auditor (no permissions assigned)');
        } else {
            $this->command->line('  – Role already exists: auditor');
        }

        $this->command->info('Phase D2A Identity Foundation seeder complete.');
    }
}
