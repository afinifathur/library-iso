<?php
// Quick script to inspect document department status
require __DIR__ . '/../vendor/autoload.php';
$app = require __DIR__ . '/../bootstrap/app.php';
$app->make(\Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Document;

$all = Document::select('id','doc_code','department_id')->get();
echo "Total documents: " . $all->count() . PHP_EOL;
echo "With dept_id:    " . $all->whereNotNull('department_id')->count() . PHP_EOL;
echo "Without dept_id: " . $all->whereNull('department_id')->count() . PHP_EOL;
echo PHP_EOL;
echo "--- Sample doc_codes (first 20) ---" . PHP_EOL;
$all->take(20)->each(fn($d) => print($d->doc_code . " | dept:" . ($d->department_id ?? 'NULL') . PHP_EOL));
echo PHP_EOL;

// Show unique prefixes
$prefixes = $all->map(function($d) {
    $parts = explode('.', $d->doc_code);
    if (count($parts) >= 2) return $parts[0] . '.' . $parts[1];
    return $parts[0];
})->unique()->sort()->values();
echo "--- Unique category.dept prefixes ---" . PHP_EOL;
foreach ($prefixes as $p) {
    echo "  " . $p . PHP_EOL;
}
