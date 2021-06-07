<?php

/**
 * @author    Proximify Inc <support@proximify.com>
 * @copyright Copyright (c) 2020, Proximify Inc
 * @license   https://opensource.org/licenses/GPL-3.0 GNU GPL 3.0
 */

namespace Proximify\Glot;

/**
 * Abstract implementation of a PHP Stream Wrapper class.
 * 
 * The interface defined bellow should not always be implemented by a stream 
 * wrapper class, as several of the methods should not be implemented if the 
 * class has no use for them (as per the manual).
 * 
 * Specifically, mkdir, rename, rmdir, and unlink are methods that "should not be 
 * defined" if the wrapper has no use for them. The consequence is that the 
 * appropriate error message will not be returned.
 * 
 * If the interface is implemented, you won't have the flexibility to not 
 * implement those methods.
 *
 * @see https://www.php.net/manual/en/class.streamwrapper.php
 * @see https://www.php.net/manual/en/function.stream-wrapper-register.php
 * @see https://www.php.net/manual/en/internals2.ze1.streams.php
 * @see https://www.php.net/manual/en/stream.streamwrapper.example-1.php
 */
abstract class Stream
{
    /* @var resource */
    public $context;

    /**
     * Constructs a new stream wrapper.
     */
    // abstract public function __construct();

    // abstract public function __destruct();

    // abstract public function dir_closedir(): bool;
    // abstract public function dir_opendir(string $path, int $options): bool;
    // abstract public function dir_readdir(): string;
    // abstract public function dir_rewinddir(): bool;

    // abstract public function mkdir(string $path, int $mode, int $options): bool;
    // abstract public function rename(string $path_from, string $path_to): bool;
    // abstract public function rmdir(string $path, int $options): bool;

    /**
     * Retrieve the underlying resource.
     *
     * @param integer $cast_as
     * @return resource
     */
    abstract public function stream_cast(int $cast_as);

    /**
     * Close a resource.
     *
     * @return void
     */
    abstract public function stream_close(): void;

    /**
     * Tests for end-of-file on a file pointer.
     *
     * @return boolean
     */
    abstract public function stream_eof(): bool;

    // abstract public function stream_flush(): bool;
    // abstract public function stream_lock(int $operation): bool;

    /**
     * Change stream metadata.
     *
     * @param string $path
     * @param integer $option
     * @param [type] $value
     * @return boolean
     */
    abstract public function stream_metadata(string $path, int $option, $value): bool;

    /**
     * Opens file or URL.
     *
     * @param string $path
     * @param string $mode
     * @param integer $options
     * @param string $opened_path
     * @return boolean
     */
    abstract public function stream_open(string $path, string $mode, int $options, string &$opened_path): bool;

    /**
     * Read from stream.
     *
     * @param integer $count
     * @return string
     */
    abstract public function stream_read(int $count): string;

    /**
     * Seeks to specific location in a stream.
     *
     * @param integer $offset
     * @param integer $whence
     * @return boolean
     */
    abstract public function stream_seek(int $offset, int $whence = SEEK_SET): bool;

    // abstract public function stream_set_option(int $option, int $arg1, int $arg2): bool;

    /**
     * Retrieve information about a file resource.
     *
     * @return array
     */
    abstract public function stream_stat(): array;

    /**
     * Retrieve the current position of a stream.
     *
     * @return integer
     */
    abstract public function stream_tell(): int;

    abstract public function stream_truncate(int $new_size): bool;
    abstract public function stream_write(string $data): int;

    // abstract public function unlink(string $path): bool;

    /**
     * Retrieve information about a file
     *
     * @param string $path
     * @param integer $flags
     * @return array
     */
    abstract public function url_stat(string $path, int $flags): array;
}
