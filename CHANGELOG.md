# Changelog

All notable changes to `phpmlkit/sndfile` will be documented in this file.

The format is based on [Keep a Changelog](https://keepachangelog.com/en/1.0.0/),
and this project adheres to [Semantic Versioning](https://semver.org/spec/v2.0.0.html).

## v1.2.0 - 2026-06-10

### What's Changed

* feat: user-controlled dtype on read with Float32 default by @CodeWithKyrian in https://github.com/phpmlkit/soundfile/pull/3
* fix: remove public getString/setString — arbitrary metadata types not supported by @CodeWithKyrian in https://github.com/phpmlkit/soundfile/pull/4

**Full Changelog**: https://github.com/phpmlkit/soundfile/compare/1.1.1...1.2.0

## v1.1.1 - 2026-06-07

### What's Changed

* fix: better subtype selection based on Dtype and Audio Format by @CodeWithKyrian in https://github.com/phpmlkit/soundfile/pull/1

### New Contributors

* @CodeWithKyrian made their first contribution in https://github.com/phpmlkit/soundfile/pull/1

**Full Changelog**: https://github.com/phpmlkit/soundfile/compare/1.1.0...1.1.1

## v1.1.0 - 2026-05-29

### What's Changed

- `sf_read()` now returns `[NDArray, SfInfo]` instead of `[NDArray, int]`.
- Added `SfMetadata` class and `sf_metadata()` for one-off reading of embedded tags
- Clarify terminology for signal properties vs embedded tags
- Make a platform package

**Full Changelog**: https://github.com/phpmlkit/soundfile/compare/1.0.0...1.1.0

## v1.0.0 - 2026-05-29

### Initial release

Low-level audio I/O and resampling for PHP, backed by libsndfile and libsamplerate.

#### Reading and writing

- `sf_read()` — read audio files into NDArrays with optional `start`/`stop` range, `always2d` flag, and chunked internal I/O
- `sf_write()` — write NDArrays to 25+ audio formats with automatic dtype-to-subtype conversion
- `sf_info()` — probe file metadata (frames, channels, sample rate, format, subtype, seekable) without loading data
- `sf_check_format()` — validate container/encoding format compatibility

#### Streaming I/O

- `SoundFile` class — instance-based handle with read, write, seek, tell, block iteration, and metadata tags
- `blocks()` generator — iterate over large files in fixed-size chunks without loading everything into memory
- Read, Write, and ReadWrite modes with API-level mode guards

#### Sample rate conversion

- `sf_resample()` — convert between sample rates with four quality levels
- Chunked progressive mode (default) for safe memory usage on large signals
- One-shot simple mode for small signals

#### Metadata

- Read and write title, artist, album, track number, genre, and 10+ other artbitrary tags on open handles

#### Credits

- [libsndfile](https://github.com/libsndfile/libsndfile) and [libsamplerate](https://github.com/libsndfile/libsamplerate) — the C libraries powering all audio I/O and resampling
- [phpmlkit/ndarray](https://github.com/phpmlkit/ndarray) — the NDArray data structure used for audio data
- Static links: Ogg, Vorbis, FLAC, Opus, mpg123, and LAME
