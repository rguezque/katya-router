<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2024 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use rguezque\Exceptions\CurlException;

/**
 * Represents an HTTP client-side request.
 * 
 * @method ClientRequest withRequestMethod(string $method) Specifies/overwrite the http request method for request
 * @method ClientRequest withHeader(string $key, string $value) Add a header to the request
 * @method ClientRequest withHeaders(array $headers) Add multiple headers to the request
 * @method ClientRequest withBasicAuth(string $username, string $password) Add an Authorization header for basic authorization
 * @method ClientRequest withTokenAuth(string $token) Add an Authorization header for JWT authorization
 * @method ClientRequest withPostFields(array $data, bool $encode = false) Add posts fields to send to request
 * @method string|bool send() Send the client request
 * @method mixed getContent() Return the result of http client request
 * @method array toArray() Return the result of http client request decoded from json to an associative array
 * @method array getInfo() Retrieves info about the responsed request
 */
class ClientRequest {

    /**
     * Predefined const GET
     * 
     * @var string
     */
    public const GET = 'GET';

    /**
     * Predefined const POST
     * 
     * @var string
     */
    public const POST = 'POST';

    /**
     * Predefined const PUT
     * 
     * @var string
     */
    public const PUT = 'PUT';

    /**
     * Predefined const PATCH
     * 
     * @var string
     */
    public const PATCH = 'PATCH';

    /**
     * Predefined const DELETE
     * 
     * @var string
     */
    public const DELETE = 'DELETE';

    /**
     * URI to request
     * 
     * @var string
     */
    private $uri;

    /**
     * Default request method
     * 
     * @var string
     */
    private $method = 'GET';

    /**
     * Headers to send
     * 
     * @var string[]
     */
    private $headers = [];

    /**
     * Data to send
     * 
     * @var string|array
     */
    private $data_string;

    /**
     * Info request
     * 
     * @var array
     */
    private $info_request = [];

    /**
     * Resutt of client request
     * 
     * @var mixed
     */
    private $result;

    /**
     * Prepare the request
     * 
     * @var string $uri URI to send the request
     * @var string $method Default HTTP request method
     */
    public function __construct(string $uri, string $method = ClientRequest::GET) {
        $this->uri = $uri;
        $this->withRequestMethod($method);
    }

    /**
     * Specifies/overwrite the http request method for request
     * 
     * @var string $method Default HTTP request method
     * @return ClientRequest
     */
    public function withRequestMethod(string $method): ClientRequest {
        $this->method = strtoupper(trim($method));
        return $this;
    }

    /**
     * Add a header to the request
     * 
     * @var string $key Header name
     * @var string $value Header content
     * @return ClientRequest
     */
    public function withHeader(string $key, string $value): ClientRequest {
        $this->headers[trim($key)] = $value;
        return $this;
    }

    /**
     * Add multiple headers to the request
     * 
     * @var string $headers Associative array with headers as key and his content
     * @return ClientRequest
     */
    public function withHeaders(array $headers): ClientRequest {
        $this->headers = array_merge($this->headers, $headers);

        return $this;
    }

    /**
     * Retrieves the headers array
     * 
     * @return array
     */
    private function getHeaders(): array {
        $headers = [];
        foreach($this->headers as $key=>$value) {
            $headers[] = sprintf('%s: %s', $key, $value);
        }

        return $headers;
    }

    /**
     * Add an Authorization header for basic authorization
     * 
     * @var string $username Identity
     * @var string $password Credential
     * @return ClientRequest
     */
    public function withBasicAuth(string $username, string $password): ClientRequest {
        $this->withHeader('Authorization', sprintf('Basic %s:%s', $username, $password));
        return $this;
    }

    /**
     * Add an Authorization header for JWT authorization
     * 
     * @var string $token JSON Web Token
     * @return ClientRequest
     */
    public function withTokenAuth(string $token): ClientRequest {
        $this->withHeader('Authorization', sprintf('Bearer %s', $token));
        return $this;
    }

    /**
     * Add post fields to send to request
     * 
     * @param array $data Data to send
     * @param bool $encode Specifies if data must be encoded to JSON
     * @return ClientRequest
     */
    public function withPostFields(array $data, bool $encode = false): ClientRequest {
        $this->data_string = $encode ? json_encode($data) : $data;
        
        if($encode) {
            $this->withHeaders([
                'Content-Type' => 'application/json;charset=utf-8',
                'Accept' => 'application/json',
                'Content-Length' => strlen($this->data_string)
            ]);
        }

        return $this;
    }

    /**
     * Send the client request
     * 
     * @return void
     * @throws CurlException
     */
    public function send(): void {
        $curl = curl_init();
        curl_setopt($curl, CURLOPT_URL, $this->uri);

        curl_setopt($curl, CURLOPT_CUSTOMREQUEST, $this->method); 
        curl_setopt($curl, CURLOPT_FAILONERROR, true);

        if(ClientRequest::GET !== $this->method) {
            $post_data = is_string($this->data_string) ? $this->data_string : http_build_query($this->data_string);
            curl_setopt($curl, CURLOPT_POST, true);
            curl_setopt($curl, CURLOPT_POSTFIELDS, $post_data);
        }

        curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($curl, CURLOPT_HTTPHEADER, $this->getHeaders());

        $response = curl_exec($curl);
        
        $this->info_request = curl_getinfo($curl);
        $curl_error = curl_error($curl);
        curl_close($curl);

        if($curl_error) {
            throw new CurlException(sprintf('cURL error: %s', $curl_error));
        }
        
        $this->result = $response;
    }

    /**
     * Return the result of http client request
     * 
     * @return mixed
     */
    public function getContent() {
        return $this->result;
    }

    /**
     * Return the result of http client request decoded from json to an associative array
     * 
     * @return array
     */
    public function toArray(): array {
        return json_decode($this->result, true) ?? [];
    }

    /**
     * Retrieves info about the request
     * 
     * @return array
     */
    public function getInfo(): array {
        return $this->info_request;
    }

}
