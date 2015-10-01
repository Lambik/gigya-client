<?php

namespace Graze\Gigya\Test\Unit\Response;

use DateTimeImmutable;
use Graze\Gigya\Exceptions\UnknownResponseException;
use Graze\Gigya\Response\ResponseCollectionInterface;
use Graze\Gigya\Response\ResponseFactory;
use Graze\Gigya\Test\TestCase;
use Graze\Gigya\Test\TestFixtures;
use GuzzleHttp\Message\ResponseInterface as GuzzleResponseInterface;
use Mockery as m;
use Mockery\MockInterface;

// use Psr\Http\Message\ResponseInterface; Guzzle v6

class ResponseFactoryTest extends TestCase
{
    /**
     * @var ResponseFactory
     */
    private $factory;

    /**
     * @var MockInterface|\Graze\Gigya\Validation\GuzzleResponseValidatorInterface
     */
    private $validator;

    public function setUp()
    {
        $this->validator = m::mock('Graze\Gigya\Validation\GuzzleResponseValidatorInterface');
        $this->factory = new ResponseFactory($this->validator);
    }

    /**
     * @param GuzzleResponseInterface $response
     */
    private function expectResponse(GuzzleResponseInterface $response)
    {
        $this->validator->shouldReceive('assert')
                        ->with($response)
                        ->andReturn(true);
    }

    public function testAccountModel()
    {
        $response = m::mock('GuzzleHttp\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn(TestFixtures::getFixture('accounts.getAccountInfo'));
        $this->expectResponse($response);

        $model = $this->factory->getModel($response);

        static::assertInstanceOf('Graze\Gigya\Response\Response', $model);
        static::assertEquals(200, $model->getStatusCode());
        static::assertEquals(0, $model->getErrorCode());
        static::assertEquals("OK", $model->getStatusReason());
        static::assertEquals("e6f891ac17f24810bee6eb533524a152", $model->getCallId());
        static::assertEquals(new DateTimeImmutable("2015-03-22T11:42:25.943Z"), $model->getTime());
        $data = $model->getData();
        static::assertEquals("_gid_30A3XVJciH95WEEnoRmfZS7ee3MY+lUAtpVxvUWNseU=", $data->get('UID'));
    }

    public function testCollectionModel()
    {
        $response = m::mock('GuzzleHttp\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn(TestFixtures::getFixture('accounts.search_simple'));
        $this->expectResponse($response);

        /** @var ResponseCollectionInterface $model */
        $model = $this->factory->getModel($response);

        static::assertInstanceOf('Graze\Gigya\Response\ResponseCollection', $model);
        static::assertEquals(200, $model->getStatusCode());
        static::assertEquals(1840, $model->getTotal());
        static::assertEquals(5, $model->getCount());
        static::assertNull($model->getNextCursor());

        $results = $model->getData();

        static::assertEquals(5, $results->count());
        static::assertEquals('g1@gmail.com', $results[0]->profile->email);
    }

    public function testError403()
    {
        $response = m::mock('GuzzleHttp\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn(TestFixtures::getFixture('failure_403'));
        $this->expectResponse($response);

        $model = $this->factory->getModel($response);

        static::assertInstanceOf('Graze\Gigya\Response\Response', $model);
        static::assertEquals(403, $model->getStatusCode());
        static::assertEquals(403005, $model->getErrorCode());
        static::assertEquals("Forbidden", $model->getStatusReason());
        static::assertEquals("Unauthorized user", $model->getErrorMessage());
    }

    public function testNoBody()
    {
        $response = m::mock('GuzzleHttp\Message\ResponseInterface');
        $response->shouldReceive('getBody')->andReturn('');

        $this->validator->shouldReceive('assert')
                        ->with($response)
                        ->andThrow(new UnknownResponseException($response));

        static::setExpectedException(
            'Graze\Gigya\Exceptions\UnknownResponseException',
            'The contents of the response could not be determined'
        );

        $this->factory->getModel($response);
    }
}