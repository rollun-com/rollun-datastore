<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\datastore\DataStore\ConditionBuilder;

use rollun\datastore\DataStore\ConditionBuilder\ConditionBuilderAbstract;
use Xiag\Rql\Parser\DataType\Glob;

/**
 * {@inheritdoc}
 *
 * {@inheritdoc}
 */
class RqlConditionBuilder extends ConditionBuilderAbstract
{

    const TEXT_NULL = 'null()';

    protected $literals = [
        'LogicOperator' => [
            'and' => ['before' => 'and(', 'between' => ',', 'after' => ')'],
            'or' => ['before' => 'or(', 'between' => ',', 'after' => ')'],
            'not' => ['before' => 'not(', 'between' => ',', 'after' => ')'],
        ],
        'ArrayOperator' => [
            'in' => ['before' => 'in(', 'between' => ',(', 'delimiter' => ',', 'after' => '))'],
            'out' => ['before' => 'out(', 'between' => ',(', 'delimiter' => ',', 'after' => '))']
        ],
        'ScalarOperator' => [
            'eq' => ['before' => 'eq(', 'between' => ',', 'after' => ')'],
            'ne' => ['before' => 'ne(', 'between' => ',', 'after' => ')'],
            'ge' => ['before' => 'ge(', 'between' => ',', 'after' => ')'],
            'gt' => ['before' => 'gt(', 'between' => ',', 'after' => ')'],
            'le' => ['before' => 'le(', 'between' => ',', 'after' => ')'],
            'lt' => ['before' => 'lt(', 'between' => ',', 'after' => ')'],
            'like' => ['before' => 'like(', 'between' => ',', 'after' => ')'],
            'contains' => ['before' => 'contains(', 'between' => ',', 'after' => ')'],
        ]
    ];
    protected $emptyCondition = '';

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public static function encodeString($value)
    {
        return strtr(rawurlencode($value), [
            '-' => '%2D',
            '_' => '%5F',
            '.' => '%2E',
            '~' => '%7E',
            '`' => '%60',
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function prepareFieldValue($fieldValue)
    {
        $regexRqlDecoded = parent::prepareFieldValue($fieldValue);

        if (is_null($regexRqlDecoded)) {
            return 'null()';
        }
        if (is_string($regexRqlDecoded)) {
            $constStar = 'starhjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkb';
            $constQuestion = 'questionhjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkb';
            $regexRqlEnecoded = self::encodeString($regexRqlDecoded);
            $regexRqlPrepared = strtr($regexRqlEnecoded, [$constStar => '*', $constQuestion => '?']);
            $value = empty($regexRqlPrepared) ? "empty" : 'string:' . $regexRqlPrepared;
            return $value;
        }
        return $regexRqlDecoded;
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function getValueFromGlob(Glob $globNode)
    {
        $constStar = 'starhjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkb';
        $constQuestion = 'questionhjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkb';

        $glob = parent::getValueFromGlob($globNode);

        $regexRqlPrepared = strtr($glob, ['*' => $constStar, '?' => $constQuestion]);
        $regexRqlDecoded = rawurldecode($regexRqlPrepared);
        return $regexRqlDecoded;
    }

}
