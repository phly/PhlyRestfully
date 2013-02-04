<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\View\Helper;

use PhlyRestfully\ApiProblem;
use PhlyRestfully\Exception;
use PhlyRestfully\HalCollection;
use Zend\Paginator\Paginator;
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
            'self' => array(
                'href' => call_user_func($this->serverUrlHelper, $path),
            ),
        );
    }

    public function forCollection($route, array $routeParams = array())
    {
        $path = call_user_func($this->urlHelper, $route, $routeParams);
        return array(
            'self' => array(
                'href' => call_user_func($this->serverUrlHelper, $path),
            ),
        );
    }

    public function forPaginatedCollection(HalCollection $halCollection)
    {
        $collection = $halCollection->collection;
        if (!$collection instanceof Paginator) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid collection provided: must be a Paginator instance; received "%s"',
                get_class($collection)
            ));
        }

        $page     = $halCollection->page;
        $pageSize = $halCollection->pageSize;
        $route    = $halCollection->collectionRoute;

        $collection->setItemCountPerPage($pageSize);
        $collection->setCurrentPageNumber($page);

        $count    = count($collection);
        if (!$count) {
            return $this->forCollection($route);
        }

        if ($page < 1 || $page > $count) {
            return new ApiProblem(409, 'Invalid page provided');
        }

        $path = call_user_func($this->urlHelper, $route);
        $base = call_user_func($this->serverUrlHelper, $path);

        $next  = ($page == $count) ? false : $page + 1;
        $prev  = ($page == 1) ? false : $page - 1;
        $links = array(
            'self'  => $base . ((1 == $page) ? '' : '?page=' . $page),
        );
        if ($page != 1) {
            $links['first'] = $base;
        }
        if ($count != 1) {
            $links['last'] = $base . '?page=' . $count;
        }
        if ($prev) {
            $links['prev'] = $base . ((1 == $prev) ? '' : '?page=' . $prev);
        }
        if ($next) {
            $links['next'] = $base . '?page=' . $next;
        }

        foreach ($links as $index => $link) {
            $links[$index] = array('href' => $link);
        }

        return $links;
    }
}
