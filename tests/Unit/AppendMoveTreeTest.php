<?php


namespace Tests\Unit\Nested;

use App\Common\Models\ORModel;
use App\Common\Models\TNestedSet;
use Tests\Unit\Nested\models\BaseNestedTest;
use Tests\Unit\Nested\models\NestedModel;

/**
 * Class AppendMoveTreeTest
 *
 * @package Tests\Unit
 */
class AppendMoveTreeTest extends BaseNestedTest
{
    protected $_seeds = ['Products', 'ProductsPropertiesUnitsNested'];

    /**
     * Test append child to node
     *
     * @param bool $useORM    Use ORM or build array tree
     * @param bool $moveNodes Move nodes
     */
    public function testAppendChildNewNode()
    {
        $this->_testAppend(true, false);
    }

    /**
     * Test nested append node
     *
     * @param bool $useORM    Use ORM or use array (if false)
     * @param bool $moveNodes Test move nodes
     */
    protected function _testAppend($useORM, $moveNodes)
    {
        if ($useORM) {
            $model = new NestedModel();
            $this->assertFalse($model->getIsBuild());
            $timerBegin = microtime(true);
            $model = $model->addChild(new NestedModel());
            $timerEnd = microtime(true);
            $this->assertTrue($model->getIsBuild());
            $timerBeginSecond = microtime(true);
            $model::rebuildTree($model);
            $timerEndSecond = microtime(true);

            /**
             * Test rebuild build tree
             */
            $this->assertTrue(($timerEndSecond - $timerBeginSecond) < ($timerEnd - $timerBegin));
        } else {
            $model = [];
            $model = NestedModel::addTo($model, []);
            $this->assertArrayHasKey('_isBuild', $model);
            $this->assertTrue($model['_isBuild']);
        }
        $this->assertTrue($useORM ? $model->getIsBuild() : $model['_isBuild']);

        $this->_checkNextNode($model, 1, 4, 1, null, null, true, $useORM);

        /** @var TNestedSet|ORModel|NestedModel $child */
        if ($useORM) {
            $child = $model->childNodes->first();
        } else {
            $child = &$model['childNodes'][0];
        }
        $this->_checkNextNode($child, 2, 3, 2, $model, $model, false, $useORM);

        if ($useORM) {
            $model->addChild(new NestedModel());
        } else {
            $model = NestedModel::addTo($model, []);
        }
        $this->_checkNextNode($model, 1, 6, 1, null, null, true, $useORM);
        $this->_checkNextNode($child, 2, 3, 2, $model, $model, false, $useORM);

        /** @var TNestedSet|ORModel|NestedModel $child */
        if ($useORM) {
            $child = $model->childNodes->get(1);
        } else {
            $child = &$model['childNodes'][1];
        }
        $this->_checkNextNode($child, 4, 5, 2, $model, $model, false, $useORM);

        /**
         * Add 3 depth level child
         */
        if ($useORM) {
            $child->addChild(new NestedModel());
        } else {
            $model = NestedModel::addTo($child, [], $model);
        }
        $this->assertTrue($useORM ? $model->getIsBuild() : $model['_isBuild']);
        $this->_checkNextNode($model, 1, 8, 1, null, null, true, $useORM);

        $this->_checkNextNode($child, 4, 7, 2, $model, $model, false, $useORM);

        if ($useORM) {
            $childNew = $child->childNodes->first();
        } else {
            $childNew = &$child['childNodes'][0];
        }
        $this->_checkNextNode($childNew, 5, 6, 3, $child, $model, false, $useORM);

        /**
         * Add 4 depth level child
         *
         * @var $childNew1 TNestedSet|array
         */
        if ($useORM) {
            $childNew->addChild(new NestedModel());
        } else {
            $model = NestedModel::addTo($childNew, [], $model);
        }

        $this->assertTrue($useORM ? $model->getIsBuild() : $model['_isBuild']);

        $this->_checkNextNode($model, 1, 10, 1, null, null, true, $useORM);

        $this->_checkNextNode($child, 4, 9, 2, $model, $model, false, $useORM);
        $this->_checkNextNode($childNew, 5, 8, 3, $child, $model, false, $useORM);

        if ($useORM) {
            $childNew4 = $childNew->childNodes->first();
        } else {
            $childNew4 = $childNew['childNodes'][0];
        }
        $this->_checkNextNode($childNew4, 6, 7, 4, $childNew, $model, false, $useORM);

        if ($moveNodes) {
            $this->_moveNodesTest($model, $useORM);
        }
    }

    /**
     * Check next node
     *
     * @param array|TNestedSet|ORModel $node   Current check node
     * @param int                      $left   Left Index value
     * @param int                      $right  Right index value
     * @param int                      $depth  Depth
     * @param array|TNestedSet|ORModel $parent Parent node
     * @param array|TNestedSet|ORModel $root   Root node
     * @param bool                     $isRoot Is root node
     * @param bool                     $useORM Use ORM or use array (if false)
     */
    protected function _checkNextNode($node, $left, $right, $depth, $parent, $root, $isRoot = false, $useORM = false)
    {
        $this->assertEquals($left, $node['left']);
        $this->assertEquals($right, $node['right']);
        $this->assertEquals($depth, $node['depth']);

        if ($useORM) {
            $this->assertEquals($parent, $node->getParentNode());
            $this->assertEquals($root, $node->getRootElement());
        } else {
            $roots = [$root, $parent];
            foreach ([$node['_root'], $node['_parentNode']] as $index => $item) {
                if (!is_array($item)) {
                    $this->assertNull($node['_root']);
                    continue;
                }
                foreach ($item as $name => $attr) {
                    if ($name != 'childNodes' && $name != '_isBuild') {
                        $this->assertEquals($roots[$index][$name], $attr);
                    }
                }
            }
        }

        $this->assertEquals($isRoot, $useORM ? $node->isRoot() : !isset($node['_parentNode']) || !(isset($node['_parentNode']) && !empty($node['_parentNode'])));
    }

    /**
     * Move nodes test
     *
     * @param array|TNestedSet|ORModel $tree   Tree object
     * @param bool                     $useORM Use ORM or use array (if false)
     */
    protected function _moveNodesTest($tree, $useORM)
    {
        /**
         * Test move node
         */
        if ($useORM) {
            $tree = $tree->childNodes[1]->childNodes[0]->moveTo($tree);
        } else {
            NestedModel::moveToArray($tree['childNodes'][1]['childNodes'][0], $tree, $tree);
        }

        $this->_checkNextNode($tree, 1, 10, 1, null, null, true, $useORM);

        $this->_checkNextNode($tree, 1, 10, 1, null, null, true, $useORM);
        $this->_checkNextNode($tree['childNodes'][0], 2, 3, 2, $tree, $tree, false, $useORM);
        $this->_checkNextNode($tree['childNodes'][1], 4, 5, 2, $tree, $tree, false, $useORM);
        $this->_checkNextNode($tree['childNodes'][2], 6, 9, 2, $tree, $tree, false, $useORM);
        $this->_checkNextNode($tree['childNodes'][2]['childNodes'][0], 7, 8, 3, $tree['childNodes'][2], $tree, false, $useORM);
    }

    /**
     * Test append node in array tree
     */
    public function testAppendChildNewArrayNode()
    {
        $this->_testAppend(false, false);
    }

    /**
     * Test move node ORM
     *
     * @param bool $useORM Use ORM as array
     */
    public function testMoveNodeORM()
    {
        $this->_testAppend(true, true);
    }

    /**
     * Test move node as array tree
     */
    public function testMoveNodeArray()
    {
        $this->_testAppend(false, true);
    }
}