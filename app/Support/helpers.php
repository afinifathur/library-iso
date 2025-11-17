<?php

use Illuminate\Support\Str;

if (! function_exists('roleMatchesStage')) {
    /**
     * Flexible helper:
     * - roleMatchesStage($stage)
     * - roleMatchesStage($user, $stage)
     */
    function roleMatchesStage($arg1, $arg2 = null): bool
    {
        // Case 1: called as roleMatchesStage($stage)
        if ($arg2 === null) {
            $user  = auth()->user();
            $stage = $arg1;
        }
        // Case 2: roleMatchesStage($user, $stage)
        else {
            $user  = $arg1;
            $stage = $arg2;
        }

        if (! $user) return false;

        $s = Str::upper(trim((string) ($stage ?? '')));

        // Admin can act on everything
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['admin'])) {
            return true;
        }

        // MR can only act on MR stage
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['mr'])) {
            return $s === 'MR';
        }

        // Director can act on DIR / DIRECTOR
        if (method_exists($user, 'hasAnyRole') && $user->hasAnyRole(['director'])) {
            return in_array($s, ['DIR', 'DIRECTOR']);
        }

        // Fallback if roles stored differently
        try {
            $roles = method_exists($user, 'roles')
                ? $user->roles()->pluck('name')->map(fn($r)=>Str::upper($r))->toArray()
                : [];

            if (in_array('ADMIN', $roles)) return true;
            if (in_array('MR', $roles) && $s === 'MR') return true;
            if (in_array('DIRECTOR', $roles) && in_array($s, ['DIR','DIRECTOR'])) return true;

        } catch (\Throwable $e) {
            return false;
        }

        return false;
    }
}
