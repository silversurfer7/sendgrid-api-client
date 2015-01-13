<?php
/**
 *
 * @author: sschulze@silversurfer7.de
 */

namespace Silversurfer7\Sendgrid\Api\Client\Tests\Connection;


use GuzzleHttp\Message\RequestInterface;
use GuzzleHttp\Message\Response;
use GuzzleHttp\Stream\Stream;
use PHPUnit_Framework_TestCase;
use Silversurfer7\Sendgrid\Api\Client\Exception\ApiCallFailedException;
use Silversurfer7\Sendgrid\Api\Client\Exception\ApiCallParameterErrorException;
use Silversurfer7\Sendgrid\Api\Client\Exception\UnknownResponseStatusCodeException;
use Silversurfer7\Sendgrid\Api\Client\SendgridApiClient;

class ConnectionTest extends PHPUnit_Framework_TestCase {

    private $payload;

    public function test4xxResponse() {
        $client = new SendgridApiClient('login', 'password');

        $errorData = array('message' => 'an error occured');
        $response = $this->createResponse(403, json_encode($errorData));
        try {
            $client->parseResponse($response);
            $this->fail();
        }
        catch (ApiCallParameterErrorException $e) {
            $this->assertEquals(403, $e->getHttpStatusCode());
            $this->assertContains($errorData['message'], $e->getMessage());
        }
    }

    public function test5xxResponse() {
        $client = new SendgridApiClient('login', 'password');


        $errorData = array('message' => 'service not reachable');

        $response = $this->createResponse(501, json_encode($errorData));
        try {
            $client->parseResponse($response);
            $this->fail();
        }
        catch (ApiCallFailedException $e) {
            $this->assertEquals(501, $e->getHttpStatusCode());
            $this->assertContains($errorData['message'], $e->getMessage());
        }
    }

    public function test2xxResponse() {
        $client = new SendgridApiClient('login', 'password');

        $responseData = array('data' => 'payload', 'data2' => array('key' => 'value'));

        $response = $this->createResponse(200, json_encode($responseData));
        $result = $client->parseResponse($response);
        $this->assertEquals($responseData, $result);
    }


    public function testUnknownResponse() {
        $client = new SendgridApiClient('login', 'password');

        $errorData = array('message' => 'service not reachable');

        $response = $this->createResponse(102, json_encode($errorData));
        try {
            $client->parseResponse($response);
            $this->fail();
        }
        catch (UnknownResponseStatusCodeException $e) {
            $this->assertEquals(102, $e->getHttpStatusCode());
            $this->assertContains($errorData['message'], $e->getMessage());
        }
    }

    public function testRequest() {
        $this->payload = array('data' => "testvalue");

        $client = new SendgridApiClient('login', 'password');

        $mockClient = $this->getMockBuilder('\GuzzleHttp\Client')
            ->setMethods(array('send'))
            ->getMock();

        $mockClient->expects($this->any())->method('send')->with($this->callback(function (RequestInterface $request) {
                    $tmpData = $request->getBody()->getContents();
                    $this->assertContains('data=', $tmpData);
                    $this->assertContains('=testvalue', $tmpData);
                    $this->assertContains('login', $tmpData);
                    $this->assertContains('password', $tmpData);
                    $this->assertContains('api_user', $tmpData);
                    $this->assertContains('api_key', $tmpData);
                    $this->assertStringEndsWith('mocking/test.json', $request->getUrl());
                    return true;

                }))
            ->willReturn($this->createResponse(200, json_encode(array('message' => 'OK'))))
        ;

        $client->setClient($mockClient);

        $client->run('mocking/test', $this->payload);
    }


    private function createResponse($code, $bodyString) {
        $response = new Response($code);
        $stream = Stream::factory($bodyString);
        $response->setBody($stream);
        return $response;
    }

} 