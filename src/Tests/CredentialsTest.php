<?php
/**
 *
 * @author: sschulze@silversurfer7.de
 */

namespace Silversurfer7\Sendgrid\Api\Client\Tests\Initialization;


use PHPUnit_Framework_TestCase;
use Silversurfer7\Sendgrid\Api\Client\SendgridApiClient;

class CredentialsTest extends PHPUnit_Framework_TestCase {

    public function testEmptyCredentials() {
        $this->setExpectedException('\Silversurfer7\Sendgrid\Api\Client\Exception\InvalidCredentialsException');
        new SendgridApiClient(null, null);
    }

    public function testArgCredentials() {
        new SendgridApiClient('login', 'password');

    }

    public function testEnvCredentials() {
        putenv(SendgridApiClient::ENV_SENDGRID_LOGIN . '=login');
        putenv(SendgridApiClient::ENV_SENDGRID_PASSWORD . '=password');
        new SendgridApiClient(null, null);
    }

    public function testBaseUrlStatus() {
        $client = new SendgridApiClient('login', 'password');
        $guzzleClient  = $client->getClient();
        $this->assertEquals(SendgridApiClient::API_URL, $guzzleClient->getBaseUrl());
    }

    public function testBaseUrlCanNotBeOverwritten() {
        $connectorOptions = array(
            'base_url' => 'http://www.silversurfer7.de'
        );

        $client = new SendgridApiClient('login', 'password', $connectorOptions);
        $guzzleClient  = $client->getClient();
        $this->assertNotEquals($connectorOptions['base_url'], $guzzleClient->getBaseUrl());
        $this->assertEquals(SendgridApiClient::API_URL, $guzzleClient->getBaseUrl());
    }
}