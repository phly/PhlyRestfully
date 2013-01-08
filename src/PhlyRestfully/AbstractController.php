<?php

namespace PhlyRestfully;

use Zend\Mvc\Controller\AbstractRestfulController;
use Zend\Mvc\MvcEvent;

abstract class AbstractController extends AbstractRestfulController
{
    /**
     * HTTP methods we allow; used by options()
     * @var array
     */
    protected $options = array(
        'DELETE',
        'GET',
        'HEAD',
        'PATCH',
        'POST',
        'PUT',
    );

    /**
     * @var ResourceInterface
     */
    protected $resource;

    public function setResource(ResourceInterface $resource)
    {
        $this->resource = $resource;
    }

    /**
     * Handle the dispatch event
     *
     * Raises an exception if no resource is composed.
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
        return parent::onDispatch($e);
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
        array_walk($this->options, 'strtoupper');
        $response = $this->getResponse();
        $headers  = $response->getHeaders();
        $headers->addHeaderLine('Allow', implode(', ', $this->options));
        return $response;
    }
}
