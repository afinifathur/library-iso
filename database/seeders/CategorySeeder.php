<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Str;
use App\Models\Category;

class CategorySeeder extends Seeder
{
    public function run()
    {
        $rows = [
            ['code'=>'IK','name'=>'Instruksi Kerja'],
            ['code'=>'UT','name'=>'Uraian Tugas'],
            ['code'=>'FR','name'=>'Formulir'],
            ['code'=>'PJM','name'=>'Prosedur Jaminan Mutu'],
            ['code'=>'MJM','name'=>'Manual Jaminan Mutu'],
            ['code'=>'DP','name'=>'Dokumen Pendukung'],
            ['code'=>'DE','name'=>'Dokumen Eksternal'],
        ];

        foreach ($rows as $r) {
            Category::updateOrCreate(
                ['code'=>$r['code']],
                ['name'=>$r['name'], 'slug'=> Str::slug($r['code'].'-'.$r['name'])]
            );
        }
    }
}
