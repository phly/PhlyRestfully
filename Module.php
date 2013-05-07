<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

use Zend\Stdlib\Hydrator\HydratorInterface;

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
        return array('Zend\Loader\StandardAutoloader' => array('namespaces' => array(
            __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
        )));
    }

    /**
     * Retrieve module configuration
     *
     * @return array
     */
    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
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
        return array('factories' => array(
            'PhlyRestfully\ApiProblemListener' => function ($services) {
                $config = array();
                if ($services->has('config')) {
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
            'PhlyRestfully\JsonRenderer' => function ($services) {
                $helpers  = $services->get('ViewHelperManager');
                $config   = $services->get('Config');

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
            'PhlyRestfully\RestfulJsonStrategy' => function ($services) {
                $renderer = $services->get('PhlyRestfully\JsonRenderer');
                return new View\RestfulJsonStrategy($renderer);
            },
        ));
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
        return array('factories' => array(
            'HalLinks' => function ($plugins) {
                $services = $plugins->getServiceLocator();
                $helpers  = $services->get('ViewHelperManager');
                return $helpers->get('HalLinks');
            },
        ));
    }

    /**
     * Defines the "HalLinks" view helper
     *
     * @return array
     */
    public function getViewHelperConfig()
    {
        return array('factories' => array(
            'HalLinks' => function ($helpers) {
                $serverUrlHelper = $helpers->get('ServerUrl');
                $urlHelper       = $helpers->get('Url');

                $services        = $helpers->getServiceLocator();
                $config          = $services->get('Config');
                $request         = $services->get('Request');

                $helper          = new Plugin\HalLinks();
                $helper->setRequest($request);
                $helper->setServerUrlHelper($serverUrlHelper);
                $helper->setUrlHelper($urlHelper);

                if (isset($config['phlyrestfully'])
                    && isset($config['phlyrestfully']['renderer'])
                ) {
                    $config = $config['phlyrestfully']['renderer'];

                    if (isset($config['default_hydrator'])) {
                        $hydratorServiceName = $config['default_hydrator'];

                        $hydrator = $services->get($hydratorServiceName);

                        if ($hydrator instanceof HydratorInterface) {
                            $helper->setDefaultHydrator($hydrator);
                        } else {
                            throw new Exception\DomainException(
                                sprintf(
                                    'Hydrator %s must implement the Zend\Stdlib\Hydrator\HydratorInterface' .
                                        'to be used as the default hydrator.',
                                    get_class($hydrator)
                                )
                            );
                        }
                    }

                    if (isset($config['hydrators']) && is_array($config['hydrators'])) {
                        $hydratorMap = $config['hydrators'];
                        foreach ($hydratorMap as $class => $hydratorServiceName) {
                            $hydrator = $services->get($hydratorServiceName);
                            if ($hydrator instanceof HydratorInterface) {
                                $helper->addHydrator($class, $hydrator);
                            } else {
                                throw new Exception\DomainException(
                                    sprintf(
                                        'Hydrator %s for class %s must implement the' .
                                            'Zend\Stdlib\Hydrator\HydratorInterface',
                                        get_class($hydrator), $class
                                    )
                                );
                            }
                        }
                    }
                }

                return $helper;
            }
        ));
    }

    /**
     * Listener for bootstrap event
     *
     * Attaches a render event.
     *
     * @param  \Zend\Mvc\MvcEvent $e
     */
    public function onBootstrap($e)
    {
        $app      = $e->getTarget();
        $services = $app->getServiceManager();
        $events   = $app->getEventManager();
        $events->attach('render', array($this, 'onRender'), 100);
        $sharedEvents = $events->getSharedManager();
        $sharedEvents->attach('PhlyRestfully\ResourceController', 'dispatch', function($e) use ($services) {
            $eventManager = $e->getApplication()->getEventManager();
            $eventManager->attach($services->get('PhlyRestfully\ApiProblemListener'));
        }, 300);
        $sharedEvents->attachAggregate($services->get('PhlyRestfully\ResourceParametersListener'));
    }

    /**
     * Listener for the render event
     *
     * Attaches a rendering/response strategy to the View.
     *
     * @param  \Zend\Mvc\MvcEvent $e
     */
    public function onRender($e)
    {
        $result = $e->getResult();
        if (!$result instanceof View\RestfulJsonModel) {
            return;
        }

        $app                 = $e->getTarget();
        $services            = $app->getServiceManager();
        $view                = $services->get('View');
        $restfulJsonStrategy = $services->get('PhlyRestfully\RestfulJsonStrategy');
        $events              = $view->getEventManager();

        // register at high priority, to "beat" normal json strategy registered
        // via view manager
        $events->attach($restfulJsonStrategy, 200);
    }
}
