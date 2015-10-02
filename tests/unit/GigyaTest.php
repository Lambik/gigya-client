<?php

namespace Graze\Gigya\Test\Unit;

use Graze\Gigya\Endpoints\Client;
use Graze\Gigya\Gigya;
use Graze\Gigya\Response\ResponseInterface;
use Graze\Gigya\Test\TestCase;
use Graze\Gigya\Test\TestFixtures;
use Mockery as m;
use Mockery\MockInterface;

/**
 * @runTestsInSeparateProcesses
 * @preserveGlobalState disabled
 */
class GigyaTest extends TestCase
{
    /**
     * @var MockInterface|\GuzzleHttp\Client
     */
    private $guzzleClient;

    /**
     * @var MockInterface|\Graze\Gigya\Response\ResponseFactory
     */
    private $factory;

    /**
     * @var string
     */
    private $certPath;

    public function setUp()
    {
        $this->guzzleClient = m::mock('overload:GuzzleHttp\Client');
        $this->factory = m::mock('overload:Graze\Gigya\Response\ResponseFactory');

        $this->certPath = realpath(__DIR__ . '/../../src/Endpoints/' . Client::CERTIFICATE_FILE);
    }

    public function tearDown()
    {
        $this->guzzleClient = $this->factory = null;
    }

    /**
     * @param string|null $dc
     * @return Gigya
     */
    public function createClient($dc = null)
    {
        return new Gigya('key', 'secret', $dc ?: Gigya::DC_EU, null);
    }

    /**
     * @param string $fixture
     * @param array  $getOptions
     * @return ResponseInterface
     */
    private function setupCall($fixtureName, $uri, $getOptions)
    {
        $response = m::mock('GuzzleHttp\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn(TestFixtures::getFixture($fixtureName));

        $this->guzzleClient
            ->shouldReceive('get')
            ->with(
                $uri,
                $getOptions
            )
            ->andReturn($response);

        $gigyaResponse = m::mock('Graze\Gigya\Response\ResponseInterface');

        $this->factory->shouldReceive('getResponse')
                      ->with($response)
                      ->andReturn($gigyaResponse);

        return $gigyaResponse;
    }

    public function testSettingKeyAndSecretWillPassToGuzzleClient()
    {
        $key = 'key' . rand(1, 1000);
        $secret = 'secret' . rand(1001, 2000002);
        $client = new Gigya($key, $secret, Gigya::DC_EU, null);

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.getAccountInfo',
            [
                'query' => [
                    'apiKey' => $key,
                    'secret' => $secret
                ],
                'cert'  => $this->certPath,
            ]
        );

        $result = $client->accounts()->getAccountInfo([]);

        static::assertSame($gigyaResponse, $result);
    }

    public function testSettingDataCenterToAuWillCallAuUri()
    {
        $client = $this->createClient(Gigya::DC_AU);

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.au1.gigya.com/accounts.getAccountInfo',
            [
                'query' => [
                    'apiKey' => 'key',
                    'secret' => 'secret'
                ],
                'cert'  => $this->certPath,
            ]
        );

        $result = $client->accounts()->getAccountInfo([]);

