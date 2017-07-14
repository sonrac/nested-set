<?php

namespace App\Common\Models;

use Doctrine\DBAL\Driver\PDOStatement;
use Illuminate\Database\Eloquent\Collection;

/**
 * Trait TNestedSet
 * Nested Set tree data implementation for Laravel ORM or for as array using
 *
 * IMPORTANT!!! Adding to model boot call method bootNTH for auto update tree on ORM object changes
 *
 * VERY IMPORTANT!!! Don't use
 *           relation parentNode with DeepRelation & ORModel, and method \Illuminate\Eloquent\Database\Model::toArray
 *           because this will lead to a recurring looping. All nodes will be loading in protected attribute
 *           _parentNode Use it for loading parent node.
 *
 * @see     TNestedSet::bootNTH()
 *
 * @property \Illuminate\Database\Eloquent\Model|TNestedSet         $root          Get tree root
 * @property \Illuminate\Database\Eloquent\Model|TNestedSet         $prev          Previous element
 * @property \Illuminate\Database\Eloquent\Model|TNestedSet         $next          Next element
 * @property \Illuminate\Database\Eloquent\Model|TNestedSet         $neighborsTree Element neighbors
 * @property \Illuminate\Database\Eloquent\Model                    $parentNode    Parent node for tree. Don't use
 *           relation parentNode with DeepRelation & ORModel, and method \Illuminate\Eloquent\Database\Model::toArray
 *           because this will lead to a recurring looping. All nodes will be loading in protected attribute
 *           _parentNode Use it for loading parent node.
 * @see     TNestedSet::getParentNode()
 * @see     TNestedSet::setParentNode()
 * @property Collection|\Illuminate\Database\Eloquent\Model[]|mixed $childNodes    Children's for node
 *
 * @see     https://habrahabr.ru/post/46659/ for algoritm description
 *
 * @package App\Common\Models
 */
trait TNestedSet
{
    /**
     * Parent column name. Related with static::$uniqTreeFieldColumn
     *
     * @var string
     */
    protected static $parentColumn = 'parent_id';

    /**
     * Left column name
     *
     * @var string
     */
    protected static $leftColumn = 'left';

    /**
     * Depth column name
     *
     * @var string
     */
    protected static $depthColumn = 'depth';

    /**
     * Tree type column name
     *
     * @var string
     */
    protected static $treeTypeColumn = 'tree_type';

    /**
     * Right column name
     *
     * @var string
     */
    protected static $rightColumn = 'right';

    /**
     * If tree using many root elements, set additional param for root for rebuild only one tree
     *
     * @var string
     */
    protected static $rootColumn = 'root_number';

    /**
     * Cached trees
     *
     * @var array[]|TNestedSet[]|TNestedSet[TNestedSet][]|\Illuminate\Database\Eloquent\Model[\Illuminate\Database\Eloquent\Model][]
     */
    protected static $_cachedTrees = [];

    /**
     * Update tree condition on save or update
     *
     * @var null|array|string
     */
    protected static $_updateCondition = [0 => [], 1 => []];

    /**
     * Delete conditions
     *
     * @var array
     */
    protected static $_deleteConditions = [0 => [], 1 => []];

    /**
     * Insert tree condition on save or update
     *
     * @var null|array|string
     */
    protected static $_insertCondition = [0 => [], 1 => []];

    /**
     * Max child node for update statements
     *
     * @var int
     */
    protected static $maxUpdateModelCount = 150;

    /**
     * Instance cached for static function
     *
     * @var null|TNestedSet|\Illuminate\Database\Eloquent\Model
     */
    protected static $_cacheInstance = null;
    /**
     * Skip attributes in insert update
     * You will change for extend skip attributes list
     *
     * @var array
     */
    protected static $_skipTableAttributes = ['_isBuild', '_parentNode', 'childNodes', '_root'];

    /**
     * Unique model identifier
     *
     * @var string
     */
    protected static $uniqTreeFieldColumn = 'unique_id';

    /**
     * Parent node
     * IMPORTANT!!!
     * Don't use
     *           relation parentNode with DeepRelation & ORModel, and method
     *           \Illuminate\Eloquent\Database\Model::toArray because this will lead to a recurring looping. All nodes
     *           will be loading in protected attribute
     *           _parentNode Use it for loading parent node.
     *
     * @var TNestedSet
     */
    protected $_parentNode;
    /**
     * Is build flag. If false - build is not build or re-build needed
     *
     * @var bool
     */
    protected $_isBuild = false;
    /**
     * Main parent node. If using virtual object - sets empty object
     * When you make new node to root, trait will be creating new tree node with new virtualRootNode (if uses) and
     * updating all new created trees
     *
     * @var \Illuminate\Database\Eloquent\Model|array|null
     */
    protected $_root;
    /**
     * Unique model identified
     *
     * @var null|string|int
     */
    protected $_modelIdentified = null;
    /**
     * Unique model identified
     *
     * @var null|string
     */
    protected $_unique_id = null;
    /**
     * Current root number
     *
     * @var null|string|int
     */
    protected $root_number = null;

    /**
     * Boot nested set
     * When we called save, update or delete method, we re-calculate all tree: fool protection
     */
    protected static function bootNTH()
    {
        /** @var static TNestedSet|\Illuminate\Database\Eloquent\Model */

        static::registerModelEvent('booted', function ($model) {
            /**
             * @var TNestedSet $model
             */
            if (!$model->{$model::$treeTypeColumn}) {
                static::setTreeTypeForModel($model);
            }

            $model->setIsBuild(false);
        });

        /**
         * When we called save, update or delete method, we re-calculate all tree: fool protection
         */

        /**
         * Create tree build
         */
        static::created(function ($model) {
            /** @var $model TNestedSet|\Illuminate\Database\Eloquent\Model */
            $model->syncOriginal();
            static::setTreeTypeForModel($model);
            static::updateModelUnique($model);
            $model->updateTree();
        });

        /**
         * Update tree build
         */
        static::updated(function ($model) {
            /** @var $model TNestedSet|\Illuminate\Database\Eloquent\Model */
            /** @var $this TNestedSet|\Illuminate\Database\Eloquent\Model */
            if (!$dirty = $model->getDirty()) {
                return;
            }

            if ($model->isDirty(static::getNTHColumnsList())) {
                $model->updateTree();
            }
        });

        /**
         * Build tree on update
         */
        static::deleted(function ($model) {
            /** @var $model TNestedSet|\Illuminate\Database\Eloquent\Model */
            $model->removeNode();
            $model->updateTree();
        });
    }

    /**
     * Check is root node
     *
     * @return bool
     */
    public function isRoot()
    {
        return empty($this->{static::$parentColumn}) && !$this->_parentNode;
    }

    /**
     *
     *
     * Relations
     *
     *
     */
    /**
     * Get Neighbors elements
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne|\Illuminate\Database\Eloquent\Builder
     */
    public function neighborsTree()
    {
        return $this->hasMany(static::class, function ($q) {
            /** @var $query \Illuminate\Database\Eloquent\Builder */
            return $query->where(static::$depthColumn, '=', $this->{static::$depthColumn})
                ->where(static::$treeTypeColumn, '=', $this->{static::$treeTypeColumn});
        });
    }

    /**
     * Get child nodes
     *
     * @return []|TNestedSet[]|\Illuminate\Database\Eloquent\Model[]
     */
    public function childNodes()
    {
        /** @var $this TNestedSet */
        return $this->hasMany(static::class, static::$parentColumn, static::$uniqTreeFieldColumn);
    }

    /**
     * Get previous element
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne|\Illuminate\Database\Eloquent\Builder
     */
    public function prevTreeElement()
    {
        return $this->hasOne(static::class, function ($query) {
            /** @var $query \Illuminate\Database\Eloquent\Builder */
            return $query->where(static::$leftColumn, '=', $this->{static::$leftColumn} - 1)
                ->where(static::$treeTypeColumn, '=', $this->{static::$treeTypeColumn});
        });
    }

