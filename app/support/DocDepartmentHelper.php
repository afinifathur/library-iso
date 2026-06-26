<?php

namespace App\Support;

use App\Models\Department;

/**
 * DocDepartmentHelper — Phase D2B
 *
 * Single source of truth for mapping a doc_code prefix to a Department.
 *
 * Pattern: <category>.<dept_code>.<number>   e.g. IK.BBT-FL.01
 *
 * Logic:
 *   1. Extract the second segment (index 1) of the dot-delimited doc_code.
 *   2. Look up that segment in the departments.code column (case-insensitive).
 *   3. Return the Department model, or null when no match is found.
 *
 * No duplicate logic — only THIS class owns the mapping strategy.
 * The seeder, the artisan command, and future create/import logic all call here.
 */
class DocDepartmentHelper
{
    /** @var array<string, \App\Models\Department>|null In-process cache */
    private static ?array $map = null;

    // ──────────────────────────────────────────────────────────────────────────
    //  Public API
    // ──────────────────────────────────────────────────────────────────────────

    /**
     * Resolve a Department from a doc_code string.
     *
     * @param  string|null  $docCode  e.g. "IK.BBT-FL.01", "DP.MR.003", "SOP"
     * @return \App\Models\Department|null
     */
    public static function resolveFromDocCode(?string $docCode): ?Department
    {
        $deptCode = static::extractDeptCode($docCode);

        if ($deptCode === null) {
            return null;
        }

        return static::departmentMap()[$deptCode] ?? null;
    }

    /**
     * Return a department_id (int) from a doc_code, or null.
     *
     * @param  string|null  $docCode
     * @return int|null
     */
    public static function resolveIdFromDocCode(?string $docCode): ?int
    {
        return static::resolveFromDocCode($docCode)?->id;
    }

    /**
     * Extract the dept_code segment from a doc_code string.
     *
     * Returns null when the doc_code has fewer than 2 dot-separated segments
     * (e.g. bare "SOP" cannot be mapped to any department).
     *
     * @param  string|null  $docCode
     * @return string|null  e.g. "BBT-FL", "MR", "ACC&FIN"
     */
    public static function extractDeptCode(?string $docCode): ?string
    {
        if (! $docCode) {
            return null;
        }

        $seg = explode('.', $docCode);

        return count($seg) >= 2 ? strtoupper($seg[1]) : null;
    }

    /**
     * Lazy-load all departments into an uppercase [CODE => Department] map.
     * Cached for the lifetime of this process (safe for seeder batch runs).
     *
     * @return array<string, \App\Models\Department>
     */
    public static function departmentMap(): array
    {
        if (static::$map === null) {
            static::$map = Department::all()
                ->keyBy(fn (Department $d) => strtoupper($d->code))
                ->all();
        }

        return static::$map;
    }

    /**
     * Flush the in-process static cache.
     * Call this if departments are mutated mid-process.
     */
    public static function flushCache(): void
    {
        static::$map = null;
    }
}
