<?php

namespace PhlyRestfully;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Mvc\MvcEvent;
use Zend\Paginator\Paginator;
use Zend\View\Helper\ServerUrl;

class ResourceController extends AbstractRestfulController
{
    /**
     * Criteria for the AcceptableViewModelSelector
     * 
     * @var array
     */
    protected $acceptCriteria = array(
        'PhlyRestfully\RestfulJsonModel' => array(
            'application/json',
            'application/hal+json',
        ),
    );

    /**
     * Content types that will trigger marshalling data from the request body.
     *
     * @var array
     */
    protected $contentTypes = array(
        self::CONTENT_TYPE_JSON => array(
            'application/json',
            'application/hal+json',
        ),
    );

    /**
     * HTTP methods we allow; used by options()
     * @var array
     */
    protected $httpOptions = array(
        'DELETE',
        'GET',
        'HEAD',
        'PATCH',
        'POST',
        'PUT',
    );

    /**
     * Number of items to return per page
     * 
     * @var int
     */
    protected $pageSize = 30;

    /**
     * Status titles for common problems
     * 
     * @var array
     */
    protected $problemStatusTitles = array(
        404 => 'NotFound',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error',
    );

    /**
     * @var ResourceInterface
     */
    protected $resource;

    /**
     * Route name that resolves to this resource; used to generate links.
     * 
     * @var string
     */
    protected $route;

    /**
     * Helper for generating server URL
     * 
     * @var ServerUrl
     */
    protected $serverUrlHelper;

    /**
     * Set the Accept header criteria for use with the AcceptableViewModelSelector
     * 
     * @param  array $criteria 
     */
    public function setAcceptCriteria(array $criteria)
    {
        $this->acceptCriteria = $criteria;
    }

    /**
     * Set the allowed HTTP OPTIONS
     * 
     * @param  array $options 
     */
    public function setHttpOptions(array $options)
    {
        $this->httpOptions = $options;
    }

    /**
     * Set the default page size for paginated responses
     * 
     * @param  int
     */
    public function setPageSize($count)
    {
        $this->pageSize = (int) $count;
    }

