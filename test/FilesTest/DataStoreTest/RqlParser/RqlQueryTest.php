<?php

/**
 * Created by PhpStorm.
 * User: victorsecuring
 * Date: 14.12.16
 * Time: 10:51 AM
 */

namespace rollun\test\datastore\RqlParser;

use Xiag\Rql\Parser\Node\LimitNode;
use Xiag\Rql\Parser\Node\Query\LogicOperator\AndNode;
use Xiag\Rql\Parser\Node\Query\LogicOperator\OrNode;
use Xiag\Rql\Parser\Node\Query\ScalarOperator\EqNode;
use Xiag\Rql\Parser\Node\Query\ScalarOperator\GtNode;
use Xiag\Rql\Parser\Node\Query\ScalarOperator\LtNode;
use Xiag\Rql\Parser\Node\SelectNode;
use Xiag\Rql\Parser\Node\SortNode;
use Xiag\Rql\Parser\Query;
use rollun\datastore\Rql\Node\AggregateSelectNode;
use rollun\datastore\Rql\RqlQuery;
use Zend\Db\Adapter\Platform\Oracle;

class RqlQueryTest extends \PHPUnit_Framework_TestCase
{

    /** @var  RqlQuery */
    private $object;

    public function setUp()
    {

    }

    public function testQueryNode()
    {
        $this->object = new RqlQuery("eq(a,1)");

        $query = new Query();
        $query->setQuery(
                new EqNode("a", "1")
        );
        $this->assertEquals($query->getQuery(), $this->object->getQuery());
    }

    public function testSortNode()
    {
        $this->object = new RqlQuery("sort(-a,+b)");

        $query = new Query();
        $query->setSort(new SortNode(['a' => -1, 'b' => 1]));
        $this->assertEquals($query->getSort(), $this->object->getSort());
    }

    public function testSelectNode()
    {
        $this->object = new RqlQuery("select(sadf_q,ads_x)");

        $query = new Query();
        $query->setSelect(new AggregateSelectNode(['sadf_q', 'ads_x']));
        $this->assertEquals($query->getSelect(), $this->object->getSelect());
    }

    public function testLimitNode()
    {
        $this->object = new RqlQuery("limit(10,13)");

        $query = new Query();
        $query->setLimit(new LimitNode(10, 13));
        $this->assertEquals($query->getLimit(), $this->object->getLimit());
    }

    public function testLimitNodeDefaultOffset()
    {
        $this->object = new RqlQuery("limit(10)");

        $query = new Query();
        $query->setLimit(new LimitNode(10, 0));
        $this->assertEquals($query->getLimit(), $this->object->getLimit());
    }

    /* public function testLSSQNodes()
      {
      $this->object = new RqlQuery("and(eq(b,1),or(gt(z,2),lt(z,-1)))&select(b,z,a)&limit(12,14)&sort(+z)");

      $query = new Query();
      $query->setQuery(
      new AndNode([
      new EqNode('b', 1),
      new OrNode([
      new GtNode('z',2),
      new LtNode('z',-1),
      ])
      ])
      );
      } */
}
