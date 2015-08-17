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
use Psr\Http\Message\MessageInterface;
use Psr\Http\Message\StreamInterface;

/**
 * Trait implementing the various methods defined in MessageInterface.
 *
 * @see https://github.com/php-fig/http-message/tree/master/src/MessageInterface.php
 */
abstract class AbstractMessage implements MessageInterface
{
    /**
     * List of all registered headers, as key => array of values.
     *
     * @var array
     */
    protected $headers = [];

    /**
     * Map of normalized header name to original name used to register header.
     *
     * @var array
     */
    protected $headerNames = [];

    /**
     * @var string
     */
    private $protocol = '1.1';

    /**
     * @var StreamInterface
     */
    protected $stream;

    /**
     * Retrieves the HTTP protocol version as a string.
     *
     * The string MUST contain only the HTTP version number (e.g., "1.1", "1.0").
     *
     * @return string HTTP protocol version.
     */
    public function getProtocolVersion()
    {
        return $this->protocol;
    }

    /**
     * Set the HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * @param string $version HTTP protocol version
     * @return self
     */
    public function setProtocolVersion($version)
    {
        $this->protocol = $version;

        return $this;
    }

    /**
     * Return an instance with the specified HTTP protocol version.
     *
     * The version string MUST contain only the HTTP version number (e.g.,
     * "1.1", "1.0").
     *
     * @param string $version HTTP protocol version
     * @return self
     */
    public function withProtocolVersion($version)
    {
        $new = clone $this;

        return $new->setProtocolVersion($version);
    }

    /**
     * Retrieves all message headers.
     *
     * The keys represent the header name as it will be sent over the wire, and
     * each value is an array of strings associated with the header.
     *
     *     // Represent the headers as a string
     *     foreach ($message->getHeaders() as $name => $values) {
     *         echo $name . ": " . implode(", ", $values);
     *     }
     *
     *     // Emit headers iteratively:
     *     foreach ($message->getHeaders() as $name => $values) {
     *         foreach ($values as $value) {
     *             header(sprintf('%s: %s', $name, $value), false);
     *         }
     *     }
     *
     * @return array Returns an associative array of the message's headers. Each
     *     key MUST be a header name, and each value MUST be an array of strings.
     */
    public function getHeaders()
    {
        return $this->headers;
    }

    /**
     * Checks if a header exists by the given case-insensitive name.
     *
     * @param string $header Case-insensitive header name.
     * @return bool Returns true if any header names match the given header
     *     name using a case-insensitive string comparison. Returns false if
     *     no matching header name is found in the message.
     */
    public function hasHeader($header)
    {
        return array_key_exists(strtolower($header), $this->headerNames);
    }

    /**
     * Retrieves a message header value by the given case-insensitive name.
     *
     * This method returns an array of all the header values of the given
     * case-insensitive header name.
     *
     * If the header does not appear in the message, this method MUST return an
     * empty array.
     *
     * @param string $header Case-insensitive header field name.
     * @return string[] An array of string values as provided for the given
     *    header. If the header does not appear in the message, this method MUST
     *    return an empty array.
     */
    public function getHeader($header)
    {
        if (! $this->hasHeader($header)) {
            return [];
        }

        $header = $this->headerNames[strtolower($header)];
        $value  = $this->headers[$header];
        $value  = is_array($value) ? $value : [$value];

        return $value;
    }

    /**
     * Retrieves a comma-separated string of the values for a single header.
     *
     * This method returns all of the header values of the given
     * case-insensitive header name as a string concatenated together using
     * a comma.
     *
     * NOTE: Not all header values may be appropriately represented using
     * comma concatenation. For such headers, use getHeader() instead
     * and supply your own delimiter when concatenating.
     *
     * If the header does not appear in the message, this method MUST return
     * an empty string.
     *
     * @param string $name Case-insensitive header field name.
     * @return string A string of values as provided for the given header
     *    concatenated together using a comma. If the header does not appear in
     *    the message, this method MUST return an empty string.
     */
    public function getHeaderLine($name)
    {
        $value = $this->getHeader($name);
        if (empty($value)) {
            return '';
        }

        return implode(',', $value);
    }