    /**
     * Inject the resource with which this controller will communicate.
     * 
     * @param  ResourceInterface $resource 
     */
    public function setResource(ResourceInterface $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Inject the route name for this resource.
     * 
     * @param  string $route 
     */
    public function setRoute($route)
    {
        $this->route = $route;
    }

    /**
     * Set the helper used for generating the server URL
     * 
     * @param  ServerUrl $serverUrlHelper 
     */
    public function setServerUrlHelper(ServerUrl $serverUrlHelper)
    {
        $this->serverUrlHelper = $serverUrlHelper;
    }

    /**
     * Get the helper used for generating the server URL
     *
     * Lazy-instantiates, if not present.
     * 
     * @return ServerUrl
     */
    public function getServerUrlHelper()
    {
        if (!$this->serverUrlHelper) {
            $this->setServerUrlHelper(new ServerUrl);
        }
        return $this->serverUrlHelper;
    }

    /**
     * Handle the dispatch event
     *
     * Does several "pre-flight" checks:
     * - Raises an exception if no resource is composed.
     * - Raises an exception if no route is composed.
     * - Returns a 405 response if the current HTTP request method is not in 
     *   $options
     *
     * When the dispatch is complete, it will check to see if an array was
     * returned; if so, it will cast it to a view model using the
     * AcceptableViewModelSelector plugin, and the $acceptCriteria property.
     * 
     * @param  MvcEvent $e 
     * @return mixed
     * @throws Exception\DomainException
     */
    public function onDispatch(MvcEvent $e)
    {
        if (!$this->resource) {
            throw new Exception\DomainException(sprintf(
                '%s requires that a %s\ResourceInterface object is composed; none provided',
                __CLASS__, __NAMESPACE__
            ));
        }

        if (!$this->route) {
            throw new Exception\DomainException(sprintf(
                '%s requires that a route name for the resource is composed; none provided',
                __CLASS__
            ));
        }

        array_walk($this->httpOptions, 'strtoupper');
        $request = $e->getRequest();
        $method  = strtoupper($request->getMethod());
        if (!in_array($method, $this->httpOptions)) {
            $response = $e->getResponse();
            $response->setStatusCode(405);
            $headers->addHeaderLine('Allow', implode(', ', $this->httpOptions));
            return $response;
        }

        $return = parent::onDispatch($e);
        if (!is_array($return)) {
            return $return;
        }

        $viewModel = $this->acceptableViewModelSelector($this->acceptCriteria);
        $viewModel->setVariables($return);

        if ($viewModel instanceof RestfulJsonModel) {
            $viewModel->setTerminal(true);
        }

        $e->setResult($viewModel);
        return $viewModel;
    }

    /**
     * Create a new item
     *
     * @param  array $data
     * @return array
     */
    public function create($data)
    {
        $response = $this->getResponse();
        try {
            $item = $this->resource->create($data);
        } catch (Exception\CreateException $e) {
            return $this->createProblemResult(
                500,
                $e->getMessage()
            );
        }

        $id = $this->getIdentifierFromItem($item);
        if (!$id) {
            return $this->createProblemResult(
                422,
                'No item identifier present following item creation.'
            );
        }

        $resourceLink = $this->createLink();
        $selfLink = $this->createLink($id);

        $response->setStatusCode(201);
        $response->getHeaders()->addHeaderLine('Location', $selfLink);

        return array(
            '_links' => $this->generateHalLinkRelations(array(
                'up'   => $resourceLink,
                'self' => $selfLink,
            )),
            'item' => $item,
        );
    }

    /**
     * Delete an existing item
     *
     * @param  int|string $id
     * @return array|\Zend\Http\Response
     */
    public function delete($id)
    {
        if (!$this->resource->delete($data)) {
            return $this->createProblemResult(
                422,
                'Unable to delete item.'
            );
        }

        $response = $this->getResponse();
        $response->setStatusCode(204);
        return $response;
    }

    /**
     * Return single item
     *
     * @param  int|string $id
     * @return array
     */
    public function get($id)
    {
        $item = $this->resource->fetch($id);
        if (!$item) {
            return $this->createProblemResult(
                404,
                'Item not found.'
            );
        }

        $resourceLink = $this->createLink();
        $selfLink = $this->createLink($id);

        return array(
            '_links' => $this->generateHalLinkRelations(array(
                'up'   => $resourceLink,
                'self' => $selfLink,
            )),
            'item' => $item,
        );
    }

    /**
     * Return list of items
     *
     * @return array
     */
    public function getList()
    {
        $response = $this->getResponse();
        $items    = $this->resource->fetch($id);

        if ($items instanceof Paginator) {
            return $this->createPaginatedResponse($items);
        }

        return $this->createNonPaginatedResponse($items);
    }

    /**
     * Retrieve HEAD metadata for the resource and/or item
     *
     * @param  null|mixed $id
     * @return mixed
     */
    public function head($id = null)
    {
        if ($id) {
            return $this->get($id);
        }
        return $this->getList();
    }

    /**
     * Respond to OPTIONS request
     *
     * Uses $options to set the Allow header line and return an empty response.
     * 
     * @return \Zend\Http\Response
     */
    public function options()
    {
        $response = $this->getResponse();
        $headers  = $response->getHeaders();
        $headers->addHeaderLine('Allow', implode(', ', $this->httpOptions));
        return $response;
    }

    /**
     * Respond to the PATCH method (partial update of existing item)
     *
     * @param  int|string $id
     * @param  array $data
     * @return array
     */
    public function patch($id, $data)
    {
        $response = $this->getResponse();

        try {
            $item = $this->resource->patch($id, $data);
        } catch (Exception\PatchException $e) {
            return $this->createProblemResult(
                500,
                $e->getMessage()
            );
        }

        $resourceLink = $this->createLink();
        $selfLink = $this->createLink($id);

        return array(
            '_links' => $this->generateHalLinkRelations(array(
                'up'   => $resourceLink,
                'self' => $selfLink,
            )),
            'item' => $item,
        );
    }

    /**
     * Update an existing item
     *
     * @param  int|string $id
     * @param  array $data
     * @return array
     */
    public function update($id, $data)
    {
        $response = $this->getResponse();

        try {
            $item = $this->resource->update($id, $data);
        } catch (Exception\UpdateException $e) {
            return $this->createProblemResult(
                500,
                $e->getMessage()
            );
        }

        $resourceLink = $this->createLink();
        $selfLink = $this->createLink($id);

        return array(
            '_links' => $this->generateHalLinkRelations(array(
                'up'   => $resourceLink,
                'self' => $selfLink,
            )),
            'item' => $item,
        );
    }

    /**
     * Retrieve an identifier from an item
     * 
     * @param  array|object $item 
     * @return false|int|string
     */
    protected function getIdentifierFromItem($item)
    {
        // Found id in array
        if (is_array($item) && array_key_exists('id', $item)) {
            return $item['id'];
        }

        // No id in array, or not an object; return false
        if (is_array($item) || !is_object($item)) {
            return false;
        }

        // Found public id property on object
        if (isset($item->id)) {
            return $item->id;
        }

        // Found public id getter on object
        if (method_exists($item, 'getid')) {
            return $item->getId();
        }

        // not found
        return false;
    }

    /**
     * Create link
     * 
     * @param mixed $id 
     * @return void
     */
    protected function createLink($id = null)
    {
        $params = array();
        if (null !== $id) {
            $params['id'] = $id;
        }

        $path   = $this->url()->fromRoute($this->route, $params);
        $helper = $this->getServerUrlHelper();
        return $helper($path);
    }

    /**
     * Generate HAL link relation list
     * 
     * @param  array $links 
     * @return array
     */
    protected function generateHalLinkRelations(array $links)
    {
        $halLinks = array();
        foreach ($links as $rel => $link) {
            $halLinks[$rel] = array('href' => $link);
        }
        return $halLinks;
    }

    /**
     * Create a Problem API result
     *
     * @see    http://tools.ietf.org/html/draft-nottingham-http-problem-02
     * @param  int $httpStatus
     * @param  string $detail 
     * @param  string $describedBy 
     * @param  string $title
     * @return array
     */
    protected function createProblemResult($httpStatus, $detail, $describedBy = 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html', $title = 'Unknown')
    {
        if ($title == 'Unknown'
            && $describedBy == 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html' 
            && array_key_exists($httpStatus, $this->problemStatusTitles)
        ) {
            $title = $this->problemStatusTitles[$httpStatus];
        }

        $response = $this->getResponse();
        $response->setStatusCode($httpStatus);
        $result = compact('describedBy', 'title', 'httpStatus', 'detail');
        return $result;
    }

    /**
     * Create a response payload for a paginated collection
     * 
     * @param  Paginator $items 
     * @return array
     */
    protected function createPaginatedResponse(Paginator $items)
    {
        $items->setItemCountPerPage($this->pageSize);
        $count = count($items);
        $page  = (int) $this->params()->fromQuery('page', 1);
        if ($page < 1 || $page > $count) {
            return $this->createProblemResult(409, 'Invalid page provided');
        }

        $items->setCurrentPageNumber($page);
        $next  = ($page == $count) ? false : $page + 1;
        $prev  = ($page == 1) ? false : $page - 1;
        $last  = $count;
        $links = array(
            'self'  => $this->createLink() . ((1 == $page) ? '' : '?page=' . $page),
            'first' => $this->createLink(),
            'last'  => $this->createLink() . '?page=' . $last,
        );
        if ($prev) {
            $links['prev'] = $this->createLink() . '?page=' . $prev;
        }
        if ($next) {
            $links['next'] = $this->createLink() . '?page=' . $next;
        }

        return array(
            '_links' => $this->generateHalLinkRelations($links),
            'items'  => $items,
        );
    }

    /**
     * Create a response payload for a non-paginated collection
     * 
     * @todo   Add metadata, such as count of items?
     * @param  Paginator $items 
     * @return array
     */
    protected function createNonPaginatedResponse($items)
    {
        return array(
            '_links' => $this->generateHalLinkRelations(array(
                'self' => $this->createLink(),
            )),
            'items' => $items,
        );
    }
}
