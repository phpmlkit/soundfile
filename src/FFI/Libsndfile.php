<?php

declare(strict_types=1);

namespace PhpMlKit\SoundFile\FFI;

use FFI;
use FFI\CData;
use PhpMlKit\NDArray\DType;
use PhpMlKit\SoundFile\Enums\FileMode;
use PhpMlKit\SoundFile\Exceptions\SoundFileException;

/**
 * Low-level libsndfile FFI wrapper.
 *
 * Provides direct access to the libsndfile C API for opening, closing,
 * reading, writing, seeking, and metadata retrieval. All methods map
 * directly to their C counterparts.
 *
 * This class is a singleton — use Libsndfile::get() to obtain the shared instance.
 */
final class Libsndfile extends NativeLibrary
{
    private static ?self $instance = null;

    private function __construct()
    {
        parent::__construct();
    }

    /** Obtain the singleton instance. */
    public static function get(): self
    {
        return self::$instance ??= new self();
    }

    /**
     * Open a sound file.
     *
     * @param string   $path File path
     * @param FileMode $mode Read, Write, or ReadWrite
     * @param CData    $info Pre-allocated SF_INFO struct (populated on return)
     *
     * @return null|CData SNDFILE handle, or null on error
     */
    public function open(string $path, FileMode $mode, CData $info): ?CData
    {
        $handle = $this->ffi->sf_open($path, $mode->value, \FFI::addr($info));

        return null === $handle ? null : $handle;
    }

    /** Read frames as 32-bit float. */
    public function readFloat(CData $handle, CData $buffer, int $frames): int
    {
        return $this->ffi->sf_readf_float($handle, $buffer, $frames);
    }

    /** Read frames as 64-bit double. */
    public function readDouble(CData $handle, CData $buffer, int $frames): int
    {
        return $this->ffi->sf_readf_double($handle, $buffer, $frames);
    }

    /** Read frames as 32-bit int. */
    public function readInt(CData $handle, CData $buffer, int $frames): int
    {
        return $this->ffi->sf_readf_int($handle, $buffer, $frames);
    }

    /** Read frames as 16-bit short. */
    public function readShort(CData $handle, CData $buffer, int $frames): int
    {
        return $this->ffi->sf_readf_short($handle, $buffer, $frames);
    }

    /**
     * Dispatch to the correct read function and C buffer type for a given DType.
     *
     * Only Float32, Float64, Int16, and Int32 are supported. Integer samples
     * are normalized to [-1.0, 1.0] for float reads, and float data is
     * truncated to the nearest integer for int reads.
     *
     * @return array{string, \Closure(self, CData, CData, int): int}
     *
     * @throws SoundFileException for unsupported dtypes
     */
    public function readFn(DType $dtype): array
    {
        return match ($dtype) {
            DType::Float32 => ['float', static fn (self $l, $h, $b, $f) => $l->readFloat($h, $b, $f)],
            DType::Float64 => ['double', static fn (self $l, $h, $b, $f) => $l->readDouble($h, $b, $f)],
            DType::Int32 => ['int', static fn (self $l, $h, $b, $f) => $l->readInt($h, $b, $f)],
            DType::Int16 => ['short', static fn (self $l, $h, $b, $f) => $l->readShort($h, $b, $f)],
            default => throw new SoundFileException(
                "Unsupported read dtype: {$dtype->name}. Only Float32, Float64, Int16, and Int32 are supported."
            ),
        };
    }

    /** Write frames as 32-bit float. */
    public function writeFloat(CData $handle, CData $buffer, int $frames): int
    {
        return $this->ffi->sf_writef_float($handle, $buffer, $frames);
    }

    /** Write frames as 64-bit double. */
    public function writeDouble(CData $handle, CData $buffer, int $frames): int
    {
        return $this->ffi->sf_writef_double($handle, $buffer, $frames);
    }

    /** Write frames as 32-bit int. */
    public function writeInt(CData $handle, CData $buffer, int $frames): int
    {
        return $this->ffi->sf_writef_int($handle, $buffer, $frames);
    }

    /** Write frames as 16-bit short. */
    public function writeShort(CData $handle, CData $buffer, int $frames): int
    {
        return $this->ffi->sf_writef_short($handle, $buffer, $frames);
    }

    /**
     * Dispatch to the correct write function and C buffer type for a given DType.
     *
     * Only Float32, Float64, Int16, and Int32 are supported.
     *
     * @return array{string, \Closure(self, CData, CData, int): int}
     *
     * @throws SoundFileException for unsupported dtypes
     */
    public function writeFn(DType $dtype): array
    {
        return match ($dtype) {
            DType::Float32 => ['float', static fn (self $l, $h, $b, $f) => $l->writeFloat($h, $b, $f)],
            DType::Float64 => ['double', static fn (self $l, $h, $b, $f) => $l->writeDouble($h, $b, $f)],
            DType::Int32 => ['int', static fn (self $l, $h, $b, $f) => $l->writeInt($h, $b, $f)],
            DType::Int16 => ['short', static fn (self $l, $h, $b, $f) => $l->writeShort($h, $b, $f)],
            default => throw new SoundFileException(
                "Unsupported write dtype: {$dtype->name}. Only Float32, Float64, Int16, and Int32 are supported."
            ),
        };
    }

    /**
     * Seek to a frame offset within the file.
     *
     * @param int $whence SEEK_SET (0), SEEK_CUR (1), or SEEK_END (2)
     *
     * @return int New absolute frame position, or -1 on error
     */
    public function seek(CData $handle, int $frameOffset, int $whence): int
    {
        return $this->ffi->sf_seek($handle, $frameOffset, $whence);
    }

    /** Close the file handle. Returns 0 on success. */
    public function close(CData $handle): int
    {
        return $this->ffi->sf_close($handle);
    }

    /** Get a human-readable error message for a handle (pass null for the most recent error). */
    public function strError(?CData $handle): string
    {
        return $this->ffi->sf_strerror($handle);
    }

    /**
     * Read a string metadata field.
     *
     * @param int $strType One of the SF_STR_* constants (e.g. 0x01 = Title)
     */
    public function getString(CData $handle, int $strType): ?string
    {
        $result = $this->ffi->sf_get_string($handle, $strType);

        if (null === $result || '' === $result) {
            return null;
        }

        return $result;
    }

    /**
     * Write a string metadata field.
     *
     * @param int $strType One of the SF_STR_* constants
     *
     * @return int 0 on success
     */
    public function setString(CData $handle, int $strType, string $value): int
    {
        return $this->ffi->sf_set_string($handle, $strType, $value);
    }

    /** Allocate a new SF_INFO struct for use with open() / formatCheck(). */
    public function newInfo(): CData
    {
        return $this->new('struct SF_INFO');
    }

    /**
     * Validate a format + subtype combination.
     *
     * @return bool True if the combination is valid for libsndfile
     */
    public function formatCheck(CData $info): bool
    {
        return 0 !== $this->ffi->sf_format_check(\FFI::addr($info));
    }

    protected function getHeaderName(): string
    {
        return 'sndfile';
    }

    protected function getLibraryName(): string
    {
        return 'sndfile';
    }

    protected function getLibraryVersion(): string
    {
        return '1.2.2';
    }
}
