<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 06.02.17
 * Time: 16:26
 */

namespace rollun\datastore\Middleware\Factory;

use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Psr\Http\Message\ResponseInterface as Response;
use Psr\Http\Message\ServerRequestInterface as Request;
use rollun\datastore\Middleware\HtmlDataPrepare;
use Zend\Expressive\Template\TemplateRendererInterface;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Factory\FactoryInterface;
use Zend\Stratigility\MiddlewareInterface;

class HtmlDataStoreRendererFactory implements FactoryInterface
{


    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string $requestedName
     * @param  null|array $options
     * @return object
     * @throws \Exception
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        if ($container->has(TemplateRendererInterface::class)) {
            return new HtmlDataPrepare($container->get(TemplateRendererInterface::class));
        }
        throw new \Exception(TemplateRendererInterface::class . " not fount in container");
    }
}
