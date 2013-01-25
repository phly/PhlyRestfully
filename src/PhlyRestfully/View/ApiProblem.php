<?php

namespace PhlyRestfully\View;

class ApiProblem
{
    protected $describedBy = 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html';
    protected $detail = '';
    protected $detailIncludesStackTrace = false;
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

    protected $title;

    public function __construct($httpStatus, $detail, $describedBy = null, $title = null)
    {
        $this->httpStatus = $httpStatus;
        $this->detail = $detail;
        if (null !== $describedBy) {
            $this->describedBy = $describedBy;
        }
        $this->$title = $title;
    }

    public function toArray()
    {
        return array(
            'describedBy' => $this->describedBy,
            'title'       => $this->getTitle(),
            'httpStatus'  => $this->httpStatus,
            'detail'      => $this->detail,
        );
    }

    public function setDetailIncludesStackTrace($flag)
    {
        $this->detailIncludesStackTrace = (bool) $flag;
        return $this;
    }

    protected function getDetail()
    {
        if ($this->detail instanceof \Exception) {
            return $this->createDetailFromException();
        }
        return $this->detail;
    }

    protected function getTitle()
    {
        if (null === $this->title
            && $this->describedBy == 'http://www.w3.org/Protocols/rfc2616/rfc2616-sec10.html'
            && array_key_exists($this->httpStatus, $this->problemStatusTitles)
        ) {
            return $this->problemStatusTitles[$this->httpStatus];
        }

        if (null === $this->title) {
            return 'Unknown';
        }

        return $this->title;
    }

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
}
