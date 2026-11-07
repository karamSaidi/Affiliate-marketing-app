<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class CategoryTreeLock extends Model
{
    public $timestamps = false;

    protected $table = 'category_tree_lock';

    protected $guarded = [];
}
