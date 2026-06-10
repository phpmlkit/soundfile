# Reading and Writing

SoundFile is built around a simple idea: **audio is an NDArray**.

- **Time-major** layout: `[frames, channels]`
- `frames` = number of samples per channel
- `channels` = 1 for mono, 2 for stereo, etc.

You can use either the global functions (`sf_read`, `sf_write`) or the streaming class (`SoundFile`).

## Reading (`sf_read`)

You can read an audio file into a single NDArray using the `sf_read` global function. It opens, reads, and closes the file internally.

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

### Basic usage

```php
use function PhpMlKit\SoundFile\sf_read;

[$audio, $info] = sf_read('song.wav');
```

By default, audio is returned as `Float32` regardless of the file's native encoding. Integer files (e.g. PCM16) are normalized to `[-1.0, 1.0]`, and float files are returned as-is or downcast if the file uses a wider type.

### Choosing a dtype

The `$dtype` parameter controls the output array's data type. Only four dtypes are supported:

| dtype            | Behavior                                                |
|------------------|---------------------------------------------------------|
| `Float32`        | Default — normalized floats in `[-1.0, 1.0]`           |
| `Float64`        | High-precision normalized floats                        |
| `Int16`          | Raw 16-bit integers in `[-32768, 32767]`                |
| `Int32`          | Raw 32-bit integers in `[-2147483648, 2147483647]`      |

Dtype conversion is handled automatically — you can read a PCM16 file as `Float32` (it normalizes to `[-1.0, 1.0]`) or a Float file as `Int16` (it truncates to the nearest integer):

```php
// Read PCM16 file as normalized floats (default)
[$audio, $info] = sf_read('pcm16_file.wav');

// Read PCM16 file as raw integers
[$audio, $info] = sf_read('pcm16_file.wav', dtype: DType::Int16);

// Read Float file as raw 16-bit integers
[$audio, $info] = sf_read('float_file.wav', dtype: DType::Int16);
```

### Partial reads

Use `start` and `stop` to read a slice of the file without loading the entire thing:

```php
// Read frames 44100 through 88199 (1 second starting at 1s in)
[$slice, $info] = sf_read('song.wav', start: 44100, stop: 88200);
[$head, $info] = sf_read('song.wav', stop: 1000);
[$tail, $info] = sf_read('song.wav', start: 50000);
```

`stop` is clipped to the file's total frame count, so passing a large value is safe.

### always2d

Controls whether mono files are returned as 1D or 2D arrays:

| File   | `always2d`        | Shape    |
|--------|-------------------|----------|
| Mono   | `false` (default) | `[N]`    |
| Mono   | `true`            | `[N, 1]` |
| Stereo | either            | `[N, 2]` |

### blocksize

Controls how many frames are read per internal chunk. A smaller value uses less peak memory but makes more FFI calls.
The default of 4096 is a good balance. This affects performance, not the result.

## Writing (`sf_write`)

Writes an NDArray to an audio file.

```php
function sf_write(
    string $file,
    NDArray $data,
    int $sampleRate,
    ?AudioFormat $format = null,
    ?SampleFormat $subtype = null,
): void
```

### Basic usage

```php
use function PhpMlKit\SoundFile\sf_write;

sf_write('output.wav', $audio, sampleRate: 44100);
```

### Format and subtype

When `$format` is `null`, it is inferred from the file extension. When `$subtype` is `null`, it defaults to the format's
preferred encoding:

```php
use PhpMlKit\SoundFile\Enums\AudioFormat;
use PhpMlKit\SoundFile\Enums\SampleFormat;
use function PhpMlKit\SoundFile\sf_write;

sf_write('out.flac', $audio, 44100); // inferred: Flac + Pcm16

// Explicit: WAV 32-bit float
sf_write('out.wav', $audio, 44100, AudioFormat::Wav, SampleFormat::Float);
```

| Extension | Default Format      | Default Subtype              |
|-----------|---------------------|------------------------------|
| `.wav`    | `AudioFormat::Wav`  | `SampleFormat::Pcm16`        |
| `.flac`   | `AudioFormat::Flac` | `SampleFormat::Pcm16`        |
| `.ogg`    | `AudioFormat::Ogg`  | `SampleFormat::Vorbis`       |
| `.mp3`    | `AudioFormat::Mpeg` | `SampleFormat::MpegLayerIII` |

### 1D input

If you pass a 1D array `[N]`, it is automatically expanded to `[N, 1]` before writing:

```php
$mono = NDArray::array([0.1, 0.2, 0.3], DType::Float32);
sf_write('out.wav', $mono, 8000); // Works — expands to [3, 1]
```

### Compatibility checks

Not every format supports every subtype. For example:

- `Ogg` supports `Vorbis` (and not PCM)
- `Flac` supports PCM (and not float)

Incompatible format/subtype combinations throw a `SoundFileException` before any file I/O occurs:

```php
// This throws: Ogg does not support Pcm16
sf_write('output.ogg', $audio, 44100,
    format: AudioFormat::Ogg,
    subtype: SampleFormat::Pcm16,
);
```

Use `sf_check_format()` to validate before attempting to write,  especially when format and subtype come from user input.

```php
use PhpMlKit\SoundFile\Enums\AudioFormat;
use PhpMlKit\SoundFile\Enums\SampleFormat;
use function PhpMlKit\SoundFile\sf_check_format;

sf_check_format(AudioFormat::Wav, SampleFormat::Pcm16); // true
sf_check_format(AudioFormat::Ogg, SampleFormat::Pcm16); // false
```

## Signal Properties (`sf_info()`)

Reads a file's technical properties without loading its audio data:

```php
use function PhpMlKit\SoundFile\sf_info;

$info = sf_info('song.wav');

$info->frames;       // int — total frame count
$info->channels;     // int — number of channels
$info->sampleRate;   // int — sample rate in Hz
$info->format;       // AudioFormat
$info->sampleFormat; // SampleFormat
$info->seekable;     // bool — whether seeking is supported
$info->duration();   // float — duration in seconds
$info->nSamples();   // int — frames × channels
```

## Embedded Tags (`sf_metadata()`)

Reads embedded string tags without loading audio data:

```php
use function PhpMlKit\SoundFile\sf_metadata;

$meta = sf_metadata('song.wav');

$meta->title;   // ?string
$meta->artist;  // ?string
$meta->album;   // ?string
$meta->genre;   // ?string
```

Tags not present in the file return null. For reading and writing tags on open handles, see [Metadata](/guide/fundamentals/metadata).
```
