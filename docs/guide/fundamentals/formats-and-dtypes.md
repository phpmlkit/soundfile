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
- `toDtype()` provides the dtype used when reading/writing that subtype.

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

When you read a file, SndFle:

- Reads the file header to determine format + subtype.
- Chooses the dtype based on the Sample Format.
- Allocates one C buffer and reads frames in chunks into that buffer.
- Creates an NDArray from that buffer.

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

## Bit Depth and Clipping

PCM formats store integer samples (e.g. `Int16`) and floating formats store `Float32`/`Float64`. When you write data without an explicit subtype, SoundFile selects an encoding by consulting both the format's compatible subtypes and the data's DType — float data in a float-capable container stays in a float encoding, avoiding unintended clipping.

If you **explicitly** write float data to a PCM subtype, values are scaled to integers. Values outside the integer range are clipped by libsndfile:

```php
// Explicit PCM16 — float values are scaled to Int16 range
$loud = NDArray::array([[50000.0], [-50000.0]], DType::Float32);
sf_write('loud.wav', $loud, 44100,
    subtype: SampleFormat::Pcm16,
);

[$read, $] = sf_read('loud.wav');
$arr = $read->toArray();
// $arr[0] will be ~32767, $arr[1] will be ~-32768
```

SoundFile does not impose an extra normalization layer; you are responsible for choosing a representation that matches your pipeline. Apply scaling/clipping in NDArray before writing when needed.