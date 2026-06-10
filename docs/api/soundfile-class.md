# SoundFile

An opened audio file handle for streaming read/write with seeking and block iteration.

## Overview

`SoundFile` wraps a libsndfile `SNDFILE*` handle. In read mode, metadata is read from the file header. In write mode, you
provide the metadata and the constructor validates the format combination.

The handle is closed automatically by the destructor but should be closed explicitly in long-running processes.

## Constructor

```php
new SoundFile(
    string $path,
    FileMode $mode = FileMode::Read,
    // Write-mode parameters:
    ?int $sampleRate = null,
    ?int $channels = null,
    ?AudioFormat $format = null,
    ?SampleFormat $subtype = null,
)
```

**Parameters:**

| Parameter     | Type            | Description                                                     |
|---------------|-----------------|-----------------------------------------------------------------|
| `$path`       | `string`        | Path to the audio file                                          |
| `$mode`       | `FileMode`      | `Read` (default), `Write`, or `ReadWrite`                       |
| `$sampleRate` | `?int`          | Sample rate in Hz (write mode)                                  |
| `$channels`   | `?int`          | Number of channels (write mode, defaults to 1)                  |
| `$format`     | `?AudioFormat`  | Container format (write mode; default: inferred from extension) |
| `$subtype`    | `?SampleFormat` | Encoding subtype (write mode; default: format's preferred)      |

**Throws:** `SoundFileException` if the file cannot be opened or the format/subtype combination is invalid.

**Examples:**

```php
// Read mode
$sf = new SoundFile('input.wav', FileMode::Read);

// Write mode with explicit format
$sf = new SoundFile('output.wav', FileMode::Write,
    sampleRate: 44100,
    channels: 2,
    format: AudioFormat::Wav,
    subtype: SampleFormat::Float,
);

// Write mode — format inferred from extension
$sf = new SoundFile('output.flac', FileMode::Write,
    sampleRate: 48000,
    channels: 1,
);
```

---

## read()

Read up to `$numFrames` frames from the current position. Each call advances the position — subsequent reads continue
from where the previous one left off.

```php
public function read(?int $numFrames = null, DType $dtype = DType::Float32, bool $always2d = false): NDArray
```

**Parameters:**

| Parameter    | Type   | Description                                                                           |
|--------------|--------|---------------------------------------------------------------------------------------|
| `$numFrames` | `?int` | Maximum frames to read. `null` = all remaining from current position.                 |
| `$dtype`     | `DType`| Desired dtype of the returned array. One of Float32 (default), Float64, Int16, Int32. |
| `$always2d`  | `bool` | If `true`, mono files return `[N, 1]` instead of `[N]`. Default `false`.              |

**Returns:** `NDArray` of shape `[framesRead]` for mono (or `[framesRead, 1]` with `always2d: true`) and `[framesRead, channels]` for multi-channel. Default dtype is `Float32`.

**Throws:** `SoundFileException` if the handle is closed, opened in write-only mode, an unsupported dtype is given, or a read error occurs.

**Example:**

```php
$chunk = $sf->read(512);                      // Read 512 frames (Float32, 1D for mono)
$chunk = $sf->read(512, always2d: true);      // Read 512 frames as 2D
$chunk = $sf->read(512, dtype: DType::Int16); // Read 512 frames as Int16
$rest = $sf->read(null);                      // Read everything remaining
```

---

## write()

Write frames to the file. Can be called **multiple times** — each call appends the data and advances the position. Only
the **channel count** must match; the frame count can be any size.

```php
public function write(NDArray $data): void
```

**Parameters:**

| Parameter | Type      | Description                                                                                                       |
|-----------|-----------|-------------------------------------------------------------------------------------------------------------------|
| `$data`   | `NDArray` | Data of shape `[N, channels]` where `channels` matches the file's channel count. `N` can be any positive integer. |

**Throws:** `SoundFileException` if the handle is closed, opened in read-only mode, channels mismatch, or a write error
occurs.

**Example:**

```php
// Write in stages
$out->write(NDArray::array([[0.1, 0.2], [0.3, 0.4]], DType::Float32));
$out->write(NDArray::array([[0.5, 0.6]], DType::Float32));
echo $out->tell(); // 3 — position tracks all writes
```

---

## seek()

Move the read/write position.

```php
public function seek(int $frameOffset, int $whence = SEEK_SET): void
```

**Parameters:**

| Parameter      | Type  | Description                                       |
|----------------|-------|---------------------------------------------------|
| `$frameOffset` | `int` | Target frame offset                               |
| `$whence`      | `int` | `SEEK_SET` (0), `SEEK_CUR` (1), or `SEEK_END` (2) |

**Throws:** `SoundFileException` if the handle is closed, seeking is not supported, or the seek fails.

---

## tell()

Current frame position.

```php
public function tell(): int
```

---

## eof()

Whether the read position has reached or passed the end of the file. In write mode this always returns `false` — writing
appends indefinitely.

```php
public function eof(): bool
```

---

## close()

Close the file handle. After closing, all I/O methods throw. Called automatically by the destructor.

```php
public function close(): void
```

---

## Metadata accessors

### info()

Full file metadata. In write mode, the frame count reflects total frames written so far.

```php
public function info(): SfInfo
```

### frames()

Total frames in the file, or frames written so far in write mode.

```php
public function frames(): int
```

### channels()

Number of audio channels.

```php
public function channels(): int
```

### sampleRate()

Sample rate in Hz.

```php
public function sampleRate(): int
```

### mode()

The FileMode this handle was opened with.

```php
public function mode(): FileMode
```

---

## blocks()

Generator that yields NDArrays of up to `$blocksize` frames.

```php
public function blocks(int $blocksize = 4096, DType $dtype = DType::Float32, bool $always2d = false): Generator
```

**Parameters:**

| Parameter    | Type    | Description                                                            |
|--------------|---------|------------------------------------------------------------------------|
| `$blocksize` | `int`   | Maximum frames per block. Default `4096`.                              |
| `$dtype`     | `DType` | Desired dtype of each block. One of Float32 (default), Float64, Int16, Int32. |
| `$always2d`  | `bool`  | If `true`, mono blocks return `[N, 1]` instead of `[N]`. Default `false`. |

**Returns:** `Generator<int, NDArray>` — each yielded value is an NDArray of shape `[≤blocksize, channels]`.

**Example:**

```php
foreach ($sf->blocks(1024) as $block) {
    process($block);
}

// With explicit dtype and 2D mono output
foreach ($sf->blocks(1024, dtype: DType::Int16, always2d: true) as $block) {
    process($block);
}
```

---

## close()

Close the file handle. After closing, all I/O methods throw.

```php
public function close(): void
```

Called automatically by the destructor.

---

## Metadata accessors

### info()

Full file metadata.

```php
public function info(): SfInfo
```

### frames()

Total frame count.

```php
public function frames(): int
```

### channels()

Number of audio channels.

```php
public function channels(): int
```

### sampleRate()

Sample rate in Hz.

```php
public function sampleRate(): int
```

---

## String metadata

These methods read and write the supported metadata tags on open handles.

**Getters** (read mode, return `?string`):

| Method          |
|-----------------|
| `title()`       |
| `copyright()`   |
| `software()`    |
| `artist()`      |
| `comment()`     |
| `date()`        |
| `album()`       |
| `license()`     |
| `trackNumber()` |
| `genre()`       |

**Setters** (write mode, return `void`, throw on read-only handles):

| Method                   |
|--------------------------|
| `setTitle(string)`       |
| `setCopyright(string)`   |
| `setSoftware(string)`    |
| `setArtist(string)`      |
| `setComment(string)`     |
| `setDate(string)`        |
| `setAlbum(string)`       |
| `setLicense(string)`     |
| `setTrackNumber(string)` |
| `setGenre(string)`       |
