<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\test\datastore\DataStore\ConditionBuilder;

use Xiag\Rql\Parser\DataType\Glob;

/**
 *
 */
abstract class ConditionBuilderTest extends \PHPUnit_Framework_TestCase
{
    /*
     * var PhpConditionBuilder
     */

    protected $object;

    abstract public function providerPrepareFieldName();

    /**
     * @dataProvider providerPrepareFieldName
     */
    public function testPrepareFieldName($in, $out)
    {
        $fieldName = $this->object->prepareFieldName($in);
        $this->assertEquals(
                $out, $fieldName
        );
    }

    abstract public function providerGetValueFromGlob();

    /**
     * @dataProvider providerGetValueFromGlob
     */
    public function testGetValueFromGlob($in, $out)
    {
        $globOgject = new Glob($in);
        $value = $this->object->getValueFromGlob($globOgject);
        $this->assertEquals(
                $out, $value
        );
    }

    abstract public function provider__invoke();

    /**
     * @dataProvider provider__invoke
     */
    public function test__invoke($rootQueryNode, $out)
    {
        $condition = $this->object->__invoke($rootQueryNode);
        $this->assertEquals(
                $out, $condition
        );
    }

}
