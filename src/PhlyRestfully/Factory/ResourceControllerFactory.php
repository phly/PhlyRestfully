<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\Factory;

use PhlyRestfully\Resource;
use PhlyRestfully\ResourceController;
use Zend\ServiceManager\AbstractFactoryInterface;
use Zend\ServiceManager\ServiceLocatorInterface;

/**
 * Class ResourceControllerFactory
 * @package PhlyRestfully\Factory
 */
class ResourceControllerFactory implements AbstractFactoryInterface
{
    /**
     * Determine if we can create a service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param string                  $name
     * @param string                  $requestedName
     *
     * @return bool
     */
    public function canCreateServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        $config = $serviceLocator->getServiceLocator()->get('Config')['phlyrestfully']['resources'];
        return isset($config[$requestedName]);
    }

    /**
     * Create service with name
     *
     * @param ServiceLocatorInterface $serviceLocator
     * @param string                  $name
     * @param string                  $requestedName
     *
     * @return mixed
     */
    public function createServiceWithName(ServiceLocatorInterface $serviceLocator, $name, $requestedName)
    {
        /**
         * @var $service \Zend\ServiceManager\ServiceLocatorInterface
         */
        $services = $serviceLocator->getServiceLocator();
        $config   = $services->get('Config')['phlyrestfully']['resources'][$requestedName];

        /**
         * @var $events   \Zend\EventManager\EventManagerInterface
         * @var $listener \Zend\EventManager\ListenerAggregateInterface
         */
        $events   = $services->get('EventManager');
        $listener = $services->get($config['listener']);

        $events->attach($listener);

        $resource   = new Resource();
        $resource->setEventManager($events);


        $controller = new ResourceController();
        $controller->setResource($resource);
        $controller->setRoute($config['route_name']);
        $controller->setResourceHttpOptions($config['resource_http_options']);
        $controller->setCollectionHttpOptions($config['collection_http_options']);

        if (isset($config['identifier_name'])) {
            $controller->setIdentifierName($config['identifier_name']);
        }

        return $controller;
    }
}
