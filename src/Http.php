<?php namespace Stevenmaguire\Services\Trello;

use GuzzleHttp\Client as HttpClient;
use GuzzleHttp\ClientInterface as HttpClientInterface;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Psr7\Request;
use Psr\Http\Message\RequestInterface;

class Http
{
    /**
     * Http client
     *
     * @var HttpClientInterface
     */
    protected $httpClient;

    /**
     * Creates a new http broker.
     */
    public function __construct()
    {
        $this->httpClient = new HttpClient;
    }

    /**
     * Adds authentication credentials to given request.
     *
     * @param  RequestInterface  $request
     *
     * @return RequestInterface
     */
    protected function authenticateRequest(RequestInterface $request)
    {
        $uri = $request->getUri();
        parse_str($uri->getQuery(), $query);

        $query['key'] = Configuration::get('key');
        $query['token'] = Configuration::get('token');

        $uri = $uri->withQuery(http_build_query($query));

        return $request->withUri($uri);
    }

    /**
     * Creates a request.
     *
     * @param  string $verb
     * @param  string $path
     * @param  array  $parameters
     *
     * @return Request
     */
    protected function createRequest($verb, $path, $parameters = [])
    {
        $queryString = in_array(strtolower($verb), ['delete', 'get']);

        $request = new Request(
            $verb,
            $this->getUrlFromPath($path),
            $this->getHeaders(),
            (!$queryString ? json_encode($parameters) : '{}')
        );

        if ($queryString) {
            $uri = $request->getUri();
            array_walk($parameters, function ($value, $key) use (&$uri) {
                $uri = $uri->withQueryValue($uri, $key, $value);
            });
            $request = $request->withUri($uri);
        }

        return $request;
    }

    /**
     * Retrieves http response for a request with the delete method.
     *
     * @param  string $path
     * @param  array  $parameters
     *
     * @return object
     */
    public function delete($path, $parameters = [])
    {
        $request = $this->getRequest('DELETE', $path, $parameters);

        return $this->sendRequest($request);
    }

    /**
     * Retrieves http response for a request with the get method.
     *
     * @param  string $path
     * @param  array  $parameters
     *
     * @return object
     */
    public function get($path, $parameters = [])
    {
        $request = $this->getRequest('GET', $path, $parameters);

        return $this->sendRequest($request);
    }

    /**
     * Creates and returns a request.
     *
     * @param  string $method
     * @param  string $path
     * @param  array  $parameters
     *
     * @return RequestInterface
     */
    public function getRequest($method, $path, $parameters = [], $authenticated = true)
    {
        $request = $this->createRequest($method, $path, $parameters);

        if ($authenticated) {
            $request = $this->authenticateRequest($request);
        }

        return $request;
    }

    /**
     * Retrieves default headers.
     *
     * @return array
     */
    protected function getHeaders()
    {
        return [];
    }

    /**
     * Creates fully qualified domain from given path.
     *
     * @param  string  $path
     *
     * @return string
     */
    protected function getUrlFromPath($path = '/')
    {
        return Configuration::get('domain').'/'.Configuration::get('version').'/'.ltrim($path, '/');
    }

    /**
     * Retrieves http response for a request with the post method.
     *
     * @param  string $path
     * @param  array  $parameters
     *
     * @return object
     */
    public function post($path, $parameters)
    {
        $request = $this->getRequest('POST', $path, $parameters);

        return $this->sendRequest($request);
    }

    /**
     * Retrieves http response for a request with the put method.
     *
     * @param  string $path
     * @param  array  $parameters
     *
     * @return object
     */
    public function put($path, $parameters)
    {
        $request = $this->getRequest('PUT', $path, $parameters);

        return $this->sendRequest($request);
    }

    /**
     * Retrieves http response for a given request.
     *
     * @param  RequestInterface $request
     *
     * @return object
     * @throws Exception
     */
    protected function sendRequest(RequestInterface $request)
    {
        try {
            $response = $this->httpClient->send($request);

            return json_decode($response->getBody());
        } catch (RequestException $e) {
            $exception = new Exceptions\Exception(
                $e->getResponse()->getReasonPhrase(),
                $e->getResponse()->getStatusCode(),
                $e
            );

            throw $exception->setResponseBody(
                json_decode(
                    (string) $e->getResponse()->getBody()
                )
            );
        }
    }

    /**
     * Updates the http client.
     *
     * @param HttpClientInterface  $httpClient
     *
     * @return Http
     */
    public function setClient(HttpClientInterface $httpClient)
    {
        $this->httpClient = $httpClient;

        return $this;
    }
}
