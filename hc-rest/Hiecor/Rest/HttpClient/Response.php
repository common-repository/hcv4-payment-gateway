<?php
/**
 * Hiecor REST API HTTP Client Response
 *
 * @category HttpClient
 * @package  Hiecor/Rest
 */

namespace Hiecor\Rest\HttpClient;

/**
 * REST API HTTP Client Response class.
 *
 * @package Hiecor/Rest
 */
class Response
{

    /**
     * Response code.
     *
     * @var int
     */
    private $code;

    /**
     * Response headers.
     *
     * @var array
     */
    private $headers;

    /**
     * Response body.
     *
     * @var string
     */
    private $body;

    /**
     * Initialize response.
     *
     * @param int    $code    Response code.
     * @param array  $headers Response headers.
     * @param string $body    Response body.
     */
    public function __construct($code = 0, $headers = [], $body = '')
    {
        $this->code    = $code;
        $this->headers = $headers;
        $this->body    = $body;
    }

    /**
     * Set code.
     *
     * @param int $code Response code.
     */
    public function setCode($code)
    {
        $this->code = (int) $code;
    }

    /**
     * Set headers.
     *
     * @param array $headers Response headers.
     */
    public function setHeaders($headers)
    {
        $this->headers = $headers;
    }

    /**
     * Set body.
     *
     * @param string $body Response body.
     */
    public function setBody($body)
    {
        $this->body = $body;
    }

    /**
     * Get code.
     *
     * @return int
     */
    public function getCode()
    {
        return $this->code;
    }

    /**
     * Get headers.
     *
     * @return array $headers Response headers.
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Get body.
     *
     * @return string $body Response body.
     */
    public function getBody()
    {
        return $this->body;
    }
}
