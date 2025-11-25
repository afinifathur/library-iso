<?php
// app/Support/helpers.php
// Minimal helper fallbacks untuk menghindari fatal error jika file hilang.
// Tambahkan fungsi lain yang kamu perlukan di sini.

use Illuminate\Support\Str;

if (! function_exists('roleMatchesStage')) {
    /**
     * Simple mapping helper: apakah role tertentu berhubungan dengan approval stage.
     * Contoh penggunaan: roleMatchesStage('mr','MR') => true
     *
     * @param  string|array  $role   role name (or array of roles)
     * @param  string        $stage  approval stage code (MR, DIR, DONE, KABAG, etc.)
     * @return bool
     */
    function roleMatchesStage($role, string $stage): bool
    {
        // allow $role to be array or single
        $roles = is_array($role) ? $role : [$role];

        $stageMap = [
            'MR'   => ['mr', 'management_representative'],
            'DIR'  => ['director', 'dir'],
            'DONE' => ['admin', 'director', 'mr'],
            'KABAG'=> ['kabag', 'head', 'manager'],
            // fallback: treat lowercased role containing substring match as true
        ];

        $stage = strtoupper((string) $stage);

        foreach ($roles as $r) {
            $lower = Str::lower((string) $r);
            if (isset($stageMap[$stage])) {
                foreach ($stageMap[$stage] as $candidate) {
                    if ($lower === Str::lower($candidate) || Str::contains($lower, Str::lower($candidate))) {
                        return true;
                    }
                }
            }
        }

        return false;
    }
}

if (! function_exists('userHasAnyRole')) {
    /**
     * Checks current authenticated user has any of the provided roles.
     * Usage: userHasAnyRole(['mr','director'])
     * If no auth user, return false.
     *
     * @param  array|string  $roles
     * @return bool
     */
    function userHasAnyRole($roles): bool
    {
        $user = auth()->user();
        if (! $user) return false;

        // If model has method hasAnyRole (Spatie or similar), use it.
        if (method_exists($user, 'hasAnyRole')) {
            return $user->hasAnyRole($roles);
        }

        // try roles relation (array of role names)
        try {
            if (method_exists($user, 'roles')) {
                $userRoles = $user->roles()->pluck('name')->map(fn($s) => strtolower($s))->toArray();
                $need = is_array($roles) ? $roles : [$roles];
                foreach ($need as $r) {
                    if (in_array(strtolower($r), $userRoles, true)) {
                        return true;
                    }
                }
            }
        } catch (\Throwable $e) {
            // ignore and fallback to false
        }

        return false;
    }
}

if (! function_exists('ensure_dir')) {
    /**
     * Simple helper: ensure directory exists (used sometimes in file helpers).
     */
    function ensure_dir(string $path): bool
    {
        if (is_dir($path)) return true;
        return @mkdir($path, 0755, true);
    }
}
