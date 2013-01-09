<?php

namespace PhlyRestfully;

use Zend\View\Strategy\JsonStrategy;
use Zend\View\ViewEvent;

class RestfulJsonStrategy extends JsonStrategy
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

        if (!$model instanceof RestfulJsonModel) {
            // unrecognized model; do nothing
            return;
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

        $model       = $e->getModel();
        $contentType = $this->contentType;
        if ($model instanceof RestfulJsonModel && $model->isProblemApi()) {
            $contentType = 'application/api-problem+json';
        } elseif ($model instanceof RestfulJsonModel && $model->isHal()) {
            $contentType = 'application/hal+json';
        }

        // Populate response
        $response = $e->getResponse();
        $response->setContent($result);
        $headers = $response->getHeaders();
        $headers->addHeaderLine('content-type', $contentType);
    }
}
