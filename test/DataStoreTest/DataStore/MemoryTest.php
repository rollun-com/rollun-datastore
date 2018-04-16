<?php
/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\test\datastore\DataStore;

use rollun\datastore\DataStore\DataStoreException;
use rollun\datastore\DataStore\Memory;
use rollun\datastore\Rql\RqlQuery;
use rollun\installer\Command;
use Xiag\Rql\Parser\Node\Query\ScalarOperator\EqNode;
use Xiag\Rql\Parser\Node\SortNode;
use Xiag\Rql\Parser\Query;

class MemoryTest extends AbstractTest
{

    /**
     * @var Memory
     */
    protected $object;

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    public function presetUp()
    {
        $this->setUp();
    }

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->object = $this->container->get('testMemory');
    }

    /**
     * Tears down the fixture, for example, closes a network connection.
     * This method is called after a test is executed.
     */
    protected function tearDown()
    {

    }

    /**
     * This method init $this->object
     */
    protected function _initObject($data = null)
    {
        if (is_null($data)) {
            $data = $this->_itemsArrayDelault;
        }
        foreach ($data as $record) {
            $this->object->create($record);
        }
    }

    public function test_exploitQueryValue()
    {
        $file = Command::getDataDir() . "haha.dat";
        if (file_exists($file)) {
            unlink($file);
        }
        $this->_initObject([
            ["name" => "a"],
            ["name" => "b"],
            ["name" => "c"],
        ]);

        $exploitString = "file_put_contents(\"data/haha.dat\", \"HAHA\");";
        $base64 = base64_encode($exploitString);
        $exploit = "'.eval(base64_decode('$base64')).'";
        $query = new Query();
        $query->setQuery(new EqNode("name", $exploit));
        try {
            $this->object->query($query);
        } catch (\Throwable $exception) {
            //Silence Throwable
        }
        $get = "";
        if (file_exists($file)) {
            $get = file_get_contents($file);
        }
        $this->assertEmpty($get);
    }

    public function test_exploitQueryField()
    {
        $file = Command::getDataDir() . "haha.dat";
        if (file_exists($file)) {
            unlink($file);
        }
        $this->_initObject([
            ["name" => "a"],
            ["name" => "b"],
            ["name" => "c"],
        ]);

        $exploitString = "file_put_contents(\"data/haha.dat\", \"HAHA\");";
        $base64 = base64_encode($exploitString);
        $exploit = "'.eval(base64_decode('$base64')).'";
        $query = new Query();
        $query->setQuery(new EqNode($exploit, "data"));
        try {
            $this->object->query($query);
        } catch (\Throwable $exception) {
            //Silence Throwable
        }
        $get = "";
        if (file_exists($file)) {
            $get = file_get_contents($file);
        }
        $this->assertEmpty($get);
    }

    public function test_exploitSort()
    {
        $file = Command::getDataDir() . "haha.dat";
        if (file_exists($file)) {
            unlink($file);
        }
        $this->_initObject([
            ["name" => "a"],
            ["name" => "b"],
            ["name" => "c"],
        ]);

        $exploitString = "file_put_contents(\"data/haha.dat\", \"HAHA\");";
        $base64 = base64_encode($exploitString);
        $exploit = "'.eval(base64_decode('$base64')).'";
        $query = new Query();
        $query->setSort(new SortNode([$exploit => SortNode::SORT_DESC]));
        try {
            $this->object->query($query);
        } catch (\Throwable $exception) {
            //Silence Throwable
        }
        $get = "";
        if (file_exists($file)) {
            $get = file_get_contents($file);
        }
        $this->assertEmpty($get);
    }
}
