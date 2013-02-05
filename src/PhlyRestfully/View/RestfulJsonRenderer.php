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
use PhlyRestfully\HalItem;
use Zend\Paginator\Paginator;
use Zend\Stdlib\Hydrator\HydratorInterface;
use Zend\View\HelperPluginManager;
use Zend\View\Renderer\JsonRenderer;

/**
 * Handles rendering of the following:
 *
 * - API-Problem
 * - HAL collections
 * - HAL items
 */
class RestfulJsonRenderer extends JsonRenderer
{
    /**
     * @var ApiProblem
     */
    protected $apiProblem;

    /**
     * Default hydrator to use if no hydrator found for a specific item class.
     * 
     * @var HydratorInterface
     */
    protected $defaultHydrator;

    /**
     * @var HelperPluginManager
     */
    protected $helpers;

    /**
     * Map of item classes => hydrators
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
     * Map an item class to a specific hydrator instance
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
     * an ApiProblem, HalCollection, or HalItem, and, if so, creates a custom
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

        if ($nameOrModel->isHalItem()) {
            return $this->renderHalItem($nameOrModel->getPayload());
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
        return parent::render($apiProblem->toArray());
    }

    /**
     * Render an individual HAL item
     *
     * Creates the hyperlinks necessary, and serializes the item to JSON.
     * 
     * @param  HalItem $halItem 
     * @return string
     */
    protected function renderHalItem(HalItem $halItem)
    {
        $item    = $halItem->item;
        $id      = $halItem->id;
        $route   = $halItem->route;

        $helper  = $this->helpers->get('HalLinks');
        $links   = $helper->forItem($route, $id, $item);

        if (!is_array($item)) {
            $item = $this->convertItemToArray($item);
        }

        $item['_links'] = $links;
        return parent::render($item);
    }

    /**
     * Render a HAL collection
     *
     * Determines if the collection composes a Paginator or non-paginated
     * set, and delegates to the appropriate method in order to generate
     * a HAL response. All items are serialized in a "collection" member
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
     * Convert an individual item to an array
     * 
     * @param  object $item 
     * @return array
     */
    protected function convertItemToArray($item)
    {
        $hydrator = $this->getHydratorForItem($item);
        if (!$hydrator) {
            return (array) $item;
        }

        return $hydrator->extract($item);
    }

    /**
     * Retrieve a hydrator for a given item
     *
     * If the item has a mapped hydrator, returns that hydrator. If not, and
     * a default hydrator is present, the default hydrator is returned. 
     * Otherwise, a boolean false is returned.
     * 
     * @param  object $item 
     * @return HydratorInterface|false
     */
    protected function getHydratorForItem($item)
    {
        $class = strtolower(get_class($item));
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
     * items rendered.
     * 
     * @param  HalCollection $halCollection 
     * @return string
     */
    protected function renderNonPaginatedCollection(HalCollection $halCollection)
    {
        $collection = $halCollection->collection;

        $helper  = $this->helpers->get('HalLinks');
        $payload = array(
            '_links'     => $helper->forCollection($halCollection->collectionRoute),
            'collection' => array(),
        );

        $itemRoute = $halCollection->itemRoute;
        foreach ($collection as $item) {
            $origItem = $item;
            if (!is_array($item)) {
                $item = $this->convertItemToArray($item);
            }

            $id = $this->getIdFromItem($item);
            if (!$id) {
                // Cannot handle items without an identifier
                continue;
            }

            $item['_links']          = $helper->forItem($itemRoute, $id, $origItem);
            $payload['collection'][] = $item;
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
        $collection = $halCollection->collection;

        $helper  = $this->helpers->get('HalLinks');
        $links   = $helper->forPaginatedCollection($halCollection);
        if ($links instanceof ApiProblem) {
            return $this->renderApiProblem($links);
        }

        $payload = array(
            '_links'     => $links,
            'collection' => array(),
        );

        $itemRoute = $halCollection->itemRoute;
        foreach ($collection as $item) {
            $origItem = $item;
            if (!is_array($item)) {
                $item = $this->convertItemToArray($item);
            }

            $id = $this->getIdFromItem($item);
            if (!$id) {
                // Cannot handle items without an identifier
                // Return as-is
                $payload['collection'][] = $item;
                continue;
            }

            $item['_links']          = $helper->forItem($itemRoute, $id, $origItem);
            $payload['collection'][] = $item;
        }

        return parent::render($payload);
    }

    /**
     * Retrieve the identifier from an item
     *
     * Expects an "id" member to exist; if not, a boolean false is returned.
     * 
     * @todo   Potentially allow registering a callback to run, before using
     *         the default routine here.
     * @param  array $item 
     * @return mixed|false
     */
    protected function getIdFromItem(array $item)
    {
        if (array_key_exists('id', $item)) {
            return $item['id'];
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
        $helper = new Helper\HalLinks();
        $helper->setView($this);
        $helper->setServerUrlHelper($helpers->get('ServerUrl'));
        $helper->setUrlHelper($helpers->get('Url'));
        $helpers->setService('HalLinks', $helper);
    }
}
