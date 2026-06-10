# Global Functions

All global functions live in the `PhpMlKit\SoundFile` namespace and are importable with `use function`.

## Importing

```php
use function PhpMlKit\SoundFile\sf_read;
use function PhpMlKit\SoundFile\sf_write;
use function PhpMlKit\SoundFile\{sf_read, sf_write, sf_resample};
```

---

## sf_read()

Read an audio file into a single NDArray. Opens the file, reads data in chunks via a single pre-allocated buffer, and
closes automatically.

```php
function sf_read(
    string $file,
    ?int $start = null,
    ?int $stop = null,
    DType $dtype = DType::Float32,
    bool $always2d = false,
    int $blocksize = 4096,
): array // [NDArray, SfInfo]
```

**Parameters:**

| Parameter    | Type     | Description                                                                       |
|--------------|----------|-----------------------------------------------------------------------------------|
| `$file`      | `string` | Path to the audio file                                                            |
| `$start`     | `?int`   | First frame index to read (0-based). `null` = beginning.                          |
| `$stop`      | `?int`   | One past the last frame index. `null` = end of file. Clipped to file length.      |
| `$dtype`     | `DType`  | Desired dtype of the returned array. One of Float32 (default), Float64, Int16, Int32. |
| `$always2d`  | `bool`   | If `true`, mono files return `[N, 1]` instead of `[N]`. Default `false`.          |
| `$blocksize` | `int`    | Frames per internal read chunk. Affects memory usage during read, not the result. |

**Returns:** `[NDArray, SfInfo]` — the signal data (default `Float32`, normalized to `[-1.0, 1.0]` for integer files) and the file's full metadata.

**Throws:** `SoundFileException` if the file cannot be opened, an unsupported dtype is given, or a read error occurs.

**Examples:**

```php
// Read entire file (default Float32)
[$audio, $info] = sf_read('song.wav');

// Read as raw 16-bit integers
[$audio, $info] = sf_read('song.wav', dtype: DType::Int16);

// Read frames 100–299 (200 frames starting at frame 100)
[$slice, $info] = sf_read('song.wav', start: 100, stop: 300);

// Read mono file as 2D
[$audio, $info] = sf_read('song.wav', always2d: true);
// Shape: [frames, 1]
```

---

## sf_write()

Write an NDArray to an audio file. Format is inferred from the file extension when not specified. When the encoding
subtype is omitted, it is resolved by consulting both the AudioFormat's compatible subtypes and the data's DType.
The DType is auto-converted to match the chosen subtype.

```php
function sf_write(
    string $file,
    NDArray $data,
    int $sampleRate,
    ?AudioFormat $format = null,
    ?SampleFormat $subtype = null,
): void
```

**Parameters:**

| Parameter     | Type            | Description                                                                   |
|---------------|-----------------|-------------------------------------------------------------------------------|
| `$file`       | `string`        | Output file path                                                              |
| `$data`       | `NDArray`       | Signal data, `[N]` or `[N, channels]`. 1D is auto-expanded to `[N, 1]`.       |
| `$sampleRate` | `int`           | Sample rate in Hz                                                             |
| `$format`     | `?AudioFormat`  | Container format. `null` = inferred from extension.                           |
| `$subtype`    | `?SampleFormat` | Encoding subtype. `null` = resolved from the format's compatible subtypes and the data's DType. |

**Throws:** `SoundFileException` if the format/subtype combination is invalid or a write error occurs.

**Examples:**

```php
// Subtype resolved from format and data dtype — Float32 in WAV produces Float encoding
sf_write('output.wav', $audio, sampleRate: 44100);

// Explicit format and subtype
use PhpMlKit\SoundFile\Enums\AudioFormat;
use PhpMlKit\SoundFile\Enums\SampleFormat;

sf_write('output.flac', $audio, 44100,
    format: AudioFormat::Flac,
    subtype: SampleFormat::Pcm24,
);

// 1D input works
$mono = NDArray::array([0.1, 0.2, 0.3], DType::Float32);
sf_write('out.wav', $mono, 8000); // Auto-expands to [3, 1]
```

---

## sf_info()

Read signal properties from an audio file without loading its audio data. Returns frames, channels, sample rate, format, and seekability. For embedded string tags, use sf_metadata() instead.

```php
function sf_info(string $file): SfInfo
```

**Returns:** `SfInfo` — all technical fields populated from the file header.

**Throws:** `SoundFileException` if the file cannot be opened.

**Example:**

```php
$info = sf_info('song.wav');
echo "{$info->frames} frames, {$info->channels} channels, {$info->duration()}s";
```
---

## sf_metadata()

Read embedded string tags from an audio file without loading its audio data. Each tag is null if not present in the file.

```php
function sf_metadata(string $file): SfMetadata
```

**Returns:** `SfMetadata` — all fields populated from the file's tags. Null for any tag not present.

**Throws:** `SoundFileException` if the file cannot be opened.

**Example:**

```php
$meta = sf_metadata('song.wav');
echo $meta->artist;
echo $meta->album;
```

---

## sf_check_format()

Validate that an AudioFormat and SampleFormat combination is supported by libsndfile.

```php
function sf_check_format(AudioFormat $format, SampleFormat $subtype): bool
```

**Parameters:**

| Parameter  | Type           | Description      |
|------------|----------------|------------------|
| `$format`  | `AudioFormat`  | Container format |
| `$subtype` | `SampleFormat` | Encoding subtype |

**Returns:** `bool` — `true` if the combination is valid.

**Examples:**

```php
sf_check_format(AudioFormat::Wav, SampleFormat::Pcm16);   // true
sf_check_format(AudioFormat::Ogg, SampleFormat::Pcm16);   // false
sf_check_format(AudioFormat::Flac, SampleFormat::Float);  // false
```

---

## sf_resample()

Convert an NDArray from one sample rate to another using libsamplerate.

```php
function sf_resample(
    NDArray $input,
    int $inputRate,
    int $outputRate,
    ResampleQuality $quality = ResampleQuality::Best,
    ?int $chunkSize = 2048,
): NDArray
```

**Parameters:**

| Parameter     | Type              | Description                                                                 |
|---------------|-------------------|-----------------------------------------------------------------------------|
| `$input`      | `NDArray`         | Signal of shape `[frames, channels]`                                        |
| `$inputRate`  | `int`             | Source sample rate in Hz                                                    |
| `$outputRate` | `int`             | Target sample rate in Hz                                                    |
| `$quality`    | `ResampleQuality` | Converter quality level. Default `Best`.                                    |
| `$chunkSize`  | `?int`            | Frames per processing chunk. `null` = one-shot simple mode. Default `2048`. |

**Returns:** `NDArray` of shape `[newFrames, channels]` with `DType::Float32`.

**Throws:** `SoundFileException` if the resampler fails or produces zero output.

**Examples:**

```php
// Chunked progressive (default) — safe for large files
$r = sf_resample($audio, 44100, 22050);

// One-shot simple — best for small signals
$r = sf_resample($audio, 44100, 8000, chunkSize: null);

// Explicit quality and chunk size
$r = sf_resample($audio, 44100, 48000,
    quality: ResampleQuality::Fastest,
    chunkSize: 1024,
);
```
