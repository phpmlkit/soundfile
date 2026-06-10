<?php

declare(strict_types=1);

namespace PhpMlKit\SoundFile\Tests;

use PhpMlKit\NDArray\DType;
use PhpMlKit\NDArray\NDArray;
use PhpMlKit\SoundFile\Enums\AudioFormat;
use PhpMlKit\SoundFile\Enums\FileMode;
use PhpMlKit\SoundFile\Enums\ResampleQuality;
use PhpMlKit\SoundFile\Enums\SampleFormat;
use PhpMlKit\SoundFile\Exceptions\SoundFileException;
use PhpMlKit\SoundFile\SoundFile;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

use function PhpMlKit\SoundFile\sf_check_format;
use function PhpMlKit\SoundFile\sf_info;
use function PhpMlKit\SoundFile\sf_read;
use function PhpMlKit\SoundFile\sf_resample;
use function PhpMlKit\SoundFile\sf_write;

/**
 * @internal
 */
#[CoversNothing]
final class FunctionsTest extends TestCase
{
    private string $monoFloatWav;
    private string $stereoWav;
    private string $pcm16Wav;

    protected function setUp(): void
    {
        $this->monoFloatWav = Fixtures::monoFloatWav();
        $this->stereoWav = Fixtures::stereoFloatWav();
        $this->pcm16Wav = Fixtures::monoPcm16Wav();
    }

    // ===================================================================
    // sf_read
    // ===================================================================

    public function testReadFullFile(): void
    {
        [$data, $info] = sf_read($this->monoFloatWav);

        $this->assertSame([800], $data->shape());
        $this->assertSame(8000, $info->sampleRate);
        $this->assertSame(DType::Float32, $data->dtype());
    }

    public function testReadMonoAlways2dFalse(): void
    {
        [$data, $_] = sf_read($this->monoFloatWav);

        $this->assertSame(1, $data->ndim());
        $this->assertSame([800], $data->shape());
    }

    public function testReadMonoAlways2dTrue(): void
    {
        [$data, $_] = sf_read($this->monoFloatWav, always2d: true);

        $this->assertSame(2, $data->ndim());
        $this->assertSame([800, 1], $data->shape());
    }

    public function testReadStereo(): void
    {
        [$data, $_] = sf_read($this->stereoWav);

        $this->assertSame(2, $data->ndim());
        $this->assertSame([800, 2], $data->shape());
    }

    public function testReadStartStop(): void
    {
        [$data, $_] = sf_read($this->monoFloatWav, start: 100, stop: 300);

        $this->assertSame([200], $data->shape());
    }

    public function testReadEmptyRange(): void
    {
        [$data, $_] = sf_read($this->monoFloatWav, start: 5, stop: 5);

        $this->assertSame(0, $data->shape()[0]);
    }

    public function testReadStopBeyondFile(): void
    {
        [$data, $_] = sf_read($this->monoFloatWav, start: 0, stop: 99999);

        $this->assertSame([800], $data->shape());
    }

    public function testReadStartBeyondFile(): void
    {
        [$data, $_] = sf_read($this->monoFloatWav, start: 99999);

        $this->assertSame(0, $data->shape()[0]);
    }

    public function testReadPcm16ReturnsFloat32ByDefault(): void
    {
        [$data, $_] = sf_read($this->pcm16Wav);

        $this->assertSame(DType::Float32, $data->dtype());
    }

    public function testReadPcm16AsInt16ReturnsRawIntegers(): void
    {
        [$data, $_] = sf_read($this->pcm16Wav, dtype: DType::Int16);

        $this->assertSame(DType::Int16, $data->dtype());
    }

    public function testReadPcm16AsFloat32ProducesNormalizedValues(): void
    {
        [$data, $_] = sf_read($this->pcm16Wav, dtype: DType::Float32);

        $this->assertSame(DType::Float32, $data->dtype());
        $vals = $data->toArray();
        foreach ($vals as $v) {
            $this->assertGreaterThanOrEqual(-1.0, (float) $v);
            $this->assertLessThanOrEqual(1.0, (float) $v);
        }
    }

    public function testReadPcm16AsFloat64ProducesNormalizedDoubleValues(): void
    {
        [$data, $_] = sf_read($this->pcm16Wav, dtype: DType::Float64);

        $this->assertSame(DType::Float64, $data->dtype());
    }

    public function testReadPcm16AsInt32ProducesInt32Values(): void
    {
        [$data, $_] = sf_read($this->pcm16Wav, dtype: DType::Int32);

        $this->assertSame(DType::Int32, $data->dtype());
    }