    /**
     * Get next element
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne|\Illuminate\Database\Eloquent\Builder
     */
    public function nextTreeElement($depth = null)
    {
        return $this->hasOne(static::class, function ($query) use ($depth) {
            /** @var $query \Illuminate\Database\Eloquent\Builder */
            return $query->where(static::$rightColumn, '=', $this->{static::$rightColumn} + 1)
                ->where(static::$treeTypeColumn, '=', $this->{static::$treeTypeColumn});
        });
    }

    /**
     * Get parent node
     * IMPORTANT!!!
     * Don't use
     *           relation parentNode with DeepRelation & ORModel, and method
     *           \Illuminate\Eloquent\Database\Model::toArray because this will lead to a recurring looping. All nodes
     *           will be loading in protected attribute
     *           _parentNode Use it for loading parent node.
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne|\Illuminate\Database\Eloquent\Builder
     */
    public function parentNode()
    {
        return $this->hasOne(static::class, 'parent_id', 'id');
    }

    /**
     * Get all parents
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasMany|\Illuminate\Database\Eloquent\Builder
     */
    public function parentNodes()
    {
        return $this->hasOne(static::class, function ($query) {
            /** @var $query \Illuminate\Database\Eloquent\Builder */
            return $query->where(static::$leftColumn, '<', $this->{static::$leftColumn})
                ->where(static::$rightColumn, '>', $this->{static::$rightColumn})
                ->where(static::$treeTypeColumn, '=', $this->{static::$treeTypeColumn});
        });
    }

    /**
     * Get lives elements
     *
     * @return \Illuminate\Database\Eloquent\Relations\HasOne|\Illuminate\Database\Eloquent\Builder
     */
    public function treeLeaves()
    {
        return $this->hasOne(static::class, function ($q) {
            /** @var $query \Illuminate\Database\Eloquent\Builder */
            return $query->where(static::$rightColumn, '>', $this->{static::$rightColumn})
                ->where(static::$leftColumn, '<', $this->{static::$leftColumn} + 1)
                ->where(static::$treeTypeColumn, '=', $this->{static::$treeTypeColumn});
        });
    }
    /**
     *
     *
     * End relations
     *
     *
     */

    /**
     *
     *
     * Add nodes methods
     *
     *
     */
    /**
     * Add children
     *
     * @param array|TNestedSet|\Illuminate\Database\Eloquent\Model $node
     * @param array|Collection                                     $children
     */
    public static function addArrayChildren($node, $children)
    {
        foreach ($children as $child) {
            static::addTo($node, $child);
        }
    }

    /**
     * Add children
     *
     * @param array|Collection $children
     */
    public function addChildren($children)
    {
        static::addArrayChildren($this, $children);
    }

    /**
     * Make root node
     */
    public function makeRoot()
    {
        $uniqID = $this->{static::$treeTypeColumn} ?? uniqid();
        if (!isset(self::$_cachedTrees[$uniqID])) {
            self::$_cachedTrees[$uniqID] = [];
        }

        $this->{static::$parentColumn} = null;

        $this->_root = $this;
    }
    /**
     *
     * End add nodes methods
     *
     */

    /**
     *
     *
     * Moving nodes method
     *
     *
     */
    /**
     * Append node to new parent with array or ORM
     *
     * @param array|TNestedSet $node      Moving node
     * @param array|TNestedSet $newParent New root node
     * @param array|TNestedSet $root      Parent root element
     *
     * @throws \Exception
     *
     * @return \Illuminate\Database\Eloquent\Model|TNestedSet
     */
    public static function moveToArray(&$node, &$newParent, &$root = null)
    {
        if (is_object($node)) {
            return $node->moveTo($newParent);
        }

        if (!isset($node['_parentNode']) && ($node[static::$treeTypeColumn] == $newParent[static::$treeTypeColumn]) && $node[static::$rootColumn] == $newParent[static::$rootColumn]) {
            throw new \Exception('It is parent node');
        }

        $node['_parentNode']['childNodes'] = array_filter($node['_parentNode']['childNodes'], function ($arr) use ($node) {
            return $arr[static::$uniqTreeFieldColumn] != $node[static::$uniqTreeFieldColumn];
        });

        return static::addTo($newParent, $node, $root);
    }

    /**
     * Mode tree node
     *
     * @param TNestedSet|\Illuminate\Database\Eloquent\Model $rootNode New root node
     *
     * @throws \Exception
     *
     * @return \Illuminate\Database\Eloquent\Model|TNestedSet
     */
    public function moveTo($rootNode)
    {
        $this->_parentNode->setRelation('childNodes', $this->_parentNode->childNodes ? $this->_parentNode->childNodes->diff(new Collection([$rootNode])) : []);
        return $rootNode->addChild($this);
    }

    /**
     * Add children
     *
     * @param array|TNestedSet|\Illuminate\Database\Eloquent\Model $child
     *
     * @return array|TNestedSet|\Illuminate\Database\Eloquent\Model
     */
    public function addChild($child)
    {
        /** @var $this array|\Illuminate\Database\Eloquent\Model */
        if (!$this->relationLoaded('childNodes')) {
            $this->setRelation('childNodes', new Collection());
        }

        $root = $this->getRootElement() ?? $this;
        $root->setIsBuild(false);
        static::updateModelUnique($root);
        static::updateModelUnique($child);

        $child->setRootElement($root);
        $child->setParentNode($this);

        $index = static::getNodeIndex($child, $this->getKeyName());

        if ($index) {
            $this->childNodes[$index] = $child;
        } else {
            $this->childNodes->push($child);
        }

        return static::rebuildRoot($root);
    }

    /**
     * Add one child to node. Working with array too
     *
     * @param array|TNestedSet|\Illuminate\Database\Eloquent\Model      $pNode    Parent node
     * @param array|TNestedSet|\Illuminate\Database\Eloquent\Model      $children Children node
     * @param array|TNestedSet|null|\Illuminate\Database\Eloquent\Model $pRoot    Root element. If are you using array
     *                                                                            data,
     *                                                                            must be define for correction complete
     *                                                                            build tree or not give otherwise
     *
     * @return array|TNestedSet|\Illuminate\Database\Eloquent\Model
     */
    public static function addTo(&$pNode, $children, &$pRoot = null)
    {
        $useORM = is_object($pNode);
        $pNode = static::checkExistsNeeded($pNode);
        $children = static::checkExistsNeeded($children);

        if ($useORM) {
            return $pNode->addChild($children);
        }

        if (!isset($pNode['childNodes'])) {
            $pNode['childNodes'] = [];
        }

        if (!$pRoot) {
            if (isset($pNode['_root'])) {
                $pRoot = &$pNode['_root'];
            } else {
                $pRoot = &$pNode;
            };
        }

        if ($useORM) {
            $pRoot->setIsBuild(false);
        } else {
            $pRoot['_isBuild'] = false;
        }

        static::updateModelUnique($pRoot);
        static::updateModelUnique($children);
        /** @var $children TNestedSet */
        $children[static::$treeTypeColumn] = $pNode[static::$treeTypeColumn];
        $index = static::getNodeIndex($children, static::getModelInstance()->getKeyName());
        if ($useORM) {
            $children->setRootElement($pRoot);
        } else {
            $children['_root'] = $pRoot;
        }

        if ($index) {
            $pNode['childNodes'][$index] = &$children;
        } else {
            $pNode['childNodes'][] = &$children;
        }

        return static::rebuildRoot($pRoot);
    }
    /**
     *
     *
     * End moving nodes method
     *
     *
     */

    /**
     *
     *
     * Remove nodes methods
     *
     *
     */
    /**
     * Remove node with children
     *
     * @param bool $withChild Remove node with child if true, or remove only root & child nodes drop to remove node
     *                        level
     *
     * @return bool
     */
    public function removeNode($withChild = true)
    {
        $root = $this->getParentNode() ?? $this;
        return static::removeNodeArray($root, $this, $withChild);
    }

    /**
     * Check is changed attributes
     *
     * @param array $dirty Dirty attributes list
     *
     * @return bool
     */
    protected static function isChanged($dirty)
    {
        $attributes = static::getNTHColumnsList();
        return count(array_diff($attributes, array_flip(array_diff($dirty, array_flip($attributes))))) !== count($attributes);
    }

