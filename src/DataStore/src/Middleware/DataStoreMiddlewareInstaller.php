<?php
/**
 * Created by PhpStorm.
 * User: root
 * Date: 13.03.17
 * Time: 11:32
 */

namespace rollun\datastore\Middleware;

use rollun\actionrender\Factory\ActionRenderAbstractFactory;
use rollun\actionrender\Factory\LazyLoadMiddlewareAbstractFactory;
use rollun\actionrender\Factory\MiddlewarePipeAbstractFactory;
use rollun\actionrender\Installers\ActionRenderInstaller;
use rollun\actionrender\Installers\BasicRenderInstaller;;

use rollun\actionrender\MiddlewareDeterminator\AttributeParam;
use rollun\actionrender\MiddlewareDeterminator\Factory\AbstractMiddlewareDeterminatorAbstractFactory;
use rollun\actionrender\MiddlewareDeterminator\Factory\AttributeParamAbstractFactory;
use rollun\actionrender\MiddlewareDeterminator\Factory\HeaderSwitchAbstractFactory;
use rollun\actionrender\MiddlewareDeterminator\HeaderSwitch;
use rollun\actionrender\MiddlewareDeterminator\Installers\AttributeParamInstaller;
use rollun\actionrender\MiddlewareDeterminator\Installers\HeaderSwitchInstaller;
use rollun\actionrender\MiddlewarePluginManager;
use rollun\actionrender\Renderer\Json\JsonRenderer;
use rollun\datastore\DataStoreMiddlewareDeterminator;
use \rollun\datastore\Middleware;
use \rollun\actionrender\Renderer;
use rollun\datastore\Middleware\Factory\ImplicitDataStoreMiddlewareAbstractFactory;
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
                    HtmlDataPrepare::class => InvokableFactory::class,
                ],
                "abstract_factories" => [
                    ImplicitDataStoreMiddlewareAbstractFactory::class
                ]
            ],
            AbstractMiddlewareDeterminatorAbstractFactory::KEY => [
                'dataStoreHtmlJsonRenderer' => [
                    HeaderSwitchAbstractFactory::KEY_CLASS => HeaderSwitch::class,
                    HeaderSwitchAbstractFactory::KEY_NAME => "Accept",
                    HeaderSwitchAbstractFactory::KEY_MIDDLEWARE_MATCHING => [
                        '/application\/json/' => JsonRenderer::class,
                        '/text\/html/' => 'dataStoreHtmlRenderer'
                    ],
                ],
                DataStoreMiddlewareDeterminator::class => [
                    AttributeParamAbstractFactory::KEY_CLASS => DataStoreMiddlewareDeterminator::class,
                    AttributeParamAbstractFactory::KEY_NAME => "resourceName",
                ]
            ],
            LazyLoadMiddlewareAbstractFactory::KEY => [
                'dataStoreLLPipe' => [
                    LazyLoadMiddlewareAbstractFactory::KEY_MIDDLEWARE_DETERMINATOR => DataStoreMiddlewareDeterminator::class,
                    LazyLoadMiddlewareAbstractFactory::KEY_MIDDLEWARE_PLUGIN_MANAGER => MiddlewarePluginManager::class
                ],
                'dataStoreHtmlJsonRendererLLPipe' => [
                    LazyLoadMiddlewareAbstractFactory::KEY_MIDDLEWARE_DETERMINATOR => "dataStoreHtmlJsonRenderer",
                    LazyLoadMiddlewareAbstractFactory::KEY_MIDDLEWARE_PLUGIN_MANAGER => MiddlewarePluginManager::class,

                ]
            ],
            ActionRenderAbstractFactory::KEY => [
                'api-datastore' => [
                    ActionRenderAbstractFactory::KEY_ACTION_MIDDLEWARE_SERVICE => 'apiDataStoreAction',
                    ActionRenderAbstractFactory::KEY_RENDER_MIDDLEWARE_SERVICE => 'dataStoreHtmlJsonRendererLLPipe'
                ],
            ],

            MiddlewarePipeAbstractFactory::KEY => [
                'apiDataStoreAction' => [
                    MiddlewarePipeAbstractFactory::KEY_MIDDLEWARES => [
                        Middleware\ResourceResolver::class,
                        Middleware\RequestDecoder::class,
                        'dataStoreLLPipe'
                    ]
                ],
                'dataStoreHtmlRenderer' => [
                    MiddlewarePipeAbstractFactory::KEY_MIDDLEWARES => [
                        Renderer\Html\HtmlParamResolver::class,
                        Middleware\HtmlDataPrepare::class,
                        Renderer\Html\HtmlRenderer::class,
                    ]
                ]
            ],
            'routes' => [
                [
                    'name' => 'api-datastore',
                    'path' => '/api/datastore[/{resourceName}[/{id}]]',
                    'middleware' => 'api-datastore',
                    'allowed_methods' => ['GET', 'POST', 'PUT', 'DELETE', 'PATCH'],
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
        return (
            isset($config['dependencies']['factories']) &&
            in_array(ImplicitDataStoreMiddlewareAbstractFactory::class,$config['dependencies']['abstract_factories']) &&
            in_array(ResourceResolver::class,$config['dependencies']['factories']) &&
            in_array( RequestDecoder::class,$config['dependencies']['factories']) &&
            in_array( HtmlDataPrepare::class,$config['dependencies']['factories']) &&
            in_array( DataStoreMiddlewareDeterminator::class,$config['dependencies']['factories']) &&
            isset($config[AbstractMiddlewareDeterminatorAbstractFactory::KEY]['dataStoreHtmlJsonRenderer']) &&
            isset($config[LazyLoadMiddlewareAbstractFactory::KEY]['dataStoreLLPipe']) &&
            isset($config[LazyLoadMiddlewareAbstractFactory::KEY]['dataStoreHtmlJsonRendererLLPipe']) &&
            isset($config[ActionRenderAbstractFactory::KEY]['api-datastore'])
        );
    }

    public function getDependencyInstallers()
    {
        return [
            BasicRenderInstaller::class,
            ActionRenderInstaller::class,
            AttributeParamInstaller::class,
            HeaderSwitchInstaller::class,
        ];
    }


}
