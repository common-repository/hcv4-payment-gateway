<?php
/**
 * Hiecor REST API HTTP Client Options
 *
 * @category HttpClient
 * @package  Hiecor/Rest
 */

namespace Hiecor\Rest\HttpClient;

/**
 * REST API HTTP Client Options class.
 *
 * @package Hiecor/Rest
 */
class Options
{

    /**
     * Default Hiecor REST API version.
     */
    const VERSION = 'v1';

    /**
     * Default request timeout.
     */
    const TIMEOUT = 15;

    /**
     * Default Hiecor API prefix.
     * Including leading and trailing slashes.
     */
    const HC_API_PREFIX = '/rest/';

    /**
     * Default User Agent.
     * No version number.
     */
    const USER_AGENT = 'Hiecor API Client-PHP';

    /**
     * Options.
     *
     * @var array
     */
    private $options;

    /**
     * Initialize HTTP client options.
     *
     * @param array $options Client options.
     */
    public function __construct($options)
    {
        $this->options = $options;
    }

    /**
     * Get API version.
     *
     * @return string
     */
    public function getVersion()
    {
        return isset($this->options['version']) ? $this->options['version'] : self::VERSION;
    }

    /**
     * Check if need to verify SSL.
     *
     * @return bool
     */
    public function verifySsl()
    {
        return isset($this->options['verify_ssl']) ? (bool) $this->options['verify_ssl'] : true;
    }

    /**
     * Get timeout.
     *
     * @return int
     */
    public function getTimeout()
    {
        return isset($this->options['timeout']) ? (int) $this->options['timeout'] : self::TIMEOUT;
    }

    /**
     * Basic Authentication as query string.
     *
     * @return bool
     */
    public function isQueryStringAuth()
    {
        return isset($this->options['query_string_auth']) ? (bool) $this->options['query_string_auth'] : false;
    }

    /**
     * Check if is Hiecor REST API.
     *
     * @return bool
     */
    public function isHiecorAPI()
    {
        return isset($this->options['hc_api']) ? (bool) $this->options['hc_api'] : false;
    }

    /**
     * Custom API Prefix for Hiecor API.
     *
     * @return string
     */
    public function apiPrefix()
    {
        return isset($this->options['hc_api_prefix']) ? $this->options['hc_api_prefix'] : self::HC_API_PREFIX;
    }

    /**
     * oAuth timestamp.
     *
     * @return string
     */
    public function oauthTimestamp()
    {
        return isset($this->options['oauth_timestamp']) ? $this->options['oauth_timestamp'] : \time();
    }

    /**
     * Custom user agent.
     *
     * @return string
     */
    public function userAgent()
    {
        return isset($this->options['user_agent']) ? $this->options['user_agent'] : self::USER_AGENT;
    }

    /**
     * Get follow redirects
     *
     * @return bool
     */
    public function getFollowRedirects()
    {
        return isset($this->options['follow_redirects']) ? (bool) $this->options['follow_redirects'] : false;
    }
    
    /**
     * Get APi Source
     *
     * @return string
     */
    public function getApiSource()
    {
        return isset($this->options['X_API_SOURCE']) ? $this->options['X_API_SOURCE'] : '';
    }
}
