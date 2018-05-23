<?php

/**
 * Created by PhpStorm.
 * User: root
 * Date: 30.05.16
 * Time: 12:59
 */

namespace rollun\test\datastore\DataStore\ConditionBuilder;

use Xiag\Rql\Parser\DataType\Glob;
use Xiag\Rql\Parser\Node;
use Xiag\Rql\Parser\QueryBuilder;
use rollun\datastore\DataStore\ConditionBuilder\RqlConditionBuilder;

class RqlConditionBuilderTest extends ConditionBuilderTest
{

    public function providerPrepareFieldName()
    {
        return array(
            array('fieldName', 'fieldName'),
            array('FieldName', 'FieldName'),
            array('Field_Name', 'Field_Name'),
        );
    }

    public function providerGetValueFromGlob()
    {

        return array(
            array('abc', 'abc'),
            array('*abc', 'starhjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkbabc'),
            array('abc*', 'abcstarhjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkb'),
            array('a*b?c', 'astarhjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkbbquestionhjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkbc'),
            array('?abc', 'questionhjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkbabc'),
            array('abc?', 'abcquestionhjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkb'),
            array(rawurlencode('Шщ +-*._'), 'Шщ +-*._'),
        );
    }

    public function provider__invoke()
    {
        return array(
            array(null, ''),
            array(
                        (new QueryBuilder())
                        ->addQuery(new Node\Query\ScalarOperator\EqNode('name', 'value'))
                        ->getQuery()->getQuery(),
                'eq(name,string:value)'
            ),
            array(
                        (new QueryBuilder())
                        ->addQuery(new Node\Query\ScalarOperator\EqNode('a', 1))
                        ->addQuery(new Node\Query\ScalarOperator\NeNode('b', 2))
                        ->addQuery(new Node\Query\ScalarOperator\LtNode('c', 3))
                        ->addQuery(new Node\Query\ScalarOperator\GtNode('d', 4))
                        ->addQuery(new Node\Query\ScalarOperator\LeNode('e', 5))
                        ->addQuery(new Node\Query\ScalarOperator\GeNode('f', 6))
                        ->addQuery(new Node\Query\ScalarOperator\LikeNode('g', new Glob('*abc?')))
                        ->getQuery()->getQuery(),
                'and(eq(a,1),ne(b,2),lt(c,3),gt(d,4),le(e,5),ge(f,6),like(g,string:*abc?))'
            ),
            array(
                        (new QueryBuilder())
                        ->addQuery(new Node\Query\LogicOperator\AndNode([
                            new Node\Query\ScalarOperator\EqNode('a', 'b'),
                            new Node\Query\ScalarOperator\LtNode('c', 'd'),
                            new Node\Query\LogicOperator\OrNode([
                                new Node\Query\ScalarOperator\LtNode('g', 5),
                                new Node\Query\ScalarOperator\GtNode('g', 2),
                                    ])
                        ]))
                        ->addQuery(new Node\Query\LogicOperator\NotNode([
                            new Node\Query\ScalarOperator\NeNode('h', 3),
                        ]))
                        ->getQuery()->getQuery(),
                'and(eq(a,string:b),lt(c,string:d),or(lt(g,5),gt(g,2)),not(ne(h,3)))'
            ),
            array(
                        (new QueryBuilder())
                        ->addQuery(new Node\Query\LogicOperator\AndNode([
                            new Node\Query\ScalarOperator\EqNode('a', null),
                            new Node\Query\ScalarOperator\LtNode('c', 'd'),
                            new Node\Query\LogicOperator\OrNode([
                                new Node\Query\ScalarOperator\LtNode('g', 5),
                                new Node\Query\ScalarOperator\GtNode('g', 2),
                                    ])
                        ]))
                        ->addQuery(new Node\Query\LogicOperator\NotNode([
                            new Node\Query\ScalarOperator\NeNode('h', 3),
                        ]))
                        ->getQuery()->getQuery(),
                'and(eq(a,null()),lt(c,string:d),or(lt(g,5),gt(g,2)),not(ne(h,3)))'
            ),
        );
    }

    /**
     * Sets up the fixture, for example, opens a network connection.
     * This method is called before a test is executed.
     */
    protected function setUp()
    {
        parent::setUp();
        $this->object = new RqlConditionBuilder();
    }

}
