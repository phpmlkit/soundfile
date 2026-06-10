<?php

declare(strict_types=1);

namespace PhpMlKit\SoundFile\Tests;

use PhpMlKit\NDArray\DType;
use PhpMlKit\NDArray\NDArray;
use PhpMlKit\SoundFile\Enums\AudioFormat;
use PhpMlKit\SoundFile\Enums\FileMode;
use PhpMlKit\SoundFile\Enums\SampleFormat;
use PhpMlKit\SoundFile\Exceptions\SoundFileException;
use PhpMlKit\SoundFile\SoundFile;
use PHPUnit\Framework\Attributes\CoversNothing;
use PHPUnit\Framework\TestCase;

/**
 * @internal
 */
#[CoversNothing]
final class SoundFileTest extends TestCase
{
    private string $monoWav;
    private string $stereoWav;

    protected function setUp(): void
    {
        $this->monoWav = Fixtures::monoFloatWav();
        $this->stereoWav = Fixtures::stereoFloatWav();
    }

    public function testOpenReadReturnsCorrectMetadata(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $this->assertSame(800, $sf->frames());
        $this->assertSame(1, $sf->channels());
        $this->assertSame(8000, $sf->sampleRate());
        $this->assertSame(AudioFormat::Wav, $sf->info()->format);
        $this->assertSame(SampleFormat::Float, $sf->info()->sampleFormat);
        $this->assertTrue($sf->info()->seekable);

        $sf->close();
    }

    public function testOpenMissingFileThrows(): void
    {
        $this->expectException(SoundFileException::class);

        new SoundFile(sys_get_temp_dir().'/nonexistent_'.uniqid().'.wav', FileMode::Read);
    }

    public function testReadReturnsCorrectShape(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);
        $chunk = $sf->read(50);

        $this->assertSame([50], $chunk->shape());
        $this->assertSame(DType::Float32, $chunk->dtype());

