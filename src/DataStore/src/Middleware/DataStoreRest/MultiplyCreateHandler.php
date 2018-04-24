<?php


namespace rollun\datastore\Middleware\DataStoreRest;


use Interop\Http\ServerMiddleware\DelegateInterface;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use rollun\actionrender\Renderer\AbstractRenderer;
use rollun\datastore\RestException;
use Zend\Diactoros\Response;
use Zend\Diactoros\Stream;

class MultiplyCreateHandler extends AbstractHandler
{
    /**
     * check if datastore rest middleware may handle this request
     * @param ServerRequestInterface $request
     * @return bool
     */
    protected function canHandle(ServerRequestInterface $request): bool
    {
        $canHandle = $request->getMethod() === "POST";
        $row = $request->getParsedBody();

        $primaryKeyValue = $request->getAttribute('primaryKeyValue');
        $canHandle = $canHandle && is_null($primaryKeyValue);

        $canHandle = $canHandle && isset($row) && is_array($row) && array_reduce(array_keys($row), function ($carry, $item) {
                return $carry && is_integer($item);
            }, true);
        return $canHandle;
    }

    /**
     * Handle request to dataStore;
     * @param ServerRequestInterface $request
     * @return ResponseInterface
     */
    protected function handle(ServerRequestInterface $request): ResponseInterface
    {
        $itemsData = $request->getParsedBody();

        $newItemsData = $this->dataStore->multiCreate($itemsData);

        $response = new Response();
        $stream = fopen("data://text/plain;base64,".base64_encode(serialize($newItemsData)), 'r');
        $response = $response->withBody(new Stream($stream));
        return $response;
    }
}