    /**
     * Find node in tree with attributes values and return first find node
     * Return false if node not find or array in format
     * <code>
     *    [
     *         'map'  => string
     *         'node' => array|TNestedSet|\Illuminate\Database\Eloquent\Model
     *    ]
     * </code>
     * Map using for saving path from current root to node with child detected. If find node is current node return '.'.
     *
     * @example return:
     *          '.childNodes.1.childNodes.2' From root node destination in $tree[childNodes][1][childNodes][2]
     * It using for array tree. Array pointers does not worked correctly
     *
     *
     * @param array                                                $attributes Condition attributes for find
     * @param array|TNestedSet|\Illuminate\Database\Eloquent\Model $tree       Tree
     * @param string                                               $map        Map path in tree
     *
     * @return array|bool
     */
    public static function findNodeInTree(array $attributes, &$tree, $map = '.')
    {
        $modelAttributes = is_object($tree) ? $tree->getAttributes() : $tree;
        $findAttributes = array_intersect_key($modelAttributes, $attributes);
        if ($findAttributes == $attributes) {
            return [
                'map'  => $map,
                'node' => &$tree,
            ];
        }

        if (is_array($tree) && (!isset($tree['childNodes']) || (isset($tree['childNodes']) && !count($tree['childNodes'])))) {
            return false;
        }

        foreach ($tree['childNodes'] as $index => &$childNode) {
            $_map = "childNodes.{$index}";
            if ($node = static::findNodeInTree($attributes, $childNode, $_map)) {
                return [
                    'map'  => preg_replace('/\.\./im', '.', "{$map}" . ($node['map'] ? '.' . $node['map'] : '')),
                    'node' => $node['node'],
                ];
            }
        }

        return false;
    }

    /**
     * Remove node by id
     *
     * @param int  $id        Node primary key
     * @param bool $withChild Remove with child. If true - all child will be removed, if false, all nodes will be moved
     *
     * @return bool
     */
    public function removeById(&$id, $withChild = true)
    {
        $nodeInfo = static::findNodeInTree([static::getModelInstance()->getKeyName() => $id], $pNode);

        if (!$nodeInfo) {
            return false;
        }

        /** @var TNestedSet $node */
        $node = $nodeInfo['node'];

        $node->removeNode($withChild);
    }

    /**
     * Remove node by attributes
     *
     * @param TNestedSet|\Illuminate\Database\Eloquent\Model $pNode      Parent node
     * @param array                                          $attributes Attributes
     * @param bool                                           $withChild  Remove node with child if true, or remove only
     *                                                                   root & child nodes drop to remove node level
     *
     * @return bool
     */
    public function removeByAttributes(&$pNode, array $attributes, $withChild = true)
    {
        $nodeInfo = static::findNodeInTree($attributes, $pNode);

        if (!$nodeInfo) {
            return false;
        }

        /** @var TNestedSet $node */
        $node = $nodeInfo['node'];

        $node->removeNode($withChild);
    }

    /**
     * Remove node by primary key from array tree
     *
     * @param array|TNestedSet|\Illuminate\Database\Eloquent\Model $pNode     Tree root
     * @param int                                                  $id        Primary key value
     * @param bool                                                 $withChild Remove node with child if true, or remove
     *                                                                        only root & child nodes drop to remove
     *                                                                        node level
     *
     * @return bool
     */
    public static function removeByIdArray(&$pNode, &$id, $withChild = true)
    {
        return static::removeByAttributesArray($pNode, [static::getModelInstance()->getKeyName() => $id], $withChild);
    }

    /**
     * @param array|TNestedSet|\Illuminate\Database\Eloquent\Model $pNode      Parent node
     * @param array                                                $attributes Attributes find
     * @param bool                                                 $withChild  Remove node with child if true, or
     *                                                                         remove only root & child nodes drop to
     *                                                                         remove node level
     *
     * @return bool
     */
    public static function removeByAttributesArray(&$pNode, array $attributes, $withChild = true)
    {
        $nodeInfo = static::findNodeInTree($attributes, $pNode);

        if (!$nodeInfo) {
            return false;
        }

        return static::removeNodeArray($pNode, $nodeInfo['node'], $withChild);
    }

    /**
     * Remove node with children
     *
     * @param TNestedSet|\Illuminate\Database\Eloquent\Model $pNode      Current node
     * @param TNestedSet|\Illuminate\Database\Eloquent\Model $removeNode Remove node
     * @param bool                                           $withChild  Remove node with child if true, or remove only
     *                                                                   root & child nodes drop to remove node level
     *
     * @return bool
     */
    public static function removeNodeArray(&$pNode, $removeNode, $withChild = true)
    {
        $useORM = !is_array($pNode);

        if (!$useORM && !isset($pNode['childNodes'])) {
            $pNode['childNodes'] = [];
        }

        if (!count($pNode['childNodes'])) {
            return false;
        }

        $key = static::getModelInstance()->getKeyName();
        if (($useORM && $pNode->{$key}) || (!$useORM && isset($pNode[$key]) && $pNode[$key])) {
            static::addDeleteCondition($useORM ? $pNode->getAttributes() : $pNode, $useORM);
        }

        $count = count($pNode['childNodes']);

        $filter = function ($first) use ($removeNode) {
            return $first[static::$uniqTreeFieldColumn] != $removeNode[static::$uniqTreeFieldColumn];
        };

        /** @var array $items */
        if ($useORM) {
            $pNode->childNodes = $pNode->childNodes->filter($filter);
        } else {
            $pNode['childNodes'] = array_filter($pNode['childNodes'], $filter);
        }

        $countItems = count($pNode['childNodes']);
        $result = $countItems < $count;

        $root = null;
        if ($useORM) {
            $root = $pNode->isRoot() ? $pNode : $pNode->getRootElement();
        } else {
            if (isset($pNode['_root'])) {
                $root = &$pNode['_root'];
            } else {
                $root = $pNode;
            }
        }

        if (count($removeNode['childNodes'])) {

            if (isset($removeNode['childNodes']) && count($removeNode['childNodes'])) {
                if ($withChild) {
                    foreach ($removeNode['childNodes'] as $index => &$childNode) {
                        if (isset($childNode['childNodes']) && count($childNode['childNodes'])) {
                            $result = static::removeNodeArray($childNode, $removeNode, $withChild) || $result;
                        }
                    }
                } else {
                    if (isset($removeNode['childNodes']) && count($removeNode['childNodes'])) {
                        /** @var TNestedSet|array $pParent */
                        $pParent = null;
                        if ($useORM) {
                            $pParent = $removeNode->getParentNode();
                            if (!$pParent) {
                                $pParent = &$pNode;
                            }
                        } else if (isset($removeNode['_parentNode'])) {
                            $pParent = &$removeNode['_parentNode'];
                        }

                        if (!$pParent) {
                            $pParent = $pNode;
                        }

                        if (!$useORM && !isset($pParent['childNodes'])) {
                            $pParent['childNodes'] = [];
                        }

                        foreach ($removeNode['childNodes'] as &$pChildNode) {
                            /** @var TNestedSet|array $pChildNode */
                            if ($useORM) {
                                $pChildNode->setParentNode($pParent);
                            } else {
                                $pChildNode['_parentNode'] = &$pParent;
                            }
                        }

                        foreach ($removeNode['childNodes'] as $index => &$pChildNode) {
                            $root = static::moveToArray($pChildNode, $pParent, $root);
                        }
                    }
                }
            }
        }

        static::removeFromAllCondition($useORM ? $removeNode->getAttributes() : $removeNode, $useORM);

        return $result;
    }

    /**
     * Drop remove node from update & insert condition
     *
     * @param array $attributes Node attributes list
     * @param bool  $useORM     Use ORM or use array (if false)
     */
    protected static function removeFromAllCondition($attributes, $useORM)
    {
        if (!($data = static::getConditionMetaData($attributes))) {
            return;
        }

        list($index, $pk) = $data;
        static::diffCondition($attributes, $index, $useORM);
        static::diffCondition($attributes, $index, $useORM, 2);
    }

