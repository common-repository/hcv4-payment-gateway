<?php
/**
 * Hiecor REST API Client
 *
 * @category Client
 * @package  Hiecor/Rest
 */

namespace Hiecor\Rest;

use Hiecor\Rest\HttpClient\HttpClient;

/**
 * REST API Client class.
 *
 * @package Hiecor/Rest
 */
class Client
{

    /**
     * Hiecor REST API Client version.
     */
    const VERSION = '1.0.0';

    /**
     * HttpClient instance.
     *
     * @var HttpClient
     */
    public $http;

    /**
     * Initialize client.
     *
     * @param string $url            Site URL.
     * @param string $userName       API user name.
     * @param string $authKey        API auth key.
     * @param integer $agentId       Hiecor user Id
     * @param array  $options        Options (version, timeout, verify_ssl).
     */
    public function __construct($url, $userName, $authKey, $agentId, $options = [])
    {
        $this->http = new HttpClient($url, $userName, $authKey, $agentId, $options);
    }

    /**
     * POST method.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     *
     * @return array
     */
    public function post($endpoint, $data)
    {
        return $this->http->request($endpoint, 'POST', $data);
    }

    /**
     * PUT method.
     *
     * @param string $endpoint API endpoint.
     * @param array  $data     Request data.
     *
     * @return array
     */
    public function put($endpoint, $data)
    {
        return $this->http->request($endpoint, 'PUT', $data);
    }

    /**
     * GET method.
     *
     * @param string $endpoint   API endpoint.
     * @param array  $parameters Request parameters.
     *
     * @return array
     */
    public function get($endpoint, $parameters = [])
    {
        return $this->http->request($endpoint, 'GET', [], $parameters);
    }

    /**
     * DELETE method.
     *
     * @param string $endpoint   API endpoint.
     * @param array  $parameters Request parameters.
     *
     * @return array
     */
    public function delete($endpoint, $parameters = [])
    {
        return $this->http->request($endpoint, 'DELETE', [], $parameters);
    }

    /**
     * OPTIONS method.
     *
     * @param string $endpoint API endpoint.
     *
     * @return array
     */
    public function options($endpoint)
    {
        return $this->http->request($endpoint, 'OPTIONS', [], []);
    }
}
