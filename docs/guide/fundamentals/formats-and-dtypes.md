# Formats and DTypes

When writing an audio file, three independent “type” concepts interact:

- **NDArray dtype**: how samples are stored in memory (e.g. `Float32`, `Int16`)
- **SampleFormat**: how samples are encoded in the file (e.g. PCM16, Float, Vorbis)
- **AudioFormat**: the container format (e.g. WAV, FLAC, OGG, MP3)

This guide explains how SoundFile combines them on **read** and **write**.

---

## NDArray dtype (in-memory)

`phpmlkit/ndarray` uses `DType` to represent in-memory types.

Examples:

- `DType::Int16` — common for PCM WAV
- `DType::Float32` — common for floating-point audio
- `DType::Float64` — high precision

---

## SampleFormat (file encoding subtype)

`SampleFormat` maps to libsndfile `SF_FORMAT_*` subtype constants.

Examples:

- `SampleFormat::Pcm16`
- `SampleFormat::Float`
- `SampleFormat::Vorbis`
- `SampleFormat::MpegLayerIII`

Important details:

- Some subtypes have a meaningful `bitDepth()` (PCM), while compressed formats return 0.
- `toDtype()` provides the dtype used when writing that subtype.

---

## AudioFormat (container)

`AudioFormat` maps to libsndfile major format constants (`SF_FORMAT_WAV`, `SF_FORMAT_FLAC`, ...).

It also provides convenience:

- `AudioFormat::fromExtension()` / `fromPath()`
- `extension()`
- `compatibleSampleFormats()` — all valid subtypes ordered by preference
- `preferredSampleFormat(DType)` — best subtype for a given in-memory dtype

---

## What happens on read?

When you read a file, SoundFile:

- Reads the file header to determine format, subtype, channels, and frame count.
- Uses the `$dtype` you specify (defaults to `Float32`) to select the output data type.
- Integer samples are normalized to `[-1.0, 1.0]` for float reads; float data is truncated to the nearest integer for int reads.
- Allocates one C buffer and reads frames in chunks into that buffer.
- Creates an NDArray from that buffer with the requested dtype.

Only four dtypes are supported for reading: `Float32`, `Float64`, `Int16`, and `Int32`. Passing any other dtype throws a `SoundFileException`.

## What happens on write?

- If you omit `AudioFormat`, it is inferred from the file extension.
- If you omit `SampleFormat`, SoundFile selects an encoding by consulting both the
  AudioFormat's compatible subtypes and the data's DType — `Float32` data in a
  WAV container selects `Float`, `Int16` data selects `PCM16`, and so on. When
  the format doesn't support the data's preferred encoding, the format's best
  default is used instead.
- The input NDArray is converted to the dtype implied by the chosen SampleFormat:
  - WAV + Float → `Float32`
  - WAV + PCM16 → `Int16`
  - WAV + Double → `Float64`
- The data is written to the file.

## Format Compatibility

Not every AudioFormat supports every SampleFormat. Use `sf_check_format()` to validate:

```php
use function PhpMlKit\SoundFile\sf_check_format;
use PhpMlKit\SoundFile\Enums\AudioFormat;
use PhpMlKit\SoundFile\Enums\SampleFormat;

// Common valid combinations
sf_check_format(AudioFormat::Wav, SampleFormat::Pcm16);     // true
sf_check_format(AudioFormat::Wav, SampleFormat::Float);     // true
sf_check_format(AudioFormat::Flac, SampleFormat::Pcm16);    // true
sf_check_format(AudioFormat::Flac, SampleFormat::Pcm24);    // true
sf_check_format(AudioFormat::Ogg, SampleFormat::Vorbis);    // true
sf_check_format(AudioFormat::Aiff, SampleFormat::Float);    // true

// Invalid combinations
sf_check_format(AudioFormat::Ogg, SampleFormat::Pcm16);     // false
sf_check_format(AudioFormat::Flac, SampleFormat::Float);    // false
```

An invalid combination throws a `SoundFileException` before any file is created.

## Normalization, Scaling, and Clipping

When you pick a `$dtype` on read, SoundFile converts between the file's native encoding and the requested type:

### Integer → Float (normalized)

Reading an integer file (PCM16, PCM32) as `Float32` or `Float64` normalizes samples to `[-1.0, 1.0]`. For PCM16, `-32768` maps to `-1.0` and `32767` maps to `~1.0`. This is the default behavior — ideal for ML pipelines.

```php
[$audio, $] = sf_read('pcm16_file.wav');
// DType::Float32, values in [-1.0, 1.0]
```

### Float → Integer (truncated)

Reading a float file as `Int16` or `Int32` truncates each sample to the nearest integer. Values outside the target type's range are clipped. No automatic scaling is applied — a float value of `1.0` becomes `1`, not `32767`.

```php
[$audio, $] = sf_read('float_file.wav', dtype: DType::Int16);
// DType::Int16, float values truncated to ints
```

### Write-side clipping

When writing float data to a PCM subtype, values outside the subtype's integer range are clipped. For example, writing `Float32` data to a `Pcm16` file clips values outside `[-32768, 32767]`:

```php
$loud = NDArray::array([[50000.0], [-50000.0]], DType::Float32);
sf_write('loud.wav', $loud, 44100,
    subtype: SampleFormat::Pcm16,
);

// Reading back as Int16 confirms the clipping
[$read, $] = sf_read('loud.wav', dtype: DType::Int16);
// $read will be ~32767 and ~-32768
```

If you need explicit scaling or clipping rules for your application, apply them in NDArray before writing.
