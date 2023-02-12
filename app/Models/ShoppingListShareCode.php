<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class ShoppingListShareCode extends Model
{
    use HasFactory;

    protected $fillable = [
        'code',
        'shopping_list_id',
        'expires_at',
    ];
}
