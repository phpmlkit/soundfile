<?php

declare(strict_types=1);

namespace PhpMlKit\SoundFile;

use FFI\CData;
use PhpMlKit\NDArray\DType;
use PhpMlKit\NDArray\NDArray;
use PhpMlKit\SoundFile\Enums\AudioFormat;
use PhpMlKit\SoundFile\Enums\FileMode;
use PhpMlKit\SoundFile\Enums\SampleFormat;
use PhpMlKit\SoundFile\Exceptions\SoundFileException;
use PhpMlKit\SoundFile\FFI\Libsndfile;

/**
 * An opened audio file — streaming read/write with seeking and block iteration.
 *
 * Read mode:
 *   $sf = new SoundFile('audio.wav', FileMode::Read);
 *   $chunk = $sf->read(512);
 *   foreach ($sf->blocks(1024) as $block) { ... }
 *
 * Write mode:
 *   $sf = new SoundFile('out.wav', FileMode::Write,
 *       sampleRate: 44100, channels: 2,
 *       format: AudioFormat::Wav, subtype: SampleFormat::Float);
 *   $sf->write($data);
 *   $sf->close();
 *
 * ReadWrite mode:
 *   $sf = new SoundFile('file.wav', FileMode::ReadWrite,
 *       sampleRate: 44100, channels: 2,
 *       format: AudioFormat::Wav, subtype: SampleFormat::Float);
 *   $sf->write($data);
 *   $sf->seek(0);
 *   $sf->read(100);
 *
 * For one-shot read/write without managing the handle, use the convenience
 * functions sf_read() and sf_write() instead.
 *
 * @see sf_read()
 * @see sf_write()
 */
final class SoundFile
{
    private ?CData $handle = null;
    private SfInfo $info;
    private int $position = 0;
    private readonly Libsndfile $lib;
    private readonly FileMode $mode;
    private ?SfMetadata $metadata = null;

    /**
     * Open a sound file.
     *
     * In read mode, metadata is read from the file header. In write or
     * read-write mode, sampleRate, channels, format, and subtype must be
     * provided.
     *
     * @param string            $path       Path to the audio file
     * @param FileMode          $mode       Read (default), Write, or ReadWrite
     * @param null|int          $sampleRate Sample rate in Hz (write / read-write mode)
     * @param null|int          $channels   Number of channels (write / read-write mode; defaults to 1)
     * @param null|AudioFormat  $format     Container format (write / read-write mode; default: inferred from extension)
     * @param null|SampleFormat $subtype    Encoding subtype (write / read-write mode; default: format's preferred)
     *
     * @throws SoundFileException If the file cannot be opened or the format is invalid
     */
    public function __construct(
        string $path,
        FileMode $mode = FileMode::Read,
        ?int $sampleRate = null,
        ?int $channels = null,
        ?AudioFormat $format = null,
        ?SampleFormat $subtype = null,
    ) {
        $this->mode = $mode;
        $this->lib = Libsndfile::get();

        if (FileMode::Read === $mode || FileMode::ReadWrite === $mode) {
            $sfInfo = $this->lib->newInfo();
            $handle = $this->lib->open($path, $mode, $sfInfo);

            if (null === $handle) {
                throw new SoundFileException(
                    "Failed to open '{$path}': ".$this->lib->strError(null)
                );
            }

            $this->handle = $handle;
            $this->info = SfInfo::fromCData($sfInfo);

            return;
        }

        // Write mode
        $format ??= AudioFormat::fromPath($path)
            ?? throw new SoundFileException("Cannot determine format for '{$path}'");

        $subtype ??= $format->compatibleSampleFormats()[0];

        if (!sf_check_format($format, $subtype)) {
            throw new SoundFileException(
                "Incompatible format/subtype: {$format->name} + {$subtype->name}"
            );
        }

        $writeInfo = new SfInfo(0, $channels ?? 1, $sampleRate ?? 44100, $format, $subtype);

        $handle = $this->lib->open($path, $mode, $writeInfo->toCData($this->lib));

        if (null === $handle) {
            throw new SoundFileException(
                "Failed to open '{$path}' for writing: ".$this->lib->strError(null)
            );
        }

        $this->handle = $handle;
        $this->info = $writeInfo;
    }

