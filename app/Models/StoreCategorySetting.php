<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/** CT-1: one store's priority, order, visibility and schedule for a category. See StoreCategoryService. */
class StoreCategorySetting extends Model
{
    public const PRIORITIES = ['high', 'normal', 'low'];
    public const SCHEDULES = ['always', 'morning', 'evening', 'weekend'];

    protected $fillable = ['store_id', 'category_id', 'priority', 'sort', 'hidden', 'schedule'];

    protected $casts = [
        'store_id' => 'integer',
        'category_id' => 'integer',
        'sort' => 'integer',
        'hidden' => 'boolean',
    ];
}
