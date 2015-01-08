<?php
/**
 *
 * @author: sschulze@silversurfer7.de
 */

namespace Silversurfer7\Sendgrid\Api\Client;


use GuzzleHttp\Client;
use GuzzleHttp\ClientInterface;
use GuzzleHttp\Message\ResponseInterface;
use GuzzleHttp\Post\PostBody;
use GuzzleHttp\Subscriber\Log\LogSubscriber;
use Psr\Log\LoggerInterface;
use Silversurfer7\Sendgrid\Api\Client\Exception\ApiCallNotSuccessfulException;
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


        $client = new Client(self::API_URL, $connectorOptions);
        return $client;
    }

    public function setClient(ClientInterface $client) {
        $client->getEmitter()->attach(new LogSubscriber($this->logger));
        $this->client = $client;

    }

    public function run($url, array $data) {

        $url .= '.json';

        $data['api_user'] = $this->login;
        $data['api_key']  = $this->password;

        $postBody = new PostBody();
        $postBody->replaceFields($data);

        $request = $this->client->createRequest('POST', $url);
        $request->setBody($postBody);

        $response = $this->client->send($request);

        return $this->parseResponse($response);
    }

    public function parseResponse(ResponseInterface $response) {

        $statusCode = $response->getStatusCode();
        $statusCodeBeginning = substr(0,1, $statusCode);
        if ($statusCodeBeginning == '5') {
            return $this->handleRequestFailed($response);
        }
        elseif ($statusCodeBeginning == '4') {
            return $this->handleRequestError($response);
        }
        elseif ($statusCodeBeginning == '2') {
            return $this->handleRequestSuccessful($response);
        }

        throw new UnknownResponseStatusCodeException('API responded with HTTPCode: ' . $statusCode . ' which could not be handled');
    }

    /**
     * @param ResponseInterface $response
     * @throws Exception\ApiCallNotSuccessfulException
     */
    public function handleRequestFailed(ResponseInterface $response) {
        throw new ApiCallNotSuccessfulException('HTTPCode: ' . $response->getStatusCode() . '. Please try again later.');
    }

    /**
     * @param ResponseInterface $response
     * @throws Exception\ApiCallParameterErrorException
     */
    public function handleRequestError(ResponseInterface $response) {

        $responseData = $response->json();
        $errorMessages = $responseData['message'];

        if (isset($responseData['errors'])) {
            $errorMessages = implode('; ', $responseData['errors']);
        }
        throw new ApiCallParameterErrorException('Error Description: ' . $errorMessages);
    }

    /**
     * @param ResponseInterface $response
     * @return \GuzzleHttp\Stream\StreamInterface|null
     */
    public function handleRequestSuccessful(ResponseInterface $response) {
        return $response->json();
    }
}