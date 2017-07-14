<?php

namespace Tests\Unit\Nested\models;

use App\Common\Models\ORModel;
use App\Models\ProductsProperties\ProductsPropertiesUnits;
use App\Common\Models\TNestedSet;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Tests\TestCase;
use Tests\Unit\Nested\models\NestedModel;

/**
 * Class BaseNestedTest
 *
 * @package Tests\Unit
 */
abstract class BaseNestedTest extends TestCase
{
    /**
     * Test build tree
     *
     * @param int              $countChildren      Count children
     * @param null             $additionalCallback Additional callback
     * @param null|NestedModel $model              Current tree model
     * @param bool             $useORM             Use ORM or use array (if false)
     * @param bool             $resetParent        Reset parent_id & id for exists records
     */
    protected function _testBuildTree($countChildren, $additionalCallback = null, $model = null, $useORM = true, $resetParent = false)
    {
        $model = $model ?? $this->getLevel2Tree($countChildren, $resetParent);
        if (!$useORM && is_object($model)) {
            $model = $model->toArray();

            if (isset($model['child_nodes'])) {
                $model['childNodes'] = $model['child_nodes'];
                unset($model['child_nodes']);
            }
        }

        $tree = NestedModel::rebuildTree($model, $useORM);

        if ($additionalCallback instanceof \Closure) {
            $additionalCallback($tree);
        }

        $this->_checkCustomCondition($tree, $this->_getCount($tree), $useORM);
    }

    /**
     * Build model with deep children
     *
     * @param int  $countChildren Count children
     * @param bool $resetParent   Reset parent_id & id columns for exists records test
     *
     * @return NestedModel
     */
    protected function getLevel2Tree($countChildren, $resetParent = false)
    {
        $model = $this->getModel(0, $resetParent);

        $children = [];
        for ($i = 0; $i < $countChildren; $i++) {
            $next = $this->getModel($i + 1, $resetParent);
            $children[] = $next;
        }

        $model->setRelation('childNodes', new Collection($children));

        return $model;
    }

    /**
     * Get model
     *
     * @param int  $offset      Offset for query model find
     * @param bool $resetParent Reset parent_id & id for exist records
     * @param bool $new         Unset exist attributes
     *
     * @return NestedModel|TNestedSet|\Illuminate\Database\Eloquent\Model|array
     */
    protected function getModel($offset = 0, $resetParent = false, $new = true)
    {
        /** @var NestedModel $model */
        $model = NestedModel::query()->offset($offset)->first();
        if ($new) {
            $model->id = null;
            $model->parent_id = null;
            $model->left = null;
            $model->depth = null;
            $model->right = null;
            $model->setUniqTreeFieldAttribute(null);
        } else if ($resetParent) {
            # Change ID for disable childNodes autoload in tests
            $model->id = rand(1, 1000) * microtime(true);
            $model->parent_id = time();
            $model->setUniqTreeFieldAttribute(null);
        }

        NestedModel::updateModelUnique($model);

        return $model;
    }

    /**
     * Check conditions
     *
     * @param TNestedSet|Model|ORModel|array $tree        Tree
     * @param int                            $countUpdate Count update records
     * @param bool                           $useORM      Use ORM build (or array tree)
     * @param int                            $type        Type (1 - insert, 2 - update, 3 - delete)
     */
    protected function _checkCustomCondition($tree, $countUpdate = 0, $useORM = false, $type = 1)
    {
        $this->_checkConditions($tree, $type == 2 ? $countUpdate : 0, $type == 1 ? $countUpdate : 0, $type == 3 ? $countUpdate : 0, $useORM);
    }

