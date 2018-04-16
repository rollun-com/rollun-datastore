<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 26.01.17
 * Time: 15:11
 */

namespace rollun\datastore\Middleware;

use Interop\Http\ServerMiddleware\DelegateInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use rollun\actionrender\Renderer\AbstractRenderer;

class HtmlDataPrepare implements MiddlewareInterface
{
    public function process(Request $request, DelegateInterface $delegate)
    {
        $responseData = $request->getAttribute(AbstractRenderer::RESPONSE_DATA);

        $request = $request->withAttribute(
            AbstractRenderer::RESPONSE_DATA,
            ["data" => $responseData]
        );

        $response = $delegate->process($request);

        return $response;
    }
}
