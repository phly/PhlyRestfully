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
     * Additional details to include in report
     *
     * @var array
     */
    protected $additionalDetails = array();

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
     * Normalized property names for overloading
     *
     * @var array
     */
    protected $normalizedProperties = array(
        'describedby'  => 'describedBy',
        'described_by' => 'describedBy',
        'httpstatus'   => 'httpStatus',
        'http_status'  => 'httpStatus',
        'title'        => 'title',
        'detail'       => 'detail',
    );

    /**
     * Status titles for common problems
     *
     * @var array
     */
    protected $problemStatusTitles = array(
        // INFORMATIONAL CODES
        100 => 'Continue',
        101 => 'Switching Protocols',
        102 => 'Processing',
        // SUCCESS CODES
        200 => 'OK',
        201 => 'Created',
        202 => 'Accepted',
        203 => 'Non-Authoritative Information',
        204 => 'No Content',
        205 => 'Reset Content',
        206 => 'Partial Content',
        207 => 'Multi-status',
        208 => 'Already Reported',
        // REDIRECTION CODES
        300 => 'Multiple Choices',
        301 => 'Moved Permanently',
        302 => 'Found',
        303 => 'See Other',
        304 => 'Not Modified',
        305 => 'Use Proxy',
        306 => 'Switch Proxy', // Deprecated
        307 => 'Temporary Redirect',
        // CLIENT ERROR
        400 => 'Bad Request',
        401 => 'Unauthorized',
        402 => 'Payment Required',
        403 => 'Forbidden',
        404 => 'Not Found',
        405 => 'Method Not Allowed',
        406 => 'Not Acceptable',
        407 => 'Proxy Authentication Required',
        408 => 'Request Time-out',
        409 => 'Conflict',
        410 => 'Gone',
        411 => 'Length Required',
        412 => 'Precondition Failed',
        413 => 'Request Entity Too Large',
        414 => 'Request-URI Too Large',
        415 => 'Unsupported Media Type',
        416 => 'Requested range not satisfiable',
        417 => 'Expectation Failed',
        418 => 'I\'m a teapot',
        422 => 'Unprocessable Entity',
        423 => 'Locked',
        424 => 'Failed Dependency',
        425 => 'Unordered Collection',
        426 => 'Upgrade Required',
        428 => 'Precondition Required',
        429 => 'Too Many Requests',
        431 => 'Request Header Fields Too Large',
        // SERVER ERROR
        500 => 'Internal Server Error',
        501 => 'Not Implemented',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
        504 => 'Gateway Time-out',
        505 => 'HTTP Version not supported',
        506 => 'Variant Also Negotiates',
        507 => 'Insufficient Storage',
        508 => 'Loop Detected',
        511 => 'Network Authentication Required',
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
    public function __construct($httpStatus, $detail, $describedBy = null, $title = null, array $additional = array())
    {
        $this->httpStatus = $httpStatus;
        $this->detail     = $detail;
        $this->title      = $title;
        if (null !== $describedBy) {
            $this->describedBy = $describedBy;
        }
        $this->additionalDetails = $additional;
    }

    /**
     * Retrieve properties
     *
     * @param  string $name
     * @return mixed
     */
    public function __get($name)
    {
        $normalized = strtolower($name);
        if (in_array($normalized, array_keys($this->normalizedProperties))) {
            $prop = $this->normalizedProperties[$normalized];
            return $this->{$prop};
        }

        if (isset($this->additionalDetails[$name])) {
            return $this->additionalDetails[$name];
        }

        if (isset($this->additionalDetails[$normalized])) {
            return $this->additionalDetails[$normalized];
        }

        throw new Exception\InvalidArgumentException(sprintf(
            'Invalid property name "%s"',
            $name
        ));
    }

    /**
     * Cast to an array
     *
     * @return array
     */
    public function toArray()
    {
        $problem = array(
            'describedBy' => $this->describedBy,
            'title'       => $this->getTitle(),
            'httpStatus'  => $this->getHttpStatus(),
            'detail'      => $this->getDetail(),
        );
        // Required fields should always overwrite additional fields
        return array_merge($this->additionalDetails, $problem);
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
