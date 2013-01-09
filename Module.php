<?php

namespace PhlyRestfully;

class Module
{
    public function getAutoloaderConfig()
    {
        return array('Zend\Loader\StandardAutoloader' => array('namespaces' => array(
            __NAMESPACE__ => __DIR__ . '/src/' . __NAMESPACE__,
        )));
    }

    public function getConfig()
    {
        return include __DIR__ . '/config/module.config.php';
    }

    public function getServiceConfig()
    {
        return array('factories' => array(
            'PhlyRestfully\RestfulJsonStrategy' => function ($services) {
                $renderer = $services->get('PhlyRestfully\JsonRenderer');
                return new RestfulJsonStrategy($renderer);
            },
        ));
    }

    public function onBootstrap($e)
    {
        $app    = $e->getTarget();
        $events = $app->getEventManager();
        $events->attach('render', array($this, 'onRender'), 100);
    }

    public function onRender($e)
    {
        $result = $e->getResult();
        if (!$result instanceof RestfulJsonModel) {
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
