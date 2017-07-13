<?php

namespace Tests\Unit\Nested;

/**
 * Class BuildArrayTreeExistsModelTest
 *
 * @package Tests\Unit
 */
class BuildArrayTreeExistsModelTest extends BuildArrayTreeTest
{
    protected $_seeds = ['Products', 'ProductsPropertiesUnitsNested'];

    /**
     * @inheritdoc
     */
    public function testBuildTreeDepth3($cntChildren = 1, $useORM = false, $resetParent = true)
    {
        parent::testBuildTreeDepth3($cntChildren, $useORM, $resetParent);
    }

    /**
     * @inheritdoc
     */
    protected function _testBuildTree($countChildren, $additionalCallback = null, $model = null, $useORM = true, $resetParent = true)
    {
        parent::_testBuildTree($countChildren, $additionalCallback, $model, $useORM, $resetParent);
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
    protected function _checkCustomCondition($tree, $countUpdate = 0, $useORM = false, $type = 2)
    {
        parent::_checkCustomCondition($tree, $countUpdate, $useORM, $type);
    }
}