<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\datastore;

use Zend\ServiceManager\Factory\FactoryInterface;
use Interop\Container\ContainerInterface;

abstract class FactoryAbstract implements FactoryInterface
{

    /**
     * Alias for "createService"
     *
     * @param ContainerInterface $container
     * @param $requestedName
     * @param array|null $options
     * @return mixed
     */
    abstract public function __invoke(ContainerInterface $container, $requestedName, array $options = null);
}
