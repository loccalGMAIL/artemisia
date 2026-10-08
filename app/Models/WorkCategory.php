<?php

namespace App\Models;

use Database\Factories\WorkCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['name'])]
class WorkCategory extends Model
{
    /** @use HasFactory<WorkCategoryFactory> */
    use HasFactory;

    public $timestamps = false;
}