    public function testReadFloatWavAsInt16ProducesIntValues(): void
    {
        [$data, $_] = sf_read($this->monoFloatWav, dtype: DType::Int16);

        $this->assertSame(DType::Int16, $data->dtype());
    }

    public function testReadFloatWavAsFloat64ProducesDoubleValues(): void
    {
        [$data, $_] = sf_read($this->monoFloatWav, dtype: DType::Float64);

        $this->assertSame(DType::Float64, $data->dtype());
    }

    public function testReadRejectsUnsupportedDtype(): void
    {
        $this->expectException(SoundFileException::class);
        $this->expectExceptionMessage('Unsupported read dtype');

        sf_read($this->monoFloatWav, dtype: DType::Int8);
    }

    // ===================================================================
    // sf_write
    // ===================================================================

    public function testWriteReadRoundTripFloat(): void
    {
        $src = NDArray::array([[0.5], [-0.5], [0.25], [-0.25]], DType::Float32);
        $tmp = sys_get_temp_dir().'/sw_float_'.uniqid().'.wav';

        sf_write($tmp, $src, 8000, AudioFormat::Wav, SampleFormat::Float);
        [$back, $info] = sf_read($tmp, always2d: true);

        $this->assertSame(8000, $info->sampleRate);
        $srcArr = $src->toArray();
        $backArr = $back->toArray();

        for ($i = 0; $i < 4; ++$i) {
            $this->assertEqualsWithDelta((float) $srcArr[$i][0], (float) $backArr[$i][0], 0.001);
        }

        unlink($tmp);
    }

    public function testWriteReadRoundTripPcm16(): void
    {
        $src = NDArray::array([[100], [200], [-300], [400]], DType::Int16);
        $tmp = sys_get_temp_dir().'/sw_pcm16_'.uniqid().'.wav';

        sf_write($tmp, $src, 8000, AudioFormat::Wav, SampleFormat::Pcm16);
        [$back, $_] = sf_read($tmp, dtype: DType::Int16);

        $this->assertSame(DType::Int16, $back->dtype());
        $this->assertSame([4], $back->shape());

        $arr = $back->toArray();
        $this->assertSame(100, (int) $arr[0]);
        $this->assertSame(200, (int) $arr[1]);

        unlink($tmp);
    }

    public function testWrite1DInput(): void
    {
        $src = NDArray::array([0.1, 0.2, 0.3], DType::Float32);
        $tmp = sys_get_temp_dir().'/sw_1d_'.uniqid().'.wav';

        sf_write($tmp, $src, 8000);
        [$back, $_] = sf_read($tmp);

        $this->assertSame([3], $back->shape());

        unlink($tmp);
    }

    public function testWriteFloat32ToPcm16ConvertsDtype(): void
    {
        $src = NDArray::array([[0.5], [-0.5], [0.25]], DType::Float32);
        $tmp = sys_get_temp_dir().'/sw_f32pcm16_'.uniqid().'.wav';

        sf_write($tmp, $src, 8000, AudioFormat::Wav, SampleFormat::Pcm16);
        [$back, $_] = sf_read($tmp, dtype: DType::Int16);

        $this->assertSame(DType::Int16, $back->dtype());

        unlink($tmp);
    }

    public function testWriteInt16ToOggVorbis(): void
    {
        $src = NDArray::array([[100], [200], [300]], DType::Int16);
        $tmp = sys_get_temp_dir().'/sw_ogg_'.uniqid().'.ogg';

        sf_write($tmp, $src, 8000, AudioFormat::Ogg, SampleFormat::Vorbis);
        [$back, $_] = sf_read($tmp);

        $this->assertGreaterThan(0, $back->shape()[0]);

        unlink($tmp);
    }

    public function testWriteInvalidFormatComboThrows(): void
    {
        $src = NDArray::array([[0.1], [0.2]], DType::Float32);
        $tmp = sys_get_temp_dir().'/sw_inv_'.uniqid().'.ogg';

        $this->expectException(SoundFileException::class);

        sf_write($tmp, $src, 8000, AudioFormat::Ogg, SampleFormat::Pcm16);
    }

    public function testWriteFormatFromExtension(): void
    {
        $src = NDArray::array([[0.5], [-0.5]], DType::Float32);
        $tmp = sys_get_temp_dir().'/sw_ext_'.uniqid().'.flac';

        sf_write($tmp, $src, 8000);
        [$back, $_] = sf_read($tmp);

        $this->assertGreaterThan(0, $back->shape()[0]);

        unlink($tmp);
    }

