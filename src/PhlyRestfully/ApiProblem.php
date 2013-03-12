<?php
/**
 * @link      https://github.com/weierophinney/PhlyRestfully for the canonical source repository
 * @copyright Copyright (c) 2013 Matthew Weier O'Phinney
 * @license   http://opensource.org/licenses/BSD-2-Clause BSD-2-Clause
 * @package   PhlyRestfully
 */

namespace PhlyRestfully;

/**
 * Object describing an API-Problem payload
 */
class ApiProblem
{
    /**
     * URL describing the problem type; defaults to HTTP status codes
     * @var string
     */
    protected $describedBy = 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html';

    /**
     * Description of the specific problem.
     * @var string
     */
    protected $detail = '';

    /**
     * Whether or not to include a stack trace and previous
     * exceptions when an exception is provided for the detail.
     *
     * @var bool
     */
    protected $detailIncludesStackTrace = false;

    /**
     * HTTP status for the error.
     *
     * @var int
     */
    protected $httpStatus;

    /**
     * Status titles for common problems
     *
     * @var array
     */
    protected $problemStatusTitles = array(
        404 => 'Not Found',
        409 => 'Conflict',
        422 => 'Unprocessable Entity',
        500 => 'Internal Server Error',
    );

    /**
     * Title of the error.
     *
     * @var string
     */
    protected $title;

    /**
     * Constructor
     *
     * Create an instance using the provided information. If nothing is
     * provided for the describedBy field, the class default will be used;
     * if the httpStatus matches any known, the title field will be selected
     * from $problemStatusTitles as a result.
     *
     * @param  int $httpStatus
     * @param  string $detail
     * @param  string $describedBy
     * @param  string $title
     */
    public function __construct($httpStatus, $detail, $describedBy = null, $title = null)
    {
        $this->httpStatus = $httpStatus;
        $this->detail     = $detail;
        $this->title      = $title;
        if (null !== $describedBy) {
            $this->describedBy = $describedBy;
        }
    }

    /**
     * Retrieve properties
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        $names = array(
            'describedby'  => 'describedBy',
            'described_by' => 'describedBy',
            'httpstatus'   => 'httpStatus',
            'http_status'  => 'httpStatus',
            'title'        => 'title',
            'detail'       => 'detail',
        );
        $name = strtolower($name);
        if (!in_array($name, array_keys($names))) {
            throw new Exception\InvalidArgumentException(sprintf(
                'Invalid property name "%s"',
                $name
            ));
        }
        $prop = $names[$name];
        return $this->{$prop};
    }

    /**
     * Cast to an array
     *
     * @return array
     */
    public function toArray()
    {
        return array(
            'describedBy' => $this->describedBy,
            'title'       => $this->getTitle(),
            'httpStatus'  => $this->getHttpStatus(),
            'detail'      => $this->getDetail(),
        );
    }

    /**
     * Set the flag indicating whether an exception detail should include a
     * stack trace and previous exception information.
     *
     * @param  bool $flag
     * @return ApiProblem
     */
    public function setDetailIncludesStackTrace($flag)
    {
        $this->detailIncludesStackTrace = (bool) $flag;
        return $this;
    }

    /**
     * Retrieve the API-Problem detail
     *
     * If an exception was provided, creates the detail message from it;
     * otherwise, detail as provided is used.
     *
     * @return string
     */
    protected function getDetail()
    {
        if ($this->detail instanceof \Exception) {
            return $this->createDetailFromException();
        }
        return $this->detail;
    }

    /**
     * Retrieve the API-Problem HTTP status code
     *
     * If an exception was provided, creates the status code from it;
     * otherwise, code as provided is used.
     *
     * @return string
     */
    protected function getHttpStatus()
    {
        if ($this->detail instanceof \Exception) {
            $this->httpStatus = $this->createStatusFromException();
        }
        return $this->httpStatus;
    }

    /**
     * Retrieve the title
     *
     * If the default $describedBy is used, and the $httpStatus is found in
     * $problemStatusTitles, then use the matching title.
     *
     * If no title was provided, and the above conditions are not met, use the
     * string 'Unknown'.
     *
     * Otherwise, use the title provided.
     *
     * @return string
     */
    protected function getTitle()
    {
        if (null === $this->title
            && $this->describedBy == 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html'
            && array_key_exists($this->getHttpStatus(), $this->problemStatusTitles)
        ) {
            return $this->problemStatusTitles[$this->httpStatus];
        }
        if ($this->detail instanceof \Exception) {
            return get_class($this->detail);
        }
        if (null === $this->title) {
            return 'Unknown';
        }

        return $this->title;
    }

    /**
     * Create detail message from an exception.
     *
     * @return string
     */
    protected function createDetailFromException()
    {
        $e = $this->detail;

        if (!$this->detailIncludesStackTrace) {
            return $e->getMessage();
        }
        $message = '';
        do {
            $message .= $e->getMessage() . "\n";
            $message .= $e->getTraceAsString() . "\n";
            $e = $e->getPrevious();
        } while ($e instanceof \Exception);
        return trim($message);
    }
    /**
     * Create HTTP status from an exception.
     *
     * @return string
     */
    protected function createStatusFromException()
    {
        $e = $this->detail;
        $httpStatus = $e->getCode();
        if (!empty($httpStatus)) {
            return $httpStatus;
        } else {
            return 500;
        }
    }
}