    public function __destruct()
    {
        $this->close();
    }

    // ===================================================================
    // I/O
    // ===================================================================

    /**
     * Read up to $numFrames frames from the current position.
     *
     * The $dtype controls the array's data type. Integer samples are normalized
     * to [-1.0, 1.0] for float reads, and float data is truncated to the
     * nearest integer for int reads. Only Float32, Float64, Int16, and Int32 are supported. Defaults to Float32.
     *
     * For mono files, the default output is a 1D array of shape [framesRead];
     * set $always2d to true for the canonical [framesRead × 1] shape.
     *
     * Each call advances the position; subsequent reads continue from where the
     * previous one left off. Pass null to read all remaining frames.
     *
     * @param null|int $numFrames Maximum frames to read (null = remaining)
     * @param DType    $dtype     Desired dtype of the returned array (default Float32)
     * @param bool     $always2d  If true, mono files return [frames, 1] instead of [frames]
     *
     * @throws SoundFileException If the file is closed, opened in write-only
     *                            mode, an unsupported dtype is given, or a read error occurs
     */
    public function read(?int $numFrames = null, DType $dtype = DType::Float32, bool $always2d = false): NDArray
    {
        if (null === $this->handle) {
            throw new SoundFileException('Cannot read from a closed file');
        }

        if (FileMode::Write === $this->mode) {
            throw new SoundFileException('Cannot read from a write-only file');
        }

        $numFrames ??= $this->remaining();
        $total = $numFrames * $this->info->channels;

        [$cType, $readFn] = $this->lib->readFn($dtype);
        $buffer = $this->lib->new("{$cType}[{$total}]");

        $read = $readFn($this->lib, $this->handle, $buffer, $numFrames);

        if ($read < 0) {
            throw new SoundFileException('Read error: '.$this->lib->strError($this->handle));
        }

        $this->position += $read;

        $data = NDArray::fromBuffer($buffer, [$read, $this->info->channels], $dtype);

        if (!$always2d && 1 === $this->info->channels) {
            $data = $data->squeeze();
        }

        return $data;
    }

    /**
     * Write frames to the file.
     *
     * Can be called multiple times — each call appends the data and advances
     * the position. Only the channel count must match the file's channel count;
     * the frame count in $data can be any size.
     *
     * The dtype is automatically converted to match the file's subtype.
     * After writing, $sf->tell() reflects the total frames written so far.
     *
     * @param NDArray $data Data of shape [N, channels] where channels matches
     *                      the file's channel count
     *
     * @throws SoundFileException If the handle is closed, opened in read-only
     *                            mode, channels mismatch, or a write error occurs
     */
    public function write(NDArray $data): void
    {
        if (null === $this->handle) {
            throw new SoundFileException('Cannot write to a closed file');
        }

        if (FileMode::Read === $this->mode) {
            throw new SoundFileException('Cannot write to a read-only file');
        }

        $shape = $data->shape();
        $frames = $shape[0];
        $channels = $shape[1] ?? 1;

        if ($channels !== $this->info->channels) {
            throw new SoundFileException(
                "Channel mismatch: expected {$this->info->channels}, got {$channels}"
            );
        }

        $dtype = $this->info->sampleFormat->toDtype();
        $dataOut = $data->cast($dtype);

        $total = $frames * $channels;
        [$cType, $writeFn] = $this->lib->writeFn($dtype);
        $buffer = $this->lib->new("{$cType}[{$total}]");
        $dataOut->toBuffer($buffer);
        $written = $writeFn($this->lib, $this->handle, $buffer, $frames);

        if ($written !== $frames) {
            throw new SoundFileException(
                "Write error: wrote {$written}/{$frames} frames: ".$this->lib->strError($this->handle)
            );
        }

        $this->position += $written;
        $this->info = $this->info->withFrames($this->position);
    }

    // ===================================================================
    // Block iteration
    // ===================================================================

