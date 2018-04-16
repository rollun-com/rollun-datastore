<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @see http://tools.ietf.org/html/rfc2616#page-122
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\datastore\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface;
use rollun\actionrender\Renderer\Html\HtmlParamResolver;
use rollun\actionrender\Renderer\Html\HtmlRenderer;
use rollun\datastore\DataStore\Interfaces\ReadInterface;
use rollun\datastore\Rql\RqlQuery;
use Xiag\Rql\Parser\Node\LimitNode;
use Xiag\Rql\Parser\Node\SelectNode;
use Xiag\Rql\Parser\Query;
use rollun\datastore\DataStore\DataStoreException;
use rollun\datastore\DataStore\Interfaces\RefreshableInterface;
use rollun\datastore\Middleware;
use rollun\datastore\RestException;
use rollun\datastore\Rql\Node\AggregateFunctionNode;
use rollun\datastore\Rql\Node\AggregateSelectNode;
use Zend\Diactoros\Response\EmptyResponse;
use Zend\Diactoros\Response\JsonResponse;

/**
 * Send GET POST PUT DELETE request
 *
 * @todo to make correct 'Content-Range'
 * @todo if primary key exist but not in url
 * @category   rest
 * @package    zaboy
 */
class DataStoreRest extends Middleware\DataStoreAbstract
{

    /**
     *
     * @var ServerRequestInterface
     */
    protected $request;

