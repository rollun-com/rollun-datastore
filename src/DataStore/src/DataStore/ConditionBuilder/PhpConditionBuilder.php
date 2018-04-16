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
use rollun\datastore\DataStore\DataStoreException;

/**
 * {@inheritdoc}
 *
 * {@inheritdoc}
 */
class PhpConditionBuilder extends ConditionBuilderAbstract
{

    protected $literals = [
        'LogicOperator' => [
            'and' => ['before' => '(', 'between' => ' && ', 'after' => ')'],
            'or' => ['before' => '(', 'between' => ' || ', 'after' => ')'],
            'not' => ['before' => '( !(', 'between' => ' error ', 'after' => ') )'],
        ],
        'ArrayOperator' => [
            'in' => ['before' => '(in_array(', 'between' => ',[', 'delimiter' => ',', 'after' => ']))'],
            'out' => ['before' => '(!in_array(', 'between' => ',[', 'delimiter' => ',', 'after' => ']))']
        ],
        'ScalarOperator' => [
            'eq' => ['before' => '(', 'between' => '==', 'after' => ')'],
            'ne' => ['before' => '(', 'between' => '!=', 'after' => ')'],
            'ge' => ['before' => '(', 'between' => '>=', 'after' => ')'],
            'gt' => ['before' => '(', 'between' => '>', 'after' => ')'],
            'le' => ['before' => '(', 'between' => '<=', 'after' => ')'],
            'lt' => ['before' => '(', 'between' => '<', 'after' => ')'],
            'like' => ['before' => '( ($_field = ', 'between' => ") !=='' && preg_match(", 'after' => ', $_field) )'],
            'contains' => [
                'before' => '( ($_field = ',
                'between' => ") !=='' && preg_match('/' . trim(",
                'after' => ',"\'"). \'/i\', $_field) )'
            ],
        ]
    ];

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function prepareFieldName($fieldName)
    {
        return '$item[\'' . addslashes($fieldName) . '\']';
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function prepareFieldValue($fieldValue)
    {
        $fieldValue = parent::prepareFieldValue($fieldValue);
        switch (true) {
            case is_bool($fieldValue):
                $fieldValue = (bool)$fieldValue ? TRUE : FALSE;
                return $fieldValue;
            case is_numeric($fieldValue):
                return $fieldValue;
            case is_null($fieldValue):
                return 'null';
            case is_string($fieldValue):
                return "'" . addslashes($fieldValue) . "'";
            default:
                throw new DataStoreException(
                    'Type ' . gettype($fieldValue) . ' is not supported'
                );
        }
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function getValueFromGlob(Glob $globNode)
    {
        $constStar = 'star_hjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkb';
        $constQuestion = 'question_hjc7vjHg6jd8mv8hcy75GFt0c67cnbv74FegxtEDJkcucG64frblmkb';

        $glob = parent::getValueFromGlob($globNode);
        $anchorStart = true;
        if (substr($glob, 0, 1) === '*') {
            $anchorStart = false;
            $glob = ltrim($glob, '*');
        }
        $anchorEnd = true;
        if (substr($glob, -1) === '*') {
            $anchorEnd = false;
            $glob = rtrim($glob, '*');
        }
        $regex = strtr(
            preg_quote(rawurldecode(strtr($glob, ['*' => $constStar, '?' => $constQuestion])), '/'), [$constStar => '.*', $constQuestion => '.']
        );
        if ($anchorStart) {
            $regex = '^' . $regex;
        }
        if ($anchorEnd) {
            $regex = $regex . '$';
        }
        return '/' . $regex . '/i';
    }

}
