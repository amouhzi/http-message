<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace HttpMessageTest;

use PHPUnit_Framework_TestCase as TestCase;
use Psr\Http\Message\ResponseInterface;
use Psr\Http\Message\ServerRequestInterface;
use HttpMessage\Server;
use HttpMessageTest\TestAsset\HeaderStack;

class ServerTest extends TestCase
{
    /**
     * @var Callable
     */
    protected $callback;

    /**
     * @var ServerRequestInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $request;

    /**
     * @var ResponseInterface|\PHPUnit_Framework_MockObject_MockObject
     */
    protected $response;

    public function setUp()
    {
        HeaderStack::reset();

        $this->callback   = function ($req, $res, $done) {
            //  Intentionally empty
        };
        $this->request = $this->getMock('Psr\Http\Message\ServerRequestInterface');
        $this->response = $this->getMock('Psr\Http\Message\ResponseInterface');
    }

    public function tearDown()
    {
        HeaderStack::reset();
    }

    public function testCreateServerFromRequestReturnsServerInstanceWithProvidedObjects()
    {
        $server = Server::createServerFromRequest(
            $this->callback,
            $this->request,
            $this->response
        );
        $this->assertInstanceOf('HttpMessage\Server', $server);
        $this->assertSame($this->callback, $server->callback);
        $this->assertSame($this->request, $server->request);
        $this->assertSame($this->response, $server->response);
    }

    public function testCreateServerFromRequestWillCreateResponseIfNotProvided()
    {
        $server = Server::createServerFromRequest(
            $this->callback,
            $this->request
        );
        $this->assertInstanceOf('HttpMessage\Server', $server);
        $this->assertSame($this->callback, $server->callback);
        $this->assertSame($this->request, $server->request);
        $this->assertInstanceOf('HttpMessage\Response', $server->response);
    }

    public function testCannotAccessArbitraryProperties()
    {
        $server = new Server(
            $this->callback,
            $this->request,
            $this->response
        );
        $prop = uniqid();
        $this->setExpectedException('OutOfBoundsException');
        $server->$prop;
    }

    public function testCreateServerWillCreateDefaultInstancesForRequestAndResponse()
    {
        $server = [
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'application/json',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'bar=baz',
        ];
        $server = Server::createServer($this->callback, $server, [], [], [], []);
        $this->assertInstanceOf('HttpMessage\Server', $server);
        $this->assertSame($this->callback, $server->callback);

        $this->assertInstanceOf('HttpMessage\ServerRequest', $server->request);
        $request = $server->request;
        $this->assertEquals('POST', $request->getMethod());
        $this->assertEquals('/foo/bar', $request->getUri()->getPath());
        $this->assertTrue($request->hasHeader('Accept'));

        $this->assertInstanceOf('HttpMessage\Response', $server->response);
    }

    public function testListenInvokesCallbackAndSendsResponse()
    {
        $server = [
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'application/json',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'bar=baz',
        ];

        $callback = function ($req, $res) {
            $res = $res->withAddedHeader('Content-Type', 'text/plain');
            $res->getBody()->write('FOOBAR');
            return $res;
        };
        $server = Server::createServer($callback, $server, [], [], [], []);

        $this->expectOutputString('FOOBAR');
        $server->listen();
        ob_end_flush();

        $this->assertContains('HTTP/1.1 200 OK', HeaderStack::stack());
        $this->assertContains('Content-Type: text/plain', HeaderStack::stack());
    }

    public function testListenEmitsStatusHeaderWithoutReasonPhraseIfNoReasonPhrase()
    {
        $server = [
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'application/json',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'bar=baz',
        ];

        $callback = function (ServerRequestInterface $req, ResponseInterface $res) {
            $res = $res->withStatus(299);
            $res = $res->withAddedHeader('Content-Type', 'text/plain');
            $res->getBody()->write('FOOBAR');
            return $res;
        };
        $server = Server::createServer($callback, $server, [], [], [], []);

        $this->expectOutputString('FOOBAR');
        $server->listen();
        ob_end_flush();

        $this->assertContains('HTTP/1.1 299', HeaderStack::stack());
        $this->assertContains('Content-Type: text/plain', HeaderStack::stack());
    }