    /**
     * Remove node from model update list
     *
     * @param array  $attributes Attributes list
     * @param string $index      Tree type index
     * @param bool   $useORM     Use ORM or use array (if false)
     * @param int    $type       1 - update, 2 - insert. Trait does not may having constants definition
     */
    protected static function diffCondition($attributes, $index, $useORM, $type = 1)
    {
        $type = $type == 1 ? '_updateCondition' : '_insertCondition';
        if (isset(static::$$type[(int)$useORM][$index])) {
            foreach (static::$$type[(int)$useORM][$index] as &$item) {
                $item = array_filter($item, function ($first) use ($attributes) {
                    return $first[static::$uniqTreeFieldColumn] != $attributes[static::$uniqTreeFieldColumn];
                });

                if (!count($item)) {
                    $item = [];
                }
            }
        }
    }

    /**
     * Unset queries for index (from tree_type model attribute)
     *
     * @param string $index  Tree type index
     * @param bool   $useORM Use ORM or use array (if false)
     */
    protected static function removeTreeFromQuery($index, $useORM = false)
    {
        foreach ([$index . '1', $index . '0'] as $_index) {
            if (isset(static::$_updateCondition[(int)$useORM][$_index])) {
                unset(static::$_updateCondition[(int)$useORM][$_index]);
            }
            if (isset(static::$_insertCondition[(int)$useORM][$_index])) {
                unset(static::$_insertCondition[(int)$useORM][$_index]);
            }
            if (isset(static::$_deleteConditions[(int)$useORM][$_index])) {
                unset(static::$_deleteConditions[(int)$useORM][$_index]);
            }
        }
    }
    /**
     *
     *
     * End remove nodes methods
     *
     *
     */

    /**
     *
     *
     * Build tree methods
     *
     *
     */
    /**
     * Rebuild indexes for tree root
     *
     * @param array|\Illuminate\Database\Eloquent\Model $tree
     *
     * @return array|TNestedSet|\Illuminate\Database\Eloquent\Model
     */
    public static function rebuildRoot(&$tree)
    {
        $useORM = !is_array($tree);

        return static::rebuildTree($tree, $useORM);
    }

    /**
     * @param string $index  Tree type
     * @param bool   $useORM Use ORM or use array (if false)
     */
    protected static function initBuildConditions($index, $useORM)
    {
        foreach (['_updateCondition', '_insertCondition', '_deleteConditions'] as $item) {
            if (!is_array(static::$$item)) {
                static::$$item = [
                    0 => [], 1 => [],
                ];
            }

            foreach ([0, 1] as $_item) {
                if (!isset(static::$$item[$_item])) {
                    static::$$item[$_item] = [];
                }
            }

            static::$$item[(int)$useORM][$index] = $item == '_deleteConditions' ? [] : [[]];
        }
    }

    /**
     * Rebuild tree indexes
     * We don't divide this method, because array link is lost in additional methods.
     *
     * @param string|array|Collection|TNestedSet|\Illuminate\Database\Eloquent\Model $pTree  Source tree or tree name
     *                                                                                       for using in cache
     * @param bool                                                                   $useORM Use ORM or use array (if
     *                                                                                       false)
     *
     * @return array|TNestedSet|\Illuminate\Database\Eloquent\Model
     */
    public static function rebuildTree(&$pTree, $useORM = true)
    {
        if ((is_object($pTree) && $pTree->getIsBuild()) || (is_array($pTree) && isset($pTree['_isBuild']) && $pTree['_isBuild'])) {
            return $pTree;
        }

        $pTree = static::extendAttributes($pTree);
        $pTree[static::$depthColumn] = 1;
        $pTree[static::$leftColumn] = 1;
        $indexValue = 1;
        if (!isset($pTree[static::$rootColumn]) || (isset($pTree[static::$rootColumn]) && !$pTree[static::$rootColumn])) {
            $pTree[static::$rootColumn] = (int)uniqid();
        }
        $rootNumber = $pTree[static::$rootColumn];
        static::addUpdateCondition($pTree, $useORM);

        $treeType = $pTree[static::$treeTypeColumn];
        static::initBuildConditions($treeType, $useORM);

        $childrenIndexes = [];

        $pCurrentRoot = &$pTree;
        $depth = 1;

        /**
         * I'm not using recursive calls for memory saving during re-build tree
         * Instead recursive calls I'm using pointer and stack children collection indexes & previously
         * parent roots
         */
        do {
            if (is_null($pCurrentRoot)) {
                break;
            }

            if (!count($pCurrentRoot['childNodes'])) {
                /**
                 * We need come back at level up to previous root element
                 * If current root will be root node, breaking
                 */
                $indexValue++;
                $pCurrentRoot[static::$rightColumn] = $indexValue;
                $pCurrentRoot[static::$depthColumn] = $depth;
                // Adding attributes for update tree
                static::addUpdateCondition($pCurrentRoot, $useORM);

                unset($pNewRoot);
                if ($useORM) {
                    $pNewRoot = $pCurrentRoot->getParentNode();
                } else {
                    if (isset($pCurrentRoot['_parentNode'])) {
                        $pNewRoot = &$pCurrentRoot['_parentNode'];
                    } else {
                        $pNewRoot = null;
                    }
                }
                if (!$pNewRoot) {
                    if (!$pCurrentRoot[static::$rightColumn] && $pCurrentRoot[static::$leftColumn] !== $pTree[static::$leftColumn]) {
                        $pCurrentRoot[static::$rightColumn] = ++$indexValue;
                        static::addUpdateCondition($pCurrentRoot, $useORM);
                    }
                    break;
                }
                $pCurrentRoot = &$pNewRoot;
                $depth--;
                if ($depth < 1) {
                    $depth = 1;
                }
            } else {
                if (isset($childrenIndexes[$pCurrentRoot[static::$leftColumn]]) && count($childrenIndexes[$pCurrentRoot[static::$leftColumn]])) {
                    /**
                     * Get next child root and build indexes
                     */
                    $index = array_pop($childrenIndexes[$pCurrentRoot[static::$leftColumn]]);
                    $depth++;

                    $pParent = &$pCurrentRoot;
                    $pCurrentRoot = &$pCurrentRoot['childNodes'][$index];
                    if ($pParent) {
                        if (is_object($pCurrentRoot)) {
                            $pCurrentRoot->setParentNode($pParent);
                            $pCurrentRoot->{static::$parentColumn} = $pParent->{static::$uniqTreeFieldColumn};
                        } else {
                            $pCurrentRoot['_parentNode'] = &$pParent;
                            $pCurrentRoot[static::$parentColumn] = $pParent[static::$uniqTreeFieldColumn];
                        }
                    }
                    $pCurrentRoot = static::extendAttributes($pCurrentRoot);
                    $indexValue++;
                    /**
                     * Set new node attributes
                     */
                    $pCurrentRoot[static::$leftColumn] = $indexValue;
                    $pCurrentRoot[static::$depthColumn] = $depth;
                    $pCurrentRoot[static::$rootColumn] = $rootNumber;
                    $pCurrentRoot[static::$treeTypeColumn] = $treeType;
                    static::addUpdateCondition($pCurrentRoot, $useORM);
                    if ($useORM) {
                        $pCurrentRoot->setRootElement($pTree);
                    } else {
                        $pCurrentRoot['_root'] = &$pTree;
                        if (!isset($pCurrentRoot['childNodes'])) {
                            $pCurrentRoot['childNodes'] = [];
                        }
                    }
                } else if (count($pCurrentRoot['childNodes'])) {
                    /**
                     * Begin build indexes on child nodes
                     */
                    if (isset($childrenIndexes[$pCurrentRoot[static::$leftColumn]])) {

                        if ($pCurrentRoot[static::$leftColumn] != $pTree[static::$leftColumn]) {
                            $indexValue++;
                            /**
                             * Set new right tree index
                             */
                            $pCurrentRoot[static::$rightColumn] = $indexValue;
                            // Adding attributes for update tree
                            static::addUpdateCondition($pCurrentRoot, $useORM);
                        }
                    } else {
                        /**
                         * Remember node child nodes
                         */
                        $depth++;
                        if ($useORM) {
                            $childrenIndexes[$pCurrentRoot[static::$leftColumn]] = array_reverse((array)$pCurrentRoot->childNodes->keys()->all());
                        } else {
                            $childrenIndexes[$pCurrentRoot[static::$leftColumn]] = array_reverse(array_keys($pCurrentRoot['childNodes']));
                        }
                    }
                    $index = array_pop($childrenIndexes[$pCurrentRoot[static::$leftColumn]]);
                    if (!is_null($index)) {
                        $pParent = &$pCurrentRoot;
                        $pCurrentRoot = &$pCurrentRoot['childNodes'][$index];
                        if ($pParent) {
                            if ($useORM) {
                                $pCurrentRoot->setParentNode($pParent);
                                $pCurrentRoot->{static::$parentColumn} = $pParent->{static::$uniqTreeFieldColumn};
                            } else {
                                $pCurrentRoot['_parentNode'] = &$pParent;
                                $pCurrentRoot[static::$parentColumn] = $pParent[static::$uniqTreeFieldColumn];
                            }
                        }
                        $pCurrentRoot = static::extendAttributes($pCurrentRoot);

                        $indexValue++;
                        /**
                         * Set new tree attributes
                         */
                        $pCurrentRoot[static::$leftColumn] = $indexValue;
                        $pCurrentRoot[static::$depthColumn] = $depth;
                        $pCurrentRoot[static::$rootColumn] = $rootNumber;
                        $pCurrentRoot[static::$treeTypeColumn] = $treeType;
                        // Adding attributes for update tree
                        static::addUpdateCondition($pCurrentRoot, $useORM);

                        if ($useORM) {
                            $pCurrentRoot->setRootElement($pTree);
                        } else {
                            $pCurrentRoot['_root'] = &$pTree;
                            if (!isset($pCurrentRoot['childNodes'])) {
                                $pCurrentRoot['childNodes'] = [];
                            }
                        }
                    } else {
                        /**
                         * Extract parent for continue build
                         */
                        unset($pNewRoot);
                        if ($useORM) {
                            $pNewRoot = $pCurrentRoot->getParentNode();
                        } else {
                            if (isset($pCurrentRoot['_parentNode'])) {
                                $pNewRoot = &$pCurrentRoot['_parentNode'];
                            } else {
                                $pNewRoot = null;
                            }
                        }
                        if (!$pNewRoot) {
                            if (!$pCurrentRoot[static::$rightColumn] && $pCurrentRoot[static::$leftColumn] !== $pTree[static::$leftColumn]) {
                                $pCurrentRoot[static::$rightColumn] = ++$indexValue;
                                // Adding attributes for update tree
                                static::addUpdateCondition($pCurrentRoot, $useORM);
                            }
                            break;
                        }
                        $pCurrentRoot = &$pNewRoot;
                        $depth--;
                        if ($depth < 1) {
                            $depth = 1;
                        }
                    }
                } else {
                    /**
                     * Child nodes is empty
                     * Come back alt level up root node
                     * If parent is root - breaking
                     */
                    $indexValue++;

                    /**
                     * Change node right index
                     */
                    $pCurrentRoot[static::$rightColumn] = $indexValue;
                    static::addUpdateCondition($pCurrentRoot, $useORM);

                    /**
                     * Extract parent for continue build
                     */
                    unset($pNewRoot);
                    if ($useORM) {
                        $pNewRoot = $pCurrentRoot->getParentNode();
                    } else {
                        if (isset($pCurrentRoot['_parentNode'])) {
                            $pNewRoot = &$pCurrentRoot['_parentNode'];
                        } else {
                            $pNewRoot = null;
                        }
                    }
                    if (!$pNewRoot) {
                        if (!$pCurrentRoot[static::$rightColumn] && $pCurrentRoot[static::$leftColumn] !== $pTree[static::$leftColumn]) {
                            $pCurrentRoot[static::$rightColumn] = ++$indexValue;
                            // Adding attributes for update tree
                            static::addUpdateCondition($pCurrentRoot, $useORM);
                        }
                        break;
                    }
                    $pCurrentRoot = &$pNewRoot;
                    $depth--;
                    if ($depth < 1) {
                        $depth = 1;
                    }

                    /**
                     * We are on root node, break while
                     */
                    if ($pCurrentRoot[static::$leftColumn] == $pTree[static::$leftColumn]) {
                        break;
                    }
                }
            }
        } while (true);

        if (!$pTree[static::$rightColumn]) {
            $pTree[static::$rightColumn] = ++$indexValue;
            // Adding attributes for update tree
            static::addUpdateCondition($pTree, $useORM);
        }

        if (is_object($pTree)) {
            $pTree->setIsBuild(true);
        } else {
            $pTree['_isBuild'] = true;
        }

        return $pTree;
    }