    /**
     * Check conditions
     *
     * @param TNestedSet|Model|ORModel|array $tree        Tree
     * @param int                            $updateCount Count update records
     * @param int                            $insertCount Count insert records
     * @param int                            $deleteCount Count delete records
     * @param bool                           $useORM      Use ORM or use array (if false)
     */
    protected function _checkConditions($tree, $updateCount, $insertCount, $deleteCount, $useORM)
    {
        $inserts = NestedModel::getInsertConditions($tree['tree_type'], $useORM);
        $updates = NestedModel::getUpdateConditions($tree['tree_type'], $useORM);
        $deletes = NestedModel::getDeleteConditions($tree['tree_type'], $useORM);
        $this->assertCount($insertCount, isset($inserts[0]) ? $inserts[0] : $inserts);
        $this->assertCount($updateCount, isset($updates[0]) ? $updates[0] : $updates);
        $this->assertCount($deleteCount, isset($deletes[0]) ? $deletes[0] : $deletes);
    }

    /**
     * Get count nodes in tree
     *
     * @return int
     */
    protected function _getCount($tree)
    {
        if (!isset($tree['childNodes'])) {
            return 0;
        }

        $count = 1;
        foreach ($tree['childNodes'] as $child) {
            $count += $this->_getCount($child);
        }

        return $count;
    }

    /**
     * Test build tree without orm
     *
     * @param int   $countRoots Count roots in tree
     * @param array $tree       Tree
     * @param int   $countTrees Count cached trees
     */
    protected function _testBuild($countRoots = 1, $tree = null, $countTrees = 1)
    {
        /** @var NestedModel $model */
        if (!$tree) {
            $model = NestedModel::query()->first();
            $tree = $model::buildTree($model->tree_type, false);
        }
        $this->assertCount($countRoots, $tree);
        $this->assertCount(11, $tree[1]);
        $this->assertCount(2, $tree[1]['childNodes']);
        $this->assertCount(11, $tree[1]['childNodes'][3]);
        $this->assertCount(3, $tree[1]['childNodes'][3]['childNodes']);
        $this->assertCount(11, $tree[1]['childNodes'][3]['childNodes'][4]);
        $this->assertCount(1, $tree[1]['childNodes'][3]['childNodes'][5]['childNodes']);

        $this->assertCount($countTrees, NestedModel::getAllCached());
    }

    /**
     * Test build ORM models
     *
     * @param int                      $countRoots Count roots in tree
     * @param Collection|NestedModel[] $tree       Tree
     * @param int                      $countTrees Count cached trees
     * @param bool                     $useORM     Use ORM or use array (if false)
     */
    protected function _testBuildORM($countRoots = 1, $tree = null, $countTrees = 2, $useORM = true)
    {
        /** @var NestedModel $model */
        if (!$tree) {
            $model = NestedModel::query()->first();
            $tree = $model::buildTree($model->tree_type, $useORM);
        }

        $this->_checkNextModel(2, 1, $tree, $countRoots, $useORM);
        $this->_checkNextModel(3, 3, $tree[1]['childNodes'], 2, $useORM);
        $this->_checkNextModel(0, 4, $tree[1]['childNodes'][3]['childNodes'], 3, $useORM);
        $this->_checkNextModel(0, 6, $tree[1]['childNodes'][3]['childNodes'][5]['childNodes'], 1, $useORM);

        $this->assertCount($countTrees, NestedModel::getAllCached());
    }

    /**
     * Check next model
     *
     * @param int        $countChildrens    Count childrens in collection element
     * @param int        $id                ID element of collection
     * @param Collection $collection        Test collection
     * @param int        $countInCollection Count elements in collection
     * @param bool       $useORM            Use ORM or use array (if false)
     */
    protected function _checkNextModel($countChildrens, $id, $collection, $countInCollection = 1, $useORM = true)
    {
        $this->assertCount($countInCollection, $collection);
        if ($useORM) {
            $this->assertInstanceOf(NestedModel::class, $collection[$id]);
        } else {
            $this->assertInternalType('array', $collection[$id]);
        }
        $this->assertEquals($id, $collection[$id]['unique_id']);
        $this->assertCount($countChildrens, $collection[$id]['childNodes']);
        if ($useORM) {
            if (!is_null($collection[$id]['parent_id']) && $collection[$id]['parent_id']) {
                $this->assertInstanceOf(NestedModel::class, $collection[$id]->getRootElement());
            }
        } else {
            if (!is_null($collection[$id]['parent_id']) && $collection[$id]['parent_id']) {
                $this->assertArrayHasKey('_root', $collection[$id]);
                $this->assertInternalType('array', $collection[$id]['_root']);
            }
        }
    }

