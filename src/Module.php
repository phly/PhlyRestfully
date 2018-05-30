<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

use Zend\Hydrator\HydratorPluginManager;

/**
 * ZF2 module
 */
class Module
{
    /**
     * Retrieve autoloader configuration
     *
     * @return array
     */
    public function getAutoloaderConfig()
    {
        return ['Zend\Loader\StandardAutoloader' => ['namespaces' => [
            __NAMESPACE__ => __DIR__,
        ]]];
    }

    /**
     * Retrieve module configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/../config/module.config.php';
    }

    /**
     * Retrieve Service Manager configuration
     *
     * Defines PhlyRestfully\RestfulJsonStrategy service factory.
     *
     * @return array
     */
    public function getServiceConfig()
    {
        return [
            'aliases' => [
                View\RestfulJsonRenderer::class => 'PhlyRestfully\JsonRenderer',
                View\RestfulJsonStrategy::class => 'PhlyRestfully\RestfulJsonStrategy',
            ],
            'factories' => [
                Listener\ApiProblemListener::class =>
                /**
                 * @param \Zend\ServiceManager\ServiceManager $services
                 * @return Listener\ApiProblemListener
                 */
                function ($services) {
                    $config = [];
                    if ($services->has('config')) {
                        /** @var array $config */
                        $config = $services->get('config');
                    }

                    $filter = null;
                    if (isset($config['phlyrestfully'])
                        && isset($config['phlyrestfully']['accept_filter'])
                    ) {
                        $filter = $config['phlyrestfully']['accept_filter'];
                    }

                    return new Listener\ApiProblemListener($filter);
                },
                MetadataMap::class =>
                /**
                 * @param \Zend\ServiceManager\ServiceManager $services
                 * @return MetadataMap
                 */
                function ($services) {
                    $config = [];
                    if ($services->has('config')) {
                        /** @var array $config */
                        $config = $services->get('config');
                    }

                    if ($services->has('HydratorManager')) {
                        /** @var HydratorPluginManager $hydrators */
                        $hydrators = $services->get('HydratorManager');
                    } else {
                        $hydrators = new HydratorPluginManager();
                    }

                    $map = [];
                    if (isset($config['phlyrestfully'])
                        && isset($config['phlyrestfully']['metadata_map'])
                        && is_array($config['phlyrestfully']['metadata_map'])
                    ) {
                        $map = $config['phlyrestfully']['metadata_map'];
                    }

                    return new MetadataMap($map, $hydrators);
                },
                'PhlyRestfully\JsonRenderer' =>
                /**
                 * @param \Zend\ServiceManager\ServiceManager $services
                 * @return View\RestfulJsonRenderer
                 */
                function ($services) {
                    /** @var \Zend\View\HelperPluginManager $helpers */
                    $helpers  = $services->get('ViewHelperManager');
                    /** @var array $config */
                    $config   = $services->get('config');

                    $displayExceptions = false;
                    if (isset($config['view_manager'])
                        && isset($config['view_manager']['display_exceptions'])
                    ) {
                        $displayExceptions = (bool) $config['view_manager']['display_exceptions'];
                    }

                    $renderer = new View\RestfulJsonRenderer();
                    $renderer->setHelperPluginManager($helpers);
                    $renderer->setDisplayExceptions($displayExceptions);

                    return $renderer;
                },
                'PhlyRestfully\RestfulJsonStrategy' =>
                /**
                 * @param \Zend\ServiceManager\ServiceManager $services
                 * @return View\RestfulJsonStrategy
                 */
                function ($services) {
                    /** @var View\RestfulJsonRenderer $renderer */
                    $renderer = $services->get('PhlyRestfully\JsonRenderer');
                    return new View\RestfulJsonStrategy($renderer);
                },
            ],
        ];
    }

    /**
     * Define factories for controller plugins
     *
     * Defines the "HalLinks" plugin.
     *
     * @return array
     */
    public function getControllerPluginConfig()
    {
        return ['factories' => [
            'HalLinks' =>
            /**
             * @param \Zend\ServiceManager\ServiceLocatorAwareInterface $plugins
             * @return Plugin\HalLinks
             */
            function ($plugins) {
                $services = $plugins->getServiceLocator();
                /** @var \Zend\View\HelperPluginManager $helpers */
                $helpers  = $services->get('ViewHelperManager');
                /** @var Plugin\HalLinks $halLinks */
                $halLinks = $helpers->get('HalLinks');

                return $halLinks;
            },
        ]];
    }

