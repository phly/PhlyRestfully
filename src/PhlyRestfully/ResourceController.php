<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Mvc\MvcEvent;
use Zend\Paginator\Paginator;

/**
 * Controller for handling resources.
 *
 * Extends the base AbstractRestfulController in order to provide very specific
 * semantics for building a RESTful JSON service. All operations return either
 *
 * - a HAL-compliant response with appropriate hypermedia links
 * - a Problem API-compliant response for reporting an error condition
 *
 * You may specify what specific HTTP method types you wish to respond to, and
 * OPTIONS will then report those; attempting any HTTP method falling outside
 * that list will result in a 405 (Method Not Allowed) response.
 *
 * I recommend using resource-specific factories when using this controller,
 * to allow injecting the specific resource you wish to use (and its listeners),
 * which will also allow you to have multiple instances of the controller when
 * desired.
 *
 * @see http://tools.ietf.org/html/draft-kelly-json-hal-03
 * @see http://tools.ietf.org/html/draft-nottingham-http-problem-02
 */
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
     * Constructor
     *
     * Allows you to set the event identifier, which can be useful to allow multiple
     * instances of this controller to react to different sets of shared events.
     *
     * @param  null|string $eventIdentifer
     */
    public function __construct($eventIdentifer = null)
    {
        if (null !== $eventIdentifer) {
            $this->eventIdentifer = $eventIdentifer;
        }
    }

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

        array_walk($this->httpOptions, function (&$item) {
            $item = strtoupper($item);
        });
        $options = array_merge($this->httpOptions, array('OPTIONS', 'HEAD'));
        $request = $e->getRequest();
        $method  = strtoupper($request->getMethod());
        if (!in_array($method, $options)) {
            $response = $e->getResponse();
            $response->setStatusCode(405);
            $headers = $response->getHeaders();
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
        } catch (Exception\CreationException $e) {
            return $this->apiProblemResult(500, $e);
        }

        $id = $this->getIdentifierFromItem($item);
        if (!$id) {
            return $this->apiProblemResult(
                422,
                'No item identifier present following item creation.'
            );
        }

        $resourceLink = $this->links()->createLink($this->route);
        $selfLink     = $this->links()->createLink($this->route, $id, $item);

        $response->setStatusCode(201);
        $response->getHeaders()->addHeaderLine('Location', $selfLink);

        return array(
            '_links' => $this->links()->generateHalLinkRelations(array(
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
        if (!$this->resource->delete($id)) {
            return $this->apiProblemResult(
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
            return $this->apiProblemResult(
                404,
                'Item not found.'
            );
        }

        $resourceLink = $this->links()->createLink($this->route, false);
        $selfLink     = $this->links()->createLink($this->route, $id, $item);

        return array(
            '_links' => $this->links()->generateHalLinkRelations(array(
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
        $items    = $this->resource->fetchAll();

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
        $response->setStatusCode(204);
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
            return $this->apiProblemResult(500, $e);
        }

        $resourceLink = $this->links()->createLink($this->route, false);
        $selfLink     = $this->links()->createLink($this->route, $id, $item);

        return array(
            '_links' => $this->links()->generateHalLinkRelations(array(
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
            return $this->apiProblemResult(500, $e);
        }

        $resourceLink = $this->links()->createLink($this->route, false);
        $selfLink     = $this->links()->createLink($this->route, $id, $item);

        return array(
            '_links' => $this->links()->generateHalLinkRelations(array(
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
     * Create a response payload for a paginated collection
     *
     * @param  Paginator $items
     * @return array
     */
    protected function createPaginatedResponse(Paginator $items)
    {
        $items->setItemCountPerPage($this->pageSize);
        $count = count($items);

        if (!$count) {
            return array(
                '_links' => $this->links()->generateHalLinkRelations(array(
                    'self' => $this->links()->createLink($this->route),
                )),
                'items'  => array(),
            );
        }

        $page  = (int) $this->params()->fromQuery('page', 1);
        if ($page < 1 || $page > $count) {
            return $this->apiProblemResult(409, 'Invalid page provided');
        }

        $items->setCurrentPageNumber($page);

        $base  = $this->links()->createLink($this->route);
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

        return array(
            '_links' => $this->links()->generateHalLinkRelations($links),
            'items'  => $this->createHalItems($items),
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
            '_links' => $this->links()->generateHalLinkRelations(array(
                'self' => $this->links()->createLink($this->route),
            )),
            'items' => $this->createHalItems($items),
        );
    }

    /**
     * Create array of HAL-formatted items
     *
     * @param  array|Traversable $items
     * @return array
     */
    protected function createHalItems($items)
    {
        $halItems = array();
        foreach ($items as $item) {
            $halItems[] = $this->createHalItem($item);
        }
        return $halItems;
    }

    /**
     * Create a HAL payload for an individual item
     *
     * @param  mixed $item
     * @return array
     */
    protected function createHalItem($item)
    {
        $halItem = array('item' => $item);

        $id = $this->getIdentifierFromItem($item);
        if (!$id) {
            return $halItem;
        }

        $halItem['_links'] = $this->links()->generateHalLinkRelations(array(
            'self' => $this->links()->createLink($this->route, $id, $item),
        ));

        return $halItem;
    }
}
