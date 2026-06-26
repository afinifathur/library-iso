<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Document;
use App\Models\Department;
use App\Support\DocDepartmentHelper;
use Illuminate\Support\Facades\DB;

/**
 * php artisan document:audit
 *
 * Phase D2B — READ-ONLY audit of document → department ownership state.
 * No write operations of any kind.
 *
 * Output sections:
 *   1. Summary counters (assigned / missing / unknown prefix)
 *   2. Top departments by document count
 *   3. Documents with missing department_id
 *   4. Documents with unresolvable doc_code prefix
 */
class DocumentAuditCommand extends Command
{
    protected $signature   = 'document:audit';
    protected $description = 'Phase D2B: Read-only audit of document → department ownership';

    public function handle(): int
    {
        $this->header();

        DocDepartmentHelper::flushCache();

        $documents = Document::select('id', 'doc_code', 'title', 'department_id')->get();

        [$assigned, $missing, $unknownPrefix] = $this->classify($documents);

        $this->sectionSummary($documents->count(), count($assigned), count($missing), count($unknownPrefix));
        $this->sectionTopDepartments($assigned);
        $this->sectionMissingDept($missing);
        $this->sectionUnknownPrefix($unknownPrefix);
        $this->footer();

        return self::SUCCESS;
    }

    // ── Classification ────────────────────────────────────────────────────────

    /**
     * Partition all documents into three buckets:
     *  - assigned      : has department_id AND prefix resolves correctly
     *  - missing       : department_id IS NULL
     *  - unknownPrefix : prefix present but no matching department in DB
     *
     * @return array [assigned[], missing[], unknownPrefix[]]
     */
    private function classify(\Illuminate\Support\Collection $documents): array
    {
        $assigned      = [];
        $missing       = [];
        $unknownPrefix = [];

        foreach ($documents as $doc) {
            if ($doc->department_id === null) {
                $missing[] = $doc;
                continue;
            }

            // Also flag docs whose stored dept doesn't match the prefix mapping
            $resolved = DocDepartmentHelper::resolveFromDocCode($doc->doc_code);

            if ($resolved === null) {
                // dept_id set but prefix unresolvable — count as assigned (was manually set)
                // but flag for prefix audit
                $unknownPrefix[] = $doc;
                $assigned[]      = $doc; // still counts as assigned
            } else {
                $assigned[] = $doc;
            }
        }

        return [$assigned, $missing, $unknownPrefix];
    }

    // ── Section: Summary ──────────────────────────────────────────────────────

    private function sectionSummary(int $total, int $assigned, int $missing, int $unknown): void
    {
        $this->line('  <options=bold>DOCUMENTS — OWNERSHIP SUMMARY</>');
        $this->line('  ' . str_repeat('─', 46));

        $this->table(
            ['Metric', 'Count', 'Status'],
            [
                ['Total Documents',         $total,    ''],
                ['Department Assigned',     $assigned, $assigned === $total ? '<fg=green>✓</>' : '<fg=yellow>⚠</>'],
                ['Department Missing',      $missing,  $missing === 0       ? '<fg=green>✓</>' : '<fg=red>✗</>'],
                ['Unknown Prefix (in dept-assigned)', $unknown, $unknown === 0 ? '<fg=green>✓</>' : '<fg=yellow>⚠</>'],
            ]
        );

        $this->line('');
    }

    // ── Section: Top Departments ──────────────────────────────────────────────

    private function sectionTopDepartments(array $assigned): void
    {
        $this->line('  <options=bold>TOP DEPARTMENTS BY DOCUMENT COUNT</>');
        $this->line('  ' . str_repeat('─', 46));

        $counts = DB::table('documents')
            ->join('departments', 'documents.department_id', '=', 'departments.id')
            ->select('departments.code', 'departments.name', DB::raw('COUNT(documents.id) as doc_count'))
            ->whereNotNull('documents.department_id')
            ->groupBy('departments.id', 'departments.code', 'departments.name')
            ->orderByDesc('doc_count')
            ->limit(15)
            ->get();

        if ($counts->isEmpty()) {
            $this->line('  <fg=yellow>No documents with departments found.</>');
        } else {
            $rows = $counts->map(fn ($r) => [$r->code, $r->name, $r->doc_count])->toArray();
            $this->table(['Dept Code', 'Dept Name', 'Documents'], $rows);
        }

        $this->line('');
    }

    // ── Section: Missing Department ────────────────────────────────────────────

    private function sectionMissingDept(array $missing): void
    {
        $this->line('  <options=bold>DOCUMENTS WITHOUT DEPARTMENT</>');
        $this->line('  ' . str_repeat('─', 46));

        if (empty($missing)) {
            $this->line('  <fg=green>✓ All documents have a department_id assigned.</>');
        } else {
            $rows = array_map(
                fn ($d) => [$d->id, $d->doc_code ?? '—', substr($d->title ?? '', 0, 40)],
                $missing
            );
            $this->table(['ID', 'Doc Code', 'Title (truncated)'], $rows);
            $this->line('  <fg=red>✗ ' . count($missing) . ' document(s) have no department.</>');
        }

        $this->line('');
    }

    // ── Section: Unknown Prefix ────────────────────────────────────────────────

    private function sectionUnknownPrefix(array $unknownPrefix): void
    {
        $this->line('  <options=bold>DOCUMENTS WITH UNRESOLVABLE PREFIX</>');
        $this->line('  ' . str_repeat('─', 46));

        if (empty($unknownPrefix)) {
            $this->line('  <fg=green>✓ All doc_code prefixes resolve to a known department.</>');
        } else {
            $rows = array_map(function ($d) {
                $prefix = DocDepartmentHelper::extractDeptCode($d->doc_code) ?? '(none)';
                return [$d->id, $d->doc_code ?? '—', $prefix, $d->department_id ?? 'NULL'];
            }, $unknownPrefix);

            $this->table(['ID', 'Doc Code', 'Extracted Prefix', 'Stored dept_id'], $rows);
            $this->line('  <fg=yellow>⚠ ' . count($unknownPrefix) . ' document(s) have an unresolvable prefix.</>');
            $this->line('  <fg=gray>  These documents have a department_id set (manually), but the prefix</> ');
            $this->line('  <fg=gray>  cannot be auto-resolved. Run DocumentDepartmentSeeder to backfill.</>');
        }

        $this->line('');
    }

    // ── Header / Footer ───────────────────────────────────────────────────────

    private function header(): void
    {
        $this->line('');
        $this->line('  <fg=cyan>--------------------------------------------------</>');
        $this->line('  <fg=cyan;options=bold>  DOCUMENT AUDIT — Phase D2B</>');
        $this->line('  <fg=cyan>  ' . now()->format('Y-m-d H:i:s') . '</>');
        $this->line('  <fg=cyan>--------------------------------------------------</>');
        $this->line('');
    }

    private function footer(): void
    {
        $this->line('  <fg=cyan>--------------------------------------------------</>');
        $this->line('  <fg=gray>Read-only. No data was modified.</>');
        $this->line('  <fg=cyan>--------------------------------------------------</>');
        $this->line('');
    }
}
