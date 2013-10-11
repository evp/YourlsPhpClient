<?php

/**
 * API client for yourls.org
 *
 * @author Šarūnas Dubinskas <s.dubinskas@evp.lt>
 */
class YourlsClient
{
    /**
     * Available actions
     */
    const ACTION_SHORTURL = 'shorturl';
    const ACTION_URL_STATS = 'url-stats';
    const ACTION_EXPAND = 'expand';

    /**
     * @var array
     */
    static protected $httpCodeMessageMap = array(
        100 => "Continue",
        101 => "Switching Protocols",

        200 => "OK",
        201 => "Created",
        202 => "Accepted",
        203 => "Non-Authoritative Information",
        204 => "No Content",
        205 => "Reset Content",
        206 => "Partial Content",

        300 => "Multiple Choices",
        301 => "Moved Permanently",
        302 => "Found",
        303 => "See Other",
        304 => "Not Modified",
        305 => "Use Proxy",
        306 => "(Unused)",
        307 => "Temporary Redirect",

        400 => "Bad Request",
        401 => "Unauthorized",
        402 => "Payment Required",
        403 => "Forbidden",
        404 => "Not Found",
        405 => "Method Not Allowed",
        406 => "Not Acceptable",
        407 => "Proxy Authentication Required",
        408 => "Request Timeout",
        409 => "Conflict",
        410 => "Gone",
        411 => "Length Required",
        412 => "Precondition Failed",
        413 => "Request Entity Too Large",
        414 => "Request-URI Too Long",
        415 => "Unsupported Media Type",
        416 => "Requested Range Not Satisfiable",
        417 => "Expectation Failed",

        500 => "Internal Server Error",
        501 => "Not Implemented",
        502 => "Bad Gateway",
        503 => "Service Unavailable",
        504 => "Gateway Timeout",
        505 => "HTTP Version Not Supported",
    );

    /**
     * @var string
     */
    protected $apiUrl;

    /**
     * @var null|string
     */
    protected $username;

    /**
     * @var null|string
     */
    protected $password;

    /**
     * @var null|string
     */
    protected $token;

    /**
     * @var string
     */
    private $lastResponse;

    /**
     * Class constructor
     *
     * @param string $apiUrl
     * @param null|string $username
     * @param null|string $password
     * @param null|string $token
     */
    public function __construct($apiUrl, $username = null, $password = null, $token = null)
    {
        $this->apiUrl = $apiUrl;
        $this->username = $username;
        $this->password = $password;
        $this->token = $token;
    }

    /**
     * Get short URL for a URL
     *
     * @param string $url
     * @param null|string $keyword
     *
     * @return string
     */
    public function shorten($url, $keyword = null)
    {
        $result = $this->call(self::ACTION_SHORTURL, array('url' => $url, 'keyword' => $keyword));
        if (empty($result['status']) || $result['status'] == 'fail') {
            throw new YourlsClient_Exception(
                isset($result['message'])
                    ? $result['message']
                    : sprintf('Could not shorten url address %s [%s]', $url, $keyword)
            );
        }
        return $result['shorturl'];
    }

    /**
     * Get stats about short URL
     *
     * @param string $shortUrl
     *
     * @return array
     */
    public function getUrlStats($shortUrl)
    {
        return $this->call(self::ACTION_URL_STATS, array('shorturl' => $shortUrl));
    }

    /**
     * Get long URL of a short URL
     *
     * @param string $shortUrl
     *
     * @return string
     */
    public function expand($shortUrl)
    {
        $result = $this->call(self::ACTION_EXPAND, array('shorturl' => $shortUrl));
        return $result['longurl'];
    }

    /**
     * Returns last raw response from API
     *
     * @return string
     */
    public function getLastResponse()
    {
        return $this->lastResponse;
    }

    /**
     * Calls API action with specified params
     *
     * @param string $action
     * @param array $params
     *
     * @return array
     *
     * @throws YourlsClient_Exception
     */
    protected function call($action, $params = array())
    {
        $params['action'] = $action;
        if ($this->username) {
            $params['username'] = $this->username;
            $params['password'] = $this->username;
        } else {
            $params['timestamp'] = time();
            $params['signature'] = md5($this->token . $params['timestamp']);
        }
        $params['format'] = 'json';

        $url = $this->apiUrl . '?' . http_build_query($params);

        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_AUTOREFERER, true);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array("Expect:"));

        $output = $this->lastResponse = curl_exec($ch);

        try {
            if ($output === false) {
                throw new YourlsClient_Exception(curl_error($ch), curl_errno($ch));
            } else if (($code = curl_getinfo($ch, CURLINFO_HTTP_CODE)) != 200) {
                $result = json_decode($output, true);
                if ($result == null) {
                    $result['message'] = self::$httpCodeMessageMap[$code];
                }
                throw new YourlsClient_Exception($result['message'], $code);
            }
        } catch (YourlsClient_Exception $e) {
            curl_close($ch);
            throw $e;
        }

        curl_close($ch);

        $result = json_decode($output, true);
        if ($result === null) {
            throw new YourlsClient_Exception("JSON decode error", json_last_error());
        }

        return $result;
    }

}
