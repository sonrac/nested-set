<?php
namespace Tests\Unit\Nested\models;

use App\Models\ProductsProperties\ProductsPropertiesUnits;
use App\Common\Models\TNestedSet;

/**
 * Class NestedModel
 *
 * @property string $tree_type
 * @property int    $left
 * @property int    $right
 * @property int    $depth
 * @property int    $parent_id
 * @property int    $root_tree
 *
 * @package Tests\Unit
 */
class NestedModel extends ProductsPropertiesUnits
{

    use TNestedSet;

    protected $fillable = [
        'parent_id', 'left', 'right', 'depth', 'id', 'tree_type', 'product_id', 'unit_id', 'property_id', 'value',
        'unique_id', 'root_number'
    ];

    public static function boot()
    {
        parent::boot();

        static::bootNTH();
    }
}