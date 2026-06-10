<?php

declare(strict_types=1);

namespace PhpMlKit\SoundFile\Tests;

use PhpMlKit\SoundFile\Enums\AudioFormat;
use PhpMlKit\SoundFile\Enums\SampleFormat;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class EnumsTest extends TestCase
{
    // ===================================================================
    // AudioFormat
    // ===================================================================

    public function testAudioFormatExtensions(): void
    {
        $this->assertSame('wav', AudioFormat::Wav->extension());
        $this->assertSame('flac', AudioFormat::Flac->extension());
        $this->assertSame('ogg', AudioFormat::Ogg->extension());
        $this->assertSame('mp3', AudioFormat::Mpeg->extension());
        $this->assertSame('aiff', AudioFormat::Aiff->extension());
        $this->assertSame('caf', AudioFormat::Caf->extension());
    }

    public function testAudioFormatFromExtensionRoundTrip(): void
    {
        $format = AudioFormat::Wav;
        $this->assertSame($format, AudioFormat::fromExtension($format->extension()));
    }

    public function testAudioFormatDefaultSampleFormat(): void
    {
        $this->assertSame(SampleFormat::Pcm16, AudioFormat::Wav->defaultSampleFormat());
        $this->assertSame(SampleFormat::Vorbis, AudioFormat::Ogg->defaultSampleFormat());
        $this->assertSame(SampleFormat::MpegLayerIII, AudioFormat::Mpeg->defaultSampleFormat());
        $this->assertSame(SampleFormat::Float, AudioFormat::Caf->defaultSampleFormat());
    }

    public function testAudioFormatFromPath(): void
    {
        $this->assertSame(AudioFormat::Wav, AudioFormat::fromPath('song.wav'));
        $this->assertSame(AudioFormat::Flac, AudioFormat::fromPath('track.flac'));
        $this->assertSame(AudioFormat::Ogg, AudioFormat::fromPath('voice.ogg'));
        $this->assertNull(AudioFormat::fromPath('unknown.xyz'));
    }

    // ===================================================================
    // SampleFormat
    // ===================================================================

    public function testSampleFormatBitDepth(): void
    {
        $this->assertSame(8, SampleFormat::PcmS8->bitDepth());
        $this->assertSame(16, SampleFormat::Pcm16->bitDepth());
        $this->assertSame(24, SampleFormat::Pcm24->bitDepth());
        $this->assertSame(32, SampleFormat::Pcm32->bitDepth());
        $this->assertSame(32, SampleFormat::Float->bitDepth());
        $this->assertSame(64, SampleFormat::Double->bitDepth());
        $this->assertSame(0, SampleFormat::Vorbis->bitDepth());
        $this->assertSame(0, SampleFormat::MpegLayerIII->bitDepth());
    }

    public function testSampleFormatIsInteger(): void
    {
        $this->assertTrue(SampleFormat::Pcm16->isInteger());
        $this->assertTrue(SampleFormat::PcmS8->isInteger());
        $this->assertFalse(SampleFormat::Float->isInteger());
        $this->assertFalse(SampleFormat::Double->isInteger());
        $this->assertFalse(SampleFormat::Vorbis->isInteger());
    }

    public function testSampleFormatIsPcm(): void
    {
        $this->assertTrue(SampleFormat::Pcm16->isPcm());
        $this->assertFalse(SampleFormat::Float->isPcm());
    }
}
