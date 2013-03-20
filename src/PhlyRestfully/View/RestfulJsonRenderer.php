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
use PhlyRestfully\Link;
use PhlyRestfully\LinkCollection;
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
            $helper  = $this->helpers->get('HalLinks');
            $payload = $helper->renderResource($nameOrModel->getPayload());
            return parent::render($payload);
        }

        if ($nameOrModel->isHalCollection()) {
            $helper  = $this->helpers->get('HalLinks');
            $payload = $helper->renderCollection($nameOrModel->getPayload());
            if ($payload instanceof ApiProblem) {
                return $this->renderApiProblem($payload);
            }
            return parent::render($payload);
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
