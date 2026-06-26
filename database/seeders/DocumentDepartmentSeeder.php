<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use App\Models\Document;
use App\Support\DocDepartmentHelper;

/**
 * DocumentDepartmentSeeder — Phase D2B
 *
 * Backfill / verify documents.department_id for ALL existing documents
 * using the single shared DocDepartmentHelper.
 *
 * Rules:
 *  - Uses doc_code prefix to resolve department (e.g. IK.BBT-FL.01 → BBT-FL dept)
 *  - Only updates rows where department_id is NULL, or where the resolved
 *    department differs from the stored one (self-healing).
 *  - Documents whose doc_code prefix has no matching department are flagged
 *    as warnings — they are NOT updated and will appear in `document:audit`.
 *  - Safe to run multiple times (idempotent).
 *  - No raw SQL, no duplicated mapping logic.
 */
class DocumentDepartmentSeeder extends Seeder
{
    public function run(): void
    {
        $this->command->info('');
        $this->command->info('Phase D2B — DocumentDepartmentSeeder');
        $this->command->info(str_repeat('─', 50));

        // Pre-flush helper cache to ensure fresh department data
        DocDepartmentHelper::flushCache();

        $documents = Document::select('id', 'doc_code', 'department_id')->get();
        $total     = $documents->count();

        $this->command->info("Total documents: {$total}");

        $assigned  = 0;
        $skipped   = 0;
        $unchanged = 0;
        $unknown   = 0;
        $warnings  = [];

        foreach ($documents as $doc) {
            $resolved = DocDepartmentHelper::resolveFromDocCode($doc->doc_code);

            if ($resolved === null) {
                // Cannot map this doc_code prefix to any department
                $deptCode = DocDepartmentHelper::extractDeptCode($doc->doc_code);
                $unknown++;
                $warnings[] = [
                    'id'       => $doc->id,
                    'doc_code' => $doc->doc_code,
                    'prefix'   => $deptCode ?? '(no prefix)',
                    'reason'   => $deptCode ? "No dept with code '{$deptCode}'" : 'doc_code has no dept segment',
                ];
                continue;
            }

            if ((int) $doc->department_id === $resolved->id) {
                // Already correctly assigned — nothing to do
                $unchanged++;
                continue;
            }

            // Update: either was NULL or pointed to wrong dept
            Document::where('id', $doc->id)->update(['department_id' => $resolved->id]);
            $assigned++;
        }

        $skipped = $unknown; // alias for clarity in report

        // ── Summary ───────────────────────────────────────────────────────────
        $this->command->info('');
        $this->command->info('Results:');
        $this->command->line("  ✓ Assigned / corrected : {$assigned}");
        $this->command->line("  – Already correct      : {$unchanged}");
        $this->command->line("  ⚠ Unknown prefix       : {$unknown}");

        if (! empty($warnings)) {
            $this->command->warn('');
            $this->command->warn('Documents with unresolvable department prefix:');
            foreach ($warnings as $w) {
                $this->command->line(
                    sprintf("  [%d] %-30s  prefix='%s'  reason=%s",
                        $w['id'], $w['doc_code'], $w['prefix'], $w['reason'])
                );
            }
        }

        $this->command->info('');
        $this->command->info('Phase D2B seeder complete.');
        $this->command->info(str_repeat('─', 50));
    }
}
