# Streaming with SoundFile

The `SoundFile` class gives you fine-grained control over audio file access. Unlike the global functions which open, operate, and close in one shot, an `SoundFile` instance stays open, while you can read in chunks, write in stages, seek back and forth, or iterate block by block. The handle is closed automatically when the object is destroyed, but you can call `close()` yourself to release it earlier.

## Opening files

You can open a file by creating a new instance of `SoundFile` class and passing the path to the file, the file mode and other optional parameters depending on the mode. 

```php
use PhpMlKit\SoundFile\SoundFile;
use PhpMlKit\SoundFile\Enums\FileMode;

$read = new SoundFile('input.wav', FileMode::Read);
```

For writing, you typically provide `sampleRate` and (optionally) channels/format/subtype:

```php
use PhpMlKit\SoundFile\Enums\AudioFormat;
use PhpMlKit\SoundFile\Enums\SampleFormat;

$write = new SoundFile(
    'output.wav',
    FileMode::Write,
    sampleRate: 44100,
    channels: 2,
    format: AudioFormat::Wav,
    subtype: SampleFormat::Float
);
```

If `format` is omitted, it is inferred from the file extension. If `subtype` is omitted, it defaults to the format’s
preferred subtype.

## Reading

Open a file in read mode and start pulling frames. The file **MUST** exist else the constructor throws a `SoundFileException`. Each call to `read()` returns the next chunk and advances the internal position.

```php
$sf = new SoundFile('song.wav', FileMode::Read);

// Read the first 512 frames
$intro = $sf->read(512);
// $intro shape: [512, channels]

// Read another 256 frames — starts from frame 512
$next = $sf->read(256);
// $next shape: [256, channels]

echo $sf->tell(); // 768

// Read everything remaining
$rest = $sf->read();

$sf->close();
```

If you ask for more frames than remain, you get what's left — no error, just a shorter NDArray.

### Dtype control

`read()` accepts a `$dtype` parameter (default `Float32`) to control the output data type. The same four dtypes are supported as with `sf_read()`: `Float32`, `Float64`, `Int16`, and `Int32`.

```php
// Read as raw 16-bit integers
$chunk = $sf->read(512, dtype: DType::Int16);

// Read as high-precision float
$chunk = $sf->read(512, dtype: DType::Float64);
```

### always2d

`read()` also accepts an `$always2d` parameter (default `false`). When `true`, mono files return a 2D array of shape `[frames, 1]` instead of the default `[frames]`:

```php
// 2D mono output
$chunk = $sf->read(512, always2d: true);
// $chunk shape: [512, 1] for a mono file
```

You can check progress with `tell()` and `eof()`:

```php
$sf = new SoundFile('song.wav', FileMode::Read);

while (!$sf->eof()) {
    $chunk = $sf->read(1024);
    echo "Read " . $chunk->shape()[0] . " frames, at " . $sf->tell() . "\n";
}
```

### Reading in blocks

While you can use `eof` to read in chunks using the `while` loop, the `blocks()` method provides a much convenient generator so you can iterate with a `foreach` loop. Each yield is an NDArray of up to the requested size; the generator ends when the file is exhausted.

```php
$sf = new SoundFile('large-file.wav', FileMode::Read);

foreach ($sf->blocks(4096) as $block) {
    processBlock($block);
    echo "Position: {$sf->tell()}\n";
}

// With explicit dtype and 2D mono output
foreach ($sf->blocks(4096, dtype: DType::Int16, always2d: true) as $block) {
    processBlock($block);
}

// The handle is still open here — you can seek and read more
```

You can break early from the loop — the handle stays open for further use.

## Writing

Write mode lets you build a file incrementally. If the file doesn't exist, it's created; if it does, it's overwritten. Each call to `write()` appends the data, so the file grows with every call. 

The channel count of the data **MUST** match the channel count passed when creating the file.

```php
$out = new SoundFile('output.wav', FileMode::Write,
    sampleRate: 44100, channels: 2,
);

// Write in stages
$out->write(NDArray::array([[0.1, 0.2], [0.3, 0.4]], DType::Float32));
$out->write(NDArray::array([[0.5, 0.6]], DType::Float32));
$out->write(NDArray::array([[0.7, 0.8], [0.9, 1.0], [1.1, 1.2]], DType::Float32));

echo $out->tell();    // 6
echo $out->frames();  // 6

$out->close();
```

After each write, `$sf->tell()` and `$sf->frames()` reflect the total frames written so far. `eof()` always returns `false` for write handles, since writing has no end until you close.

## Seeking

`seek()` moves the frame pointer while `tell()` returns the current frame position. They both work in read, write, and read-write modes:

```php
$sf->seek(44_100);               // 1 second into a 44.1kHz file
$a = $sf->read(1024);
echo $sf->tell();               // 44_100 + framesRead

$sf->seek(100, \SEEK_CUR);      // Advance 100 frames from current position

$sf->seek(-50, \SEEK_END);      // 50 frames before the end
```

After seeking, the next `read()` or `write()` continues from the new position. If a file is not seekable, `seek()` throws `SoundFileException`.

## Read-Write Mode

`FileMode::ReadWrite` lets you read and write on the same handle. The file must already exist — open an existing file, write to it, seek back, and read what you wrote.

```php
$sf = new SoundFile('file.wav', FileMode::ReadWrite,
    sampleRate: 8000);

// Append some audio
$rw->seek(0, SEEK_END);
$sf->write(NDArray::array([[0.1], [0.2], [0.3]], DType::Float32));

// Seek back to the beginning and read everything
$sf->seek(0);
$data = $sf->read();

$sf->close();
```

## Closing

Always close the file when you're done:

```php
$read->close();
$write->close();
```

This is called automatically by the destructor when the object goes out of scope, but you can still call it explicitly to keep resource usage predictable.

## Putting It Together

A complete example that generates a tone, inserts silence, then repeats — all in one streaming session:

```php
use function PhpMlKit\NDArray\arange;

$sr = 8000;
$freq = 440;

$out = new SoundFile('tone.wav', FileMode::Write,
    sampleRate: $sr, channels: 1,
    format: AudioFormat::Wav, subtype: SampleFormat::Float,
);

$out->setTitle('Generated Tone');
$out->setArtist('SoundFile');

$sine = arange(0, 400, 1, DType::Float32)
            ->multiply(2 * \M_PI * $freq / $sr)
            ->sin()
            ->insertaxis(1);

// Write 400 frames of sine
$out->write($sine);
echo "After tone: {$out->tell()} frames\n";

// Write 200 frames of silence
$silence = NDArray::zeros([200, 1], DType::Float32);
$out->write($silence);
echo "After silence: {$out->tell()} frames\n";

// Write another 400 frames of sine
$out->write($sine);
echo "Final: {$out->tell()} frames\n";

$out->close();
```