    /**
     * Defines the "HalLinks" view helper
     *
     * @return array
     */
    public function getViewHelperConfig()
    {
        return ['factories' => [
            'HalLinks' =>
            /**
             * @param \Zend\ServiceManager\AbstractPluginManager $helpers
             * @return Plugin\HalLinks
             */
            function ($helpers) {
                /** @var \Zend\View\Helper\ServerUrl $serverUrlHelper */
                $serverUrlHelper = $helpers->get('ServerUrl');
                /** @var \Zend\View\Helper\Url $urlHelper */
                $urlHelper       = $helpers->get('Url');

                $services        = $helpers->getServiceLocator();
                /** @var array $config */
                $config          = $services->get('config');
                /** @var MetadataMap $metadataMap */
                $metadataMap     = $services->get(MetadataMap::class);
                $hydrators       = $metadataMap->getHydratorManager();

                $helper          = new Plugin\HalLinks($hydrators);
                $helper->setMetadataMap($metadataMap);
                $helper->setServerUrlHelper($serverUrlHelper);
                $helper->setUrlHelper($urlHelper);

                if (isset($config['phlyrestfully'])
                    && isset($config['phlyrestfully']['renderer'])
                ) {
                    $config = $config['phlyrestfully']['renderer'];

                    if (isset($config['default_hydrator'])) {
                        $hydratorServiceName = $config['default_hydrator'];

                        if (!$hydrators->has($hydratorServiceName)) {
                            throw new Exception\DomainException(
                                sprintf(
                                    'Cannot locate default hydrator by name "%s" via the HydratorManager',
                                    $hydratorServiceName
                                )
                            );
                        }

                        $hydrator = $hydrators->get($hydratorServiceName);
                        $helper->setDefaultHydrator($hydrator);
                    }

                    if (isset($config['hydrators']) && is_array($config['hydrators'])) {
                        $hydratorMap = $config['hydrators'];
                        foreach ($hydratorMap as $class => $hydratorServiceName) {
                            $helper->addHydrator($class, $hydratorServiceName);
                        }
                    }
                }

                return $helper;
            }
        ]];
    }

    /**
     * Listener for bootstrap event
     *
     * Attaches a render event.
     *
     * @param  \Zend\Mvc\MvcEvent $e
     * @return void
     */
    public function onBootstrap($e)
    {
        /** @var \Zend\Mvc\ApplicationInterface $app */
        $app      = $e->getTarget();
        $services = $app->getServiceManager();
        $events   = $app->getEventManager();
        $events->attach('render', [$this, 'onRender'], 100);
        $sharedEvents = $events->getSharedManager();
        if (!$sharedEvents) {
            throw new \Exception('Could not retrieve shared event manager');
        }
        $sharedEvents->attach(
            ResourceController::class,
            'dispatch',
            /**
             * @param \Zend\Mvc\MvcEvent $e
             * @return void
             */
            function ($e) use ($services) {
                /** @var \Zend\EventManager\EventManager $eventManager */
                $eventManager = $e->getApplication()->getEventManager();
                /** @var Listener\ApiProblemListener $apiProblemListener */
                $apiProblemListener = $services->get(Listener\ApiProblemListener::class);
                $eventManager->attach($apiProblemListener);
            },
            300
        );
        /** @var \PhlyRestfully\Listener\ResourceParametersListener $paramListener */
        $paramListener = $services->get('PhlyRestfully\ResourceParametersListener');
        $paramListener->attachShared($sharedEvents);
    }

    /**
     * Listener for the render event
     *
     * Attaches a rendering/response strategy to the View.
     *
     * @param  \Zend\Mvc\MvcEvent $e
     * @return void
     */
    public function onRender($e)
    {
        $result = $e->getResult();
        if (!$result instanceof View\RestfulJsonModel) {
            return;
        }

        /** @var \Zend\Mvc\ApplicationInterface $app */
        $app                 = $e->getTarget();
        $services            = $app->getServiceManager();
        /** @var \Zend\View\View $view */
        $view                = $services->get('View');
        /** @var View\RestfulJsonStrategy $restfulJsonStrategy */
        $restfulJsonStrategy = $services->get('PhlyRestfully\RestfulJsonStrategy');
        /** @var \Zend\EventManager\EventManager $events */
        $events              = $view->getEventManager();

        // register at high priority, to "beat" normal json strategy registered
        // via view manager
        $events->attach($restfulJsonStrategy, 200);
    }
}
