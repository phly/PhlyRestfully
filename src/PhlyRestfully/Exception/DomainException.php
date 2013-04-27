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
    protected $describedBy;

    /**
     * @var array
     */
    protected $details = array();

    /**
     * @var string
     */
    protected $title;

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
     * @param string $uri
     * @return self
     */
    public function setDescribedBy($uri)
    {
        $this->describedBy = (string) $uri;
        return $this;
    }

    /**
     * @param string $title
     * @return self
     */
    public function setTitle($title)
    {
        $this->title = (string) $title;
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
