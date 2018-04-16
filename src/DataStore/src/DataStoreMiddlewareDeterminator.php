<?php


namespace rollun\datastore;

use Psr\Http\Message\ServerRequestInterface as Request;
use rollun\actionrender\MiddlewareDeterminator\AttributeParam;
use rollun\datastore\Middleware\Factory\ImplicitDataStoreMiddlewareAbstractFactory;

class DataStoreMiddlewareDeterminator extends AttributeParam
{
    const KEY_MIDDLEWARE_POSTFIX = ImplicitDataStoreMiddlewareAbstractFactory::KEY_MIDDLEWARE_POSTFIX;

    /**
     * @param Request $request
     * @return string
     */
    public function getMiddlewareServiceName(Request $request)
    {
        $serviceName = parent::getMiddlewareServiceName($request);
        $serviceName .= static::KEY_MIDDLEWARE_POSTFIX;
        return $serviceName;
    }
}