<?php
namespace App\Imports;
use Maatwebsite\Excel\Concerns\ToCollection;
use Illuminate\Support\Collection;

class DocumentsImport implements ToCollection
{
    public function collection(Collection $rows)
    {
        // asumsi header di row 0
        foreach ($rows as $i => $row) {
            if ($i === 0) continue;
            // ubah index sesuai kolom filemu
            $data = [
                'doc_code' => $row[0],
                'title' => $row[1],
                'category_id' => $row[2],
                'department_id' => $row[3],
                // ...
            ];
            // panggil fungsi importRow yang mirip Tinker di atas
        }
    }
}
