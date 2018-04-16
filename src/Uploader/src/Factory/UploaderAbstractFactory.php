<?php


namespace rollun\uploader\Callback\Factory;


use Interop\Container\ContainerInterface;
use Interop\Container\Exception\ContainerException;
use Psr\Container\ContainerExceptionInterface;
use Psr\Container\NotFoundExceptionInterface;
use rollun\uploader\Uploader;
use Zend\ServiceManager\Exception\ServiceNotCreatedException;
use Zend\ServiceManager\Exception\ServiceNotFoundException;
use Zend\ServiceManager\Factory\AbstractFactoryInterface;

class UploaderAbstractFactory implements AbstractFactoryInterface
{
    const KEY = UploaderAbstractFactory::class;

    const KEY_SOURCE_DATA_ITERATOR_AGGREGATOR = "SourceDataIteratorAggregator";

    const KEY_DESTINATION_DATA_STORE = "DestinationDataStore";

    /**
     * Can the factory create an instance for the service?
     *
     * @param  ContainerInterface $container
     * @param  string $requestedName
     * @return bool
     */
    public function canCreate(ContainerInterface $container, $requestedName)
    {
        try {
            $config = $container->get("config");
        } catch (NotFoundExceptionInterface $e) {
            return false;
        } catch (ContainerExceptionInterface $e) {
            return false;
        }
        return (
        isset($config[static::KEY][$requestedName])
        );
    }

    /**
     * Create an object
     *
     * @param  ContainerInterface $container
     * @param  string $requestedName
     * @param  null|array $options
     * @return Uploader
     * @throws \Psr\Container\ContainerExceptionInterface
     * @throws \Psr\Container\NotFoundExceptionInterface
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        $config = $container->get("config");
        $serviceConfig = $config[static::KEY][$requestedName];
        $sourceDataIteratorAggregator = $container->get($serviceConfig[static::KEY_SOURCE_DATA_ITERATOR_AGGREGATOR]);
        $destinationDataStore = $container->get($serviceConfig[static::KEY_DESTINATION_DATA_STORE]);
        return new Uploader($sourceDataIteratorAggregator, $destinationDataStore);
    }
}