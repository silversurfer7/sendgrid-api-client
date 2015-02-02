<?php
/**
 *
 * @author: sschulze@silversurfer7.de
 */

namespace Silversurfer7\Sendgrid\Api\Client;


use GuzzleHttp\Client;
use GuzzleHttp\Query;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Exception\ServerException;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Post\PostBody;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use Psr\Log\LoggerInterface;
use Silversurfer7\Sendgrid\Api\Client\Exception\ApiCallFailedException;
use Silversurfer7\Sendgrid\Api\Client\Exception\ApiCallParameterErrorException;
use Silversurfer7\Sendgrid\Api\Client\Exception\InvalidCredentialsException;
use Silversurfer7\Sendgrid\Api\Client\Exception\UnknownResponseStatusCodeException;
use Silversurfer7\Sendgrid\Api\Client\Logger\NullLogger;

class SendgridApiClient {

    const API_URL = 'https://api.sendgrid.com/api/';
    const ENV_SENDGRID_LOGIN = 'SENDGRID_API_LOGIN';
    const ENV_SENDGRID_PASSWORD = 'SENDGRID_API_PASSWORD';

    const REQUEST_TIMEOUT = 30;

    private $login;
    private $password;
    /** @var \GuzzleHttp\ClientInterface  */
    private $client;


    /** @var \Psr\Log\LoggerInterface  */
    private $logger;

    public function __construct($login, $password, $connectionOptions = array(), LoggerInterface $logger = null) {

        if (!is_array($connectionOptions)) {
            throw new \InvalidArgumentException('connectionOptions must be an array');
        }
        $this->initializeCredentials($login, $password);
        $this->logger = $this->getLoggerOrNullLogger($logger);
        $client = $this->initializeHttpConnector($connectionOptions);
        $this->setClient($client);
    }

    /**
     * @param $login
     * @param $password
     * @throws Exception\InvalidCredentialsException
     */
    protected function initializeCredentials($login, $password) {

        do {
            if ($login) {
                break;
            }

            $login = getenv(self::ENV_SENDGRID_LOGIN);
            if ($login) {
                break;
            }

            throw new InvalidCredentialsException('could not resolve sendgrid login');

        } while (false);

        do {
            if ($password) {
                break;
            }

            $password = getenv(self::ENV_SENDGRID_PASSWORD);
            if ($password) {
                break;
            }

            throw new InvalidCredentialsException('could not resolve sendgrid password');

        } while (false);

        $this->login = $login;
        $this->password = $password;
    }

    /**
     * @param LoggerInterface $logger
     * @return LoggerInterface
     */
    protected function getLoggerOrNullLogger(LoggerInterface $logger = null) {
        if ($logger === null) {
            $logger = new NullLogger();
        }
        return $logger;
    }

    /**
     * @param array $connectorOptions
     * @return Client
     */
    protected function initializeHttpConnector(array $connectorOptions) {
        if (!isset($connectorOptions['timeout']) || !is_int($connectorOptions['timeout'])) {
            $connectorOptions['timeout'] = self::REQUEST_TIMEOUT;
        }
        if (!isset($connectorOptions['allow_redirects']) || !is_bool($connectorOptions['allow_redirects'])) {
            $connectorOptions['allow_redirects'] = false;
        }

        $connectorOptions['base_url'] = self::API_URL;

        $client = new Client($connectorOptions);
        return $client;
    }

    public function setClient(ClientInterface $client) {
        $client->getEmitter()->attach(new LogSubscriber($this->logger));
        $this->client = $client;
    }

    public function getClient() {
        return $this->client;
    }

    public function run($url, array $data) {
        $url .= '.json';
        $request = $this->createRequest($url, $data);
        try {
            $response = $this->client->send($request);
        }
        catch (ClientException  $e) {
            $response = $e->getResponse();
        }
        catch (ServerException $e) {
            $response = $e->getResponse();
        }
        return $this->parseResponse($response);
    }

    /**
     * @param $url
     * @param array $data
     * @return \GuzzleHttp\Message\RequestInterface
     */
    protected function createRequest($url, array $data) {
        $postBody = $this->createPostBody($data);
        $request = $this->client->createRequest('POST', $url);
        $request->setBody($postBody);
        return $request;
    }

    /**
     * @param array $data
     * @return PostBody
     */
    protected function createPostBody(array $data) {
        $data['api_user'] = $this->login;
        $data['api_key']  = $this->password;
        $postBody = new PostBody();
        $postBody->replaceFields($data);
        // set an aggregator that does not the array keys in that if they are numeric
        $postBody->setAggregator(Query::phpAggregator(false));
        return $postBody;
    }

    /**
     * @param ResponseInterface $response
     * @return mixed|null
     * @throws Exception\UnknownResponseStatusCodeException|Exception\ApiCallFailedException|Exception\ApiCallParameterErrorException
     */
    public function parseResponse(ResponseInterface $response) {

        $statusCode = $response->getStatusCode();
        $statusCodeBeginning = substr((string) $statusCode, 0,1);

        if ($statusCodeBeginning == '5') {
            return $this->handleRequestFailed($response);
        }
        elseif ($statusCodeBeginning == '4') {
            return $this->handleRequestError($response);
        }
        elseif ($statusCodeBeginning == '2') {
            return $this->handleRequestSuccessful($response);
        }

        $errorMessage = $this->getResponseErrorMessage($response);
        throw new UnknownResponseStatusCodeException('API responded with: ' . $errorMessage . '', $response->getStatusCode());
    }

    /**
     * @param ResponseInterface $response
     * @throws Exception\ApiCallFailedException
     */
    protected function handleRequestFailed(ResponseInterface $response) {
        $errorMessage = $this->getResponseErrorMessage($response);
        throw new ApiCallFailedException('API responsed with: ' . $errorMessage . '. Please try again later.', $response->getStatusCode());
    }

    /**
     * @param ResponseInterface $response
     * @throws Exception\ApiCallParameterErrorException
     */
    protected function handleRequestError(ResponseInterface $response) {
        $errorMessage = $this->getResponseErrorMessage($response);
        throw new ApiCallParameterErrorException('Error Description: ' . $errorMessage, $response->getStatusCode());
    }

    /**
     * @param ResponseInterface $response
     * @return mixed|null
     */
    protected function handleRequestSuccessful(ResponseInterface $response) {
        return $response->json();
    }


    private function getResponseErrorMessage(ResponseInterface $response) {
        $responseData = $response->json();

        $errorMessages = '';

        if (isset($responseData['message'])) {
            $errorMessages = $responseData['message'];
        }

        if (isset($responseData['errors'])) {
            $errorMessages = implode('; ', $responseData['errors']);
        }
        if (isset($responseData['error'])) {
            $errorMessages = $responseData['error'];
        }
        return $errorMessages;
    }
}