    /**
     * Update tree indexes
     * IMPORTANT!!!
     * Don't use
     *           relation parentNode with DeepRelation & ORModel, and method
     *           \Illuminate\Eloquent\Database\Model::toArray because this will lead to a recurring looping. All nodes
     *           will be loading in protected attribute
     *           _parentNode Use it for loading parent node.
     *
     * @throws \Exception
     *
     * @return bool
     */
    public function updateTree()
    {
        /** @var $model TNestedSet|\Illuminate\Database\Eloquent\Model */
        $root = $this->getParentNode() ?? $this;

        if ($root->getIsBuild()) {
            return true;
        }

        static::rebuildRoot($root);

        if (!$this->{static::$treeTypeColumn}) {
            throw new \Exception('Tree type is empty');
        }

        return $this::__updateCondition($this->{static::$treeTypeColumn}, true);
    }

    /**
     * Build tree
     *
     * @param null|string|array $treeType Search tree type
     * @param bool              $useORM   Use ORM for build tree or build array tree
     *
     * @return \Illuminate\Database\Eloquent\Model[]|array|Collection
     */
    public static function buildTree($treeType = null, $useORM = true)
    {
        if (is_string($treeType) || empty($treeType)) {
            $index = static::getCacheIndex($treeType, $useORM);

            if ($cached = static::getCachedTree($treeType, $useORM)) {
                return $cached;
            }
            $nodes = static::getAllTree($treeType);
        } else if (is_array($treeType)) {
            $nodes = $treeType;
            $index = static::getCacheIndex($treeType[0][static::$treeTypeColumn], $useORM);
            static::addToCache($index, $nodes);
        }

        $class = static::class;
        if ($useORM && is_array($nodes) && count($nodes) && !(current($nodes) instanceof $class)) {
            $nodes = static::hydrate($nodes);
        }

        static::$_cachedTrees[$index] = $nodes;

        if (!count($nodes)) {
            return $useORM ? new Collection() : [];
        }

        $rootNode = [];

        $allItems = [];
        $pAllParents = [];

        foreach ($nodes as &$pNode) {
            $pNode = $pNode instanceof \stdClass ? (array)$pNode : $pNode;
            $pk = $pNode[static::$uniqTreeFieldColumn];
            $parent = isset($pNode[static::$parentColumn]) ? $pNode[static::$parentColumn] : null;

            if ($useORM) {
                $pNode->setRelation('childNodes', new Collection());
            } else {
                $pNode['childNodes'] = [];
            }

            if (isset($rootNode[$pNode[static::$treeTypeColumn]])) { // Append root nodes
                if ($useORM) {
                    $pNode->setRootElement($rootNode[$pNode[static::$treeTypeColumn]]);
                } else {
                    $pNode['_root'] = &$rootNode[$pNode[static::$treeTypeColumn]];
                }
            }

            if (!is_null($parent)) { // Append child nodes
                $_node = static::prepareNode($pNode, $useORM, $rootNode[$pNode[static::$treeTypeColumn]]);
                if ($useORM) {
                    $_node->setParentNode($pAllParents[$parent]);
                } else {
                    $_node['_parentNode'] = &$pAllParents[$parent];
                }
                $pAllParents[$parent]['childNodes'][$pk] = $_node;
                $pAllParents[$pk] = &$pAllParents[$parent]['childNodes'][$pk];
            } else { // Append nodes
                $allItems[$pk] = static::prepareNode($pNode, $useORM);
                $pAllParents[$pk] = &$allItems[$pk];
                $rootNode[$pNode[static::$treeTypeColumn]] = &$allItems[$pk];
            }
        }

        static::addToCache($index, $useORM ? $allItems = new Collection($allItems) : $allItems);

        $pAllParents = null;
        unset($pAllParents);

        return $allItems;
    }
    /**
     *
     *
     * End build tree methods
     *
     *
     */

