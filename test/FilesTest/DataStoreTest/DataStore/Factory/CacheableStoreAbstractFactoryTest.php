<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 04.07.16
 * Time: 14:08
 */

namespace rollun\test\datastore\DataStore\Factory;

class CacheableStoreAbstractFactoryTest extends \PHPUnit_Framework_TestCase
{
    /**
     * @var Returner
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {

    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {

    }

    public function testDataStoreMemory__invoke()
    {
        $container = include 'config/container.php';
        $this->object = $container->get('testCacheable');
        $this->assertSame(
            get_class($returnedResponse = $this->object), 'rollun\datastore\DataStore\Cacheable'
        );
    }
}
