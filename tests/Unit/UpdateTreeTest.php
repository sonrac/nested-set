<?php

namespace Tests\Unit\Nested;

use App\Models\ProductsProperties\Properties;
use App\Models\ProductsProperties\Units;
use Tests\Unit\Nested\models\BaseNestedTest;
use Tests\Unit\Nested\models\NestedModel;

/**
 * Class UpdateTreeTest
 * Update tree test
 *
 * @package Tests\Unit\Nested
 */
class UpdateTreeTest extends BaseNestedTest
{
    protected $_seeds = ['products'];

    /**
     * Test save root
     */
    public function testSaveRoot()
    {
        $this->__testSaveRoot();
    }

    /**
     * Test save array tree
     */
    public function testSaveArrayRoot()
    {
        $this->__testSaveRoot(false);
    }

    /**
     * Test save with children
     */
    public function testSaveWithChildren()
    {
        for ($i = 1; $i < 6; $i++) {
            $this->__testSaveRoot(true, $i);
        }
    }

    /**
     * Test update model with new children
     */
    public function testUpdateAndAddChildren() {
        for ($i = 1; $i < 6; $i++) {
            $this->__testSaveRoot(true, 1, true);
        }
    }

    /**
     * Test update model with new children as array tree
     */
    public function testUpdateAndAddChildrenArray() {
        for ($i = 1; $i < 6; $i++) {
            $this->__testSaveRoot(false, 1, true);
        }
    }

    /**
     * Create model
     *
     * @param bool $useORM Use ORM or use array (if false)
     *
     * @return array|NestedModel|\Illuminate\Database\Eloquent\Builder
     */
    protected function _createModel($useORM = true) {
        $_model = [
            'unit_id' => rand(1, Units::UNIT_KG),
            'property_id' => Properties::PROP_WEIGHT,
            'product_id' => rand(2, 1000000),
            'value' => rand(1, 500)
        ];

        if ($useORM) {
            $attr = $_model;
            $_model = new NestedModel();
            $_model->fill($attr);
            $this->assertTrue($_model->save());
        } else {
            NestedModel::saveTree($_model);
        }

        if ($useORM) {
            return NestedModel::where('id', $_model->id)->first();
        }

        $root = (array)\DB::table('products_properties_units')->orderBy('id', 'desc')->first();
        return NestedModel::rebuildRoot($root);
    }

    /**
     * Test save tree root
     *
     * @param bool     $useORM        Use ORM or use array (if false)
     * @param int|null $childrenCount Adding child nodes (if null save only root)
     */
    protected function __testSaveRoot($useORM = true, $childrenCount = null, $model = null)
    {
        \DB::table('products_properties_units')->truncate();
        if (!$model) {
            if ($useORM) {
                $model = new NestedModel();
            } else {
                $model = [];
            }
        } else {
            $model = $this->_createModel($useORM);
        }
        $model['unit_id'] = Units::UNIT_KG;
        $model['value'] = 20;
        $model['property_id'] = Properties::PROP_WEIGHT;
        $model['product_id'] = rand(2, 100000);

        for($i = 0; $i < $childrenCount; $i++) {
            $_model = [
                'unit_id' => rand(1, Units::UNIT_KG),
                'property_id' => Properties::PROP_WEIGHT,
                'product_id' => rand(2, 100000),
                'value' => rand(1, 500)
            ];
            if ($useORM) {
                $attr = $_model;
                $_model = new NestedModel();
                $_model->fill($attr);
            }

            $model = NestedModel::addTo($model, $_model, $model);
        }

        if ($useORM) {
            $this->assertTrue($model->save());
            $this->assertTrue($model->updateTree());
        } else {
            $this->assertTrue(NestedModel::saveTree($model));
        }

        foreach ($model['childNodes'] as $index => $childNode) {
            if ($useORM) {
                $childNode = NestedModel::where('id', $childNode->id)->first();
            } else {
                $childNode = (array) \DB::table('products_properties_units')->where('id', $index + 2)->first();
            }
            $this->assertEquals($childNode['parent_id'], $model['unique_id']);
        }

        $this->assertEquals(1, $model['left']);
        $this->assertEquals(($childrenCount + 1) * 2, $model['right']);
        $this->assertNotEmpty($model['tree_type']);
        $this->assertEquals($childrenCount + 1, \DB::table((new NestedModel())->getTable())->count('id'));
    }
}