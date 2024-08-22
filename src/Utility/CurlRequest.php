<?php
namespace ZoomRx\Audio\Utility;

use Exception;

/**
 * This is a PHP class named CurlRequest. It likely encapsulates functionality related to sending HTTP requests through Curl.
 */
class CurlRequest
{
    const GET = 'GET';
    const POST = 'POST';
    const DELETE = 'DELETE';

    /**
     * Sends an HTTP request using Curl 
     * @param string $url
     * @param string $method
     * @param array $headers
     * @param mixed $data
     * @throws Exception
     * @return mixed
     */
    public static function send($url, $method = self::GET, $headers = [], $data = null)
    {
        $ch = curl_init();
    
        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
        // Set request data if applicable
        if (!empty($data)) {
            if ($method === self::POST) {
                curl_setopt($ch, CURLOPT_POST, true);
            }

            curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        }
    
        $response = curl_exec($ch);
    
        if (curl_errno($ch)) {
            $httpStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $errorMessage = 'cURL Error: ' . curl_error($ch) . ' (HTTP Status Code: ' . $httpStatusCode . ')';
            throw new Exception($errorMessage);
        }
    
        curl_close($ch);
    
        return $response;
    }
    
}