    public function testWriteDefaultSubtypeFromFormat(): void
    {
        $src = NDArray::array([[0.5], [-0.5]], DType::Float32);
        $tmp = sys_get_temp_dir().'/sw_def_'.uniqid().'.wav';

        // WAV now defaults to Float for float-native data (auto-preferred)
        sf_write($tmp, $src, 8000);
        $info = sf_info($tmp);

        $this->assertSame(SampleFormat::Float, $info->sampleFormat);

        unlink($tmp);
    }

    /**
     * Regression: writing Float32 data without an explicit subtype must
     * preserve sample values — not truncate them to zero via Int16 cast.
     */
    public function testWriteFloat32DefaultSubtypePreservesValues(): void
    {
        $src = NDArray::array([[0.75], [-0.5], [0.25], [-0.9]], DType::Float32);
        $tmp = sys_get_temp_dir().'/sw_rg_'.uniqid().'.wav';

        sf_write($tmp, $src, 8000);
        [$back, $info] = sf_read($tmp, always2d: true);

        $this->assertSame(SampleFormat::Float, $info->sampleFormat);
        $this->assertSame(DType::Float32, $back->dtype());

        $srcArr = $src->toArray();
        $backArr = $back->toArray();

        for ($i = 0; $i < 4; ++$i) {
            $this->assertEqualsWithDelta(
                (float) $srcArr[$i][0],
                (float) $backArr[$i][0],
                0.001,
            );
        }

        unlink($tmp);
    }

    /**
     * FLAC does not support Float, so it stays at Pcm16.
     */
    public function testWriteFloat32FlacDefaultsToPcm16(): void
    {
        $src = NDArray::array([[0.5], [-0.5]], DType::Float32);
        $tmp = sys_get_temp_dir().'/sw_flacdef_'.uniqid().'.flac';

        sf_write($tmp, $src, 8000, AudioFormat::Flac);
        $info = sf_info($tmp);

        $this->assertSame(SampleFormat::Pcm16, $info->sampleFormat);

        unlink($tmp);
    }

    public function testWriteFloat64ToDouble(): void
    {
        $src = NDArray::array([[0.1], [0.2]], DType::Float64);
        $tmp = sys_get_temp_dir().'/sw_dbl_'.uniqid().'.wav';

        sf_write($tmp, $src, 8000, AudioFormat::Wav, SampleFormat::Double);
        [$back, $_] = sf_read($tmp, dtype: DType::Float64);

        $this->assertSame(DType::Float64, $back->dtype());

        unlink($tmp);
    }

    // ===================================================================
    // sf_info
    // ===================================================================

    public function testInfoMatchesSoundFile(): void
    {
        $info = sf_info($this->monoFloatWav);
        $sf = new SoundFile($this->monoFloatWav, FileMode::Read);
        $sfInfo = $sf->info();
        $sf->close();

        $this->assertSame($info->frames, $sfInfo->frames);
        $this->assertSame($info->channels, $sfInfo->channels);
    }

    // ===================================================================
    // sf_check_format
    // ===================================================================

    public function testCheckWavPcm16(): void
    {
        $this->assertTrue(sf_check_format(AudioFormat::Wav, SampleFormat::Pcm16));
    }

    public function testCheckWavFloat(): void
    {
        $this->assertTrue(sf_check_format(AudioFormat::Wav, SampleFormat::Float));
    }

    public function testCheckWavDouble(): void
    {
        $this->assertTrue(sf_check_format(AudioFormat::Wav, SampleFormat::Double));
    }

    public function testCheckFlacPcm16(): void
    {
        $this->assertTrue(sf_check_format(AudioFormat::Flac, SampleFormat::Pcm16));
    }

    public function testCheckFlacFloat(): void
    {
        $this->assertFalse(sf_check_format(AudioFormat::Flac, SampleFormat::Float));
    }

    public function testCheckOggVorbis(): void
    {
        $this->assertTrue(sf_check_format(AudioFormat::Ogg, SampleFormat::Vorbis));
    }

    public function testCheckOggPcm16(): void
    {
        $this->assertFalse(sf_check_format(AudioFormat::Ogg, SampleFormat::Pcm16));
    }

    public function testCheckMpegMpegLayer3(): void
    {
        $this->assertTrue(sf_check_format(AudioFormat::Mpeg, SampleFormat::MpegLayerIII));
    }