    /**
     * Check nodes
     *
     * @param NestedModel|array $tree   Tree
     * @param int               $count  Count elements
     * @param bool              $useORM Use ORM or use array (if false)
     *
     * @return int
     */
    protected function _checkNodes($tree, $count, $useORM = true)
    {
        if ($useORM) {
            $this->assertInstanceOf(NestedModel::class, $tree);
        }
        $left = 1;
        $countNodes = 0;
        for ($i = 0; $i < $count; $i++) {
            $countNodes++;
            if (!$i) {
                $this->_checkNode($tree, 1, ($count + 1) * 2, 1, null, $useORM);
            } else {
                $this->_checkNode($tree['childNodes'][$i - 1], ++$left, ++$left, 2, $tree, $useORM);
            }
        }

        return $count + 1;
    }

    /**
     * Check tree node attributes
     *
     * @param NestedModel|TNestedSet|ORModel $model  Check model
     * @param int                            $left   Left index
     * @param int                            $right  Right index
     * @param int                            $depth  Depth index
     * @param NestedModel|null               $root   Root node
     * @param bool                           $useORM Use ORM or use array (if false)
     */
    protected function _checkNode($model, $left, $right, $depth, $root, $useORM = true)
    {
        $this->assertEquals($left, $model['left']);
        $this->assertEquals($right, $model['right']);
        $this->assertEquals($depth, $model['depth']);
        $this->assertEquals($root, $useORM ? $model->getRootElement() : (isset($model['_root']) ? $model['_root'] : null));
    }

    /**
     * Check nodes for level 3
     *
     * @param NestedModel $tree      Tree
     * @param int         $count     Count nodes
     * @param int         $mainCount Count main roots
     * @param bool        $useORM    Use ORM or use array (if false)
     *
     * @return int
     */
    protected function _checkNodes3($tree, $count, $mainCount = null, $useORM = true)
    {
        $left = 1;
        $countNodes = 0;
        for ($i = 0; $i <= $count; $i++) {
            $countNodes++;
            if (!$i) {
                $this->_checkNode($tree, 1, $mainCount ?? ($count + 1) * 2, 1, null, $useORM);
            } else {
                $children = $tree['childNodes'][$i - 1]['childNodes'];
                $count = $useORM ? $children->count() : count($children);
                $this->_checkNode($tree['childNodes'][$i - 1], ++$left, $left + $count * 2 + $count * 1, 2, $tree, $useORM);

                if ($count) {
                    foreach ($children as $item) {
                        $countNodes++;
                        $this->_checkNode($item, ++$left, ++$left, 3, $tree, $useORM);
                    }
                }

                $left += $count;
            }
        }

        return $countNodes;
    }

    /**
     * Hydrate tree models
     *
     * @param array $array Array model
     * @param bool $child Child nodes hydrate
     *
     * @return array|Collection|NestedModel
     */
    protected function _hydrateModels($array, $child = false)
    {
        $model = new NestedModel();
        if (!$child) {
            $model->fill($array);
            if (isset($array['childNodes']) && count($array['childNodes'])) {
                $model->setRelation('childNodes', $this->_hydrateModels($array['childNodes'], true));
            }
        } else {
            $models = new Collection();
            foreach ($array as $item) {
                $_model = $this->_hydrateModels($item);
                $models[] = $_model;
            }

            return $models;
        }

        return $model;
    }
}