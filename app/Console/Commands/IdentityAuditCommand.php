<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\User;
use App\Models\Department;
use Spatie\Permission\Models\Role;
use Illuminate\Support\Facades\DB;

/**
 * php artisan identity:audit
 *
 * Phase D2A — READ-ONLY audit of user/department/role identity state.
 * No write operations of any kind.
 */
class IdentityAuditCommand extends Command
{
    protected $signature   = 'identity:audit';
    protected $description = 'Phase D2A: Read-only audit of user identity, department, and role assignments';

    public function handle(): int
    {
        $this->header();

        $this->auditUsers();
        $this->auditRoles();
        $this->auditDepartments();
        $this->auditUsersWithoutDepartment();
        $this->auditDepartmentsWithoutManager();
        $this->auditDuplicateEmails();
        $this->auditInvalidEmailDomains();
        $this->auditUsersWithMultipleRoles();
        $this->summary();

        return self::SUCCESS;
    }

    // ── Section header ────────────────────────────────────────────────────────

    private function header(): void
    {
        $this->line('');
        $this->line('  <fg=cyan>--------------------------------------------------</>');
        $this->line('  <fg=cyan;options=bold>  IDENTITY AUDIT — Phase D2A</>');
        $this->line('  <fg=cyan>  ' . now()->format('Y-m-d H:i:s') . '</>');
        $this->line('  <fg=cyan>--------------------------------------------------</>');
        $this->line('');
    }

    // ── 1. Users ──────────────────────────────────────────────────────────────

    private function auditUsers(): void
    {
        $this->line('  <options=bold>USERS</>');
        $this->line('  ' . str_repeat('─', 46));

        $users = User::with(['roles', 'department'])->orderBy('id')->get();

        $rows = $users->map(function (User $u) {
            $roles = $u->roles->pluck('name')->join(', ') ?: '—';
            $dept  = $u->department ? $u->department->code : 'NULL';
            return [
                $u->id,
                $u->name,
                $u->email,
                $roles,
                $dept,
            ];
        })->toArray();

        $this->table(
            ['ID', 'Name', 'Email', 'Role(s)', 'Dept'],
            $rows
        );

        $this->line('  Total users: ' . $users->count());
        $this->line('');
    }

    // ── 2. Roles ──────────────────────────────────────────────────────────────

    private function auditRoles(): void
    {
        $this->line('  <options=bold>ROLES</>');
        $this->line('  ' . str_repeat('─', 46));

        $roles = Role::orderBy('name')->get();

        $rows = $roles->map(function (Role $r) {
            $count = DB::table('model_has_roles')
                ->where('role_id', $r->id)
                ->where('model_type', 'App\Models\User')
                ->count();
            return [$r->id, $r->name, $r->guard_name, $count];
        })->toArray();

        $this->table(['ID', 'Name', 'Guard', 'Users Assigned'], $rows);

        $this->line('  Total roles: ' . $roles->count());
        $this->line('');
    }

    // ── 3. Departments ────────────────────────────────────────────────────────

    private function auditDepartments(): void
    {
        $this->line('  <options=bold>DEPARTMENTS</>');
        $this->line('  ' . str_repeat('─', 46));

        $depts = Department::with('manager')->orderBy('code')->get();

        $rows = $depts->map(function (Department $d) {
            $mgr = $d->manager ? $d->manager->name : 'NULL';
            $userCount = User::where('department_id', $d->id)->count();
            return [$d->id, $d->code, $d->name, $mgr, $userCount];
        })->toArray();

        $this->table(['ID', 'Code', 'Name', 'Manager', 'Users'], $rows);

        $this->line('  Total departments: ' . $depts->count());
        $this->line('');
    }

    // ── 4. Users without department ───────────────────────────────────────────

    private function auditUsersWithoutDepartment(): void
    {
        $this->line('  <options=bold>USERS WITHOUT DEPARTMENT</>');
        $this->line('  ' . str_repeat('─', 46));

        $users = User::whereNull('department_id')->orderBy('name')->get();

        if ($users->isEmpty()) {
            $this->line('  <fg=green>✓ All users have a department assigned (or are GLOBAL).</>');
        } else {
            $rows = $users->map(fn(User $u) => [$u->id, $u->name, $u->email])->toArray();
            $this->table(['ID', 'Name', 'Email'], $rows);
            $this->line('  <fg=yellow>⚠ ' . $users->count() . ' user(s) have no department.</>');
        }

        $this->line('');
    }

    // ── 5. Departments without manager ────────────────────────────────────────

    private function auditDepartmentsWithoutManager(): void
    {
        $this->line('  <options=bold>DEPARTMENTS WITHOUT MANAGER</>');
        $this->line('  ' . str_repeat('─', 46));

        $depts = Department::whereNull('manager_id')->orderBy('code')->get();

        if ($depts->isEmpty()) {
            $this->line('  <fg=green>✓ All departments have a manager assigned.</>');
        } else {
            $rows = $depts->map(fn(Department $d) => [$d->id, $d->code, $d->name])->toArray();
            $this->table(['ID', 'Code', 'Name'], $rows);
            $this->line('  <fg=yellow>⚠ ' . $depts->count() . ' department(s) have no manager.</>');
        }

        $this->line('');
    }

    // ── 6. Duplicate emails ───────────────────────────────────────────────────