    public function testCheckAiffFloat(): void
    {
        $this->assertTrue(sf_check_format(AudioFormat::Aiff, SampleFormat::Float));
    }

    // ===================================================================
    // sf_resample
    // ===================================================================

    public function testResampleIdentity(): void
    {
        $data = NDArray::array([[0.1], [0.2], [0.3], [0.4]], DType::Float32);
        $result = sf_resample($data, 8000, 8000);

        $this->assertSame([4, 1], $result->shape());
    }

    public function testResampleDoublesFrames(): void
    {
        $data = NDArray::array([[0.1], [0.2], [0.3], [0.4]], DType::Float32);
        $result = sf_resample($data, 8000, 16000);

        $this->assertEquals([8, 1], $result->shape());
    }

    public function testResampleHalvesFrames(): void
    {
        $data = NDArray::array([[0.1], [0.2], [0.3], [0.4]], DType::Float32);
        $result = sf_resample($data, 16000, 8000);

        $this->assertEquals([2, 1], $result->shape());
    }

    public function testResampleChunkedVsSimpleMatch(): void
    {
        $data = NDArray::arange(0, 200, 1, DType::Float32)
            ->multiply(2 * \M_PI * 440 / 8000)
            ->sin()
            ->insertaxis(1);

        $chunked = sf_resample($data, 8000, 16000, chunkSize: 50);
        $simple = sf_resample($data, 8000, 16000, chunkSize: null);

        $this->assertSame($chunked->shape(), $simple->shape());
    }

    public function testResampleOutputIsFloat32(): void
    {
        $data = NDArray::array([[0.1], [0.2], [0.3], [0.4]], DType::Float32);
        $result = sf_resample($data, 8000, 16000);

        $this->assertSame(DType::Float32, $result->dtype());
    }

    public function testResampleInt16Input(): void
    {
        $data = NDArray::array([[100], [200], [300], [400]], DType::Int16);
        $result = sf_resample($data, 8000, 16000);

        $this->assertSame(DType::Float32, $result->dtype());
        $this->assertGreaterThan(0, $result->shape()[0]);
    }

    public function testResampleStereo(): void
    {
        $data = NDArray::array([[0.1, 0.2], [0.3, 0.4], [0.5, 0.6]], DType::Float32);
        $result = sf_resample($data, 8000, 16000);

        $this->assertSame(2, $result->shape(1));
    }

    public function testResampleAllQualityLevels(): void
    {
        $data = NDArray::array([[0.1], [0.2], [0.3], [0.4]], DType::Float32);

        foreach (ResampleQuality::cases() as $q) {
            $result = sf_resample($data, 8000, 16000, quality: $q);
            $this->assertGreaterThan(0, $result->shape(0), "Quality {$q->name} failed");
        }
    }

    // ===================================================================
    // Cross-cutting: dtype/format/subtype matrix
    // ===================================================================

    public function testWriteFloat32AsFloat(): void
    {
        $src = NDArray::array([[0.5], [-0.5]], DType::Float32);
        $tmp = sys_get_temp_dir().'/cc_f32f_'.uniqid().'.wav';

        sf_write($tmp, $src, 8000, AudioFormat::Wav, SampleFormat::Float);
        [$back, $_] = sf_read($tmp);

        $this->assertSame(DType::Float32, $back->dtype());
        $this->assertSame([2], $back->shape());

        unlink($tmp);
    }

    public function testWriteInt32AsPcm32(): void
    {
        $src = NDArray::array([[1000], [-2000]], DType::Int32);
        $tmp = sys_get_temp_dir().'/cc_i32_'.uniqid().'.wav';

        sf_write($tmp, $src, 8000, AudioFormat::Wav, SampleFormat::Pcm32);
        [$back, $_] = sf_read($tmp, dtype: DType::Int32);

        $this->assertSame(DType::Int32, $back->dtype());

        unlink($tmp);
    }

    public function testWriteFloat32ToFlacPcm16(): void
    {
        $src = NDArray::array([[0.5], [-0.5]], DType::Float32);
        $tmp = sys_get_temp_dir().'/cc_flac_'.uniqid().'.flac';

        sf_write($tmp, $src, 8000, AudioFormat::Flac, SampleFormat::Pcm16);
        [$back, $_] = sf_read($tmp, dtype: DType::Int16);

        $this->assertSame(DType::Int16, $back->dtype());

        unlink($tmp);
    }
}
