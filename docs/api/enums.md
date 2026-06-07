# Enums

Four backed enums define the core types used throughout the library.

## AudioFormat

Major audio container formats. Values are libsndfile `SF_FORMAT_*` constants masked against `SF_FORMAT_TYPEMASK` (
`0x0FFF0000`).

### Cases

| Case   | Hex Value  | Extension |
|--------|------------|-----------|
| `Wav`  | `0x010000` | `wav`     |
| `Aiff` | `0x020000` | `aiff`    |
| `Au`   | `0x030000` | `au`      |
| `Raw`  | `0x040000` | `raw`     |
| `Flac` | `0x170000` | `flac`    |
| `Ogg`  | `0x200000` | `ogg`     |
| `Mpeg` | `0x230000` | `mp3`     |
| `Caf`  | `0x180000` | `caf`     |

Full cases: `Paf`, `Svx`, `Nist`, `Voc`, `Ircam`, `W64`, `Mat4`, `Mat5`, `Pvf`, `Xi`, `Htk`, `Sds`, `Avr`, `Wavex`,
`Sd2`, `Wve`, `Mpc2k`, `Rf64`.

### Methods

```php
AudioFormat::Wav->extension();                               // 'wav'
AudioFormat::fromExtension('flac');                          // AudioFormat::Flac
AudioFormat::fromPath('song.mp3');                           // AudioFormat::Mpeg
AudioFormat::Wav->compatibleSampleFormats();                 // [Float, Double, Pcm32, ...]
AudioFormat::Wav->preferredSampleFormat(DType::Float32);     // SampleFormat::Float
AudioFormat::Wav->preferredSampleFormat(DType::Int16);       // SampleFormat::Pcm16
AudioFormat::fromSndfileFormat($combinedFormat);             // Extract from sf format value
```

---

## SampleFormat

Audio encoding subtypes. Values are libsndfile `SF_FORMAT_*` constants masked against `SF_FORMAT_SUBMASK` (
`0x0000FFFF`).

### Common cases

| Case           | Hex Value | Bit Depth | Integer |
|----------------|-----------|-----------|---------|
| `PcmS8`        | `0x0001`  | 8         | Yes     |
| `Pcm16`        | `0x0002`  | 16        | Yes     |
| `Pcm24`        | `0x0003`  | 24        | Yes     |
| `Pcm32`        | `0x0004`  | 32        | Yes     |
| `Float`        | `0x0006`  | 32        | No      |
| `Double`       | `0x0007`  | 64        | No      |
| `Vorbis`       | `0x0060`  | 0         | No      |
| `MpegLayerIII` | `0x0082`  | 0         | No      |

Full cases: `PcmU8`, `ULaw`, `ALaw`, `ImaAdpcm`, `MsAdpcm`, `Gsm610`, `Dwvw12`, `Dwvw16`, `Dwvw24`, `DwvwN`,
`MpegLayerI`, `MpegLayerII`.

### Methods

```php
SampleFormat::Pcm16->bitDepth();            // 16
SampleFormat::Pcm16->isInteger();           // true
SampleFormat::Float->isPcm();               // false
SampleFormat::Float->toDtype();             // DType::Float32
SampleFormat::fromDtype(DType::Int16);      // SampleFormat::Pcm16
SampleFormat::fromSndfileFormat(0x010006);  // SampleFormat::Float
```

---

## FileMode

File open modes matching libsndfile's `SFM_READ` / `SFM_WRITE` / `SFM_RDWR`.

| Case        | Value  | Description                             |
|-------------|--------|-----------------------------------------|
| `Read`      | `0x10` | Open for reading                        |
| `Write`     | `0x20` | Open for writing (creates or truncates) |
| `ReadWrite` | `0x30` | Open for both reading and writing       |

```php
use PhpMlKit\SoundFile\Enums\FileMode;

$sf = new SoundFile('file.wav', FileMode::Read);
```

---

## ResampleQuality

libsamplerate converter quality levels.

| Case      | Value | Description                                      |
|-----------|-------|--------------------------------------------------|
| `Best`    | `0`   | Band-limited sinc, highest quality, slowest      |
| `Medium`  | `1`   | Band-limited sinc, medium quality                |
| `Fastest` | `2`   | Band-limited sinc, fastest                       |
| `Linear`  | `3`   | Linear interpolation, fastest but lowest quality |

```php
use PhpMlKit\SoundFile\Enums\ResampleQuality;

$r = sf_resample($audio, 44100, 22050, quality: ResampleQuality::Fastest);
```