    /**
     * Iterate over the file in blocks of up to $blocksize frames.
     *
     * Each yielded value is an NDArray of shape [framesRead × channels]
     * (the final block may be smaller). The generator stops when EOF is
     * reached.
     *
     * Only available for handles opened in read or read-write mode.
     *
     * @param int   $blocksize Maximum frames per block
     * @param DType $dtype     Desired dtype of each block (default Float32)
     * @param bool  $always2d  If false, mono blocks return [frames] instead of [frames, 1]
     *
     * @return \Generator<int, NDArray>
     */
    public function blocks(int $blocksize = 4096, DType $dtype = DType::Float32, bool $always2d = false): \Generator
    {
        while (!$this->eof()) {
            $chunk = $this->read($blocksize, $dtype, $always2d);

            if (0 === $chunk->shape()[0]) {
                break;
            }

            yield $chunk;
        }
    }

    // ===================================================================
    // Position
    // ===================================================================

    /**
     * Move the read/write position to a frame offset.
     *
     * @param int $frameOffset Target frame position
     * @param int $whence      SEEK_SET (0), SEEK_CUR (1), or SEEK_END (2)
     *
     * @throws SoundFileException If the file is closed, not seekable, or the seek fails
     */
    public function seek(int $frameOffset, int $whence = \SEEK_SET): void
    {
        if (!$this->info->seekable) {
            throw new SoundFileException('This file does not support seeking');
        }

        if (null === $this->handle) {
            throw new SoundFileException('Cannot seek a closed file');
        }

        $result = $this->lib->seek($this->handle, $frameOffset, $whence);

        if ($result < 0) {
            throw new SoundFileException('Seek error: '.$this->lib->strError($this->handle));
        }

        $this->position = $result;
    }

    /**
     * Current frame position.
     *
     * Updated by read, write, and seek. Starts at 0 on open and resets
     * to 0 on close.
     */
    public function tell(): int
    {
        return $this->position;
    }

    /**
     * Whether the read position has reached or passed the end of the file.
     *
     * In read mode, this is true when the position reaches the file's
     * total frame count. In write mode, this always returns false —
     * writing appends indefinitely, so there is no end to reach.
     */
    public function eof(): bool
    {
        if (null === $this->handle) {
            return true;
        }

        if (FileMode::Write === $this->mode) {
            return false;
        }

        return $this->position >= $this->info->frames;
    }

    /**
     * Close the file handle.
     *
     * After closing, all I/O methods throw. Called automatically by the
     * destructor.
     */
    public function close(): void
    {
        if (null !== $this->handle) {
            $this->lib->close($this->handle);
            $this->handle = null;
        }

        $this->position = 0;
        $this->metadata = null;
    }

    // ===================================================================
    // Signal properties
    // ===================================================================

    /**
     * Signal properties (frames, channels, sample rate, format, etc.).
     *
     * In read mode, the frame count is the file's total frames. In write
     * or read-write mode, it reflects the total frames written so far.
     */
    public function info(): SfInfo
    {
        return $this->info;
    }

    /** Total frames in the file, or frames written so far in write mode. */
    public function frames(): int
    {
        return $this->info->frames;
    }

    /** Number of audio channels. */
    public function channels(): int
    {
        return $this->info->channels;
    }

    /** Sample rate in Hz. */
    public function sampleRate(): int
    {
        return $this->info->sampleRate;
    }

    /** The FileMode this handle was opened with. */
    public function mode(): FileMode
    {
        return $this->mode;
    }

    // ===================================================================
    // Metadata
    // ===================================================================

    /**
     * Embedded string tags from this file (title, artist, album, etc.).
     *
     * Returns null if closed.
     */
    public function metadata(): ?SfMetadata
    {
        if (null === $this->handle) {
            return null;
        }

        return $this->metadata ??= SfMetadata::fromHandle($this->lib, $this->handle);
    }

    /** Title tag. */
    public function title(): ?string
    {
        return $this->metadata ? $this->metadata()?->title : $this->getString(0x01);
    }

    /** Copyright tag. */
    public function copyright(): ?string
    {
        return $this->metadata ? $this->metadata()?->copyright : $this->getString(0x02);
    }

    /** Encoder software tag. */
    public function software(): ?string
    {
        return $this->metadata ? $this->metadata()?->software : $this->getString(0x03);
    }

