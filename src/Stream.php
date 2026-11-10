<?php declare(strict_types = 1);
/**
 * @author    Luis Arturo Rodríguez
 * @copyright Copyright (c) 2022-2025 Luis Arturo Rodríguez <rguezque@gmail.com>
 * @link      https://github.com/rguezque
 * @license   https://opensource.org/licenses/MIT    MIT License
 */

namespace rguezque;

use InvalidArgumentException;
use RuntimeException;

/**
 * Represent a PHP stream
 * 
 * This class provides a simple interface to manage a PHP stream resource.
 * It allows you to write content to the stream, read from it, retrieve its contents
 * and size, seek to a specific position, rewind the pointer, and check if the end of the
 * stream has been reached. The stream can be detached, and it will be automatically closed
 * when the object is destroyed.
 * 
 * @method int|false write(string $string) Write content to stream and return the total bytes written, or false on error
 * @method string|false read(int $length) Read the stream
 * @method string|false getContents() Retrieve the remainder content of the stream from current pointer position
 * @method mixed detach() Free and return the current stream
 * @method ?int getSize() Returns the bytes size of the stream, or null when unavailable
 * @method int|false tell() Return the current position of the stream read/write pointer
 * @method bool eof() Return true if the stream pointer is at end-of-file, otherwise false
 * @method void seek(int $offset, int $whence = SEEK_SET) Sets the file position indicator for the file referenced by stream. The new position, measured in bytes from the beginning of the file, is obtained by adding offset to the position specified by whence.
 * @method void rewind() Rewind the stream pointer at beginning
 * @method void close() Close the stream from writing
 */
final class Stream {
    /** @var mixed The PHP stream */
    private mixed $stream;

    /**
     * Initialize the stream
     * 
     * @param mixed $resource Resource of type stream
     * @throws InvalidArgumentException When the resource is not a valid stream
     */
    public function __construct(mixed $resource){
        if (!is_resource($resource) || 'stream' !== get_resource_type($resource)) {
            throw new InvalidArgumentException('A valid stream resource is required.');
        }

        $this->stream = $resource;
    }

    /**
     * Close the stream
     */
    public function __destruct() {
        $this->close();
    }

    /**
     * Write content to stream and return the total bytes written, or false on error
     * 
     * @param string $string
     * @return int|false
     */
    public function write(string $string): int|false {
        return fwrite($this->getStream(), $string);
    }

    /**
     * Read the stream 
     * 
     * @param int $length Up to length number of bytes read
     * @return string|false
     */
    public function read(int $length): string|false {
        if ($length < 1) {
            throw new InvalidArgumentException('The read length must be greater than zero.');
        }

        return fread($this->getStream(), $length);
    }

    /**
     * Retrieve the remainder content of the stream from current pointer position
     * 
     * @return string|false
     */
    public function getContents(): string|false {
        return stream_get_contents($this->getStream());
    }

    /**
     * Free and return the current stream
     * 
     * @return mixed
     */
    public function detach(): mixed {
        $stream = $this->stream;
        $this->stream = null;
        return $stream;
    }

    /**
     * Returns the bytes size of the stream
     * 
     * @return ?int
     */
    public function getSize(): ?int {
        $stats = fstat($this->getStream());
        if (false === $stats || !isset($stats['size']) || $stats['size'] < 0) {
            return null;
        }

        return $stats['size'];
    }

    /**
     * Return the current position of the stream read/write pointer
     * 
     * @return int|false
     */
    public function tell(): int|false {
        return ftell($this->getStream());
    }

    /**
     * Return true if the stream pointer is at end-of-file, otherwise false
     * 
     * @return bool
     */
    public function eof(): bool {
        return feof($this->getStream());
    }

    /**
     * Sets the file position indicator for the file referenced by stream. The new position, measured in bytes from the beginning of the file, is obtained by adding offset to the position specified by whence.
     * 
     * @param int $offset The offset 
     * @param int $whence The whence
     * @return void
     */
    public function seek(int $offset, int $whence = SEEK_SET): void {
        if (!in_array($whence, [SEEK_SET, SEEK_CUR, SEEK_END], true)) {
            throw new InvalidArgumentException('Invalid seek mode.');
        }

        if (0 !== fseek($this->getStream(), $offset, $whence)) {
            throw new RuntimeException('Unable to seek in the stream.');
        }
    }

    /**
     * Rewind the stream pointer at beginning
     * 
     * @return void
     */
    public function rewind(): void {
        $this->seek(0);
    }

    /**
     * Close the stream from writing
     * 
     * @return void
     */
    public function close(): void {
        if (is_resource($this->stream) && 'stream' === get_resource_type($this->stream)) {
            fclose($this->stream);
        }
        $this->stream = null;
    }

    /**
     * Truncate the stream to a new length. Requires the stream to be open for writing
     * 
     * @param int $size New length in bytes
     * @return bool `true` on success, otherwise `false`
     */
    public function truncate(int $size): bool {
        if ($size < 0) {
            throw new InvalidArgumentException('The truncate size cannot be negative.');
        }

        return ftruncate($this->getStream(), $size);
    }

    /**
     * Return the remaining stream contents as a string.
     *
     * @return string
     * @throws RuntimeException When the stream contents cannot be read
     */
    public function __toString(): string {
        $contents = $this->getContents();
        if (false === $contents) {
            throw new RuntimeException('Unable to read the stream contents.');
        }

        return $contents;
    }

    /**
     * Return the underlying stream resource when it is still open.
     *
     * @return resource
     * @throws RuntimeException When the stream is closed or detached
     */
    private function getStream(): mixed {
        if (!is_resource($this->stream) || 'stream' !== get_resource_type($this->stream)) {
            throw new RuntimeException('The stream is closed or detached.');
        }

        return $this->stream;
    }
}