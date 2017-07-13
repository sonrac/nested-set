<?php


namespace Tests\Unit\Nested;

/**
 * Class BuildArrayTreeTest
 *
 * @package Tests\Unit
 */
class BuildArrayTreeTest extends BuildORMTreeTest
{
    protected $_seeds = ['Products', 'ProductsPropertiesUnitsNested'];

    /**
     * @inheritdoc
     */
    public function testBuild($useORM = false)
    {
        parent::testBuild($useORM);
    }

    /**
     * @inheritdoc
     */
    public function testBuildFullTreeORM($useORM = false)
    {
        parent::testBuildFullTreeORM($useORM);
    }

    /**
     * @inheritdoc
     */
    public function testResetCache($useORM = false)
    {
        parent::testResetCache($useORM);
    }

    /**
     * @inheritdoc
     */
    public function testBuildTree($useORM = false, $resetParent = false)
    {
        parent::testBuildTree($useORM);
    }

    /**
     * @inheritdoc
     */
    public function testBuildTreeDepth3($cntChildren = 1, $useORM = false, $resetParent = false)
    {
        parent::testBuildTreeDepth3($cntChildren, $useORM, $resetParent);
    }
}

