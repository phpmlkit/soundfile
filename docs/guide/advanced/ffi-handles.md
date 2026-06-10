# Using FFI Handles Directly

For advanced use cases, SoundFile exposes the underlying libsndfile and libsamplerate FFI instances as singletons. This
lets you call the C functions directly when the high-level API doesn't fit your needs.

::: warning
This is an advanced feature. You are responsible for correct buffer management, handle lifecycle, and error handling.
The high-level API handles all of this automatically.
:::

## Obtaining the Singletons

```php
use PhpMlKit\SoundFile\FFI\Libsndfile;
use PhpMlKit\SoundFile\FFI\Libsamplerate;

$snd = Libsndfile::get();    // Singleton — FFI::cdef() runs once
$src = Libsamplerate::get(); // Singleton — FFI::cdef() runs once
```

Both singletons load their respective C headers and shared libraries on first access. Subsequent calls return the same
instance.

## Low-Level Sndfile I/O

The `Libsndfile` class wraps the full libsndfile C API:

```php
use PhpMlKit\SoundFile\FFI\Libsndfile;
use PhpMlKit\SoundFile\Enums\FileMode;
use FFI;

$snd = Libsndfile::get();

// Open for reading
$info = $snd->newInfo();
$handle = $snd->open('song.wav', FileMode::Read, $info);

// Allocate buffer in the correct FFI scope
$totalSamples = $info->frames * $info->channels;
$buffer = $snd->new("float[{$totalSamples}]");

// Read all frames
$read = $snd->readFloat($handle, $buffer, $info->frames);

// Seek
$snd->seek($handle, 1000, SEEK_SET);

// Error checking
if ($read < 0) {
    throw new \RuntimeException($snd->strError($handle));
}

$snd->close($handle);
```

### Available methods

| Method          | C function         | Description                                 |
|-----------------|--------------------|---------------------------------------------|
| `open()`        | `sf_open`          | Open a file, return handle                  |
| `readFloat()`   | `sf_readf_float`   | Read as 32-bit float                        |
| `readDouble()`  | `sf_readf_double`  | Read as 64-bit double                       |
| `readInt()`     | `sf_readf_int`     | Read as 32-bit int                          |
| `readShort()`   | `sf_readf_short`   | Read as 16-bit short                        |
| `writeFloat()`  | `sf_writef_float`  | Write as 32-bit float                       |
| `writeDouble()` | `sf_writef_double` | Write as 64-bit double                      |
| `writeInt()`    | `sf_writef_int`    | Write as 32-bit int                         |
| `writeShort()`  | `sf_writef_short`  | Write as 16-bit short                       |
| `readFn()`      | —                  | Dispatch to correct read function by DType  |
| `writeFn()`     | —                  | Dispatch to correct write function by DType |
| `seek()`        | `sf_seek`          | Seek to frame offset                        |
| `close()`       | `sf_close`         | Close the handle                            |
| `strError()`    | `sf_strerror`      | Error message for a handle                  |
| `formatCheck()` | `sf_format_check`  | Validate format combo                       |
| `newInfo()`     | —                  | Allocate SF_INFO struct                     |

## Low-Level Samplerate

```php
use PhpMlKit\SoundFile\FFI\Libsamplerate;
use PhpMlKit\SoundFile\Enums\ResampleQuality;
use FFI;

$src = Libsamplerate::get();

// One-shot resampling
$inBuf = $data->toBuffer();
$outFrames = (int) ceil($inFrames * 2.0);
$outBuf = $src->new("float[{$outFrames}]");

$srcData = $src->new('SRC_DATA');
$srcData->data_in = FFI::addr($inBuf[0]);
$srcData->data_out = FFI::addr($outBuf[0]);
$srcData->input_frames = $inFrames;
$srcData->output_frames = $outFrames;
$srcData->src_ratio = 2.0;

$err = $src->simple($srcData, ResampleQuality::Best, 1);

// Progressive (chunked) resampling
$state = $src->newState(ResampleQuality::Best, 2, $src->new('int'));
if ($state === null) {
    throw new \RuntimeException($src->strError(/* error code */));
}
$src->process($state, $srcData);
$src->deleteState($state);
```

## When to Use This

- **Custom chunking strategies** — process audio in sizes the high-level API doesn't support
- **Non-standard formats** — access libsndfile features not exposed by SoundFile
- **Zero-copy pipelines** — pass buffer pointers between FFI layers without round-tripping through NDArray
- **Learning** — understand how the high-level API works internally

For most use cases, the global functions and `SoundFile` class are the right choice. They handle buffer allocation, scope
management, dtype dispatch, and error cleanup automatically.
