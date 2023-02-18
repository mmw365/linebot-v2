<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingList extends Model
{
    use HasFactory;
    protected $fillable = [
        'userid',
        'number',
        'name',
        'is_active',
    ];

    public function shareInfo()
    {
        return $this->hasOne(ShoppingListShareInfo::class);
    }
    
    public function refShareInfos()
    {
        return $this->hasMany(ShoppingListShareInfo::class, 'ref_shopping_list_id');
    }
}
