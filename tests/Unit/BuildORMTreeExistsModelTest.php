<?php


namespace Tests\Unit\Nested;

/**
 * Class BuildORMTreeExistsModelTest
 *
 * @package Tests\Unit
 */
class BuildORMTreeExistsModelTest extends BuildORMTreeTest
{
    protected $_seeds = ['Products', 'ProductsPropertiesUnitsNested'];

    /**
     * @inheritdoc
     */
    public function testBuildTree($useORM = true, $resetParent = true)
    {
        parent::testBuildTree($useORM, $resetParent);
    }

    /**
     * @inheritdoc
     */
    public function testBuildTreeDepth3($cntChildren = 1, $useORM = true, $resetParent = true)
    {
        return parent::testBuildTreeDepth3($cntChildren, $useORM, $resetParent);
    }

    /**
     * @inheritdoc
     */
    protected function getModel($offset = 0, $resetParent = false, $new = false)
    {
        return parent::getModel($offset, $resetParent, $new);
    }

    /**
     * @inheritdoc
     */
    protected function _checkCustomCondition($tree, $countUpdate = 1, $useORM = false, $type = 2)
    {
        parent::_checkCustomCondition($tree, $countUpdate, $useORM, $type);
    }
}