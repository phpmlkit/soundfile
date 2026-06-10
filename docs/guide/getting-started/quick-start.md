# Quick Start

Get up and running with SoundFile in 5 minutes. This guide covers the most common workflows:

- Reading an Audio File
- Writing an Audio File
- Resampling
- Streaming Large Files

## Reading an Audio File

The simplest way to load a file is `sf_read()`:

```php
use function PhpMlKit\SoundFile\sf_read;

[$audio, $info] = sf_read('song.wav');

echo $info->sampleRate;               // 44100
print_r($audio->shape());     // [441000]  (mono, 1D by default)
echo $audio->dtype()->name;   // Float32 (default)
```

Audio is returned as `Float32` by default — integer files are normalized to `[-1.0, 1.0]`. To read raw integer samples, pass an explicit dtype:

```php
// Read as raw 16-bit integers
[$audio, $info] = sf_read('song.wav', dtype: DType::Int16);
```

For a mono file, the default output is a 1D array. Set `always2d` to `true` for the canonical `[frames, 1]` shape:

```php
[$audio, $info] = sf_read('song.wav', always2d: true);
print_r($audio->shape());     // [441000, 1]
```

Stereo files always produce 2D arrays regardless of the flag:

```php
[$stereo, $info] = sf_read('stereo.wav');
print_r($stereo->shape());    // [441000, 2]
```

## Writing an Audio File

`sf_write()` takes an NDArray, a sample rate, and optionally a format and subtype:

```php
use function PhpMlKit\SoundFile\sf_write;

sf_write('output.wav', $audio, sampleRate: 44100);
```

The format is inferred from the file extension. The subtype defaults to the format's preferred encoding. You can be
explicit:

```php
use PhpMlKit\SoundFile\Enums\AudioFormat;
use PhpMlKit\SoundFile\Enums\SampleFormat;

sf_write(
    'output.flac', $audio, 44100,
    format: AudioFormat::Flac,
    subtype: SampleFormat::Pcm24,
);
```

The NDArray's dtype is automatically converted to match the target subtype. A Float32 input written as Pcm16 is
converted to Int16 before writing — no manual `astype()` needed.

## Resampling

`sf_resample()` converts between sample rates:

```php
use function PhpMlKit\SoundFile\sf_resample;

// 44.1kHz → 22.05kHz (chunked progressive, safe for large files)
$resampled = sf_resample($audio, inputRate: 44100, outputRate: 22050);

// One-shot simple mode — best for small signals
$resampled = sf_resample($audio, 44100, 16000, chunkSize: null);
```

The output is always `Float32`, regardless of the input dtype.

## Probing Metadata Without Loading Data

```php
use function PhpMlKit\SoundFile\sf_info;

$info = sf_info('song.wav');
echo "{$info->frames} frames × {$info->channels} channels";
echo "Duration: {$info->duration()}s";
echo "Format: {$info->format->name} / {$info->sampleFormat->name}";
```

::: tip
`sf_info()` opens the file, reads only the header, and closes immediately. It does not load audio data — use it when
you just need dimensions or format information.
:::

## Streaming Large Files

For large files, use the `SoundFile` class to iterate in blocks without loading the entire file into memory:

```php
use PhpMlKit\SoundFile\SoundFile;
use PhpMlKit\SoundFile\Enums\FileMode;

$sf = new SoundFile('large-file.wav', FileMode::Read);

foreach ($sf->blocks(4096) as $block) {
    // $block is an NDArray of shape [4096, channels] (final block may be smaller)
    processBlock($block);
}

$sf->close();
```

## Next Steps

- [Reading and Writing](/guide/fundamentals/reading-and-writing) — partial reads, dtype interactions, format validation
- [Streaming with SoundFile](/guide/fundamentals/streaming-with-soundfile) — seeking, blocking, writing with handles
- [Resampling](/guide/fundamentals/resampling) — quality levels, chunked vs simple, multi-channel
- [Formats and DTypes](/guide/fundamentals/formats-and-dtypes) — how format, subtype, and dtype interact