    private function auditDuplicateEmails(): void
    {
        $this->line('  <options=bold>DUPLICATE EMAILS</>');
        $this->line('  ' . str_repeat('─', 46));

        $dupes = DB::table('users')
            ->select('email', DB::raw('COUNT(*) as total'))
            ->groupBy('email')
            ->having('total', '>', 1)
            ->orderBy('email')
            ->get();

        if ($dupes->isEmpty()) {
            $this->line('  <fg=green>✓ No duplicate email addresses found.</>');
        } else {
            $rows = $dupes->map(fn($row) => [$row->email, $row->total])->toArray();
            $this->table(['Email', 'Count'], $rows);
            $this->line('  <fg=red>✗ ' . $dupes->count() . ' duplicate email(s) found.</>');
        }

        $this->line('');
    }

    // ── 7. Invalid email domains ──────────────────────────────────────────────

    private function auditInvalidEmailDomains(): void
    {
        $this->line('  <options=bold>INVALID EMAIL DOMAINS</>');
        $this->line('  ' . str_repeat('─', 46));

        $validDomain = 'peroniks.com';

        $invalid = User::orderBy('name')->get()->filter(function (User $u) use ($validDomain) {
            return ! str_ends_with(strtolower($u->email), '@' . $validDomain);
        });

        if ($invalid->isEmpty()) {
            $this->line("  <fg=green>✓ All emails use @{$validDomain}.</>");
        } else {
            $rows = $invalid->map(fn(User $u) => [$u->id, $u->name, $u->email])->toArray();
            $this->table(['ID', 'Name', 'Email'], $rows);
            $this->line('  <fg=red>✗ ' . $invalid->count() . ' user(s) with invalid email domain.</>');
        }

        $this->line('');
    }

    // ── 8. Users with multiple roles ──────────────────────────────────────────

    private function auditUsersWithMultipleRoles(): void
    {
        $this->line('  <options=bold>USERS WITH MULTIPLE ROLES</>');
        $this->line('  ' . str_repeat('─', 46));

        $multiRole = DB::table('model_has_roles')
            ->where('model_type', 'App\Models\User')
            ->select('model_id', DB::raw('COUNT(role_id) as role_count'))
            ->groupBy('model_id')
            ->having('role_count', '>', 1)
            ->get();

        if ($multiRole->isEmpty()) {
            $this->line('  <fg=green>✓ No users with multiple roles.</>');
        } else {
            $rows = $multiRole->map(function ($row) {
                $user  = User::with('roles')->find($row->model_id);
                $roles = $user ? $user->roles->pluck('name')->join(', ') : '?';
                $name  = $user ? $user->name : "ID:{$row->model_id}";
                return [$row->model_id, $name, $row->role_count, $roles];
            })->toArray();

            $this->table(['User ID', 'Name', 'Role Count', 'Roles'], $rows);
            $this->line('  <fg=yellow>⚠ ' . $multiRole->count() . ' user(s) have more than one role.</>');
        }

        $this->line('');
    }

    // ── Summary ───────────────────────────────────────────────────────────────

    private function summary(): void
    {
        $this->line('  <fg=cyan>--------------------------------------------------</>');
        $this->line('  <options=bold>SUMMARY</>');
        $this->line('  <fg=cyan>--------------------------------------------------</>');

        $totalUsers      = User::count();
        $usersWithDept   = User::whereNotNull('department_id')->count();
        $usersNoDept     = User::whereNull('department_id')->count();
        $totalDepts      = Department::count();
        $deptsWithMgr    = Department::whereNotNull('manager_id')->count();
        $deptsNoMgr      = Department::whereNull('manager_id')->count();
        $totalRoles      = Role::count();

        $dupeCount = DB::table('users')
            ->select('email', DB::raw('COUNT(*) as total'))
            ->groupBy('email')
            ->having('total', '>', 1)
            ->count();

        $invalidDomain = User::get()->filter(
            fn(User $u) => ! str_ends_with(strtolower($u->email), '@peroniks.com')
        )->count();

        $multiRoleCount = DB::table('model_has_roles')
            ->where('model_type', 'App\Models\User')
            ->select('model_id', DB::raw('COUNT(role_id) as role_count'))
            ->groupBy('model_id')
            ->having('role_count', '>', 1)
            ->count();

        $this->table(
            ['Metric', 'Value', 'Status'],
            [
                ['Total Users',               $totalUsers,     ''],
                ['Users with department',      $usersWithDept,  $usersWithDept === $totalUsers ? '<fg=green>✓</>' : '<fg=yellow>⚠</>'],
                ['Users without department',   $usersNoDept,    $usersNoDept === 0 ? '<fg=green>✓</>' : '<fg=yellow>⚠</>'],
                ['Total Departments',          $totalDepts,     ''],
                ['Departments with manager',   $deptsWithMgr,   ''],
                ['Departments without manager',$deptsNoMgr,     $deptsNoMgr === 0 ? '<fg=green>✓</>' : '<fg=yellow>⚠</>'],
                ['Total Roles',               $totalRoles,     ''],
                ['Duplicate emails',          $dupeCount,      $dupeCount === 0 ? '<fg=green>✓</>' : '<fg=red>✗</>'],
                ['Invalid email domains',     $invalidDomain,  $invalidDomain === 0 ? '<fg=green>✓</>' : '<fg=red>✗</>'],
                ['Users with multiple roles', $multiRoleCount, $multiRoleCount > 0 ? '<fg=yellow>⚠</>' : '<fg=green>✓</>'],
            ]
        );

        $this->line('');
        $this->line('  <fg=gray>Read-only. No data was modified.</>');
        $this->line('  <fg=cyan>--------------------------------------------------</>');
        $this->line('');
    }
}