        $sf->close();
    }

    public function testReadAdvancesTell(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $this->assertSame(0, $sf->tell());
        $sf->read(50);
        $this->assertSame(50, $sf->tell());

        $sf->close();
    }

    public function testReadAllRemaining(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $data = $sf->read(null);
        $this->assertSame([800], $data->shape());

        $sf->close();
    }

    public function testReadPastEofReturnsRemaining(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $sf->seek(750);
        $data = $sf->read(100);
        $this->assertSame(50, $data->shape()[0]);

        $sf->close();
    }

    public function testReadRejectsUnsupportedDtype(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $this->expectException(SoundFileException::class);
        $this->expectExceptionMessage('Unsupported read dtype');

        $sf->read(10, dtype: DType::Int8);
        $sf->close();
    }

    public function testReadAlways2dFalseIsDefault(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $chunk = $sf->read(10);
        $this->assertSame(1, $chunk->ndim());
        $this->assertSame([10], $chunk->shape());

        $chunk = $sf->read(10, always2d: true);
        $this->assertSame(2, $chunk->ndim());
        $this->assertSame([10, 1], $chunk->shape());

        $sf->close();
    }

    public function testReadAlways2dFalseReturns1DForMono(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $chunk = $sf->read(10, always2d: false);
        $this->assertSame(1, $chunk->ndim());
        $this->assertSame([10], $chunk->shape());

        $sf->close();
    }

    public function testReadAlways2dFalseHasNoEffectOnStereo(): void
    {
        $sf = new SoundFile($this->stereoWav, FileMode::Read);

        $chunk = $sf->read(10, always2d: false);
        $this->assertSame(2, $chunk->ndim());
        $this->assertSame([10, 2], $chunk->shape());

        $sf->close();
    }

    public function testReadExplicitFloat32ReturnsFloat32(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $chunk = $sf->read(10, dtype: DType::Float32);
        $this->assertSame(DType::Float32, $chunk->dtype());

        $sf->close();
    }

    public function testReadExplicitFloat64ReturnsFloat64(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $chunk = $sf->read(10, dtype: DType::Float64);
        $this->assertSame(DType::Float64, $chunk->dtype());

        $sf->close();
    }

    public function testReadExplicitInt16ReturnsInt16(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $chunk = $sf->read(10, dtype: DType::Int16);
        $this->assertSame(DType::Int16, $chunk->dtype());

        $sf->close();
    }

    public function testReadPcm16AsFloat32IsNormalized(): void
    {
        $sf = new SoundFile(Fixtures::monoPcm16Wav(), FileMode::Read);

        $chunk = $sf->read(null, dtype: DType::Float32);
        $this->assertSame(DType::Float32, $chunk->dtype());

        $vals = $chunk->toArray();
        foreach ($vals as $row) {
            $v = \is_array($row) ? $row[0] : $row;
            $this->assertGreaterThanOrEqual(-1.0, (float) $v);
            $this->assertLessThanOrEqual(1.0, (float) $v);
        }

        $sf->close();
    }

    public function testSeekToAbsolutePosition(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $sf->seek(200);
        $this->assertSame(200, $sf->tell());

        $sf->close();
    }

    public function testSeekOnClosedFileThrows(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);
        $sf->close();

        $this->expectException(SoundFileException::class);
        $sf->seek(0);
    }

    public function testSeekFromCurrentPosition(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $sf->read(100);
        $sf->seek(50, \SEEK_CUR);
        $this->assertSame(150, $sf->tell());

        $sf->close();
    }

    public function testTellStartsAtZero(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);
        $this->assertSame(0, $sf->tell());
        $sf->close();
    }

    public function testEofReturnsFalseAtStart(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);
        $this->assertFalse($sf->eof());
        $sf->close();
    }

    public function testEofReturnsTrueAfterReadingAll(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);
        $sf->read(null);
        $this->assertTrue($sf->eof());
        $sf->close();
    }

    public function testBlocksYieldsCorrectTotalFrames(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $total = 0;
        foreach ($sf->blocks(100) as $block) {
            $total += $block->shape(0);
        }

        $this->assertSame(800, $total);
        $sf->close();
    }

    public function testBlocksDoesNotExceedBlocksize(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $first = true;
        foreach ($sf->blocks(100) as $block) {
            if ($first) {
                $first = false;

                continue;
            }
            $this->assertLessThanOrEqual(100, $block->shape(0));
        }

        $sf->close();
    }

    public function testBlocksFromSeekPosition(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);
        $sf->seek(400);

        $total = 0;
        foreach ($sf->blocks(100) as $block) {
            $total += $block->shape(0);
        }

        $this->assertSame(400, $total);
        $sf->close();
    }

    public function testWriteThenReadRoundTrip(): void
    {
        $tmp = sys_get_temp_dir().'/sndfile_write_test_'.uniqid().'.wav';

        $sf = new SoundFile(
            $tmp,
            FileMode::Write,
            sampleRate: 8000,
            channels: 1,
            format: AudioFormat::Wav,
            subtype: SampleFormat::Float,
        );
        $data = NDArray::array([[0.1], [0.2], [-0.3], [0.4]], DType::Float32);
        $sf->write($data);
        $sf->close();

        $sf2 = new SoundFile($tmp, FileMode::Read);
        $read = $sf2->read(null);
        $sf2->close();

        $this->assertSame([4], $read->shape());
        $this->assertSame(DType::Float32, $read->dtype());

        unlink($tmp);
    }

    /**
     * Regression: creating a SoundFile without a subtype must auto-select
     * Float for WAV, preserving sample values on write.
     */
    public function testWriteFloat32DefaultSubtypePreservesValues(): void
    {
        $tmp = sys_get_temp_dir().'/sndfile_reg_'.uniqid().'.wav';

        $sf = new SoundFile(
            $tmp,
            FileMode::Write,
            sampleRate: 8000,
            channels: 1,
            format: AudioFormat::Wav,
        );
        $data = NDArray::array([[0.75], [-0.5], [0.25], [-0.9]], DType::Float32);
        $sf->write($data);
        $sf->close();

        $sf2 = new SoundFile($tmp, FileMode::Read);
        $read = $sf2->read(null);
        $sf2->close();

        $this->assertSame(DType::Float32, $read->dtype());
        $this->assertSame([4], $read->shape());

        $srcArr = $data->toArray();
        $backArr = $read->toArray();
        for ($i = 0; $i < 4; ++$i) {
            $this->assertEqualsWithDelta(
                (float) $srcArr[$i][0],
                (float) $backArr[$i],
                0.001,
            );
        }

        unlink($tmp);
    }

    public function testWriteChannelMismatchThrows(): void
    {
        $tmp = sys_get_temp_dir().'/sndfile_ch_err_'.uniqid().'.wav';

        $sf = new SoundFile(
            $tmp,
            FileMode::Write,
            sampleRate: 8000,
            channels: 1,
            format: AudioFormat::Wav,
            subtype: SampleFormat::Float,
        );

        $this->expectException(SoundFileException::class);

        $sf->write(NDArray::array([[0.1, 0.2], [0.3, 0.4]], DType::Float32));
        $sf->close();

        unlink($tmp);
    }

    public function testWriteOnClosedFileThrows(): void
    {
        $tmp = sys_get_temp_dir().'/sndfile_cl_err_'.uniqid().'.wav';

        $sf = new SoundFile(
            $tmp,
            FileMode::Write,
            sampleRate: 8000,
            channels: 1,
            format: AudioFormat::Wav,
            subtype: SampleFormat::Float,
        );
        $sf->close();

        $this->expectException(SoundFileException::class);
        $sf->write(NDArray::array([[0.1]], DType::Float32));

        unlink($tmp);
    }

    public function testWriteInvalidFormatComboThrowsAtConstruction(): void
    {
        $tmp = sys_get_temp_dir().'/sndfile_invalid_'.uniqid().'.ogg';

        $this->expectException(SoundFileException::class);

        new SoundFile(
            $tmp,
            FileMode::Write,
            sampleRate: 8000,
            channels: 1,
            format: AudioFormat::Ogg,
            subtype: SampleFormat::Pcm16,
        );
    }

    public function testReadOnClosedFileThrows(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);
        $sf->close();

        $this->expectException(SoundFileException::class);
        $sf->read(10);
    }

    public function testWriteAdvancesTell(): void
    {
        $tmp = sys_get_temp_dir().'/sndfile_tell_'.uniqid().'.wav';

        $sf = new SoundFile(
            $tmp,
            FileMode::Write,
            sampleRate: 8000,
            channels: 1,
            format: AudioFormat::Wav,
            subtype: SampleFormat::Float,
        );

        $this->assertSame(0, $sf->tell());
        $sf->write(NDArray::array([[0.1], [0.2]], DType::Float32));
        $this->assertSame(2, $sf->tell());

        $sf->close();
        unlink($tmp);
    }

    public function testMultipleOpenHandlesWorkSimultaneously(): void
    {
        $sf1 = new SoundFile($this->monoWav, FileMode::Read);
        $sf2 = new SoundFile($this->stereoWav, FileMode::Read);

        $c1 = $sf1->read(10);
        $c2 = $sf2->read(10);

        $this->assertSame([10], $c1->shape());
        $this->assertSame([10, 2], $c2->shape());

        $sf1->close();
        $sf2->close();
    }

    public function testMetadataRoundTrip(): void
    {
        $sf = new SoundFile(Fixtures::metadataWav(), FileMode::Read);

        $this->assertSame('Test Title', $sf->title());
        $this->assertSame('Test Artist', $sf->artist());

        $sf->close();
    }

    public function testMetadataNullWhenUnset(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $this->assertNull($sf->title());
        $this->assertNull($sf->artist());

        $sf->close();
    }

    public function testWriteThenSetMetadata(): void
    {
        $tmp = sys_get_temp_dir().'/sndfile_meta_'.uniqid().'.wav';

        $sf = new SoundFile(
            $tmp,
            FileMode::Write,
            sampleRate: 8000,
            channels: 1,
            format: AudioFormat::Wav,
            subtype: SampleFormat::Float,
        );
        $sf->setTitle('My Title');
        $sf->setArtist('My Artist');
        $sf->write(NDArray::array([[0.5]], DType::Float32));
        $sf->close();

        $sf2 = new SoundFile($tmp, FileMode::Read);
        $this->assertSame('My Title', $sf2->title());
        $this->assertSame('My Artist', $sf2->artist());
        $sf2->close();

        unlink($tmp);
    }

    public function testWriteOnReadOnlyThrows(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);

        $this->expectException(SoundFileException::class);
        $this->expectExceptionMessage('Cannot write to a read-only file');

        $sf->write(NDArray::array([[0.1]], DType::Float32));
        $sf->close();
    }

    public function testReadOnWriteOnlyThrows(): void
    {
        $tmp = sys_get_temp_dir().'/sndfile_guard_'.uniqid().'.wav';
        $sf = new SoundFile($tmp, FileMode::Write, sampleRate: 8000);

        $this->expectException(SoundFileException::class);
        $this->expectExceptionMessage('Cannot read from a write-only file');

        $sf->read(10);
        $sf->close();
        unlink($tmp);
    }

    public function testMultipleWritesAppendAndTrackPosition(): void
    {
        $tmp = sys_get_temp_dir().'/sndfile_multi_'.uniqid().'.wav';
        $sf = new SoundFile($tmp, FileMode::Write, sampleRate: 8000);

        $sf->write(NDArray::array([[0.1], [0.2]], DType::Float32));
        $this->assertSame(2, $sf->tell());

        $sf->write(NDArray::array([[0.3]], DType::Float32));
        $this->assertSame(3, $sf->tell());

        $sf->write(NDArray::array([[0.4], [0.5], [0.6]], DType::Float32));
        $this->assertSame(6, $sf->tell());
        $this->assertSame(6, $sf->frames());

        $sf->close();

        // Verify by reading back
        $sf2 = new SoundFile($tmp, FileMode::Read);
        $data = $sf2->read(null);
        $this->assertSame([6], $data->shape());
        $sf2->close();

        unlink($tmp);
    }

    public function testReadWriteModeSupportsBoth(): void
    {
        $tmp = sys_get_temp_dir().'/sndfile_rw_'.uniqid().'.wav';

        // Create file first
        $w = new SoundFile($tmp, FileMode::Write, sampleRate: 8000);
        $w->write(NDArray::array([[0.1], [0.2], [0.3]], DType::Float32));
        $w->close();

        // Open in ReadWrite and write more
        $rw = new SoundFile($tmp, FileMode::ReadWrite, sampleRate: 8000);
        $this->assertSame(3, $rw->frames());

        $rw->seek(0, \SEEK_END);
        $rw->write(NDArray::array([[0.4], [0.5]], DType::Float32));
        $this->assertSame(5, $rw->frames());

        // Seek back and read everything
        $rw->seek(0);
        $data = $rw->read(null);
        $this->assertSame([5], $data->shape());
        $rw->close();

        unlink($tmp);
    }

    public function testModeAccessor(): void
    {
        $sf = new SoundFile($this->monoWav, FileMode::Read);
        $this->assertSame(FileMode::Read, $sf->mode());
        $sf->close();

        $tmp = sys_get_temp_dir().'/sndfile_md_'.uniqid().'.wav';
        $w = new SoundFile($tmp, FileMode::Write, sampleRate: 8000);
        $this->assertSame(FileMode::Write, $w->mode());
        $w->close();
        unlink($tmp);
    }

    public function testEofFalseForWriteMode(): void
    {
        $tmp = sys_get_temp_dir().'/sndfile_eof_'.uniqid().'.wav';
        $sf = new SoundFile($tmp, FileMode::Write, sampleRate: 8000);

        $this->assertFalse($sf->eof());
        $sf->write(NDArray::array([[0.1]], DType::Float32));
        $this->assertFalse($sf->eof());

        $sf->close();
        unlink($tmp);
    }
}
