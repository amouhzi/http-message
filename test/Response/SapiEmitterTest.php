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
use HttpMessage\Response;
use HttpMessage\Response\SapiEmitter;
use HttpMessage\Stream;
use HttpMessageTest\TestAsset\HeaderStack;

class SapiEmitterTest extends TestCase
{
    /**
     * @var SapiEmitter
     */
    protected $emitter;

    public function setUp()
    {
        HeaderStack::reset();
        $this->emitter = new SapiEmitter();
    }

    public function tearDown()
    {
        HeaderStack::reset();
    }

    public function testEmitsResponseHeaders()
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        ob_start();
        $this->emitter->emit($response);
        ob_end_clean();
        $this->assertContains('HTTP/1.1 200 OK', HeaderStack::stack());
        $this->assertContains('Content-Type: text/plain', HeaderStack::stack());
    }

    public function testEmitsMessageBody()
    {
        $response = (new Response())
            ->withStatus(200)
            ->withAddedHeader('Content-Type', 'text/plain');
        $response->getBody()->write('Content!');

        $this->expectOutputString('Content!');
        $this->emitter->emit($response);
    }
}
