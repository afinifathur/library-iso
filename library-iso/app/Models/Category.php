<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = ['slug','name','description'];

    public function documents()
    {
        return $this->hasMany(Document::class);
    }
}
