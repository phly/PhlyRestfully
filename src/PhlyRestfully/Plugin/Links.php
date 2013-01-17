<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\Plugin;

use ArrayObject;
use Zend\EventManager\EventManager;
use Zend\EventManager\EventManagerAwareInterface;
use Zend\EventManager\EventManagerInterface;
use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\Plugin\Url as UrlHelper;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;

/**
 * Plugin for generating fully qualified links, and sets of HAL-compliant
 * link relations
 *
 * @see http://tools.ietf.org/html/draft-kelly-json-hal-03
 */
class Links extends AbstractPlugin implements EventManagerAwareInterface
{
    /**
     * @var EventManagerInterface
     */
    protected $events;

    /**
     * @var ServerUrlHelper
     */
    protected $serverUrlHelper;

    /**
     * @var UrlHelper
     */
    protected $urlHelper;

    /**
     * Retrieve the event manager instance
     *
     * Lazy-initializes one if none present.
     *
     * @return EventManagerInterface
     */
    public function getEventManager()
    {
        if (!$this->events) {
            $this->setEventManager(new EventManager());
        }
        return $this->events;
    }

    /**
     * Set the event manager instance
     *
     * @param  EventManagerInterface $events
     */
    public function setEventManager(EventManagerInterface $events)
    {
        $events->setIdentifiers(array(
            __CLASS__,
            get_class($this),
        ));
        $this->events = $events;
    }

    /**
     * @param ServerUrlHelper $helper
     */
    public function setServerUrlHelper(ServerUrlHelper $helper)
    {
        $this->serverUrlHelper = $helper;
    }

    /**
     * @param UrlHelper $helper
     */
    public function setUrlHelper(UrlHelper $helper)
    {
        $this->urlHelper = $helper;
    }

    /**
     * Create a fully qualified URI for a link
     *
     * Triggers the "createLink" event with the route, id, item, and a set of
     * params that will be passed to the route; listeners can alter any of the
     * arguments, which will then be used by the method to generate the url.
     *
     * @param  string $route
     * @param  null|int|string $id
     * @param  null|mixed $item
     * @return string
     */
    public function createLink($route, $id = null, $item = null)
    {
        $params             = new ArrayObject();
        $reUseMatchedParams = true;

        if (false === $id) {
            $reUseMatchedParams = false;
        } elseif (null !== $id) {
            $params['id'] = $id;
        }

        $events      = $this->getEventManager();
        $eventParams = $events->prepareArgs(array(
            'route'  => $route,
            'id'     => $id,
            'item'   => $item,
            'params' => $params,
        ));
        $events->trigger(__FUNCTION__, $this, $eventParams);
        $route = $eventParams['route'];

        $path = $this->urlHelper->fromRoute($route, $params->getArrayCopy(), $reUseMatchedParams);
        return $this->serverUrlHelper->__invoke($path);
    }

    /**
     * Generate HAL link relation list
     *
     * @param  array $links
     * @return array
     */
    public function generateHalLinkRelations(array $links)
    {
        $halLinks = array();
        foreach ($links as $rel => $link) {
            $halLinks[$rel] = array('href' => $link);
        }
        return $halLinks;
    }
}
