<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingListShareInfo extends Model
{
    use HasFactory;

    protected $fillable = [
        'shopping_list_id',
        'ref_shopping_list_id',
    ];

    public function shoppingList()
    {
        return $this->belongsTo(ShoppingList::class);
    }

    public function refShoppingList()
    {
        return $this->belongsTo(ShoppingList::class, 'ref_shopping_list_id');
    }
}
