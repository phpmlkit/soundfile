---
layout: home

hero:
  name: "PHP SoundFile"
  tagline: Low-level audio I/O and resampling for PHP
  actions:
    - theme: brand
      text: Get Started
      link: /guide/getting-started/what-is-soundfile
    - theme: alt
      text: View on GitHub
      link: https://github.com/phpmlkit/sndfile

features:
  - icon: 🎵
    title: Read & Write Audio
    details: WAV, FLAC, Ogg Vorbis, MP3, AIFF, and 20+ formats. Data flows in and out as NDArrays — no intermediate buffers.
  - icon: 🔄
    title: Sample Rate Conversion
    details: Four quality levels, chunked progressive mode for large files, one-shot simple mode for small signals.
  - icon: 🧵
    title: Streaming I/O
    details: Instance-based SoundFile class with read, write, seek, tell, block iteration, and metadata tags.
  - icon: 🏷️
    title: Full Metadata Support
    details: Read and write title, artist, album, track number, and genre on open handles.
  - icon: 🔧
    title: Battle-Tested Foundation
    details: Powered by libsndfile and libsamplerate — the standard C libraries for audio I/O and sample rate conversion.
  - icon: 🎯
    title: PHP 8.2+ Native
    details: Backed enums, readonly value objects, strict types, and importable global functions.
---

## Quick Example

```php
use function PhpMlKit\SoundFile\{sf_read, sf_write, sf_resample};

// Read an audio file — returns [NDArray, SfInfo]
[$audio, $info] = sf_read('input.wav');
// $audio shape: [441000] (mono, Float32)

// Resample from 44.1kHz to 16kHz
$audio16k = sf_resample($audio, $sr, 16000);

// Write it back
sf_write('output.wav', $audio16k, 22050);
```

## Streaming with SoundFile

```php
use PhpMlKit\SoundFile\SoundFile;
use PhpMlKit\SoundFile\Enums\FileMode;

// Open for reading
$sf = new SoundFile('input.wav', FileMode::Read);

// Seek and read
$sf->seek(44100);
$chunk = $sf->read(512);

// Iterate in blocks — each block is an NDArray
foreach ($sf->blocks(1024) as $block) {
    process($block);
}
$sf->close();

// Open for writing
$out = new SoundFile('output.wav', FileMode::Write,
    sampleRate: 44100, channels: 2,
);
$out->setTitle('My Track');
$out->write($stereoData);
$out->close();
```

## Next steps

- [What is SoundFile?](/guide/getting-started/what-is-soundfile)
- [Installation](/guide/getting-started/installation)
- [API Reference](/api/)