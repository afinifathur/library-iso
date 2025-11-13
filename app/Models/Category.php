<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class Category extends Model
{
    // pastikan kolom-kolom ini boleh di-mass-assign
    protected $fillable = [
        'slug',
        'code',
        'name',
        'description',
    ];

    // ekstra safety: kosongkan guarded supaya tidak ada atribut yang ter-block
    protected $guarded = [];

    /**
     * Otomatis set slug jika tidak diberikan saat create.
     */
    protected static function booted(): void
    {
        static::creating(function (Category $cat) {
            if (empty($cat->slug) && !empty($cat->name)) {
                $cat->slug = Str::slug($cat->name);
            }
        });
    }

    /**
     * Relasi ke dokumen.
     */
    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
