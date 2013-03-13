<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\View;

use PhlyRestfully\ApiProblem;
use PhlyRestfully\HalCollection;
use PhlyRestfully\HalResource;
use PhlyRestfully\Plugin\HalLinks;
use Zend\Paginator\Paginator;
use Zend\Stdlib\Hydrator\HydratorInterface;
use Zend\View\HelperPluginManager;
use Zend\View\Renderer\JsonRenderer;

/**
 * Handles rendering of the following:
 *
 * - API-Problem
 * - HAL collections
 * - HAL resources
 */
class RestfulJsonRenderer extends JsonRenderer
{
    /**
     * @var ApiProblem
     */
    protected $apiProblem;

    /**
     * Default hydrator to use if no hydrator found for a specific resource class.
     *
     * @var HydratorInterface
     */
    protected $defaultHydrator;

    /**
     * Whether or not to render exception stack traces in API-Problem payloads
     *
     * @var bool
     */
    protected $displayExceptions = false;

    /**
     * @var HelperPluginManager
     */
    protected $helpers;

    /**
     * Map of resource classes => hydrators
     *
     * @var HydratorInterface[]
     */
    protected $hydrators = array();

    /**
     * Set helper plugin manager instance.
     *
     * Also ensures that the 'HalLinks' helper is present.
     *
     * @param  HelperPluginManager $helpers
     */
    public function setHelperPluginManager(HelperPluginManager $helpers)
    {
        if (!$helpers->has('HalLinks')) {
            $this->injectHalLinksHelper($helpers);
        }
        $this->helpers = $helpers;
    }

    /**
     * Lazy-loads a helper plugin manager if none available.
     *
     * @return HelperPluginManager
     */
    public function getHelperPluginManager()
    {
        if (!$this->helpers instanceof HelperPluginManager) {
            $this->setHelperPluginManager(new HelperPluginManager());
        }
        return $this->helpers;
    }

    /**
     * Map a resource class to a specific hydrator instance
     *
     * @param  string $class
     * @param  HydratorInterface $hydrator
     * @return RestfulJsonRenderer
     */
    public function addHydrator($class, HydratorInterface $hydrator)
    {
        $this->hydrators[strtolower($class)] = $hydrator;
        return $this;
    }

    /**
     * Set the default hydrator to use if none specified for a class.
     *
     * @param  HydratorInterface $hydrator
     * @return RestfulJsonRenderer
     */
    public function setDefaultHydrator(HydratorInterface $hydrator)
    {
        $this->defaultHydrator = $hydrator;
        return $this;
    }

    /**
     * Set display_exceptions flag
     *
     * @param  bool $flag
     * @return RestfulJsonRenderer
     */
    public function setDisplayExceptions($flag)
    {
        $this->displayExceptions = (bool) $flag;
        return $this;
    }

    /**
     * Whether or not what was rendered represents an API problem
     *
     * @return bool
     */
    public function isApiProblem()
    {
        return (null !== $this->apiProblem);
    }

    /**
     * @return null|ApiProblem
     */
    public function getApiProblem()
    {
        return $this->apiProblem;
    }

    /**
     * Render a view model
     *
     * If the view model is a RestfulJsonRenderer, determines if it represents
     * an ApiProblem, HalCollection, or HalResource, and, if so, creates a custom
     * representation appropriate to the type.
     *
     * If not, it passes control to the parent to render.
     *
     * @param  mixed $nameOrModel
     * @param  mixed $values
     * @return string
     */
    public function render($nameOrModel, $values = null)
    {
        $this->apiProblem = null;

        if (!$nameOrModel instanceof RestfulJsonModel) {
            return parent::render($nameOrModel, $values);
        }

        if ($nameOrModel->isApiProblem()) {
            return $this->renderApiProblem($nameOrModel->getPayload());
        }

        if ($nameOrModel->isHalResource()) {
            return $this->renderHalResource($nameOrModel->getPayload());
        }

        if ($nameOrModel->isHalCollection()) {
            return $this->renderHalCollection($nameOrModel->getPayload());
        }

        return parent::render($nameOrModel, $values);
    }

    /**
     * Render an API Problem representation
     *
     * Also sets the $apiProblem member to the passed object.
     *
     * @param  ApiProblem $apiProblem
     * @return string
     */
    protected function renderApiProblem(ApiProblem $apiProblem)
    {
        $this->apiProblem   = $apiProblem;
        if ($this->displayExceptions) {
            $apiProblem->setDetailIncludesStackTrace(true);
        }
        return parent::render($apiProblem->toArray());
    }

    /**
     * Render an individual HAL resource
     *
     * Creates the hyperlinks necessary, and serializes the resource to JSON.
     *
     * @param  HalResource $halResource
     * @param  bool $returnAsArray Whether or not to return the resource as an array, or as rendered JSON
     * @return string|array
     */
    protected function renderHalResource(HalResource $halResource, $returnAsArray = false)
    {
        $resource = $halResource->resource;
        $id       = $halResource->id;
        $route    = $halResource->route;

        $helper   = $this->helpers->get('HalLinks');
        $links    = $helper->forResource($route, $id, $resource);

        if (!is_array($resource)) {
            $resource = $this->convertResourceToArray($resource);
        }

        foreach ($resource as $key => $value) {
            if (!$value instanceof HalResource) {
                continue;
            }
            $this->extractEmbeddedHalResource($resource, $key, $value);
        }

        $resource['_links'] = $links;

        if ($returnAsArray) {
            return $resource;
        }
        return parent::render($resource);
    }

