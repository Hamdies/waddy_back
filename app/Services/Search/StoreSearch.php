<?php

namespace App\Services\Search;

class StoreSearch
{
    /** Store relations whose text also counts as a match, relation => column. */
    public const RELATIONSHIPS = [
        'translations' => 'value',
        'items.nutritions' => 'nutrition',
        'items.allergies' => 'allergy',
        'items.generic' => 'generic_name',
        'items.ecommerce_item_details.brand' => 'name',
        'items.pharmacy_item_details.common_condition' => 'name',
    ];
}
