<?php

namespace Tests\Unit\Nested;

use Tests\TestCase;
use Tests\Unit\Nested\models\NestedModel;

/**
 * Class InitTest
 * Init test
 *
 * @package Tests\Unit
 */
class InitTest extends TestCase
{
    protected $_runUniqTest = false;

    /**
     * Count unique identified which will be generated
     *
     * @var int
     */
    protected $_countUniqModels = 100000;

    /**
     * In this test you're see why array build tree doing in recursion
     * Array pointers are removed from memory after unset. If unset un-using - pointer is erase previous data.
     * Array pointers does not worked!!! That's why tree build without recursion - only with `while` and using pointers
     * only in `while` scope
     */
    public function testPointerReference()
    {
        $arr = [
            [1, 2, 3 => [1, 2, 3]],
            [4, 5, 6 => [1, 2, 3]],
        ];

        $link = [];
        $array = &$arr[1];
        $array[1] = 23;

        $this->assertEquals(23, $array[1]);
        $this->assertEquals(23, $arr[1][1]);

        array_push($link, $array);
        $link[0][1] = 333;

        $this->assertEquals(333, $link[0][1]);
        $this->assertEquals(23, $array[1]);
        $this->assertEquals(23, $arr[1][1]);

        $link = [];

        $link[] = &$array;
        $link[0][1] = 444;
        $array1 = $arr[0];
        $link[] = $array1;

        $this->assertEquals(444, $link[0][1]);
        $this->assertEquals(444, $array[1]);
        $this->assertEquals(444, $arr[1][1]);
        $this->assertEquals($link[1], array_pop($link));

        unset($link);
        $link = &$array[6];
        $link[1] = 333;
        $this->assertEquals(333, $link[1]);
        $this->assertEquals(333, $array[6][1]);
        $this->assertEquals(333, $arr[1][6][1]);
        $link[1] = 123;
        $this->assertEquals(123, $link[1]);
        $this->assertEquals(123, $array[6][1]);
        $this->assertEquals(123, $arr[1][6][1]);

        $nextArr = [&$link];

        $cur = array_pop($nextArr);
        $cur[1] = 333;
        $this->assertEquals(333, $cur[1]);
        $this->assertEquals(123, $array[6][1]);
        $this->assertEquals(123, $arr[1][6][1]);

        $nextArr = [$link];

        $cur = array_pop($nextArr);
        $cur[1] = 333;
        $this->assertEquals(333, $cur[1]);
        $this->assertEquals(123, $array[6][1]);
        $this->assertEquals(123, $arr[1][6][1]);

        $nextArr = [$link];
        $cur = $this->popArrayLink($nextArr);
        $cur[1] = 333;
        $this->assertEquals(333, $cur[1]);
        $this->assertEquals(123, $array[6][1]);
        $this->assertEquals(123, $arr[1][6][1]);

        $nextArr = [$link];
        $cur = $this->popArray($nextArr);
        $cur[1] = 333;
        $this->assertEquals(333, $cur[1]);
        $this->assertEquals(123, $array[6][1]);
        $this->assertEquals(123, $arr[1][6][1]);

        $nextArr = [&$link];
        $cur = $this->popArrayLink($nextArr);
        $cur[1] = 333;
        $this->assertEquals(333, $cur[1]);
        $this->assertEquals(123, $array[6][1]);
        $this->assertEquals(123, $arr[1][6][1]);

        $nextArr = [&$link];
        $cur = $this->popArray($nextArr);
        $cur[1] = 333;
        $this->assertEquals(333, $cur[1]);
        $this->assertEquals(123, $array[6][1]);
        $this->assertEquals(123, $arr[1][6][1]);
    }

    /**
     * Pop from array by link and return link
     *
     * @param array $arr
     *
     * @return mixed|null
     */
    protected function popArrayLink(&$arr)
    {
        if (!count($arr) || !is_array($arr)) {
            return null;
        }

        $count = count($arr);

        $retArr = &$arr[$count - 1];

        return $retArr;
    }

    /**
     * Pop from array
     *
     * @param array $arr
     *
     * @return mixed|null
     */
    protected function popArray(&$arr)
    {
        if (!count($arr) || !is_array($arr)) {
            return null;
        }

        $count = count($arr);

        $retArr = $arr[$count - 1];

        return $retArr;
    }

    /**
     * Test unique model identifier
     *
     * This test working ~ 3-10 minutes
     */
    public function testUniq() {
        $uniqs = [];
        if (!$this->_runUniqTest) {
            $this->assertCount(0, $uniqs);
            return;
        }
        for ($i = 0; $i < $this->_countUniqModels; $i++) {
            $model = [];
            NestedModel::updateModelUnique($model);
            $uniqs[] = $model['unique_id'];
            $this->assertEquals(count($uniqs), count(array_unique($uniqs)));
            $this->assertInternalType('int', $uniqs[count($uniqs) - 1]);
        }
    }
}