    /**
     * Set the header, replacing any existing values of any headers with the same case-insensitive name.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * @param string $header Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * @return self
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function setHeader($header, $value)
    {
        if (is_string($value)) {
            $value = [$value];
        }

        if (! is_array($value) || ! $this->arrayContainsOnlyStrings($value)) {
            throw new InvalidArgumentException(
                'Invalid header value; must be a string or array of strings'
            );
        }

        HeaderSecurity::assertValidName($header);
        self::assertValidHeaderValue($value);

        $normalized = strtolower($header);

        $this->headerNames[$normalized] = $header;
        $this->headers[$header]         = $value;

        return $this;
    }

    /**
     * Return an instance with the provided header, replacing any existing
     * values of any headers with the same case-insensitive name.
     *
     * While header names are case-insensitive, the casing of the header will
     * be preserved by this function, and returned from getHeaders().
     *
     * @param string $header Case-insensitive header field name.
     * @param string|string[] $value Header value(s).
     * @return self
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withHeader($header, $value)
    {
        $new = clone $this;

        return $new->setHeader($header, $value);
    }

    /**
     * Set the header appended with the given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * @param string $header Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     * @return self
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function appendHeader($header, $value)
    {
        if (is_string($value)) {
            $value = [ $value ];
        }

        if (! is_array($value) || ! $this->arrayContainsOnlyStrings($value)) {
            throw new InvalidArgumentException(
                'Invalid header value; must be a string or array of strings'
            );
        }

        HeaderSecurity::assertValidName($header);
        self::assertValidHeaderValue($value);

        if (! $this->hasHeader($header)) {
            return $this->setHeader($header, $value);
        }

        $normalized = strtolower($header);
        $header     = $this->headerNames[$normalized];

        $this->headers[$header] = array_merge($this->headers[$header], $value);

        return $this;
    }

    /**
     * Return an instance with the specified header appended with the
     * given value.
     *
     * Existing values for the specified header will be maintained. The new
     * value(s) will be appended to the existing list. If the header did not
     * exist previously, it will be added.
     *
     * @param string $header Case-insensitive header field name to add.
     * @param string|string[] $value Header value(s).
     * @return self
     * @throws \InvalidArgumentException for invalid header names or values.
     */
    public function withAddedHeader($header, $value)
    {
        $new = clone $this;

        return $new->appendHeader($header, $value);
    }

    /**
     * Unset the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * @param string $header Case-insensitive header field name to remove.
     * @return self
     */
    public function unsetHeader($header)
    {
        if (! $this->hasHeader($header)) {
            return $this;
        }

        $normalized = strtolower($header);
        $original   = $this->headerNames[$normalized];

        unset($this->headers[$original], $this->headerNames[$normalized]);
        return $this;
    }

    /**
     * Return an instance without the specified header.
     *
     * Header resolution MUST be done without case-sensitivity.
     *
     * @param string $header Case-insensitive header field name to remove.
     * @return self
     */
    public function withoutHeader($header)
    {
        $new = clone $this;

        return $new->unsetHeader($header);
    }

    /**
     * Gets the body of the message.
     *
     * @return StreamInterface Returns the body as a stream.
     */
    public function getBody()
    {
        return $this->stream;
    }

    /**
     * Set the message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * @param StreamInterface $body Body.
     * @return self
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function setBody(StreamInterface $body)
    {
        $this->stream = $body;

        return $this;
    }

    /**
     * Return an instance with the specified message body.
     *
     * The body MUST be a StreamInterface object.
     *
     * @param StreamInterface $body Body.
     * @return self
     * @throws \InvalidArgumentException When the body is not valid.
     */
    public function withBody(StreamInterface $body)
    {
        $new = clone $this;

        return $new->setBody($body);
    }

    /**
     * Test that an array contains only strings
     *
     * @param array $array
     * @return bool
     */
    private function arrayContainsOnlyStrings(array $array)
    {
        return array_reduce($array, [__CLASS__, 'filterStringValue'], true);
    }

    /**
     * Filter a set of headers to ensure they are in the correct internal format.
     *
     * Used by message constructors to allow setting all initial headers at once.
     *
     * @param array $originalHeaders Headers to filter.
     * @return array Filtered headers and names.
     */
    protected function filterHeaders(array $originalHeaders)
    {
        $headerNames = $headers = [];
        foreach ($originalHeaders as $header => $value) {
            if (! is_string($header)) {
                continue;
            }

            if (! is_array($value) && ! is_string($value)) {
                continue;
            }

            if (! is_array($value)) {
                $value = [ $value ];
            }

            $headerNames[strtolower($header)] = $header;
            $headers[$header] = $value;
        }

        return [$headerNames, $headers];
    }

    /**
     * Test if a value is a string
     *
     * Used with array_reduce.
     *
     * @param bool $carry
     * @param mixed $item
     * @return bool
     */
    private static function filterStringValue($carry, $item)
    {
        if (! is_string($item)) {
            return false;
        }
        return $carry;
    }

    /**
     * Assert that the provided header values are valid.
     *
     * @see http://tools.ietf.org/html/rfc7230#section-3.2
     * @param string[] $values
     * @throws InvalidArgumentException
     */
    private static function assertValidHeaderValue(array $values)
    {
        array_walk($values, __NAMESPACE__ . '\HeaderSecurity::assertValid');
    }
}