    /**
     *
     *
     * Getters & setters methods
     *
     *
     */
    /**
     * Get parent node
     * IMPORTANT!!!
     * Don't use relation parentNode with DeepRelation & ORModel, and method
     * \Illuminate\Eloquent\Database\Model::toArray because this will lead to a recurring looping. All nodes will be
     * loading in protected attribute _parentNode Use it for loading parent node.
     *
     * @return TNestedSet|\Illuminate\Database\Eloquent\Model
     */
    public function getParentNode()
    {
        return $this->_parentNode;
    }

    /**
     * Set parent node & change parent_id column
     * Don't use relation parentNode with DeepRelation & ORModel, and method
     * \Illuminate\Eloquent\Database\Model::toArray because this will lead to a recurring looping. All nodes will be
     * loading in protected attribute _parentNode Use it for loading parent node.
     *
     * @param TNestedSet|\Illuminate\Database\Eloquent\Model $node Node
     */
    public function setParentNode($node)
    {
        $this->_parentNode = $node;
        $this->{static::$parentColumn} = $node ? $node->{static::$uniqTreeFieldColumn} : null;
    }

    /**
     * Set tree root link
     *
     * @param TNestedSet $pRoot Root node pointer
     */
    public function setRootElement(&$pRoot)
    {
        $this->_root = $pRoot;
    }

    /**
     * Check tree type exist and set if empty
     *
     * @param \Illuminate\Database\Eloquent\Model|TNestedSet|array $model
     *
     * @return \Illuminate\Database\Eloquent\Model|TNestedSet|array
     */
    protected static function setTreeTypeForModel($model)
    {
        if ((is_array($model) && !isset($model[static::$treeTypeColumn])) || (is_object($model) && !$model->{static::$treeTypeColumn})) {
            $index = md5(uniqid('tree_type_' . time()));
            $model[static::$treeTypeColumn] = $index;
            return $model;
        }

        return $model;
    }

    /**
     * Change tree type attribute
     *
     * @param string $type New node tree_type. Changed for all nodes
     */
    public function setTreeTypeAttribute($type)
    {
        /** @var \Illuminate\Database\Eloquent\Model|TNestedSet */

        $originalIndex = $this->getOriginal(static::$treeTypeColumn);
        $this->attributes[static::$treeTypeColumn] = $type;
        $root = $this->getParentNode() ?? $this;
        $root->setIsBuild(false);
        $this->_isBuild = false;
        static::removeTreeFromQuery($originalIndex);
    }

    /**
     * Get model column list in table
     *
     * @return array
     */
    public static function getNTHColumnsList()
    {
        $attributes = [
            static::$parentColumn,
            static::$leftColumn,
            static::$rightColumn,
            static::$depthColumn,
            static::$treeTypeColumn,
        ];

        return $attributes;
    }

    /**
     * Update unique field identifier
     *
     * @param array|TNestedSet $model Model for update unique field identifier
     *
     * @return int|string
     */
    public static function updateModelUnique(&$model)
    {
        $uniqueIndex = (int)(random_int(1, 512) * microtime(true));
        if (is_object($model)) {
            if (!($index = $model->getUniqTreeFieldAttribute())) {
                $model->setUniqTreeFieldAttribute($index = $uniqueIndex);
            }

            return $index;
        } else {
            if (!isset($model[static::$uniqTreeFieldColumn])) {
                $model[static::$uniqTreeFieldColumn] = $uniqueIndex;
            }
        }

        return $model[static::$uniqTreeFieldColumn];
    }

    /**
     * Get unique model identified
     *
     * @return null|string
     */
    public function getUniqTreeFieldAttribute()
    {
        return isset($this->attributes[static::$uniqTreeFieldColumn]) ? $this->attributes[static::$uniqTreeFieldColumn] : $this->_unique_id;
    }

    /**
     * Change root number
     *
     * @param string|int $value
     */
    public function setUniqTreeFieldAttribute($value)
    {
        if ($this->isFillable(static::$uniqTreeFieldColumn) && !in_array(static::$uniqTreeFieldColumn, static::$_skipTableAttributes)) {
            $this->attributes[static::$uniqTreeFieldColumn] = $value;
            $this->_unique_id = $value;
        } else {
            $this->_unique_id = $value;
        }
    }

    /**
     * Get index from node
     *
     * @param array|TNestedSet $children Node for get index
     * @param string           $column   Column for indexes
     *
     * @return string|null
     */
    protected static function getNodeIndex($children, $column = null)
    {
        $column = $column || static::getModelInstance()->getKeyName();

        if (is_array($children)) {
            return isset($children[$column]) ? $children[$column] : null;
        }

        return is_object($children) ? $children->{$column} : null;
    }

    /**
     * Get build needed for tree
     *
     * @return bool
     */
    public function getIsBuild()
    {
        $root = $this->getRootElement() ?? $this;
        return $root->_isBuild;
    }

    /**
     * Set is tree build flag
     *
     * @param bool $isBuild
     */
    public function setIsBuild(bool $isBuild)
    {
        $root = $this->getRootElement() ?? $this;
        $root->_isBuild = $isBuild;
    }

    /**
     * Get root element
     *
     * @return \Illuminate\Database\Eloquent\Model|array|null
     */
    public function getRootElement()
    {
        return $this->_root;
    }

    /**
     * Get data by index from cached data
     *
     * @param string $index
     *
     * @return array|Collection|TNestedSet[]|\Illuminate\Database\Eloquent\Model[]
     */
    protected static function getFromCacheByIndex($index = null)
    {
        if (is_null($index)) {
            return static::$_cachedTrees;
        }

        return isset(static::$_cachedTrees[$index]) ? static::$_cachedTrees[$index] : null;
    }

    /**
     * Get all tree elements
     *
     * @return array|Collection|static[]
     */
    protected static function getAllTree($treeType = null)
    {
        $inst = static::getModelInstance();
        $query = \DB::query()
            ->from($inst->getTable());

        if ($treeType) {
            $query->where([$inst::$treeTypeColumn => $treeType]);
        }

        $inst = null;
        unset($inst);

        $query->orderBy(static::$treeTypeColumn)
            ->orderBy(static::$leftColumn)
            ->orderBy('right');

        if ($treeType) {
            $query->where(static::$treeTypeColumn, $treeType);
        }

        return $query->get();
    }

    /**
     * Change root number. Change for all - rebuild tree after
     *
     * @param string|int $value
     */
    public function setRootNumberAttribute($value)
    {
        if ($this->isFillable(static::$rootColumn) && !in_array(static::$rootColumn, static::$_skipTableAttributes) && $value) {
            $this->attributes[static::$rootColumn] = $value;
        }
    }

    /**
     * Get generated update conditions
     *
     * @param string $index  Tree type index
     * @param bool   $useORM Use ORM or use array (if false)
     *
     * @return array
     */
    public static function getUpdateConditions($index, $useORM = false)
    {
        if (!isset(static::$_updateCondition[(int)$useORM][$index])) {
            return [];
        }

        return static::$_updateCondition[(int)$useORM][$index];
    }

    /**
     * Get generated insert conditions
     *
     * @param string $index
     * @param bool   $useORM Use ORM or use array (if false)
     *
     * @return array
     */
    public static function getInsertConditions($index, $useORM = false)
    {
        if (!isset(static::$_insertCondition[(int)$useORM][$index])) {
            return [];
        }

        return static::$_insertCondition[(int)$useORM][$index];
    }

