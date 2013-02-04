<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\View\Helper;

use Zend\View\Helper\AbstractHelper;
use Zend\View\Helper\ServerUrl;
use Zend\View\Helper\Url;

class HalLinks extends AbstractHelper
{
    protected $serverUrlHelper;
    protected $urlHelper;

    public function setServerUrlHelper(ServerUrl $helper)
    {
        $this->serverUrlHelper = $helper;
    }

    public function setUrlHelper(Url $helper)
    {
        $this->urlHelper = $helper;
    }

    public function forItem($id, $route, array $routeParams = array())
    {
        $routeParams['id'] = $id;
        $path = call_user_func($this->urlHelper, $route, $routeParams);
        return array(
            'self' => call_user_func($this->serverUrlHelper, $path),
        );
    }

    public function forCollection($route, array $routeParams = array())
    {
        $path = call_user_func($this->urlHelper, $route, $routeParams);
        return array(
            'self' => call_user_func($this->serverUrlHelper, $path),
        );
    }
}
