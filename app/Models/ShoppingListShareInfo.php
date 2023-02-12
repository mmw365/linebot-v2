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
}