    /** Artist tag. */
    public function artist(): ?string
    {
        return $this->metadata ? $this->metadata()?->artist : $this->getString(0x04);
    }

    /** Comment tag. */
    public function comment(): ?string
    {
        return $this->metadata ? $this->metadata()?->comment : $this->getString(0x05);
    }

    /** Date tag. */
    public function date(): ?string
    {
        return $this->metadata ? $this->metadata()?->date : $this->getString(0x06);
    }

    /** Album tag. */
    public function album(): ?string
    {
        return $this->metadata ? $this->metadata()?->album : $this->getString(0x07);
    }

    /** License tag. */
    public function license(): ?string
    {
        return $this->metadata ? $this->metadata()?->license : $this->getString(0x08);
    }

    /** Track number tag. */
    public function trackNumber(): ?string
    {
        return $this->metadata ? $this->metadata()?->trackNumber : $this->getString(0x09);
    }

    /** Genre tag. */
    public function genre(): ?string
    {
        return $this->metadata ? $this->metadata()?->genre : $this->getString(0x10);
    }

    /**
     * Set the title tag.
     *
     * @throws SoundFileException If the handle is closed or opened in read-only mode
     */
    public function setTitle(string $title): void
    {
        $this->setString(0x01, $title);
    }

    /**
     * Set the copyright tag.
     *
     * @throws SoundFileException If the handle is closed or opened in read-only mode
     */
    public function setCopyright(string $copyright): void
    {
        $this->setString(0x02, $copyright);
    }

    /**
     * Set the encoder software tag.
     *
     * @throws SoundFileException If the handle is closed or opened in read-only mode
     */
    public function setSoftware(string $software): void
    {
        $this->setString(0x03, $software);
    }

    /**
     * Set the artist tag.
     *
     * @throws SoundFileException If the handle is closed or opened in read-only mode
     */
    public function setArtist(string $artist): void
    {
        $this->setString(0x04, $artist);
    }

    /**
     * Set the comment tag.
     *
     * @throws SoundFileException If the handle is closed or opened in read-only mode
     */
    public function setComment(string $comment): void
    {
        $this->setString(0x05, $comment);
    }

    /**
     * Set the date tag.
     *
     * @throws SoundFileException If the handle is closed or opened in read-only mode
     */
    public function setDate(string $date): void
    {
        $this->setString(0x06, $date);
    }

    /**
     * Set the album tag.
     *
     * @throws SoundFileException If the handle is closed or opened in read-only mode
     */
    public function setAlbum(string $album): void
    {
        $this->setString(0x07, $album);
    }

    /**
     * Set the license tag.
     *
     * @throws SoundFileException If the handle is closed or opened in read-only mode
     */
    public function setLicense(string $license): void
    {
        $this->setString(0x08, $license);
    }

    /**
     * Set the track number tag.
     *
     * @throws SoundFileException If the handle is closed or opened in read-only mode
     */
    public function setTrackNumber(string $trackNumber): void
    {
        $this->setString(0x09, $trackNumber);
    }

    /**
     * Set the genre tag.
     *
     * @throws SoundFileException If the handle is closed or opened in read-only mode
     */
    public function setGenre(string $genre): void
    {
        $this->setString(0x10, $genre);
    }

    /**
     * Read an embedded tag by its code.
     *
     * @param int $strType Tag code (0x01 = Title, 0x04 = Artist, etc.)
     */
    private function getString(int $strType): ?string
    {
        return null !== $this->handle ? $this->lib->getString($this->handle, $strType) : null;
    }

    /**
     * @throws SoundFileException If the handle is closed or opened in read-only mode
     */
    private function setString(int $strType, string $value): void
    {
        if (null === $this->handle) {
            throw new SoundFileException('Cannot set metadata on a closed file');
        }

        if (FileMode::Read === $this->mode) {
            throw new SoundFileException('Cannot set metadata on a read-only file');
        }

        $this->lib->setString($this->handle, $strType, $value);
        $this->metadata = null;
    }

    /** Frames remaining from the current position to EOF (read mode only). */
    private function remaining(): int
    {
        return max(0, $this->info->frames - $this->position);
    }
}
