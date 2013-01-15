<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\Plugin;

use Zend\Mvc\Controller\Plugin\AbstractPlugin;
use Zend\Mvc\Controller\Plugin\Url as UrlHelper;
use Zend\View\Helper\ServerUrl as ServerUrlHelper;

/**
 * Plugin for generating fully qualified links, and sets of HAL-compliant
 * link relations
 *
 * @see http://tools.ietf.org/html/draft-kelly-json-hal-03
 */
class Links extends AbstractPlugin
{
    /**
     * Additional parameters to use when generating a URL
     * 
     * @var array
     */
    protected $routeParams = array();

    /**
     * @var ServerUrlHelper
     */
    protected $serverUrlHelper;

    /**
     * @var UrlHelper
     */
    protected $urlHelper;

    /**
     * Set additional parameters to use when generating a URL
     *
     * Pass an empty array to clear any previously set params.
     * 
     * @param  array $params 
     */
    protected function setRouteParams(array $params)
    {
        $this->routeParams = $params;
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
     * @param  string $route
     * @param  null|int|string $id
     * @return string
     */
    public function createLink($route, $id = null)
    {
        $params = $this->routeParams;
        if (null !== $id) {
            $params['id'] = $id;
        }

        $path = $this->urlHelper->fromRoute($route, $params, true);
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
