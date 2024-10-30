<?php

/**
 * Hiecor REST API HTTP Client
 *
 * @category HttpClient
 * @package  Hiecor/Rest
 */

namespace Hiecor\Rest\HttpClient;

use Hiecor\Rest\Client;
use Hiecor\Rest\HttpClient\HttpClientException;
use Hiecor\Rest\HttpClient\Options;
use Hiecor\Rest\HttpClient\Request;
use Hiecor\Rest\HttpClient\Response;

/**
 * REST API HTTP Client class.
 *
 * @package Hiecor/Rest
 */
class HttpClient {

    /**
     * cURL handle.
     *
     * @var resource
     */
    protected $ch;

    /**
     * Store API URL.
     *
     * @var string
     */
    protected $url;

    /**
     * API user name.
     *
     * @var string
     */
    protected $userName;

    /**
     * API auth key.
     *
     * @var string
     */
    protected $authKey;

    /**
     * Hiecor User Id
     * @var integer 
     */
    protected $agentId;

    /**
     * Client options.
     *
     * @var Options
     */
    protected $options;

    /**
     * Request.
     *
     * @var Request
     */
    private $request;

    /**
     * Response.
     *
     * @var Response
     */
    private $response;

    /**
     * Response headers.
     *
     * @var string
     */
    private $responseHeaders;

    /**
     * Initialize HTTP client.
     *
     * @param string $url           Store URL.
     * @param string $userName      API user name.
     * @param string $authKey       API auth key.
     * @param string $agentId       Hiecor user id.
     * @param array  $options       Client options.
     */
    public function __construct($url, $userName, $authKey, $agentId, $options) {
        if (!\function_exists('curl_version')) {
            throw new HttpClientException('cURL is NOT installed on this server', -1, new Request(), new Response());
        }

        $this->options = new Options($options);
        $this->url = $this->buildApiUrl($url);
        $this->userName = $userName;
        $this->authKey = $authKey;
        $this->agentId = $agentId;
    }

    /**
     * Check if is under SSL.
     *
     * @return bool
     */
    protected function isSsl() {
        return 'https://' === \substr($this->url, 0, 8);
    }

    /**
     * Build API URL.
     *
     * @param string $url Store URL.
     *
     * @return string
     */
    protected function buildApiUrl($url) {
        $api = $this->options->isHiecorAPI() ? $this->options->apiPrefix() : '/rest/';

        return \rtrim($url, '/') . $api . $this->options->getVersion() . '/';
    }

    /**
     * Build URL.
     *
     * @param string $url        URL.
     * @param array  $parameters Query string parameters.
     *
     * @return string
     */
    protected function buildUrlQuery($url, $parameters = []) {

        if (!empty($parameters)) {
           $url .= '?' . \http_build_query($parameters);
           //$url .= '?' . $parameters;
        }
        return $url;
    }

    /**
     * Setup method.
     *
     * @param string $method Request method.
     */
    protected function setupMethod($method) {
        if ('POST' == $method) {
            \curl_setopt($this->ch, CURLOPT_POST, true);
        } elseif (\in_array($method, ['PUT', 'DELETE', 'OPTIONS'])) {
            \curl_setopt($this->ch, CURLOPT_CUSTOMREQUEST, $method);
        }
    }

    /**
     * Get request headers.
     *
     * @param  bool $sendData If request send data or not.
     *
     * @return array
     */
    protected function getRequestHeaders($sendData = false) {
        $headers = [
            'Accept' => 'application/json',
            'User-Agent' => $this->options->userAgent() . '/' . Client::VERSION,
            'X-USERNAME' => $this->userName,
            'X-AUTH-KEY' => $this->authKey,
            'X-AGENT-ID' => $this->agentId,
            'X-API-SOURCE' => $this->options->getApiSource(),
        ];

        if ($sendData) {
            $headers['Content-Type'] = 'application/json;charset=utf-8';
        }

        return $headers;
    }

    /**
     * Create request.
     *
     * @param string $endpoint   Request endpoint.
     * @param string $method     Request method.
     * @param array  $data       Request data.
     * @param array  $parameters Request parameters.
     *
     * @return Request
     */
    protected function createRequest($endpoint, $method, $data = [], $parameters = []) {
        $body = '';
        $url = $this->url . $endpoint;
        $hasData = !empty($data);
        // Setup method.
        $this->setupMethod($method);
        // Include post fields.
        if ($method=='POST' && $hasData) {
            $body = \json_encode($data);
            \curl_setopt($this->ch, CURLOPT_POSTFIELDS, $body);
            //\curl_setopt($this->ch, CURLOPT_POSTFIELDS, $data);
        }

        $this->request = new Request(
                $this->buildUrlQuery($url, $parameters), $method, $parameters, $this->getRequestHeaders($hasData), $body
        );
        return $this->getRequest();
    }