    public function testEnsurePercentCharactersDoNotResultInOutputError()
    {
        $server = [
            'HTTP_HOST' => 'example.com',
            'HTTP_ACCEPT' => 'application/json',
            'REQUEST_METHOD' => 'POST',
            'REQUEST_URI' => '/foo/bar',
            'QUERY_STRING' => 'bar=baz',
        ];

        $callback = function (ServerRequestInterface $req, ResponseInterface $res) {
            $res = $res->withAddedHeader('Content-Type', 'text/plain');
            $res->getBody()->write('100%');
            return $res;
        };
        $server = Server::createServer($callback, $server, [], [], [], []);

        $this->expectOutputString('100%');
        $server->listen();
        ob_end_flush();

        $this->assertContains('HTTP/1.1 200 OK', HeaderStack::stack());
        $this->assertContains('Content-Type: text/plain', HeaderStack::stack());
    }

    public function testEmitsHeadersWithMultipleValuesMultipleTimes()
    {
        $server = [
            'HTTP_HOST' => 'example.com',
            'REQUEST_METHOD' => 'GET',
            'REQUEST_URI' => '/foo/bar',
        ];

        $callback = function (ServerRequestInterface $req, ResponseInterface $res) {
            $res = $res->withAddedHeader('Content-Type', 'text/plain');
            $res = $res->withAddedHeader(
                'Set-Cookie',
                'foo=bar; expires=Wed, 1 Oct 2014 10:30; path=/foo; domain=example.com'
            );
            $res = $res->withAddedHeader(
                'Set-Cookie',
                'bar=baz; expires=Wed, 8 Oct 2014 10:30; path=/foo/bar; domain=example.com'
            );
            return $res;
        };
        $server = Server::createServer($callback, $server, [], [], [], []);

        $server->listen();
        ob_end_flush();

        $this->assertContains('HTTP/1.1 200 OK', HeaderStack::stack());
        $this->assertContains('Content-Type: text/plain', HeaderStack::stack());
        $this->assertContains(
            'Set-Cookie: foo=bar; expires=Wed, 1 Oct 2014 10:30; path=/foo; domain=example.com',
            HeaderStack::stack()
        );
        $this->assertContains(
            'Set-Cookie: bar=baz; expires=Wed, 8 Oct 2014 10:30; path=/foo/bar; domain=example.com',
            HeaderStack::stack()
        );

        $stack  = HeaderStack::stack();
        return $stack;
    }

    /**
     * @group 5
     * @depends testEmitsHeadersWithMultipleValuesMultipleTimes
     */
    public function testHeaderOrderIsHonoredWhenEmitted($stack)
    {
        $header = array_pop($stack);
        $this->assertContains(
            'Set-Cookie: bar=baz; expires=Wed, 8 Oct 2014 10:30; path=/foo/bar; domain=example.com',
            $header
        );
        $header = array_pop($stack);
        $this->assertContains(
            'Set-Cookie: foo=bar; expires=Wed, 1 Oct 2014 10:30; path=/foo; domain=example.com',
            $header
        );
    }

    public function testListenPassesCallableArgumentToCallback()
    {
        $phpunit  = $this;
        $invoked  = false;
        $request  = $this->request;
        $response = $this->response;

        $this->response
            ->expects($this->once())
            ->method('getHeaders')
            ->will($this->returnValue([]));

        $final = function ($req, $res, $err = null) use ($phpunit, $request, $response, &$invoked) {
            $phpunit->assertSame($request, $req);
            $phpunit->assertSame($response, $res);
            $invoked = true;
        };

        $callback = function ($req, $res, callable $final = null) use ($phpunit) {
            if (! $final) {
                $phpunit->fail('No final callable passed!');
            }

            $final($req, $res);
        };

        $server = Server::createServerFromRequest(
            $callback,
            $this->request,
            $this->response
        );
        $server->listen($final);
        ob_end_flush();
        $this->assertTrue($invoked);
    }
}
