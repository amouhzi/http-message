<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace HttpMessageTest\Response;

use PHPUnit_Framework_TestCase as TestCase;
use HttpMessage\Response\JsonResponse;

class JsonResponseTest extends TestCase
{
    public function testConstructorAcceptsDataAndCreatesJsonEncodedMessageBody()
    {
        $data = [
            'nested' => [
                'json' => [
                    'tree',
                ],
            ],
        ];
        $json = '{"nested":{"json":["tree"]}}';

        $response = new JsonResponse($data);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('content-type'));
        $this->assertSame($json, (string) $response->getBody());
    }

    public function scalarValuesForJSON()
    {
        return [
            'null'         => [null],
            'false'        => [false],
            'true'         => [true],
            'zero'         => [0],
            'int'          => [1],
            'zero-float'   => [0.0],
            'float'        => [1.1],
            'empty-string' => [''],
            'string'       => ['string'],
        ];
    }

    /**
     * @dataProvider scalarValuesForJSON
     */
    public function testScalarValuePassedToConstructorJsonEncodesDirectly($value)
    {
        $response = new JsonResponse($value);
        $this->assertEquals(200, $response->getStatusCode());
        $this->assertEquals('application/json', $response->getHeaderLine('content-type'));
        // 15 is the default mask used by JsonResponse
        $this->assertSame(json_encode($value, 15), (string) $response->getBody());
    }

    public function testCanProvideStatusCodeToConstructor()
    {
        $response = new JsonResponse(null, 404);
        $this->assertEquals(404, $response->getStatusCode());
    }

    public function testCanProvideAlternateContentTypeViaHeadersPassedToConstructor()
    {
        $response = new JsonResponse(null, 200, ['content-type' => 'foo/json']);
        $this->assertEquals('foo/json', $response->getHeaderLine('content-type'));
    }

    /**
     * @expectedException InvalidArgumentException
     */
    public function testJsonErrorHandlingOfResources()
    {
        // Serializing something that is not serializable.
        $resource = fopen('php://memory', 'r');
        new JsonResponse($resource);
    }

    public function testJsonErrorHandlingOfBadEmbeddedData()
    {
        if (version_compare(PHP_VERSION, '5.5', 'lt')) {
            $this->markTestSkipped('Skipped as PHP versions prior to 5.5 are noisy about JSON errors');
        }

        if (defined('HHVM_VERSION')) {
            $this->markTestSkipped('Skipped as HHVM happily serializes embedded resources');
        }

        // Serializing something that is not serializable.
        $data = [
            'stream' => fopen('php://memory', 'r'),
        ];

        $this->setExpectedException('InvalidArgumentException', 'Unable to encode');
        new JsonResponse($data);
    }
}