    /**
     * Get response headers.
     *
     * @return array
     */
    protected function getResponseHeaders() {
        $headers = [];
        $lines = \explode("\n", $this->responseHeaders);
        $lines = \array_filter($lines, 'trim');

        foreach ($lines as $index => $line) {
            // Remove HTTP/xxx params.
            if (strpos($line, ': ') === false) {
                continue;
            }

            list($key, $value) = \explode(': ', $line);

            $headers[$key] = isset($headers[$key]) ? $headers[$key] . ', ' . trim($value) : trim($value);
        }

        return $headers;
    }

    /**
     * Create response.
     *
     * @return Response
     */
    protected function createResponse() {

        // Set response headers.
        $this->responseHeaders = '';
        \curl_setopt($this->ch, CURLOPT_HEADERFUNCTION, function ($_, $headers) {
            $this->responseHeaders .= $headers;
            return \strlen($headers);
        });

        // Get response data.
        $body = \curl_exec($this->ch);
        $code = \curl_getinfo($this->ch, CURLINFO_HTTP_CODE);
        $headers = $this->getResponseHeaders();

        // Register response.
        $this->response = new Response($code, $headers, $body);

        return $this->getResponse();
    }

    /**
     * Set default cURL settings.
     */
    protected function setDefaultCurlSettings() {
        $verifySsl = $this->options->verifySsl();
        $timeout = $this->options->getTimeout();
        $followRedirects = $this->options->getFollowRedirects();

        \curl_setopt($this->ch, CURLOPT_SSL_VERIFYPEER, $verifySsl);
        if (!$verifySsl) {
            \curl_setopt($this->ch, CURLOPT_SSL_VERIFYHOST, $verifySsl);
        }
        if ($followRedirects) {
            \curl_setopt($this->ch, CURLOPT_FOLLOWLOCATION, true);
        }
        \curl_setopt($this->ch, CURLOPT_CONNECTTIMEOUT, $timeout);
        \curl_setopt($this->ch, CURLOPT_TIMEOUT, $timeout);
        \curl_setopt($this->ch, CURLOPT_RETURNTRANSFER, true);
        \curl_setopt($this->ch, CURLOPT_HTTPHEADER, $this->request->getRawHeaders());
        \curl_setopt($this->ch, CURLOPT_URL, $this->request->getUrl());
    }

    /**
     * Look for errors in the request.
     *
     * @param array $parsedResponse Parsed body response.
     */
    protected function lookForErrors($parsedResponse) {
        // Any non-200/201/202 response code indicates an error.
        if (!\in_array($this->response->getCode(), ['200', '201', '202'])) {
            $errors = isset($parsedResponse->errors) ? $parsedResponse->errors : $parsedResponse;

            if (is_array($errors)) {
                $errorMessage = $errors[0]['message'];
                $errorCode = $errors[0]['code'];
            } else {
                $errorMessage = $errors->message;
                $errorCode = $errors->code;
            }

            throw new HttpClientException(
            \sprintf('Error: %s [%s]', $errorMessage, $errorCode), $this->response->getCode(), $this->request, $this->response
            );
        }
    }

    /**
     * Process response.
     *
     * @return array
     */
    protected function processResponse() {
        $body = $this->response->getBody();

        if (0 === strpos(bin2hex($body), 'efbbbf')) {
            $body = substr($body, 3);
        }

        $parsedResponse = \json_decode($body);

        // Test if return a valid JSON.
        if (JSON_ERROR_NONE !== json_last_error()) {
            $message = function_exists('json_last_error_msg') ? json_last_error_msg() : 'Invalid JSON returned';
            throw new HttpClientException($message, $this->response->getCode(), $this->request, $this->response);
        }

        $this->lookForErrors($parsedResponse);

        return $parsedResponse;
    }

    /**
     * Make requests.
     *
     * @param string $endpoint   Request endpoint.
     * @param string $method     Request method.
     * @param array  $data       Request data.
     * @param array  $parameters Request parameters.
     *
     * @return array
     */
    public function request($endpoint, $method, $data = [], $parameters = []) {
        // Initialize cURL.
        $this->ch = \curl_init();

        // Set request args.
        $request = $this->createRequest($endpoint, $method, $data, $parameters);

        // Default cURL settings.
        $this->setDefaultCurlSettings();

        // Get response.
        $response = $this->createResponse();

        // Check for cURL errors.
        if (\curl_errno($this->ch)) {
            throw new HttpClientException('cURL Error: ' . \curl_error($this->ch), 0, $request, $response);
        }

        \curl_close($this->ch);

        return $this->processResponse();
    }

    /**
     * Get request data.
     *
     * @return Request
     */
    public function getRequest() {
        return $this->request;
    }

    /**
     * Get response data.
     *
     * @return Response
     */
    public function getResponse() {
        return $this->response;
    }

}
