<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 08.03.17
 * Time: 13:51
 */

namespace rollun\datastore\Middleware\Factory;

use Interop\Container\ContainerInterface;
use Interop\Http\ServerMiddleware\MiddlewareInterface;
use rollun\datastore\DataStore\DbTable;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Factory\AbstractFactoryInterface;
use rollun\datastore\Middleware;
use rollun\datastore\DataStore\Interfaces\DataStoresInterface;

class ImplicitDataStoreMiddlewareAbstractFactory implements AbstractFactoryInterface
{
    const KEY_MIDDLEWARE_POSTFIX = "DataStoreMiddleware";

    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string $requestedName
     * @param  null|array $options
     * @return object
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $resourceName = $this->getResourceName($requestedName);
        $resourceObject = $container->get($resourceName);
        switch (true) {
            case is_a($resourceObject, 'Zend\Db\TableGateway\TableGateway'):
                $tableGateway = $resourceObject;
                $resourceObject = new DbTable($tableGateway);
            case ($resourceObject instanceof DataStoresInterface):
                $dataStore = $resourceObject;
                $resourceObject = new Middleware\DataStoreRest($dataStore);
            case $resourceObject instanceof MiddlewareInterface:
                $storeMiddleware = $resourceObject;
                break;
            default:
                if (!isset($storeMiddleware)) {
                    throw new ServiceNotCreatedException(
                        'Can\'t make Middleware\DataStoreRest'
                        . ' for resource: ' . $resourceName
                    );
                }
        }
        return $storeMiddleware;

    }

    /**
     * @param $requestedName
     * @return string
     */
    protected function getResourceName($requestedName) {
        if(preg_match('/^(?<resourceName>[\w\W]+)DataStoreMiddleware$/', $requestedName, $match)) {
            return $match["resourceName"];
        }
        return "";
    }

    /**
     * Can the factory create an instance for the service?
     *
     * @param  ContainerInterface $container
     * @param  string $requestedName
     * @return bool
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        $resourceName = $this->getResourceName($requestedName);
        if(empty($resourceName)) return false;
        return $container->has($resourceName);
    }
}
