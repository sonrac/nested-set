<?php


namespace Tests\Unit\Nested;

use Illuminate\Database\Eloquent\Collection;
use Tests\Unit\Nested\models\BaseNestedTest;
use Tests\Unit\Nested\models\NestedModel;

/**
 * Class BuildORMTreeTest
 *
 * @package Tests\Unit
 */
class BuildORMTreeTest extends BaseNestedTest
{
    protected $_seeds = ['Products', 'ProductsPropertiesUnitsNested'];

    /**
     * @inheritdoc
     */
    public function testBuild($useORM = true)
    {
        $this->_testBuildORM(1, null, 1, $useORM);
    }

    /**
     * @inheritdoc
     */
    public function testBuildFullTreeORM($useORM = true)
    {
        $tree = NestedModel::buildTree(null, $useORM);
        $this->_testBuildORM(2, $tree, 2, $useORM);
    }

    /**
     * @inheritdoc
     */
    public function testResetCache($useORM = true)
    {
        $this->assertCount(2, NestedModel::getAllCached());
        NestedModel::resetCache(NestedModel::getCacheIndex('first', $useORM));
        $this->assertCount(1, NestedModel::getAllCached());
        NestedModel::resetCache(NestedModel::getCacheIndex('all', $useORM));
        $this->assertCount(0, NestedModel::getAllCached());
    }

    /**
     * @inheritdoc
     */
    public function testBuildTree($useORM = true, $resetParent = false)
    {
        $a = 123;
        for ($i = 1; $i < 6; $i++) { // Test creation tree with 15 child on depth 2
            $this->_testBuildTree($i, function ($tree) use ($i, $useORM) {
                return $this->_checkNodes($tree, $i, $useORM);
            }, null, $useORM, $resetParent);
        }
    }

    /**
     * @inheritdoc
     */
    public function testBuildTreeDepth3($cntChildren = 1, $useORM = true, $resetParent = false)
    {
        for ($i = 1; $i < 6; $i++) { // Test creation tree with 15 child on depth 3
            $model = $this->getLevel2Tree($i, $resetParent);
            if (!$useORM) {
                $model = $model->toArray();
                if (isset($model['child_nodes'])) {
                    $model['childNodes'] = $model['child_nodes'];
                    unset($model['child_nodes']);

                    foreach ($model['childNodes'] as &$child) {
                        if (isset($child['child_nodes'])) {
                            $child['childNodes'] = $child['child_nodes'];
                            unset($child['child_nodes']);
                        }
                    }
                }
            }
            $children = [];
            for ($j = 0; $j < $cntChildren; $j++) {
                $_model = $this->getModel($i, $resetParent) ?? $this->getModel(0, $resetParent);

                if ($_model) {
                    if (!$useORM) {
                        $_model = $_model->toArray();
                        if (isset($_model['child_nodes'])) {
                            $_model['childNodes'] = $_model['child_nodes'];
                            unset($_model['child_nodes']);

                            foreach ($_model['childNodes'] as &$child) {
                                if (isset($child['child_nodes'])) {
                                    $child['childNodes'] = $child['child_nodes'];
                                    unset($child['child_nodes']);
                                }
                            }
                        }
                    }

                    $children[] = $_model;
                }

                if ($useORM) {
                    $model['childNodes'][$j]->setRelation('childNodes', new Collection($children));
                } else {
                    $model['childNodes'][$j]['childNodes'] = $children;
                }
            }

            $this->_testBuildTree($i, function ($tree) use ($i, $cntChildren, $useORM) {
                $roots = (($i + 1) * 2 + ($cntChildren * 2));
                return $this->_checkNodes3($tree, $i + $cntChildren, $roots, $useORM);
            }, $model, $useORM);
        }
    }
}

