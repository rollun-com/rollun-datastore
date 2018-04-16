<?php

/**
 * Zaboy lib (http://zaboy.org/lib/)
 *
 * @copyright  Zaboychenko Andrey
 * @license http://opensource.org/licenses/gpl-license.php GNU Public License
 */

namespace rollun\datastore\DataStore\Factory;

use Interop\Container\ContainerInterface;
use rollun\datastore\DataStore\DataStoreException;
use rollun\datastore\DataStore\HttpClient;
use Zend\Http\Client;

/**
 * Create and return an instance of the DataStore which based on Http Client
 *
 * The configuration can contain:
 * <code>
 * 'DataStore' => [
 *
 *     'HttpClient' => [
 *         'class' => 'rollun\datastore\DataStore\HttpDatastoreClassname',
 *          'url' => 'http://site.com/api/resource-name',
 *          'options' => [
 *              'timeout' => 30,
 *              'adapter' => 'Zend\Http\Client\Adapter\Socket',
 *          ]
 *     ]
 * ]
 * </code>
 *
 * @category   rest
 * @package    zaboy
 */
class HttpClientAbstractFactory extends DataStoreAbstractFactory
{
    const KEY_URL = 'url';

    const KEY_OPTIONS = 'options';

    const KEY_HTTP_CLIENT = "httpClient";

    public static $KEY_DATASTORE_CLASS = HttpClient::class;

    protected static $KEY_IN_CREATE = 0;

    /**
     * {@inheritdoc}
     *
     * {@inheritdoc}
     */
    public function __invoke(ContainerInterface $container, $requestedName, array $options = null)
    {
        if ($this::$KEY_IN_CREATE) {
            throw new DataStoreException("Create will be called without pre call canCreate method");
        }
        $this::$KEY_IN_CREATE = 1;

        $config = $container->get('config');
        $serviceConfig = $config[self::KEY_DATASTORE][$requestedName];
        $requestedClassName = $serviceConfig[self::KEY_CLASS];
        if(isset($serviceConfig[self::KEY_HTTP_CLIENT])) {
            $packArgs[]/*$httpClient*/ = $container->get($serviceConfig[self::KEY_HTTP_CLIENT]);
        } else {
            $packArgs[]/*$httpClient*/ = new Client();
        }
        if (isset($serviceConfig[self::KEY_URL])) {
            $packArgs[]/*$url*/ = $serviceConfig[self::KEY_URL];
        } else {
            $this::$KEY_IN_CREATE = 0;
            throw new DataStoreException(
                'There is not url for ' . $requestedName . 'in config \'dataStore\''
            );
        }
        if (isset($serviceConfig[self::KEY_OPTIONS])) {
            $packArgs[]/*$options*/ = $serviceConfig[self::KEY_OPTIONS];
        }
        $result = new $requestedClassName(...$packArgs);
        $this::$KEY_IN_CREATE = 0;
        return $result;
    }


}