    /**
     * Get delete conditions
     *
     * @param string $index
     * @param bool   $useORM Use ORM for build tree or build array tree
     *
     * @return array
     */
    public static function getDeleteConditions($index, $useORM = true)
    {
        if (!isset(static::$_deleteConditions[(int)$useORM][$index])) {
            return [];
        }

        return static::$_deleteConditions[(int)$useORM][$index];
    }

    /**
     * Get metadata (index & pk) from array attributes
     *
     * @param array $attributes
     *
     * @return array|null
     */
    protected static function getConditionMetaData($attributes)
    {
        if (!count($attributes)) {
            return null;
        }

        if (is_array($attributes)) {
            $pk = isset($attributes[$key = static::getModelInstance()->getKeyName()]) ? $attributes[$key] : null;
        } else {
            $pk = $attributes->{$attributes->getKeyName()};
        }

        $index = $attributes[static::$treeTypeColumn];

        return [$index, $pk];
    }

    /**
     * Get model instance
     *
     * @return TNestedSet|\Illuminate\Database\Eloquent\Model|static
     */
    protected static function getModelInstance()
    {
        return static::$_cacheInstance ?: static::$_cacheInstance = new static();
    }
    /**
     *
     *
     * End getters & setters methods
     *
     *
     */

    /**
     *
     *
     * Checker methods
     *
     *
     */
    /**
     * Check has children
     *
     * @param bool $loadForce Force load relation if not loading yet
     *
     * @return bool
     */
    public function hasChildren($loadForce = true)
    {
        if (!$loadForce && !$this->relationLoaded('childNodes')) {
            return false;
        }
        return $this->childNodes && $this->childNodes->count();
    }

    /**
     * Empty node list adding
     *
     * @param array|TNestedSet|\Illuminate\Database\Eloquent\Model $node
     *
     * @return array
     */
    protected static function checkExistsNeeded($node)
    {
        if (!is_array($node)) {
            static::setTreeTypeForModel($node);
            return $node;
        }

        $node = static::setTreeTypeForModel($node);

        foreach (static::getNTHColumnsList() as $columnName) {
            if (!isset($node[$columnName])) {
                $node[$columnName] = null;
            }
        }

        if (!isset($node['_parentNode'])) {
            $node['_parentNode'] = null;
        }

        if (!isset($node['_root'])) {
            $node['_root'] = null;
        }

        return $node;
    }

    /**
     * Clear nested attributes
     *
     * @param array|TNestedSet $pModel Model
     *
     * @return TNestedSet|\Illuminate\Database\Eloquent\Model|array
     */
    protected static function extendAttributes(&$pModel)
    {
        $pModel[static::$leftColumn] = null;
        $pModel[static::$rightColumn] = null;
        $pModel[static::$depthColumn] = null;

        if (!is_object($pModel) && !isset($pModel['childNodes'])) {
            $pModel['childNodes'] = [];
        }

        static::updateModelUnique($pModel);

        return $pModel;
    }

    /**
     * Clear attributes list
     *
     * @param array $attributes Source attributes list
     *
     * @return array
     */
    protected static function clearAttributes($attributes)
    {
        if (!count($attributes)) {
            return [];
        }

        if (isset($attributes['childNodes'])) {
            unset($attributes['childNodes']);
        }

        if (isset($attributes['_root'])) {
            unset($attributes['_root']);
        }

        if (count(static::$_skipTableAttributes)) {
            foreach (static::$_skipTableAttributes as $attribute) {
                if (isset($attributes[$attribute])) {
                    unset($attributes[$attribute]);
                }
            }
        }

        return $attributes;

    }

    /**
     * Prepare node
     *
     * @param array|TNestedSet $node      Next node
     * @param bool             $useORM    Use ORM for build tree or build array tree
     * @param null|TNestedSet  $pRootNode Root node
     *
     * @return TNestedSet|array
     */
    protected static function prepareNode($node, $useORM, &$pRootNode = null)
    {
        if ($useORM && is_array($node)) {
            $model = new static();
            $model->fill($node);
            $model->setRelation('childNodes', new Collection());
            $pRootNode = $rootNode ?? $model;
            $model->setRootElement($pRootNode);
            return $model;
        } else {
            if ($pRootNode) {
                $node['_root'] = $pRootNode;
            } else {
                $node['_root'] = $node;
            }
        }

        return $node;
    }
    /**
     *
     *
     * End checkers methods
     *
     *
     */

    /**
     *
     *
     * Update into DB tree methods
     *
     *
     */
    /**
     * Enable or disable keys checking
     *
     * @param bool $enable Enable or disable keys checking flag
     */
    protected static function keyUpdateStatement($enable)
    {
        \DB::statement('ALTER TABLE ' . static::getModelInstance()->getTable() . ' ' . ($enable ? 'ENABLE' : 'DISABLE') . ' KEYS');
        if ($enable) {
            \DB::statement('COMMIT');
        }
        \DB::statement('SET autocommit=' . ((int)$enable));
    }

    /**
     * Add tree in condition
     *
     * @param array|\Illuminate\Database\Eloquent\Model|TNestedSet $attributes Attributes list
     * @param bool                                                 $useORM     Use ORM for build tree or build array
     *                                                                         tree
     */
    protected static function addUpdateCondition($attributes, $useORM)
    {
        if (!($data = static::getConditionMetaData($attributes))) {
            return;
        }

        list($index, $pk) = $data;

        $old = null;
        if ($useORM) {
            $old = $attributes;
            $attributes = $attributes->getAttributes();
            if (!isset($attributes[static::$uniqTreeFieldColumn])) {
                $attributes[static::$uniqTreeFieldColumn] = $old->_unique_id;
            }
        }

        if (!$pk) {
            static::addInsertCondition($index, $attributes, $useORM);
            return;
        }

        $attributes = static::clearAttributes($attributes);

        if (!isset(static::$_updateCondition[(int)$useORM][$index])) {
            static::$_updateCondition[(int)$useORM][$index] = [[]];
        }

        $currentIndex = count(static::$_updateCondition[(int)$useORM][$index]) - 1;
        $current = static::$_updateCondition[(int)$useORM][$index][$currentIndex];

        if (count($current) > static::$maxUpdateModelCount) {
            static::$_updateCondition[(int)$useORM][$index][] = [];
            $currentIndex++;
            $current = [];
        }

        if (!isset($current[$pk])) {
            $current[$pk] = [];
        }
        if (($useORM && $old->isDirty()) || !$useORM) {
            $current[$pk] = array_replace($current[$pk], $attributes);
            static::$_updateCondition[(int)$useORM][$index][$currentIndex] = $current;
            $current = null;
            unset($current);
        } else {
            if (isset(static::$_updateCondition[(int)$useORM][$index][$currentIndex][$pk])) {
                static::$_updateCondition[(int)$useORM][$index][$currentIndex][$pk] = null;
                unset(static::$_updateCondition[(int)$useORM][$index][$currentIndex][$pk]);
            }
        }
    }

    /**
     * Add insert condition
     *
     * @param array $attributes Attributes list
     * @param bool  $useORM     Use ORM for build tree or build array tree
     */
    protected static function addInsertCondition($index, $attributes, $useORM)
    {
        if (!isset(static::$_insertCondition[(int)$useORM][$index])) {
            static::$_insertCondition[(int)$useORM][$index] = [[]];
        }
        $currentIndex = count(static::$_insertCondition[(int)$useORM][$index]) - 1;
        $current = static::$_insertCondition[(int)$useORM][$index][$currentIndex];
        $id = static::updateModelUnique($attributes);
        if (count($current) > static::$maxUpdateModelCount) {
            static::$_insertCondition[(int)$useORM][$index] = [[]];
            $current = [];
            $currentIndex++;
        }
        if (!isset($current[$id])) {
            $current[$id] = [];
        }

        $attributes = static::clearAttributes($attributes);

        if (!isset(static::$_insertCondition[(int)$useORM][$index])) {
            static::$_insertCondition[(int)$useORM][$index] = [];
        }

        $current[$id] = array_replace($current[$id], $attributes);
        static::$_insertCondition[(int)$useORM][$index][$currentIndex] = $current;
        $current = null;
        unset($current);
    }

