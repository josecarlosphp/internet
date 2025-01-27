<?php

namespace josecarlosphp\internet;

/**
 * @author josecarlosphp.com
 */
abstract class Http
{
    public const HTTP_RESPONSE_CODES = array(
        200 => 'OK',
        400 => 'Bad Request',
        401 => 'Unauthorized',
        403 => 'Forbidden',
        404 => 'Not Found',
        408 => 'Request Timeout',
        500 => 'Internal Server Error',
        502 => 'Bad Gateway',
        503 => 'Service Unavailable',
    );

    static public function exit($http_response_code)
    {
        if (($text = self::httpResponseCode2Text($http_response_code))) {
            header(sprintf('HTTP/1.1 %u %s', $http_response_code, $text), true, (int) $http_response_code);
        }

        http_response_code((int) $http_response_code);
        exit;
    }

    static public function httpResponseCode2Text($http_response_code)
    {
        return array_key_exists((int) $http_response_code, self::HTTP_RESPONSE_CODES) ? self::HTTP_RESPONSE_CODES[(int) $http_response_code] : '';
    }
}
