<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

use Zend\Http\Response;
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
        'PhlyRestfully\View\RestfulJsonModel' => array(
            '*/json',
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
     * HTTP methods we allow for individual items; used by options()
     *
     * HEAD and OPTIONS are always available.
     *
     * @var array
     */
    protected $itemHttpOptions = array(
        'DELETE',
        'GET',
        'PATCH',
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
     * HTTP methods we allow for the resource (collection); used by options()
     *
     * HEAD and OPTIONS are always available.
     *
     * @var array
     */
    protected $resourceHttpOptions = array(
        'GET',
        'POST',
        'PUT',
    );

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
     * @param  null|string $eventIdentifier
     */
    public function __construct($eventIdentifier = null)
    {
        if (null !== $eventIdentifier) {
            $this->eventIdentifier = $eventIdentifier;
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
     * Set the allowed HTTP OPTIONS for an item
     *
     * @param  array $options
     */
    public function setItemHttpOptions(array $options)
    {
        $this->itemHttpOptions = $options;
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
     * Set the allowed HTTP OPTIONS for the resource (collection)
     *
     * @param  array $options
     */
    public function setResourceHttpOptions(array $options)
    {
        $this->resourceHttpOptions = $options;
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

        $return = parent::onDispatch($e);

        if (!$return instanceof ApiProblem
            && !$return instanceof HalItem
            && !$return instanceof HalCollection
        ) {
            return $return;
        }

        $viewModel = $this->acceptableViewModelSelector($this->acceptCriteria);
        $viewModel->setVariables(array('payload' => $return));

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
     * @return Response|ApiProblem|HalItem
     */
    public function create($data)
    {
        if (!$this->isMethodAllowedForResource()) {
            return $this->createMethodNotAllowedResponse($this->resourceHttpOptions);
        }

        $response = $this->getResponse();
        try {
            $item = $this->resource->create($data);
        } catch (Exception\CreationException $e) {
            $code = $e->getCode() ?: 500;
            return new ApiProblem($code, $e);
        }

        $id = $this->getIdentifierFromItem($item);
        if (!$id) {
            return new ApiProblem(
                422,
                'No item identifier present following item creation.'
            );
        }

        $response->setStatusCode(201);
        $response->getHeaders()->addHeaderLine(
            'Location',
            $this->halLinks()->createLink($this->route, $id, $item)
        );

        return new HalItem($item, $id, $this->route);
    }

    /**
     * Delete an existing item
     *
     * @param  int|string $id
     * @return Response|ApiProblem
     */
    public function delete($id)
    {
        if ($id && !$this->isMethodAllowedForItem()) {
            return $this->createMethodNotAllowedResponse($this->itemHttpOptions);
        }
        if (!$id && !$this->isMethodAllowedForResource()) {
            return $this->createMethodNotAllowedResponse($this->resourceHttpOptions);
        }

        if (!$this->resource->delete($id)) {
            return new ApiProblem(422, 'Unable to delete item.');
        }

        $response = $this->getResponse();
        $response->setStatusCode(204);
        return $response;
    }

    public function deleteList()
    {
        if (!$this->isMethodAllowedForResource()) {
            return $this->createMethodNotAllowedResponse($this->resourceHttpOptions);
        }

        if (!$this->resource->deleteList()) {
            return new ApiProblem(422, 'Unable to delete collection.');
        }

        $response = $this->getResponse();
        $response->setStatusCode(204);
        return $response;
    }

    /**
     * Return single item
     *
     * @param  int|string $id
     * @return Response|ApiProblem|HalItem
     */
    public function get($id)
    {
        if (!$this->isMethodAllowedForItem()) {
            return $this->createMethodNotAllowedResponse($this->itemHttpOptions);
        }

        $item = $this->resource->fetch($id);
        if (!$item) {
            return new ApiProblem(404, 'Item not found.');
        }

        return new HalItem($item, $id, $this->route);
    }

    /**
     * Return list of items
     *
     * @return Response|HalCollection
     */
    public function getList()
    {
        if (!$this->isMethodAllowedForResource()) {
            return $this->createMethodNotAllowedResponse($this->resourceHttpOptions);
        }

        $response = $this->getResponse();
        $items    = $this->resource->fetchAll();

        $collection = new HalCollection($items, $this->route, $this->route);
        $collection->setPage($this->getRequest()->getQuery('page', 1));
        $collection->setPageSize($this->pageSize);
        return $collection;
    }

    /**
     * Retrieve HEAD metadata for the resource and/or item
     *
     * @param  null|mixed $id
     * @return Response|ApiProblem|HalItem|HalCollection
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
     * @return Response
     */
    public function options()
    {
        if (null === $id = $this->params()->fromRoute('id')) {
            $id = $this->params()->fromQuery('id');
        }

        if ($id) {
            $options = $this->itemHttpOptions;
        } else {
            $options = $this->resourceHttpOptions;
        }

        array_walk($options, function (&$method) {
            $method = strtoupper($method);
        });

        $response = $this->getResponse();
        $response->setStatusCode(204);
        $headers  = $response->getHeaders();
        $headers->addHeaderLine('Allow', implode(', ', $options));
        return $response;
    }

    /**
     * Respond to the PATCH method (partial update of existing item)
     *
     * @param  int|string $id
     * @param  array $data
     * @return Response|ApiProblem|HalItem
     */
    public function patch($id, $data)
    {
        if (!$this->isMethodAllowedForItem()) {
            return $this->createMethodNotAllowedResponse($this->itemHttpOptions);
        }

        try {
            $item = $this->resource->patch($id, $data);
        } catch (Exception\PatchException $e) {
            $code = $e->getCode() ?: 500;
            return new ApiProblem($code, $e);
        }

        return new HalItem($item, $id, $this->route);
    }

    /**
     * Update an existing item
     *
     * @param  int|string $id
     * @param  array $data
     * @return Response|ApiProblem|HalItem
     */
    public function update($id, $data)
    {
        if ($id && !$this->isMethodAllowedForItem()) {
            return $this->createMethodNotAllowedResponse($this->itemHttpOptions);
        }
        if (!$id && !$this->isMethodAllowedForResource()) {
            return $this->createMethodNotAllowedResponse($this->resourceHttpOptions);
        }

        try {
            $item = $this->resource->update($id, $data);
        } catch (Exception\UpdateException $e) {
            $code = $e->getCode() ?: 500;
            return new ApiProblem($code, $e);
        }

        return new HalItem($item, $id, $this->route);
    }

    /**
     * Update an existing collection of items
     *
     * @param array $data
     * @return array
     */
    public function replaceList($data)
    {
        if (!$this->isMethodAllowedForResource()) {
            return $this->createMethodNotAllowedResponse($this->resourceHttpOptions);
        }

        try {
            $items = $this->resource->replaceList($data);
        } catch (Exception\UpdateException $e) {
            $code = $e->getCode() ?: 500;
            return new ApiProblem($code, $e);
        }

        $collection = new HalCollection($items, $this->route, $this->route);
        $collection->setPage($this->getRequest()->getQuery('page', 1));
        $collection->setPageSize($this->pageSize);
        return $collection;
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
     * Is the current HTTP method allowed for an item?
     *
     * @return bool
     */
    protected function isMethodAllowedForItem()
    {
        array_walk($this->itemHttpOptions, function (&$method) {
            $method = strtoupper($method);
        });
        $options = array_merge($this->itemHttpOptions, array('OPTIONS', 'HEAD'));
        $request = $this->getRequest();
        $method  = strtoupper($request->getMethod());
        if (!in_array($method, $options)) {
            return false;
        }
        return true;
    }

    /**
     * Is the current HTTP method allowed for the resource (collection)?
     *
     * @return bool
     */
    protected function isMethodAllowedForResource()
    {
        array_walk($this->resourceHttpOptions, function (&$method) {
            $method = strtoupper($method);
        });
        $options = array_merge($this->resourceHttpOptions, array('OPTIONS', 'HEAD'));
        $request = $this->getRequest();
        $method  = strtoupper($request->getMethod());
        if (!in_array($method, $options)) {
            return false;
        }
        return true;
    }

    /**
     * Creates a "405 Method Not Allowed" response detailing the available options
     *
     * @param  array $options
     * @return Response
     */
    protected function createMethodNotAllowedResponse(array $options)
    {
        $response = $this->getResponse();
        $response->setStatusCode(405);
        $headers = $response->getHeaders();
        $headers->addHeaderLine('Allow', implode(', ', $options));
        return $response;
    }
}
