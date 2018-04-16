<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\datastore\DataStore\ConditionBuilder;

use Xiag\Rql\Parser\DataType\Glob;
use Xiag\Rql\Parser\Node\Query\AbstractScalarOperatorNode;
use rollun\datastore\DataStore\DataStoreException;
use Zend\Db\Adapter\AdapterInterface;

/**
 * {@inheritdoc}
 *
 * {@inheritdoc}
 */
class SqlConditionBuilder extends ConditionBuilderAbstract
{

    protected $literals = [
        'LogicOperator' => [
            'and' => ['before' => '(', 'between' => ' AND ', 'after' => ')'],
            'or' => ['before' => '(', 'between' => ' OR ', 'after' => ')'],
            'not' => ['before' => '( NOT (', 'between' => ' error ', 'after' => ') )'],
        ],
        'ArrayOperator' => [
            'in' => ['before' => '(', 'between' => ' IN (', 'delimiter' => ',', 'after' => '))'],
            'out' => ['before' => '(', 'between' => ' NOT IN (', 'delimiter' => ',', 'after' => '))']
        ],
        'ScalarOperator' => [
            'eq' => ['before' => '(', 'between' => '=', 'after' => ')'],
            'ne' => ['before' => '(', 'between' => '<>', 'after' => ')'],
            'ge' => ['before' => '(', 'between' => '>=', 'after' => ')'],
            'gt' => ['before' => '(', 'between' => '>', 'after' => ')'],
            'le' => ['before' => '(', 'between' => '<=', 'after' => ')'],
            'lt' => ['before' => '(', 'between' => '<', 'after' => ')'],
            'like' => ['before' => '(', 'between' => ' LIKE ', 'after' => ')'],
            'contains' => ['before' => '(', 'between' => ' LIKE \'%', 'after' => '%\')'],
        ]
    ];

    /**
     *
     * @var AdapterInterface
     */
    protected $db;

    protected $tableName;

    /**
     *
     * @param AdapterInterface $dbAdapter
     * @param $tableName
     */
    public function __construct(AdapterInterface $dbAdapter, $tableName)
    {
        $this->db = $dbAdapter;
        $this->emptyCondition = $this->prepareFieldValue(1)
            . ' = '
            . $this->prepareFieldValue(1);
        $this->tableName = $tableName;
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function prepareFieldValue($fieldValue)
    {
        $fieldValue = parent::prepareFieldValue($fieldValue);
        return $this->db->platform->quoteValue($fieldValue);
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

        $regexSQL = strtr(
            preg_quote(rawurldecode(strtr($glob, ['*' => $constStar, '?' => $constQuestion])), '/'), [$constStar => '%', $constQuestion => '_']
        );

        return $regexSQL;
    }

    /**
     * Make string with conditions for ScalarOperatorNode
     *
     * @param AbstractScalarOperatorNode $node
     * @return string
     * @throws DataStoreException
     */
    public function makeScalarOperator(AbstractScalarOperatorNode $node)
    {
        $nodeName = $node->getNodeName();
        if (!isset($this->literals['ScalarOperator'][$nodeName])) {
            throw new DataStoreException(
                'The Scalar Operator not suppoted: ' . $nodeName
            );
        }
        $value = $node->getValue() instanceof \DateTime ? $node->getValue()->format("Y-m-d") : $node->getValue();
        $strQuery = $this->literals['ScalarOperator'][$nodeName]['before']
            . $this->prepareFieldName($node->getField());

        if (is_null($value)) {
            if ($nodeName === 'eq') {
                $strQuery .= "IS NULL";
            } else if ($nodeName === 'ne') {
                $strQuery .= "IS NOT NULL";
            } else {
                throw new DataStoreException("Can't use `null` for " . $nodeName . ". Only for `eq` or `ne`");
            }
        } else if ($nodeName === 'contains') {
            $strQuery .= $this->literals['ScalarOperator'][$nodeName]['between'] .
                trim($this->prepareFieldValue($value), '\'');
        } else {
            $strQuery .= $this->literals['ScalarOperator'][$nodeName]['between']
                . $this->prepareFieldValue($value);
        }

        $strQuery .= $this->literals['ScalarOperator'][$nodeName]['after'];

        return $strQuery;
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function prepareFieldName($fieldName)
    {
        if (!strpos($fieldName, '.')) {
            if ($fieldName == 'id') {
                $fieldName = $this->db->platform->quoteIdentifierInFragment("{$this->tableName}.{$fieldName}");
                /*$fieldName = $this->db->platform->quoteIdentifier($this->tableName) .  '.' .$this->db->platform->quoteIdentifier($fieldName);*/
            } else {
                $fieldName = $this->db->platform->quoteIdentifierInFragment("{$fieldName}");
            }
        } else {
            //TODO: force set tableName!!!!!
            $name = explode('.', $fieldName);
            $fieldName = $this->db->platform->quoteIdentifierInFragment("{$name[0]}.{$name[1]}");
            //$fieldName = $this->db->platform->quoteIdentifier($name[0]) . '.' . $this->db->platform->quoteIdentifier($name[1]);
        }
        return $fieldName;

    }
}