        static::assertSame($gigyaResponse, $result);
    }

    public function testSettingDataCenterToUsWillCallUsUri()
    {
        $client = $this->createClient(Gigya::DC_US);

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.us1.gigya.com/accounts.getAccountInfo',
            [
                'query' => [
                    'apiKey' => 'key',
                    'secret' => 'secret'
                ],
                'cert'  => $this->certPath,
            ]
        );

        $result = $client->accounts()->getAccountInfo([]);

        static::assertSame($gigyaResponse, $result);
    }

    public function testSettingTheUserKeyWillPassItThroughToGuzzle()
    {
        $client = new Gigya('key', 'userSecret', Gigya::DC_EU, 'userKey');

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.getAccountInfo',
            [
                'query' => [
                    'apiKey'  => 'key',
                    'secret'  => 'userSecret',
                    'userKey' => 'userKey',
                ],
                'cert'  => $this->certPath,
            ]
        );

        $result = $client->accounts()->getAccountInfo([]);

        static::assertSame($gigyaResponse, $result);
    }

    public function testPassingParamsThroughToTheMethodWillPassThroughToGuzzle()
    {
        $client = $this->createClient();

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://socialize.eu1.gigya.com/socialize.notifyLogin',
            [
                'query' => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'param'  => 'passedThrough'
                ],
                'cert'  => $this->certPath,
            ]
        );

        $result = $client->socialize()->notifyLogin(['param' => 'passedThrough']);

        static::assertSame($gigyaResponse, $result);
    }

    public function testCallingChildMethodsCallTheCorrectUri()
    {
        $client = $this->createClient();

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://fidm.eu1.gigya.com/fidm.saml.idp.getConfig',
            [
                'query' => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'params' => 'passedThrough'
                ],
                'cert'  => $this->certPath,
            ]
        );

        $result = $client->saml()->idp()->getConfig(['params' => 'passedThrough']);

        static::assertSame($gigyaResponse, $result);
    }

    public function testTfaCallingChildMethodsCallTheCorrectUri()
    {
        $client = $this->createClient();

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.tfa.getCertificate',
            [
                'query' => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'params' => 'passedThrough'
                ],
                'cert'  => $this->certPath,
            ]
        );

        $result = $client->accounts()->tfa()->getCertificate(['params' => 'passedThrough']);

        static::assertSame($gigyaResponse, $result);
    }

    /**
     * @dataProvider clientCallDataProvider
     * @param $namespace
     * @param $method
     * @param $expectedUri
     */
    public function testClientCalls($namespace, $method, $expectedUri)
    {
        $client = $this->createClient();

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            $expectedUri,
            [
                'query' => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'params' => 'passedThrough'
                ],
                'cert'  => $this->certPath,
            ]
        );

        $result = $client->{$namespace}()->{$method}(['params' => 'passedThrough']);

        static::assertSame($gigyaResponse, $result);
    }

    public function testCallingMagicMethodWithArgumentsThrowsAnException()
    {
        static::setExpectedException(
            "BadMethodCallException",
            "No Arguments should be supplied for Gigya call"
        );

        $client = $this->createClient();
        $client->custom('params');
    }

    public function testAddingOptionsPassesThroughTheOptionsToGuzzle()
    {
        $client = $this->createClient();

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.getAccountInfo',
            [
                'query'   => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'params' => 'passedThrough'
                ],
                'cert'    => $this->certPath,
                'option1' => 'value1',
                'option2' => false,
            ]
        );

        $client->addOption('option1', 'value1');
        $client->addOption('option2', false);

        $result = $client->accounts()->getAccountInfo(['params' => 'passedThrough']);

        static::assertSame($gigyaResponse, $result);
    }

    public function testAddingOptionsWithASingleCallPassesThroughTheOptionsToGuzzle()
    {
        $client = $this->createClient();

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.getAccountInfo',
            [
                'query'   => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'params' => 'passedThrough'
                ],
                'cert'    => $this->certPath,
                'option1' => 'value1',
                'option2' => true,
            ]
        );

        $client->addOptions([
            'option1' => 'value1',
            'option2' => true,
        ]);

        $result = $client->accounts()->getAccountInfo(['params' => 'passedThrough']);

        static::assertSame($gigyaResponse, $result);
    }

    public function testAddingTheSameOptionAgainWillTakeTheLastValueSet()
    {
        $client = $this->createClient();

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.getAccountInfo',
            [
                'query'   => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'params' => 'passedThrough'
                ],
                'cert'    => $this->certPath,
                'option1' => false,
            ]
        );

        $client->addOption('option1', 'value1');
        $client->addOption('option1', false);

        $result = $client->accounts()->getAccountInfo(['params' => 'passedThrough']);

        static::assertSame($gigyaResponse, $result);
    }

    public function testAddingTheSameOptionAgainWithAddOptionsWillTakeTheLastValueSet()
    {
        $client = $this->createClient();

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.getAccountInfo',
            [
                'query'   => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'params' => 'passedThrough'
                ],
                'cert'    => $this->certPath,
                'option1' => true,
            ]
        );

        $client->addOption('option1', 'value1');
        $client->addOptions(['option1' => true]);

        $result = $client->accounts()->getAccountInfo(['params' => 'passedThrough']);

        static::assertSame($gigyaResponse, $result);
    }

    public function testAddingQueryAndCertOptionsWillBeIgnored()
    {
        $client = $this->createClient();

        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.getAccountInfo',
            [
                'query' => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'params' => 'passedThrough'
                ],
                'cert'  => $this->certPath,
            ]
        );

        $client->addOption('query', 'random');
        $client->addOption('cert', 'notAFile');

        $result = $client->accounts()->getAccountInfo(['params' => 'passedThrough']);

        static::assertSame($gigyaResponse, $result);
    }

    public function testSettingOptionsAsPartOfTheQuery()
    {
        $client = $this->createClient();
        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.getAccountInfo',
            [
                'query'  => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'params' => 'passedThrough'
                ],
                'cert'   => $this->certPath,
                'custom' => 'value'
            ]
        );

        $result = $client->accounts()->getAccountInfo(['params' => 'passedThrough'], ['custom' => 'value']);

        static::assertSame($gigyaResponse, $result);
    }

    public function testSettingGlobalAndRequestOptionsTheRequestOptionsOverrideGlobalOptions()
    {
        $client = $this->createClient();
        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.getAccountInfo',
            [
                'query'  => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'params' => 'passedThrough'
                ],
                'cert'   => $this->certPath,
                'custom' => 'value'
            ]
        );

        $client->addOption('custom', 'notUsed');

        $result = $client->accounts()->getAccountInfo(['params' => 'passedThrough'], ['custom' => 'value']);

        static::assertSame($gigyaResponse, $result);
    }

    public function testSettingRequestOptionsDoNotOverrideTheParams()
    {
        $client = $this->createClient();
        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.getAccountInfo',
            [
                'query' => [
                    'apiKey' => 'key',
                    'secret' => 'secret',
                    'params' => 'passedThrough'
                ],
                'cert'  => $this->certPath
            ]
        );

        $result = $client->accounts()->getAccountInfo(
            ['params' => 'passedThrough'],
            ['query' => 'value', 'cert' => false]
        );

        static::assertSame($gigyaResponse, $result);
    }

    public function testSettingParamsWillNotOverwriteTheDefaultParams()
    {
        $client = $this->createClient();
        $gigyaResponse = $this->setupCall(
            'accounts.getAccountInfo',
            'https://accounts.eu1.gigya.com/accounts.getAccountInfo',
            [
                'query' => [
                    'apiKey' => 'key',
                    'secret' => 'secret'
                ],
                'cert'  => $this->certPath
            ]
        );

        $result = $client->accounts()->getAccountInfo(
            ['secret' => 'newSecret']
        );

        static::assertSame($gigyaResponse, $result);
    }

    public function clientCallDataProvider()
    {
        return [
            ['accounts', 'getAccountInfo', 'https://accounts.eu1.gigya.com/accounts.getAccountInfo'],
            ['accounts', 'tfa.getCertificate', 'https://accounts.eu1.gigya.com/accounts.tfa.getCertificate'],
            ['audit', 'search', 'https://audit.eu1.gigya.com/audit.search'],
            ['comments', 'analyzeMediaItem', 'https://comments.eu1.gigya.com/comments.analyzeMediaItem'],
            ['dataStore', 'get', 'https://ds.eu1.gigya.com/ds.get'],
            ['ds', 'get', 'https://ds.eu1.gigya.com/ds.get'],
            ['gameMechanics', 'getChallengeStatus', 'https://gm.eu1.gigya.com/gm.getChallengeStatus'],
            ['gm', 'getChallengeStatus', 'https://gm.eu1.gigya.com/gm.getChallengeStatus'],
            ['identityStorage', 'getSchema', 'https://ids.eu1.gigya.com/ids.getSchema'],
            ['ids', 'getSchema', 'https://ids.eu1.gigya.com/ids.getSchema'],
            ['reports', 'getGMStats', 'https://reports.eu1.gigya.com/reports.getGMStats'],
            ['saml', 'setConfig', 'https://fidm.eu1.gigya.com/fidm.saml.setConfig'],
            ['fidm', 'saml.setConfig', 'https://fidm.eu1.gigya.com/fidm.saml.setConfig'],
            ['saml', 'idp.getConfig', 'https://fidm.eu1.gigya.com/fidm.saml.idp.getConfig'],
            ['fidm', 'saml.idp.getConfig', 'https://fidm.eu1.gigya.com/fidm.saml.idp.getConfig'],
            ['socialize', 'checkin', 'https://socialize.eu1.gigya.com/socialize.checkin'],
        ];
    }
}
