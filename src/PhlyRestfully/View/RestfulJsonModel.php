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
use Zend\View\Model\JsonModel;

/**
 * Simple extension to facilitate the specialized JsonStrategy and JsonRenderer
 * in this Module.
 */
class RestfulJsonModel extends JsonModel
{
    /**
     * Does the payload represent an API-Problem?
     *
     * @return bool
     */
    public function isApiProblem()
    {
        $payload = $this->getPayload();
        return ($payload instanceof ApiProblem);
    }

    /**
     * Does the payload represent a HAL collection?
     *
     * @return bool
     */
    public function isHalCollection()
    {
        $payload = $this->getPayload();
        return ($payload instanceof HalCollection);
    }

    /**
     * Does the payload represent a HAL item?
     *
     * @return bool
     */
    public function isHalResource()
    {
        $payload = $this->getPayload();
        return ($payload instanceof HalResource);
    }

    /**
     * Set the payload for the response
     *
     * This is the value to represent in the response.
     *
     * @param  mixed $payload
     * @return RestfulJsonModel
     */
    public function setPayload($payload)
    {
        $this->setVariable('payload', $payload);
        return $this;
    }

    /**
     * Retrieve the payload for the response
     *
     * @return mixed
     */
    public function getPayload()
    {
        return $this->getVariable('payload');
    }
}