    /**
     * @param ServerRequestInterface $request
     * @param DelegateInterface $delegate
     * @return Response
     */
    public function process(ServerRequestInterface $request, DelegateInterface $delegate)
    {
        $isPrimaryKeyValue = null !== $request->getAttribute('primaryKeyValue');
        $httpMethod = $request->getMethod();
        $response = new EmptyResponse();
        try {
            switch ($request->getMethod()) {
                case $httpMethod === 'GET' && $isPrimaryKeyValue:
                    $response = $this->methodGetWithId($request, $response);
                    break;
                case $httpMethod === 'GET' && !($isPrimaryKeyValue):
                    $response = $this->methodGetWithoutId($request, $response);
                    break;
                case $httpMethod === 'PUT' && $isPrimaryKeyValue:
                    $response = $this->methodPutWithId($request, $response);
                    break;
                case $httpMethod === 'PUT' && !($isPrimaryKeyValue):
                    throw new RestException('PUT without Primary Key');
                case $httpMethod === 'POST' && $isPrimaryKeyValue:
                    $response = $this->methodPostWithId($request, $response);
                    break;
                case $httpMethod === 'POST' && !($isPrimaryKeyValue):
                    $response = $this->methodPostWithoutId($request, $response);
                    break;
                case $httpMethod === 'DELETE':
                    $response = $this->methodDelete($request, $response);
                    break;
                case $httpMethod === 'DELETE' && !($isPrimaryKeyValue):
                    throw new RestException('DELETE without Primary Key');
                case $httpMethod === "PATCH":
                    $response = $this->methodRefresh($request, $response);
                    break;
                default:
                    throw new RestException(
                        'Method must be GET, PUT, POST or DELETE. '
                        . $request->getMethod() . ' given'
                    );
            }
        } catch (RestException $ex) {
            return new JsonResponse([
                $ex->getMessage()
            ], 500);
        }
        $request = $this->request->withAttribute(Response::class, $response);
        $request = $request->withAttribute(HtmlParamResolver::KEY_ATTRIBUTE_TEMPLATE_NAME, "ds-app::api-datastore");

        $response = $delegate->process($request);

        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @param Response $response
     * @return Response
     * @internal param callable|null $next
     */
    public function methodGetWithId(ServerRequestInterface $request, Response $response)
    {
        $primaryKeyValue = $request->getAttribute('primaryKeyValue');
        $row = $this->dataStore->read($primaryKeyValue);
        $this->request = $request->withAttribute('responseData', $row);

        $response = $response->withStatus(200);
        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @param Response $response
     * @return Response
     * @throws \rollun\datastore\RestException
     * @internal param callable|null $next
     */
    public function methodGetWithoutId(ServerRequestInterface $request, Response $response)
    {
        /** @var Query $rqlQueryObject */
        $rqlQueryObject = $request->getAttribute('rqlQueryObject');

        $rqlLimitNode = $rqlQueryObject->getLimit();


        /*$headerLimit = $request->getAttribute('Limit');

        if (!is_null($rqlLimitNode)) {
            if (isset($headerLimit)) {
                $limit = $rqlLimitNode->getLimit() > $headerLimit['limit'] ?
                    $headerLimit['limit'] : $rqlLimitNode->getLimit();
                if (isset($headerLimit['offset'])) {
                    $offset = $headerLimit['offset'];
                    $rqlOffset = $rqlLimitNode->getOffset();
                    if (!is_null($rqlOffset)) {
                        $offset += $rqlOffset;
                    }
                    $newLimitNode = new LimitNode($limit, $offset);
                } else {
                    $newLimitNode = new LimitNode($limit);
                }
                $rqlQueryObject->setLimit($newLimitNode);
            }
        } elseif ($headerLimit) {
            $limit = (int)$headerLimit['limit'];
            if (isset($headerLimit['offset'])) {
                $offset = (int)$headerLimit['offset'];
                $newLimitNode = new LimitNode($limit, $offset);
            } else {
                $newLimitNode = new LimitNode($limit);
            }
            $rqlQueryObject->setLimit($newLimitNode);
        }*/
        /*$rowCountQuery = new Query();
        $aggregate = new AggregateFunctionNode('count', $this->dataStore->getIdentifier());
        $rowCountQuery->setSelect(new AggregateSelectNode([$aggregate]));

        if ($rqlQueryObject->getQuery()) {
            $rowCountQuery->setQuery($rqlQueryObject->getQuery());
        }
        if ($rqlLimitNode) {
            $rowCountQuery->setLimit($rqlLimitNode);
        }*/

        //TODO: count aggregate fn can't work with limit and offset. Bug!!!
        $rowset = $this->dataStore->query($rqlQueryObject);
        $this->request = $request->withAttribute('responseData', $rowset);

        if ($rqlLimitNode) {
            $rqlQueryObject->setLimit(new LimitNode(ReadInterface::LIMIT_INFINITY));
            $rqlQueryObject->setSelect(new SelectNode([$this->dataStore->getIdentifier()]));
            $count = count($this->dataStore->query($rqlQueryObject));
            $offset = !is_null($rqlLimitNode->getOffset()) ? $rqlLimitNode->getOffset() : '0';
            $limit = !is_null($rqlLimitNode->getLimit()) ?
                ($rqlLimitNode->getLimit() == ReadInterface::LIMIT_INFINITY ? $count : $rqlLimitNode->getLimit()) :
                $count;
            $contentRange = "items $offset-$limit/$count";
        } else {
            $count = count($rowset);
            $contentRange = "items 0-$count/$count";
        }

        $response = $response->withHeader('Content-Range', $contentRange);

        /*
        $rowCountQuery = new Query();
        $rowCountQuery
            ->setSelect(new AggregateSelectNode([new AggregateFunctionNode('count', $this->dataStore->getIdentifier())]));
        $rowCount = $this->dataStore->query($rowCountQuery);
        if (isset($rowCount[0][$this->dataStore->getIdentifier() . '->count'])) {

            //throw new RestException('Can not make Content-Range header in response');

            $limitObject = $rqlQueryObject->getLimit();
            $offset = !$limitObject ? 0 : $limitObject->getOffset();
            $contentRange = 'items ' . $offset . '-' . ($offset + count($rowset) - 1) . '/' . $rowCount[0][$this->dataStore
                    ->getIdentifier() . '->count'];
            $response = $response->withHeader('Content-Range', $contentRange);

        }*/


        $response = $response->withStatus(200);
        return $response;
    }

    /**
     * @param ServerRequestInterface $request
     * @param Response $response
     * @return Response
     * @throws \rollun\datastore\RestException
     * @internal param callable|null $next
     */
    public function methodPutWithId(ServerRequestInterface $request, Response $response)
    {
        $primaryKeyValue = $request->getAttribute('primaryKeyValue');
        $primaryKeyIdentifier = $this->dataStore->getIdentifier();
        $row = $request->getParsedBody();
        if (!(isset($row) && is_array($row))) {
            throw new RestException('No body in PUT request');
        }
        $row = array_merge(array($primaryKeyIdentifier => $primaryKeyValue), $row);
        $overwriteMode = $request->getAttribute('overwriteMode');
        $isIdExist = !empty($this->dataStore->read($primaryKeyValue));

        if ($overwriteMode && !$isIdExist) {
            $response = $response->withStatus(201);
        } else {
            $response = $response->withStatus(200);
        }
        $newRow = $this->dataStore->update($row, $overwriteMode);
        $this->request = $request->withAttribute('responseData', $newRow);
        return $response;
    }

    /**                                              Location: http://www.example.com/users/4/
     * http://www.restapitutorial.com/lessons/httpmethods.html
     *
     * @param ServerRequestInterface $request
     * @param Response $response
     * @return Response
     * @throws \rollun\datastore\RestException
     * @internal param callable|null $next
     */
    public function methodPostWithId(ServerRequestInterface $request, Response $response)
    {
        $primaryKeyValue = $request->getAttribute('primaryKeyValue');
        $primaryKeyIdentifier = $this->dataStore->getIdentifier();

        $row = $request->getParsedBody();
        if (!(isset($row) && is_array($row))) {
            throw new RestException('No body in POST request');
        }

        $row = array_merge(array($primaryKeyIdentifier => $primaryKeyValue), $row);

        $overwriteMode = $request->getAttribute('overwriteMode');

        $existingRow = $this->dataStore->read($primaryKeyValue);

        $isIdExist = !empty($existingRow);

        if ($isIdExist) {
            $response = $response->withStatus(200);
        } else {
            $response = $response->withStatus(201);
            $location = $request->getUri()->getPath();
            $response = $response->withHeader('Location', $location);
        }
        $newItem = $this->dataStore->create($row, $overwriteMode);
        $this->request = $request->withAttribute('responseData', $newItem);
        return $response;
    }

    /**                                              Location: http://www.example.com/users/4/
     * http://www.restapitutorial.com/lessons/httpmethods.html
     *
     * @param ServerRequestInterface $request
     * @param Response $response
     * @return Response
     * @throws \rollun\datastore\RestException
     * @internal param callable|null $next
     */
    public function methodPostWithoutId(ServerRequestInterface $request, Response $response)
    {
        $row = $request->getParsedBody();
        if (!(isset($row) && is_array($row))) {
            throw new RestException('No body in POST request');
        }
        $primaryKeyIdentifier = $this->dataStore->getIdentifier();
        $response = $response->withStatus(201);
        $newItem = $this->dataStore->create($row);
        $insertedPrimaryKeyValue = $newItem[$primaryKeyIdentifier];
        $this->request = $request->withAttribute('responseData', $newItem);
        $location = $request->getUri()->getPath();
        $response = $response->withHeader('Location', rtrim($location, '/') . '/' . $insertedPrimaryKeyValue);
        return $response;
    }

    /**                                              Location: http://www.example.com/users/4/
     * http://www.restapitutorial.com/lessons/httpmethods.html
     *
     * @param ServerRequestInterface $request
     * @param Response $response
     * @return Response
     * @internal param callable|null $next
     */
    public function methodDelete(ServerRequestInterface $request, Response $response)
    {
        $primaryKeyValue = $request->getAttribute('primaryKeyValue');
        $items = $this->dataStore->delete($primaryKeyValue);

        if (isset($items)) {
            $response = $response->withStatus(200);
        } else {
            $response = $response->withStatus(204);
        }

        $this->request = $request->withAttribute('responseData', $items);

        return $response;
    }

    public function methodRefresh(ServerRequestInterface $request, Response $response)
    {
        if ($this->dataStore instanceof RefreshableInterface) {
            $this->dataStore->refresh();
            return $response->withStatus(200);
        } else {
            throw new DataStoreException("DataStore is not implement RefreshableInterface");
        }
    }

}
