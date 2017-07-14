<?php

namespace Tests\Unit\Nested;

use App\Common\Models\TNestedSet;
use Tests\Unit\Nested\models\BaseNestedTest;
use Tests\Unit\Nested\models\NestedModel;

/**
 * Class RemoveNodeTest
 *
 * @package Tests\Unit\Nested
 */
class RemoveNodeTest extends BaseNestedTest
{
    protected $_seeds = ['Products', 'ProductsPropertiesUnitsNested'];

    /**
     * Test remove node on new tree as ORM
     */
    public function testRemoveNodeOnNew()
    {
        $this->_testRemoveNodeNew(true);
    }

    /**
     * Test remove node on new tree as array
     */
    public function testRemoveNodeOnNewArray()
    {
        $this->_testRemoveNodeNew(false);
    }

    /**
     * Test remove node on new tree as ORM
     */
    public function testRemoveNodeExist()
    {
        $this->_testRemoveNodeNew(true, true);
    }

    /**
     * Test remove node on new tree as array
     */
    public function testRemoveNodeExistOnArray()
    {
        $this->_testRemoveNodeNew(false, true);
    }

    /**
     * Test find node by attributes
     */
    public function testFindNodeByAttributes()
    {
        $this->_testFindNode(false);
        $this->_testFindNode(true);
    }

    /**
     * Test remove nodes without child as ORM
     */
    public function testRemoveNodesWithoutChildren() {
        $this->_testRemoveNodeWithoutChildren();
    }

    /**
     * Test remove nodes without child as array
     */
    public function testRemoveNodesWithoutChildrenArray() {
        $this->_testRemoveNodeWithoutChildren(false);
    }

    protected function _testRemoveNodeWithoutChildren($useORM = true) {
        $model = $this->_getModels($useORM);
        /** @var TNestedSet|array $model */
        $model = $model[11111];

        $count = $this->_getCount($model);
        $this->assertTrue(NestedModel::removeNodeArray($model['childNodes'][22222], $model['childNodes'][22222]['childNodes'][33333], false));

        $this->assertEquals($count - 1, $this->_getCount($model));
        $this->assertCount(1, $model['childNodes'][22222]['childNodes']);
        $this->assertCount(0, $model['childNodes'][22222]['childNodes'][0]['childNodes']);

    }

    /**
     * Get models
     *
     * @param bool $useORM Use ORM or build tree array
     *
     * @return array|TNestedSet|\Illuminate\Database\Eloquent\Model
     */
    protected function _getModels($useORM = true) {
        $models = [
            [
                'left'        => 1,
                'right'       => 8,
                'depth'       => 23,
                'unique_id'   => 11111,
                'root_number' => 42,
                'tree_type'   => 'test',
            ],
            [
                'left'        => 2,
                'right'       => 7,
                'depth'       => 24,
                'root_number' => 42,
                'unique_id'   => 22222,
                'tree_type'   => 'test',
                'parent_id'   => 11111,
            ],
            [
                'left'        => 3,
                'right'       => 6,
                'depth'       => 24,
                'root_number' => 42,
                'unique_id'   => 33333,
                'tree_type'   => 'test',
                'parent_id'   => 22222,
            ],
            [
                'left'        => 4,
                'right'       => 5,
                'depth'       => 24,
                'root_number' => 42,
                'unique_id'   => 44444,
                'parent_id'   => 33333,
                'tree_type'   => 'test',
            ]
        ];

        return NestedModel::buildTree($models, $useORM);
    }

    /**
     * Test find node
     *
     * @param bool $useORM
     */
    protected function _testFindNode($useORM = true)
    {
        $model = $this->_getModels($useORM);
        /** @var TNestedSet|array $model */
        $model = $model[11111];

        if ($useORM && is_array($model)) {
            $model = $this->_hydrateModels($model);
        }

        $this->_testOneFind($model, 1);
        $this->_testOneFind($model, 2, '.childNodes.22222', $model['childNodes'][22222]);
        $this->_testOneFind($model, 3, '.childNodes.22222.childNodes.33333', $model['childNodes'][22222]['childNodes'][33333]);
        $this->_testOneFind($model, 4, '.childNodes.22222.childNodes.33333.childNodes.44444', $model['childNodes'][22222]['childNodes'][33333]['childNodes'][44444]);
    }

    /**
     * Test find one node check
     *
     * @param array|NestedModel $model      Tree
     * @param string            $mapPattern Map pattern assert equals
     */
    protected function _testOneFind($model, $left, $mapPattern = '.', $findModel = null)
    {
        $this->assertFalse(NestedModel::findNodeInTree(['left' => 20], $model));
        $find = NestedModel::findNodeInTree(['left' => $left], $model);

        $findModel = $findModel ?? $model;

        $this->assertArrayHasKey('map', $find);
        $this->assertArrayHasKey('node', $find);
        $this->assertEquals($mapPattern, $find['map']);
        $this->assertEquals($findModel, $find['node']);
    }

    /**
     * Test remove node on new tree
     *
     * @param bool $useORM Use ORM or use array (if false)
     * @param bool $exist  Usage exists model or define new
     */
    protected function _testRemoveNodeNew($useORM = true, $exist = false)
    {
        $model = $this->getModel(1, true, !$exist);
        $child = $this->getModel(2, true, !$exist);
        $childNext = $this->getModel(3, true, !$exist);

        if ($useORM) {
            $model->addChild($child);
            $model->addChild($childNext);
        } else {
            $child = $child->toArray();
            $model = $model->toArray();
            $childNext = $childNext->toArray();
            $model = NestedModel::addTo($model, $child, $model);
            $model = NestedModel::addTo($model, $childNext, $model);
            $child = $model['childNodes'][0];
        }

        $this->assertEquals(3, $this->_getCount($model));


        $this->assertTrue(NestedModel::removeNodeArray($model, $child));

        $this->assertEquals(2, $this->_getCount($model));

        $index = $model['tree_type'];
        $deletes = NestedModel::getDeleteConditions($index, $useORM);
        $this->assertCount($exist ? 1 : 0, $deletes);
        $updates = NestedModel::getUpdateConditions($index, $useORM);
        if (isset($updates[0])) {
            $this->assertCount($exist ? 2 : 0, $updates[0]);
        }
        $inserts = NestedModel::getInsertConditions($index, $useORM);
        if (isset($inserts[0])) {
            $this->assertCount($exist ? 0 : 2, $inserts[0]);
        }
    }
}