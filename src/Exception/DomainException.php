<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully\Exception;

class DomainException extends \DomainException implements
    ExceptionInterface,
    ProblemExceptionInterface
{
    /**
     * @var string
     */
    protected $describedBy = '';

    /**
     * @var array
     */
    protected $details = [];

    /**
     * @var string
     */
    protected $title = '';

    /**
     * @param array $details
     * @return self
     */
    public function setAdditionalDetails(array $details)
    {
        $this->details = $details;
        return $this;
    }

    /**
     * @return self
     */
    public function setDescribedBy(string $uri)
    {
        $this->describedBy = $uri;
        return $this;
    }

    /**
     * @return self
     */
    public function setTitle(string $title)
    {
        $this->title = $title;
        return $this;
    }

    /**
     * @return array
     */
    public function getAdditionalDetails()
    {
        return $this->details;
    }

    /**
     * @return string
     */
    public function getDescribedBy()
    {
        return $this->describedBy;
    }

    /**
     * @return string
     */
    public function getTitle()
    {
        return $this->title;
    }
}
