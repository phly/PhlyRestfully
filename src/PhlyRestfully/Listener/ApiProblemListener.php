<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\Listener;

use PhlyRestfully\RestfulJsonModel;
use Zend\EventManager\EventManagerInterface;
use Zend\EventManager\ListenerAggregateInterface;
use Zend\Mvc\MvcEvent;
use Zend\View\Model\ModelInterface;

/**
 * ApiProblemListener
 *
 * Provides a listener on the render event, at high priority.
 *
 * If the MvcEvent represents an error, then its view model and result are
 * replaced with a RestfulJsonModel containing an API-Problem payload.
 */
class ApiProblemListener implements ListenerAggregateInterface
{
    /**
     * @var \Zend\Stdlib\CallbackHandler[]
     */
    protected $listeners = array();

    /**
     * @param EventManagerInterface $events
     */
    public function attach(EventManagerInterface $events)
    {
        $events->attach(MvcEvent::EVENT_RENDER, __CLASS__ . '::onRender', 1000);
    }

    /**
     * @param EventManagerInterface $events
     */
    public function detach(EventManagerInterface $events)
    {
        foreach ($this->listeners as $index => $listener) {
            if ($events->detach($listener)) {
                unset($this->listeners[$index]);
            }
        }
    }

    /**
     * Listen to the render event
     *
     * @param MvcEvent $e
     */
    public static function onRender(MvcEvent $e)
    {
        // only worried about error pages
        if (!$e->isError()) {
            return;
        }

        // and then, only if we have an Accept header...
        $request = $e->getRequest();
        $headers = $request->getHeaders();
        if (!$headers->has('Accept')) {
            return;
        }

        // ... that matches certain criteria
        $accept = $headers->get('Accept');
        if (!$accept->match('*/json')) {
            return;
        }

        // Next, do we have a view model in the result?
        // If not, nothing more to do.
        $model = $e->getResult();
        if (!$model instanceof ModelInterface) {
            return;
        }

        // Marshall the information we need for the API-Problem response
        $httpStatus       = $e->getResponse()->getStatusCode();
        $app              = $e->getApplication();
        $services         = $app->getServiceManager();
        $plugins          = $services->get('ControllerPluginManager');
        $apiProblemPlugin = $plugins->get('apiproblemresult');
        $exception        = $model->getVariable('exception');
        if ($exception instanceof \Exception) {
            $variables = $apiProblemPlugin($httpStatus, $exception);
        } else {
            $variables = $apiProblemPlugin($httpStatus, $model->getVariable('message'));;
        }

        // Create a new model with the API-Problem payload, and reset
        // the result and view model in the event using it.
        $model = new RestfulJsonModel($variables);
        $model->setTerminal(true);
        $e->setResult($model);
        $e->setViewModel($model);
    }
}
