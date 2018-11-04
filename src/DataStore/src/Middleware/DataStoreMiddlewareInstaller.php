<?php
/**
 * @copyright Copyright © 2014 Rollun LC (http://rollun.com/)
 * @license LICENSE.md New BSD License
 */

namespace rollun\datastore\Middleware;

use rollun\datastore\DataStore\DataStorePluginManager;
use rollun\datastore\DataStore\DataStorePluginManagerFactory;
use rollun\installer\Install\InstallerAbstract;
use Zend\ServiceManager\Factory\InvokableFactory;

class DataStoreMiddlewareInstaller extends InstallerAbstract
{
    /**
     * install
     * @return array
     */
    public function install()
    {
        return [
            'dependencies' => [
                'factories' => [
                    ResourceResolver::class => InvokableFactory::class,
                    RequestDecoder::class => InvokableFactory::class,
                    DataStoreRest::class => DeterminatorFactory::class,
                    DataStorePluginManager::class => DataStorePluginManagerFactory::class,
                ],
            ],
        ];
    }

    /**
     * Clean all installation
     * @return void
     */
    public function uninstall()
    {

    }

    /**
     * Return string with description of installable functional.
     * @param string $lang ; set select language for description getted.
     * @return string
     */
    public function getDescription($lang = "en")
    {
        switch ($lang) {
            case "ru":
                $description = "Позволяет обращаться к хранилищу по http.";
                break;
            default:
                $description = "Does not exist.";
        }

        return $description;
    }

    public function isInstall()
    {
        $config = $this->container->get('config');

        return (isset($config['dependencies']['factories'])
            && in_array(ResourceResolver::class, $config['dependencies']['factories'])
            && in_array(RequestDecoder::class, $config['dependencies']['factories'])
            && in_array(DataStoreRest::class, $config['dependencies']['factories'])
            && in_array(DataStorePluginManager::class, $config['dependencies']['factories']));
    }
}
