<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\View;

use Zend\Stdlib\Hydrator\HydratorInterface;
use Zend\View\HelperPluginManager;
use Zend\View\Renderer\JsonRenderer;

class RestfulJsonRenderer extends JsonRenderer
{
    protected $defaultHydrator;

    protected $helpers;

    protected $hydrators = array();

    public function setHelperPluginManager(HelperPluginManager $helpers)
    {
        $this->helpers = $helpers;
    }

    public function getHelperPluginManager()
    {
        if (!$this->helpers instanceof HelperPluginManager) {
            $this->setHelperPluginManager(new HelperPluginManager());
        }
        return $this->helpers;
    }

    public function addHydrator($class, HydratorInterface $hydrator)
    {
        $this->hydrators[strtolower($class)] = $hydrator;
        return $this;
    }

    public function setDefaultHydrator(HydratorInterface $hydrator)
    {
        $this->defaultHydrator = $hydrator;
        return $this;
    }

    public function render($nameOrModel, $values = null)
    {
        if (!$nameOrModel instanceof RestfulJsonModel) {
            return parent::render($nameOrModel, $values);
        }

        if ($nameOrModel->isApiProblem()) {
            return $this->renderApiProblem($nameOrModel);
        }

        if ($nameOrModel->isHalItem()) {
            return $this->renderHalItem($nameOrModel);
        }
    }

    protected function renderApiProblem(RestfulJsonModel $model)
    {
        $apiProblem = $model->getPayload();
        return parent::render($apiProblem->toArray());
    }

    protected function renderHalItem(RestfulJsonModel $model)
    {
        $halItem = $model->getPayload();
        $item    = $halItem->item;
        $id      = $halItem->id;
        $route   = $halItem->route;
        $params  = $halItem->routeParams;

        $helper  = $this->helpers->get('HalLinks');
        $links   = $helper->forItem($id, $route, $params);

        if (!is_array($item)) {
            $item = $this->convertItemToArray($item);
        }

        $item['_links'] = $links;
        return parent::render($item);
    }

    protected function convertItemToArray($item)
    {
        $hydrator = $this->getHydratorForItem($item);
        if (!$hydrator) {
            return (array) $item;
        }

        return $hydrator->extract($item);
    }

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
}
