<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\datastore\DataStore;

use rollun\datastore\DataStore\DataStoreAbstract;
use rollun\datastore\DataStore\DataStoreException;
use rollun\datastore\DataStore\ConditionBuilder\RqlConditionBuilder;
use rollun\utils\Json\Serializer;
use Xiag\Rql\Parser\Query;
use Xiag\Rql\Parser\Node\SortNode;
use rollun\datastore\Rql\RqlParser;
use Zend\Http\Client;
use Zend\Http\Request;
use Zend\Json\Json;

/**
 * DataStores as http Client
 *
 * @uses Client
 * @see https://github.com/zendframework/zend-db
 * @see http://en.wikipedia.org/wiki/Create,_read,_update_and_delete
 * @category   rest
 * @package    zaboy
 * @todo Json::decode - try cathe
 */
class HttpClient extends DataStoreAbstract
{

    /**
     * @var string 'http://example.org'
     */
    protected $url;

    /**
     * @var string 'mylogin'
     * @see https://en.wikipedia.org/wiki/Basic_access_authentication
     */
    protected $login;

    /**
     * @var string 'kjfgn&56Ykjfnd'
     * @see https://en.wikipedia.org/wiki/Basic_access_authentication
     */
    protected $password;

    /**
     * @var Client
     */
    protected $client;

    /**
     * @var array
     */
    protected $options = [];

    /**
     *
     * @param string $url 'http://example.org'
     * @param Client $client
     * @param array $options
     */
    public function __construct(Client $client, $url, $options = null)
    {
        $this->client = $client;
        $this->url = rtrim(trim($url), '/');
        if (is_array($options)) {
            if (isset($options['login']) && isset($options['password'])) {
                $this->login = $options['login'];
                $this->password = $options['password'];
            }
            $supportedKeys = [
                'maxredirects',
                'useragent',
                'adapter',
                'timeout',
                'curloptions'
            ];
            $this->options = array_intersect_key($options, array_flip($supportedKeys));
        }
        $this->conditionBuilder = new RqlConditionBuilder;
    }

//** Interface "rollun\datastore\DataStore\Interfaces\ReadInterface" **/

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function read($id)
    {
        $this->checkIdentifierType($id);
        $client = $this->initHttpClient(Request::METHOD_GET, null, $id);
        $response = $client->send();
        if ($response->isOk()) {
            $result = Serializer::jsonUnserialize($response->getBody());
        } else {
            throw new DataStoreException(
            'Status: ' . $response->getStatusCode()
            . ' - ' . $response->getReasonPhrase()
            );
        }
        return $result;
    }

    /**
     *
     * @param string 'GET' 'HEAD' 'POST' 'PUT' 'DELETE';
     * @param Query $query
     * @param int|string $id
     * @param bool see $ifMatch $rewriteIfExist and $createIfAbsent in {@see DataStoreAbstract}
     * @return Client
     */
    protected function initHttpClient($method, Query $query = null, $id = null, $ifMatch = false)
    {
        $url = !$id ? $this->url : $this->url . '/' . $this->encodeString($id);
        if (isset($query)) {
            $rqlString = RqlParser::rqlEncode($query);
            $url = $url . '?' . $rqlString;
        }
        $httpClient = clone $this->client;
        $httpClient->setUri($url);
        $httpClient->setOptions($this->options);
        $headers['Content-Type'] = 'application/json';
        $headers['Accept'] = 'application/json';
        $headers['APP_ENV'] = constant('APP_ENV');
        if ($ifMatch) {
            $headers['If-Match'] = '*';
        }
        $httpClient->setHeaders($headers);
        if (isset($this->login) && isset($this->password)) {
            $httpClient->setAuth($this->login, $this->password);
        }
        $httpClient->setMethod($method);
        return $httpClient;
    }

// ** Interface "rollun\datastore\DataStore\Interfaces\DataStoresInterface"  **/

    protected function encodeString($value)
    {
        return strtr(rawurlencode($value), [
            '-' => '%2D',
            '_' => '%5F',
            '.' => '%2E',
            '~' => '%7E',
        ]);
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function query(Query $query)
    {
        $client = $this->initHttpClient(Request::METHOD_GET, $query);
        $response = $client->send();
        if ($response->isOk()) {
            $result = Serializer::jsonUnserialize($response->getBody());
        } else {
            throw new DataStoreException(
            'Status: ' . $response->getStatusCode()
            . ' - ' . $response->getReasonPhrase()
            );
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function create($itemData, $rewriteIfExist = false)
    {
        $identifier = $this->getIdentifier();
        if (isset($itemData[$identifier])) {
            $id = $itemData[$identifier];
            $this->checkIdentifierType($id);
        } else {
            $id = null;
        }
        $client = $this->initHttpClient(Request::METHOD_POST, null, $id, $rewriteIfExist);
        $json = Serializer::jsonSerialize($itemData);
        $client->setRawBody($json);
        $response = $client->send();
        if ($response->isSuccess()) {
            $result = Serializer::jsonUnserialize($response->getBody());
        } else {
            throw new DataStoreException(
            'Status: ' . $response->getStatusCode()
            . ' - ' . $response->getReasonPhrase()
            );
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function update($itemData, $createIfAbsent = false)
    {
        $identifier = $this->getIdentifier();
        if (!isset($itemData[$identifier])) {
            throw new DataStoreException('Item must has primary key');
        }
        $id = $itemData[$identifier];
        $this->checkIdentifierType($id);

        $client = $this->initHttpClient(Request::METHOD_PUT, null, $id, $createIfAbsent);
        $client->setRawBody(Serializer::jsonSerialize($itemData));
        $response = $client->send();
        if ($response->isSuccess()) {
            $result = Serializer::jsonUnserialize($response->getBody());
        } else {
            throw new DataStoreException(
            'Status: ' . $response->getStatusCode()
            . ' - ' . $response->getReasonPhrase()
            );
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function delete($id)
    {
        $this->checkIdentifierType($id);
        $client = $this->initHttpClient(Request::METHOD_DELETE, null, $id);
        $response = $client->send();
        if ($response->isSuccess()) {
            $result = !empty($response->getBody()) ? Serializer::jsonUnserialize($response->getBody()) : null;
        } else {
            throw new DataStoreException(
            'Status: ' . $response->getStatusCode()
            . ' - ' . $response->getReasonPhrase()
            );
        }
        return $result;
    }

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function count()
    {
        return parent::count();
    }

}