    /**
     * Add delete condition
     *
     * @param array $attributes Tree index
     * @param bool  $useORM     Use ORM or use array (if false)
     */
    public static function addDeleteCondition($attributes, $useORM = true)
    {
        if (!($data = static::getConditionMetaData($attributes))) {
            return;
        }

        list($index, $pk) = $data;

        if (!$pk) {
            return;
        }

        if (!isset(static::$_deleteConditions[(int)$useORM][$index])) {
            static::$_deleteConditions[(int)$useORM][$index] = [];
        }

        static::$_deleteConditions[(int)$useORM][$index][] = $pk;
    }

    /**
     * Save array tree
     *
     * @param array|TNestedSet|\Illuminate\Database\Eloquent\Model $pRootNode Root node for save
     *
     * @return bool
     */
    public static function saveTree(&$pRootNode = null)
    {
        static::updateModelUnique($pRootNode);
        if (!isset($pRootNode['parent_id'])) {
            $pRootNode['parent_id'] = 0;
        }
        $pRootNode = static::setTreeTypeForModel($pRootNode);
        $useORM = is_object($pRootNode);
        if (!isset($pRootNode[static::$treeTypeColumn])) {
            $pRootNode = static::setTreeTypeForModel($pRootNode);
        }

        $treeType = $pRootNode[static::$treeTypeColumn];

        if (!$useORM) {
            if (!isset($pRootNode['_isBuild'])) {
                $pRootNode['_isBuild'] = false;
            }

            if (!isset($pRootNode['childNodes'])) {
                $pRootNode['childNodes'] = [];
            }

            if (!$pRootNode['_isBuild']) {
                $pRootNode = static::extendAttributes($pRootNode);
                if (isset($rootNode[static::$rootColumn]) && $pRootNode[static::$rootColumn]) {
                    $root = &$pRootNode[static::$rootColumn];
                } else {
                    $root = &$pRootNode;
                }
                $root = static::rebuildRoot($root);
                $pRootNode = $root;
            }
        }

        return static::__updateCondition($treeType, $useORM);
    }

    /**
     * Update tree in one query
     *
     * @param string $index
     * @param bool   $useORM Use ORM for build tree or build array tree
     *
     * @throws \Exception
     *
     * @return bool
     */
    private static function __updateCondition($index, $useORM)
    {
        $updates = static::getUpdateConditions($index, $useORM);
        $inserts = static::getInsertConditions($index, $useORM);
        $deletes = static::getDeleteConditions($index, $useORM);

        /**
         * I'm not using lock table because we can disable re-build indexes & key check for table on transaction query
         * DISABLE KEYS does not disable unique indexes
         * Working on MyISAM. For innoDB usages SET autocommit=0
         */
        static::keyUpdateStatement(false);
        \DB::beginTransaction();

        $count = 0;
        if (count($deletes)) {
            foreach ($deletes as $delete) {
                try {
                    $count += \DB::table(static::getModelInstance()->getTable())->whereIn(static::getModelInstance()->getKeyName(), $delete)->delete();
                } catch (\Exception $exception) { // I need enable key updates
                    \DB::rollBack();
                    static::keyUpdateStatement(true);
                    throw $exception;
                }
            }
        }

        if (!count($updates) && !count($inserts)) {
            if (!$count) {
                \DB::rollBack();
            } else {
                \DB::commit();
            }

            static::keyUpdateStatement(true);
            return true;
        }

        if (count($updates)) {
            $pdo = \DB::getPdo();
            /**
             * Update in one query for more attributes
             *
             * @see https://stackoverflow.com/questions/12754470/mysql-update-case-when-then-else
             */
            $nthAttributes = static::getNTHColumnsList();
            $skip = array_merge(static::$_skipTableAttributes ?? [], [static::getModelInstance()->getKeyName()]);
            foreach ($updates as $updatePart) {
                $updateSql = [];
                foreach ($updatePart as $id => $attributes) {
                    foreach ($attributes as $name => $attribute) {
                        if (in_array($name, $skip)) {
                            continue;
                        }

                        if (!isset($updateSql[$name])) {
                            $updateSql[$name] = " `{$name}` = CASE ";
                        }

                        $quote = trim($pdo->quote(strip_tags($attribute)));
                        if (empty($quote) && isset($nthAttributes[$name])) {
                            throw new \Exception("Column {$name} could not empty");
                        }
                        $statement = " WHEN `" . static::getModelInstance()->getKeyName() . "` = {$id} THEN " . $quote;
                        $updateSql[$name] .= " " . $statement;
                    }

                    if (empty($updateSql)) {
                        continue;
                    }
                    $query = 'UPDATE ' . static::getModelInstance()->getTable() . " SET " . implode(" END, ", $updateSql) .
                        " END WHERE " . static::getModelInstance()->getKeyName() . " in (" . implode(',', array_keys($updatePart)) . ")";

                    /** @var PDOStatement $statement */
                    /**
                     * Prepare statement query for update
                     */
                    $statement = $pdo->prepare($query);

                    try {
                        $statement->execute();
                    } catch (\Exception $exception) { // I need enable key updates
                        \DB::rollBack();
                        static::keyUpdateStatement(true);

                        throw $exception;
                    }
                }
            }
        }

        if (count($inserts)) {
            foreach ($inserts as $treeType => $insert) {
                try {
                    \DB::table(static::getModelInstance()->getTable())
                        ->insert($insert);
                } catch (\Exception $exception) { // I need enable key updates
                    \DB::rollBack();
                    static::keyUpdateStatement(true);

                    throw $exception;
                }
            }
        }
        static::keyUpdateStatement(true);

        \DB::commit();

        return true;
    }
    /**
     *
     *
     * End update into DB tree methods
     *
     *
     */

    /**
     *
     *
     * Cache methods
     *
     *
     */
    /**
     * Add to cache
     *
     * @param int  $index  Cache index
     *
     * @data  array|\Illuminate\Database\Eloquent\Model[]|TNestedSet[]|Collection
     *
     * @param bool $return Return data after adding to cache
     *
     * @throws \Exception If data is empty
     *
     * @return array|\Illuminate\Database\Eloquent\Model[]|TNestedSet[]|\Illuminate\Database\Eloquent\Collection
     */
    public static function addToCache($index, $data, $return = false)
    {
        if (empty($data)) {
            throw new \Exception('Empty data does not possible to set in cached tree');
        }

        static::$_cachedTrees[$index] = $data;

        if ($return) {
            return static::$_cachedTrees[$index];
        }
    }

    /**
     * Reset data from cache
     *
     * @param string $index
     */
    public static function resetCache($index)
    {
        static::$_cachedTrees[$index] = null;
        unset(static::$_cachedTrees[$index]);
    }

    /**
     * Get index for tree cache
     *
     * @param null|string $treeType Tree name
     * @param bool        $useORM   Use ORM for build tree or build array tree
     *
     * @return string
     */
    public static function getCacheIndex($treeType = null, $useORM = true): string
    {
        return ($treeType ?? 'all') . ((int)$useORM);
    }

    /**
     * Get tree from cache
     *
     * @param bool|null   $useORM   Use ORM for build tree or build array tree
     * @param null|string $treeType Tree name
     *
     * @return array|Collection|TNestedSet[]|\Illuminate\Database\Eloquent\Model[]
     */
    public static function getCachedTree($useORM = null, $treeType = null)
    {
        if (is_null($useORM) && is_null($treeType)) {
            return static::getAllCached();
        }

        $index = static::getCacheIndex($treeType, $useORM);
        return static::getFromCacheByIndex($index);
    }

    /**
     * Get all cached trees
     *
     * @return array|TNestedSet[]|\Illuminate\Database\Eloquent\Model[]
     */
    public static function getAllCached(): array
    {
        return static::$_cachedTrees;
    }

    /**
     *
     *
     * Cache methods end
     *
     *
     */
}