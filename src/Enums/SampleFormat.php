<?php

declare(strict_types=1);

namespace PhpMlKit\SoundFile\Enums;

use PhpMlKit\NDArray\DType;

/**
 * Audio encoding subtypes supported by libsndfile.
 *
 * Values match SF_FORMAT_* constants bitmasked against SF_FORMAT_SUBMASK (0x0000FFFF).
 * The full sf format is $format->value | $subtype->value.
 *
 * Not all subtypes are compatible with every format. Use sf_check_format()
 * to validate a combination before writing.
 */
enum SampleFormat: int
{
    case PcmS8 = 0x0001;
    case Pcm16 = 0x0002;
    case Pcm24 = 0x0003;
    case Pcm32 = 0x0004;
    case PcmU8 = 0x0005;
    case Float = 0x0006;
    case Double = 0x0007;
    case ULaw = 0x0010;
    case ALaw = 0x0011;
    case ImaAdpcm = 0x0012;
    case MsAdpcm = 0x0013;
    case Gsm610 = 0x0020;
    case Vorbis = 0x0060;
    case Dwvw12 = 0x0040;
    case Dwvw16 = 0x0041;
    case Dwvw24 = 0x0042;
    case DwvwN = 0x0043;
    case MpegLayerI = 0x0080;
    case MpegLayerII = 0x0081;
    case MpegLayerIII = 0x0082;

    /** Bits per sample. Returns 0 for compressed formats where bit depth doesn't apply. */
    public function bitDepth(): int
    {
        return match ($this) {
            self::PcmS8, self::PcmU8 => 8,
            self::Pcm16 => 16,
            self::Pcm24 => 24,
            self::Pcm32, self::Float => 32,
            self::Double => 64,
            default => 0,
        };
    }

    /** True if this subtype stores integer PCM samples. */
    public function isInteger(): bool
    {
        return match ($this) {
            self::PcmS8, self::Pcm16, self::Pcm24, self::Pcm32, self::PcmU8 => true,
            default => false,
        };
    }

    /** True if this subtype is uncompressed PCM. */
    public function isPcm(): bool
    {
        return $this->isInteger();
    }

    /** Map this subtype to the closest valid NDArray DType for reading/writing. */
    public function toDtype(): DType
    {
        return match ($this) {
            self::PcmS8, self::PcmU8, self::Pcm16 => DType::Int16,
            self::Pcm24, self::Pcm32 => DType::Int32,
            self::Float => DType::Float32,
            self::Double => DType::Float64,
            default => DType::Float32,
        };
    }

    /**
     * Extract the subtype from a combined libsndfile format value.
     *
     * The combined value is `format | subtype`. This masks off the
     * container format bits to return just the encoding subtype.
     */
    public static function fromSndfileFormat(int $combinedFormat): ?self
    {
        return self::tryFrom($combinedFormat & 0x0000FFFF);
    }
}
