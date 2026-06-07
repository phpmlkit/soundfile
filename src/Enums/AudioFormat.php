<?php

declare(strict_types=1);

namespace PhpMlKit\SoundFile\Enums;

use PhpMlKit\NDArray\DType;

/**
 * Major audio container formats supported by libsndfile.
 *
 * The values are direct SF_FORMAT_* constants bitmasked against
 * SF_FORMAT_TYPEMASK (0x0FFF0000). When opening or saving a file,
 * the final format value is: $format->value | $sampleFormat->value.
 */
enum AudioFormat: int
{
    case Wav = 0x010000;
    case Aiff = 0x020000;
    case Au = 0x030000;
    case Raw = 0x040000;
    case Paf = 0x050000;
    case Svx = 0x060000;
    case Nist = 0x070000;
    case Voc = 0x080000;
    case Ircam = 0x0A0000;
    case W64 = 0x0B0000;
    case Mat4 = 0x0C0000;
    case Mat5 = 0x0D0000;
    case Pvf = 0x0E0000;
    case Xi = 0x0F0000;
    case Htk = 0x100000;
    case Sds = 0x110000;
    case Avr = 0x120000;
    case Wavex = 0x130000;
    case Sd2 = 0x160000;
    case Flac = 0x170000;
    case Caf = 0x180000;
    case Wve = 0x190000;
    case Ogg = 0x200000;
    case Mpc2k = 0x210000;
    case Rf64 = 0x220000;
    case Mpeg = 0x230000;

    /**
     * The conventional file extension for this format (without leading dot).
     */
    public function extension(): string
    {
        return match ($this) {
            self::Wav => 'wav',
            self::Aiff => 'aiff',
            self::Au => 'au',
            self::Raw => 'raw',
            self::Paf => 'paf',
            self::Svx => 'svx',
            self::Nist => 'nist',
            self::Voc => 'voc',
            self::Ircam => 'ircam',
            self::W64 => 'w64',
            self::Mat4 => 'mat',
            self::Mat5 => 'mat',
            self::Pvf => 'pvf',
            self::Xi => 'xi',
            self::Htk => 'htk',
            self::Sds => 'sds',
            self::Avr => 'avr',
            self::Wavex => 'wav',
            self::Sd2 => 'sd2',
            self::Flac => 'flac',
            self::Caf => 'caf',
            self::Wve => 'wve',
            self::Ogg => 'ogg',
            self::Mpc2k => 'mpc',
            self::Rf64 => 'wav',
            self::Mpeg => 'mp3',
        };
    }

    /**
     * All subtypes compatible with this format, ordered by preference.
     *
     * The first entry is the best default when no subtype or DType
     * preference can be determined. Subtypes later in the list are
     * increasingly niche or lossy fallbacks.
     *
     * @return SampleFormat[]
     */
    public function compatibleSampleFormats(): array
    {
        return match ($this) {
            self::Flac => [
                SampleFormat::Pcm16,
                SampleFormat::Pcm24,
                SampleFormat::PcmS8,
            ],
            self::Ogg => [SampleFormat::Vorbis],
            self::Mpeg => [
                SampleFormat::MpegLayerIII,
                SampleFormat::MpegLayerII,
                SampleFormat::MpegLayerI,
            ],
            self::Wav, self::Wavex, self::Rf64, self::W64 => [
                SampleFormat::Float,
                SampleFormat::Double,
                SampleFormat::Pcm32,
                SampleFormat::Pcm24,
                SampleFormat::Pcm16,
                SampleFormat::PcmS8,
                SampleFormat::PcmU8,
                SampleFormat::ULaw,
                SampleFormat::ALaw,
                SampleFormat::ImaAdpcm,
                SampleFormat::MsAdpcm,
                SampleFormat::Gsm610,
            ],
            default => [
                SampleFormat::Float,
                SampleFormat::Double,
                SampleFormat::Pcm32,
                SampleFormat::Pcm24,
                SampleFormat::Pcm16,
                SampleFormat::PcmS8,
                SampleFormat::PcmU8,
            ],
        };
    }

    /**
     * Best SampleFormat for writing data of the given DType to this format.
     *
     * Resolves by checking if the DType's preferred subtype is in the
     * compatible list. Falls back to the first compatible entry when no
     * direct match exists.
     */
    public function preferredSampleFormat(DType $dtype): SampleFormat
    {
        $preferred = SampleFormat::fromDtype($dtype);
        $compatible = $this->compatibleSampleFormats();

        if (null !== $preferred && \in_array($preferred, $compatible, true)) {
            return $preferred;
        }

        return $compatible[0];
    }

    /**
     * Guess the format from a file extension string.
     *
     * Returns null if the extension is unrecognized.
     */
    public static function fromExtension(string $extension): ?self
    {
        $ext = strtolower(ltrim($extension, '.'));

        return match ($ext) {
            'wav' => self::Wav,
            'aiff', 'aif' => self::Aiff,
            'au', 'snd' => self::Au,
            'raw', 'pcm' => self::Raw,
            'flac' => self::Flac,
            'ogg', 'oga' => self::Ogg,
            'mp3', 'mp2' => self::Mpeg,
            'caf' => self::Caf,
            'voc' => self::Voc,
            'w64' => self::W64,
            'mat' => self::Mat5,
            'sd2' => self::Sd2,
            'htk' => self::Htk,
            default => null,
        };
    }

    /**
     * Guess the format from a full file path.
     *
     * Returns null if the extension is unrecognized.
     */
    public static function fromPath(string $path): ?self
    {
        return self::fromExtension(pathinfo($path, \PATHINFO_EXTENSION));
    }

    /**
     * Extract the major format from a combined libsndfile format value.
     *
     * The combined value is `format | subtype`. This masks off the subtype
     * bits to return just the container format.
     */
    public static function fromSndfileFormat(int $combinedFormat): ?self
    {
        return self::tryFrom($combinedFormat & 0x0FFF0000);
    }
}
