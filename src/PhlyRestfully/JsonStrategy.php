<?php

namespace PhlyRestfully;

use Zend\View\Strategy\JsonStrategy as BaseJsonStrategy;
use Zend\View\ViewEvent;

class JsonStrategy extends BaseJsonStrategy
{
    protected $contentType = 'application/json';

    /**
     * Detect if we should use the JsonRenderer based on model type.
     *
     * @param  ViewEvent $e
     * @return null|\Zend\View\Renderer\JsonRenderer
     */
    public function selectRenderer(ViewEvent $e)
    {
        $model = $e->getModel();

        if (!$model instanceof JsonModel) {
            // not our JsonModel; do nothing
            return;
        }

        $payload  = $model->getVariables();

        // Problem API detection
        $keys     = array_keys($payload);
        if ($keys == array('describedBy', 'title', 'httpStatus', 'detail')) {
            $this->contentType = 'application/api-problem+json';
        }

        // HAL detection
        if (in_array('_links', $payload)) {
            $this->contentType = 'application/hal+json';
        }

        // JsonModel found
        return $this->renderer;
    }

    /**
     * Inject the response
     *
     * Injects the response with the rendered content, and sets the content
     * type based on the detection that occurred during renderer selection.
     * 
     * @param  ViewEvent $e 
     */
    public function injectResponse(ViewEvent $e)
    {
        $renderer = $e->getRenderer();
        if ($renderer !== $this->renderer) {
            // Discovered renderer is not ours; do nothing
            return;
        }

        $result   = $e->getResult();
        if (!is_string($result)) {
            // We don't have a string, and thus, no JSON
            return;
        }

        // Populate response
        $response = $e->getResponse();
        $response->setContent($result);
        $headers = $response->getHeaders();
        $headers->addHeaderLine('content-type', $this->contentType);
    }
}
