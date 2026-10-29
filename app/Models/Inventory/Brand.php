<?php

namespace App\Models\Inventory;

use Database\Factories\Inventory\BrandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name', 'description'])]
class Brand extends Model
{
    /** @use HasFactory<BrandFactory> */
    use HasFactory;
}
