<?php
/**
 * Zend Framework (http://framework.zend.com/)
 *
 * @see       http://github.com/zendframework/zend-diactoros for the canonical source repository
 * @copyright Copyright (c) 2015 Zend Technologies USA Inc. (http://www.zend.com)
 * @license   https://github.com/zendframework/zend-diactoros/blob/master/LICENSE.md New BSD License
 */

namespace HttpMessage;

use InvalidArgumentException;
use Psr\Http\Message\ServerRequestInterface;
use Psr\Http\Message\StreamInterface;
use Psr\Http\Message\UploadedFileInterface;

/**
 * Server-side HTTP request
 *
 * Extends the Request definition to add methods for accessing incoming data,
 * specifically server parameters, cookies, matched path parameters, query
 * string arguments, body parameters, and upload file information.
 *
 * "Attributes" are discovered via decomposing the request (and usually
 * specifically the URI path), and typically will be injected by the application.
 *
 * Requests are considered immutable; all methods that might change state are
 * implemented such that they retain the internal state of the current
 * message and return a new instance that contains the changed state.
 */
class ServerRequest extends AbstractRequest implements ServerRequestInterface
{
    /**
     * @var array
     */
    private $attributes = [];

    /**
     * @var array
     */
    private $cookieParams = [];

    /**
     * @var null|array|object
     */
    private $parsedBody;

    /**
     * @var array
     */
    private $queryParams = [];

    /**
     * @var array
     */
    private $serverParams;

    /**
     * @var array
     */
    private $uploadedFiles;

    /**
     * @param array $serverParams Server parameters, typically from $_SERVER
     * @param array $uploadedFiles Upload file information, a tree of UploadedFiles
     * @param null|string $uri URI for the request, if any.
     * @param null|string $method HTTP method for the request, if any.
     * @param string|resource|StreamInterface $body Message body, if any.
     * @param array $headers Headers for the message, if any.
     * @throws InvalidArgumentException for any invalid value.
     */
    public function __construct(
        array $serverParams = [],
        array $uploadedFiles = [],
        $uri = null,
        $method = null,
        $body = 'php://input',
        array $headers = []
    ) {
        $this->validateUploadedFiles($uploadedFiles);

        $body = $this->getStream($body);
        $this->initialize($uri, $method, $body, $headers);
        $this->serverParams  = $serverParams;
        $this->uploadedFiles = $uploadedFiles;
    }

    /**
     * {@inheritdoc}
     */
    public function getServerParams()
    {
        return $this->serverParams;
    }

    /**
     * {@inheritdoc}
     */
    public function getUploadedFiles()
    {
        return $this->uploadedFiles;
    }

    /**
     * @param array $uploadedFiles
     *
     * @return self
     */
    public function setUploadedFiles(array $uploadedFiles)
    {
        $this->validateUploadedFiles($uploadedFiles);
        $this->uploadedFiles = $uploadedFiles;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withUploadedFiles(array $uploadedFiles)
    {
        $new = clone $this;

        return $new->setUploadedFiles($uploadedFiles);
    }

    /**
     * {@inheritdoc}
     */
    public function getCookieParams()
    {
        return $this->cookieParams;
    }

    /**
     * @param array $cookies
     *
     * @return $this
     */
    public function setCookieParams(array $cookies)
    {
        $this->cookieParams = $cookies;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withCookieParams(array $cookies)
    {
        $new = clone $this;

        return $new->setCookieParams($cookies);
    }

    /**
     * {@inheritdoc}
     */
    public function getQueryParams()
    {
        return $this->queryParams;
    }

    /**
     * @param array $query
     *
     * @return self
     */
    public function setQueryParams(array $query)
    {
        $this->queryParams = $query;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withQueryParams(array $query)
    {
        $new = clone $this;

        return $new->setQueryParams($query);
    }

    /**
     * {@inheritdoc}
     */
    public function getParsedBody()
    {
        return $this->parsedBody;
    }

    /**
     * @param array|null|object $data
     *
     * @return self
     */
    public function setParsedBody($data)
    {
        $this->parsedBody = $data;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withParsedBody($data)
    {
        $new = clone $this;

        return $new->setParsedBody($data);
    }

    /**
     * {@inheritdoc}
     */
    public function getAttributes()
    {
        return $this->attributes;
    }

    /**
     * {@inheritdoc}
     */
    public function getAttribute($attribute, $default = null)
    {
        if (! array_key_exists($attribute, $this->attributes)) {
            return $default;
        }

        return $this->attributes[$attribute];
    }

    /**
     * @param string $attribute
     * @param mixed  $value
     *
     * @return self
     */
    public function setAttribute($attribute, $value)
    {
        $this->attributes[$attribute] = $value;

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withAttribute($attribute, $value)
    {
        $new = clone $this;

        return $new->setAttribute($attribute, $value);
    }

    /**
     * @param string $attribute
     *
     * @return self
     */
    public function unsetAttribute($attribute)
    {
        if (! isset($this->attributes[$attribute])) {
            return $this;
        }

        unset($this->attributes[$attribute]);

        return $this;
    }

    /**
     * {@inheritdoc}
     */
    public function withoutAttribute($attribute)
    {
        $new = clone $this;

        return $new->unsetAttribute($attribute);
    }

    /**
     * Proxy to receive the request method.
     *
     * This overrides the parent functionality to ensure the method is never
     * empty; if no method is present, it returns 'GET'.
     *
     * @return string
     */
    public function getMethod()
    {
        if (empty($this->method)) {
            return 'GET';
        }
        return $this->method;
    }

    /**
     * Set the request method.
     *
     * Unlike the regular Request implementation, the server-side
     * normalizes the method to uppercase to ensure consistency
     * and make checking the method simpler.
     *
     * @param string $method
     * @return self
     */
    public function setMethod($method)
    {
        $this->validateMethod($method);

        $this->method = $method;

        return $this;
    }

    /**
     * Set the request method.
     *
     * Unlike the regular Request implementation, the server-side
     * normalizes the method to uppercase to ensure consistency
     * and make checking the method simpler.
     *
     * This methods returns a new instance.
     *
     * @param string $method
     * @return self
     */
    public function withMethod($method)
    {
        $new = clone $this;

        return $new->setMethod($method);
    }

    /**
     * Set the body stream
     *
     * @param string|resource|StreamInterface $stream
     * @return StreamInterface
     */
    private function getStream($stream)
    {
        if ($stream === 'php://input') {
            return new PhpInputStream();
        }

        if (! is_string($stream) && ! is_resource($stream) && ! $stream instanceof StreamInterface) {
            throw new InvalidArgumentException(
                'Stream must be a string stream resource identifier, '
                . 'an actual stream resource, '
                . 'or a Psr\Http\Message\StreamInterface implementation'
            );
        }

        if (! $stream instanceof StreamInterface) {
            return new Stream($stream, 'r');
        }

        return $stream;
    }

    /**
     * Recursively validate the structure in an uploaded files array.
     *
     * @param array $uploadedFiles
     * @throws InvalidArgumentException if any leaf is not an UploadedFileInterface instance.
     */
    private function validateUploadedFiles(array $uploadedFiles)
    {
        foreach ($uploadedFiles as $file) {
            if (is_array($file)) {
                $this->validateUploadedFiles($file);
                continue;
            }

            if (! $file instanceof UploadedFileInterface) {
                throw new InvalidArgumentException('Invalid leaf in uploaded files structure');
            }
        }
    }
}