    /**
     * Render a HAL collection
     *
     * Determines if the collection composes a Paginator or non-paginated
     * set, and delegates to the appropriate method in order to generate
     * a HAL response. All resources are serialized in a "collection" member
     * of the response.
     *
     * @param  HalCollection $halCollection
     * @return string
     */
    protected function renderHalCollection(HalCollection $halCollection)
    {
        if ($halCollection->collection instanceof Paginator) {
            return $this->renderPaginatedCollection($halCollection);
        }

        return $this->renderNonPaginatedCollection($halCollection);
    }

    /**
     * Convert an individual resource to an array
     *
     * @param  object $resource
     * @return array
     */
    protected function convertResourceToArray($resource)
    {
        $hydrator = $this->getHydratorForResource($resource);
        if (!$hydrator) {
            return (array) $resource;
        }

        return $hydrator->extract($resource);
    }

    /**
     * Extracts and renders a HalResource and embeds it in the parent
     * representation
     *
     * Removes the key from the parent representation, and creates a
     * representation for the key in the _embedded object.
     *
     * @param  array $parent
     * @param  string $key
     * @param  HalResource $resource
     */
    protected function extractEmbeddedHalResource(array &$parent, $key, HalResource $resource)
    {
        $rendered = $this->renderHalResource($resource, true);
        if (!isset($parent['_embedded'])) {
            $parent['_embedded'] = array();
        }
        $parent['_embedded'][$key] = $rendered;
        unset($parent[$key]);
    }

    /**
     * Retrieve a hydrator for a given resource
     *
     * If the resource has a mapped hydrator, returns that hydrator. If not, and
     * a default hydrator is present, the default hydrator is returned.
     * Otherwise, a boolean false is returned.
     *
     * @param  object $resource
     * @return HydratorInterface|false
     */
    protected function getHydratorForResource($resource)
    {
        $class = strtolower(get_class($resource));
        if (isset($this->hydrators[$class])) {
            return $this->hydrators[$class];
        }

        if ($this->defaultHydrator instanceof HydratorInterface) {
            return $this->defaultHydrator;
        }

        return false;
    }

    /**
     * Render a non-paginated collection
     *
     * Creates a HAL response with only a "self" link, with all composed
     * resources rendered.
     *
     * @param  HalCollection $halCollection
     * @return string
     */
    protected function renderNonPaginatedCollection(HalCollection $halCollection)
    {
        $collection     = $halCollection->collection;
        $collectionName = $halCollection->collectionName;

        $helper  = $this->helpers->get('HalLinks');
        $payload = $halCollection->attributes;
        $payload['_links'] = $helper->forCollection($halCollection->collectionRoute);
        $payload['_embedded'] = array(
            $collectionName => array(),
        );

        $resourceRoute = $halCollection->resourceRoute;
        foreach ($collection as $resource) {
            $origResource = $resource;
            if (!is_array($resource)) {
                $resource = $this->convertResourceToArray($resource);
            }

            foreach ($resource as $key => $value) {
                if (!$value instanceof HalResource) {
                    continue;
                }
                $this->extractEmbeddedHalResource($resource, $key, $value);
            }

            $id = $this->getIdFromResource($resource);
            if (!$id) {
                // Cannot handle resources without an identifier
                continue;
            }

            $resource['_links'] = $helper->forResource($resourceRoute, $id, $origResource);
            $payload['_embedded'][$collectionName][] = $resource;
        }

        return parent::render($payload);
    }

    /**
     * Render a paginated collection.
     *
     * If the page is out of bounds, an ApiProblem will be returned. Otherwise,
     * creates a HAL response, with links for each of first, last, prev, next,
     * and self, as necessitated by the collection.
     *
     * @param  HalCollection $halCollection
     * @return string|ApiProblem
     */
    protected function renderPaginatedCollection(HalCollection $halCollection)
    {
        $collection     = $halCollection->collection;
        $collectionName = $halCollection->collectionName;

        $helper  = $this->helpers->get('HalLinks');
        $links   = $helper->forPaginatedCollection($halCollection);
        if ($links instanceof ApiProblem) {
            return $this->renderApiProblem($links);
        }

        $payload = $halCollection->attributes;
        $payload['_links']    = $links;
        $payload['_embedded'] = array(
            $collectionName => array(),
        );

        $resourceRoute = $halCollection->resourceRoute;
        foreach ($collection as $resource) {
            $origResource = $resource;
            if (!is_array($resource)) {
                $resource = $this->convertResourceToArray($resource);
            }

            foreach ($resource as $key => $value) {
                if (!$value instanceof HalResource) {
                    continue;
                }
                $this->extractEmbeddedHalResource($resource, $key, $value);
            }

            $id = $this->getIdFromResource($resource);
            if (!$id) {
                // Cannot handle resources without an identifier
                // Return as-is
                $payload['_embedded'][$collectionName][] = $resource;
                continue;
            }

            $resource['_links'] = $helper->forResource($resourceRoute, $id, $origResource);
            $payload['_embedded'][$collectionName][] = $resource;
        }

        return parent::render($payload);
    }

    /**
     * Retrieve the identifier from a resource
     *
     * Expects an "id" member to exist; if not, a boolean false is returned.
     *
     * @todo   Potentially allow registering a callback to run, before using
     *         the default routine here.
     * @param  array $resource
     * @return mixed|false
     */
    protected function getIdFromResource(array $resource)
    {
        if (array_key_exists('id', $resource)) {
            return $resource['id'];
        }
        return false;
    }

    /**
     * Inject the helper manager with the HalLinks helper
     *
     * @param  HelperPluginManager $helpers
     */
    protected function injectHalLinksHelper(HelperPluginManager $helpers)
    {
        $helper = new HalLinks();
        $helper->setView($this);
        $helper->setServerUrlHelper($helpers->get('ServerUrl'));
        $helper->setUrlHelper($helpers->get('Url'));
        $helpers->setService('HalLinks', $helper);
    }
}
