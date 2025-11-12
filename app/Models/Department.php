<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;

class Department extends Model
{
    protected $fillable = ['code','name','description'];

    public function documents()
    {
        return $this->hasMany(Document::class);
        
    }
    public function manager()
{
    return $this->belongsTo(\App\Models\User::class, 'manager_id');
}

}
