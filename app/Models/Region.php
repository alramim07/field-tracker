<?php

namespace App\Models;

use Database\Factories\RegionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Region extends Model
{
    /** @use HasFactory<RegionFactory> */
    use HasFactory;

    /** @var list<string> */
    protected $fillable = [
        'name',
        'code',
        'status',
    ];

    public function areas(): HasMany
    {
        return $this->hasMany(Area::class);
    }

    public function employees(): HasMany
    {
        return $this->hasMany(Employee::class);
    }
}
