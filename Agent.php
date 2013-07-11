<?php

namespace hotellook\composer\api;

use Yii;

/**
 * @method array getJson() getJson($method, array $params = array(), $auth = true)
 * @method array postJson() postJson($method, array $params = array(), array $data = array(), $auth = true)
 * @method array putJson() putJson($method, array $params = array(), array $data = array(), $auth = true)
 * @method array deleteJson() deleteJson($method, array $params = array(), $auth = true)
 * @method array patchJson() patchJson($method, array $params = array(), array $data = array(), $auth = true)
 * @method SimpleXMLElement getXml() getXml($method, array $params = array(), $auth = true)
 * @method SimpleXMLElement postXml() postXml($method, array $params = array(), array $data = array(), $auth = true)
 * @method SimpleXMLElement putXml() putXml($method, array $params = array(), array $data = array(), $auth = true)
 * @method SimpleXMLElement deleteXml() deleteXml($method, array $params = array(), $auth = true)
 * @method SimpleXMLElement patchXml() patchXml($method, array $params = array(), array $data = array(), $auth = true)
 */
class Agent extends \CApplicationComponent
{
    public $login;
    public $host;
    public $token;
    public $profile = false;
    public $version = 1;

    private $_queryCacheDuration = null;

    private $_availableHttpMethods = array(
        'get', 'post', 'put', 'delete', 'patch',
    );

    private $_availableFormats = array(
        'json', 'xml',
    );

    public function init()
    {
        if (empty($this->login)) {
            throw new Exception('Required parameter `login` is not defined');
        }

        if (empty($this->token)) {
            throw new Exception('Required parameter `token` is not defined');
        }

        if (empty($this->host)) {
            throw new Exception('Required parameter `host` is not defined');
        }

        if ($this->host !== filter_var($this->host, FILTER_VALIDATE_URL, FILTER_FLAG_SCHEME_REQUIRED)) {
            throw new Exception('Parameter `host` is malformed');
        }

        parent::init();
    }

    /**
     * Генерирует подпись к запросу
     *
     * @param array $params
     * @return string
     */
    public function signV1Params(array &$params)
    {
        unset($params['login']);
        ksort($params);

        $paramsStr = http_build_query($params);
        $params['login'] = $this->login;
        $signature = hash_hmac('sha1', $paramsStr, $this->token);
        $params['signature'] = $signature;

        return $signature;
    }

    public function __call($function, $args)
    {
        if (preg_match('#([a-z]+)([A-Z][a-z]+)#', $function, $match)) {
            $match[1] = strtolower($match[1]);
            $match[2] = strtolower($match[2]);

            if (!in_array($match[1], $this->_availableHttpMethods)) {
                throw new Exception('Unknown method');
            }

            if (!in_array($match[2], $this->_availableFormats)) {
                throw new Exception('Unknown method');
            }

            if (count($args) < 1) {
                throw new Exception('Method is not defined');
            }

            $params = array(
                $args[0],
                isset($args[1]) ? $args[1] : array(),
                $match[1],
                $match[2],
            );

            if (in_array($match[1], array('put', 'patch', 'post')) && isset($args[2])) {
                $params[] = !isset($args[3]) || $args[3];
                $params[] = $args[2];
            } else {
                $params[] = !isset($args[2]) || $args[2];
            }

            $cacheKey = '';
            if ($this->_queryCacheDuration > 0) {
                $cacheKey = serialize($params);
                $ret = Yii::app()->cache->get($cacheKey);
            }

            if (empty($ret)) {
                $ret = call_user_func_array(array($this, '_request'), $params);

                if ($this->_queryCacheDuration > 0) {
                    Yii::app()->cache->set($cacheKey, $ret, $this->_queryCacheDuration);
                }
            }

            $this->_queryCacheDuration = null;

            switch ($match[2]) {
                case 'json':
                    $ret = json_decode($ret, true);
                    break;
                case 'xml':
                    $ret = simplexml_load_string($ret);
                    break;
            }
        } else {
            throw new Exception('Unknown method');
        }

        return $ret;
    }

    /**
     * @param $duration
     * @return Agent
     */
    public function cache($duration)
    {
        $this->_queryCacheDuration = $duration;
        return $this;
    }

    /**
     *
     * @param $httpMethod
     * @param string $method
     * @param array $params
     * @param string $format
     * @param bool $auth
     * @param mixed $data
     * @return string
     */
    private function _request($method, array $params = array(), $httpMethod = 'get', $format = 'json', $auth = true, $data = null)
    {
        $url = rtrim($this->host, '/') . '/v' . $this->version . '/' . $method . '.' . $format;
        $headers = array(
            'User-Agent' => 'Hotellook Api Agent',
        );

        if ($auth) {
            $this->signV1Params($params);
        }

        if (!empty($params)) {
             $url .= '?' . http_build_query($params);
        }

        $opts = array(
            'http' => array(
                'method' => strtoupper($httpMethod),
            )
        );

        if (!empty($data)) {
            $content = $data;
            if (is_array($data)) {
                if (isset($data['headers'])) {
                    $headers = array_merge($headers, $data['headers']);
                    unset($data['headers']);
                }
                if (isset($data['content'])) {
                    $content = $data['content'];

                    if (is_array($content)) {
                        $content = http_build_query($content);
                    }

                    unset($data['content']);
                } elseif (!empty($data)) {
                    $content = http_build_query($data);
                }
            }

            $headers['Content-length'] = strlen($content);

            if (!isset($headers['Content-type'])) {
                $headers['Content-type'] = 'application/x-www-form-urlencoded';
            }

            $opts['http']['content'] = $content;
        }

        $headersStr = '';
        foreach ($headers AS $h => $v) {
            $headersStr .= "$h: $v\r\n";
        }

        $opts['http']['header'] = $headersStr;

        if ($this->profile) {
            Yii::beginProfile($httpMethod . ':' . $url, 'hl.api');
        }

        $context  = stream_context_create($opts);

        $ret = file_get_contents($url, false, $context);

        if ($this->profile) {
            Yii::beginProfile($httpMethod . ':' . $url, 'hl.api');
        }

        return $ret;
    }
}
