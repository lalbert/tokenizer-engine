# Changelog

All notable changes to this project are documented in this file. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the
project adheres to [Semantic Versioning](https://semver.org/).

## [Unreleased]

## [1.1.0] - 2026-11-06

### Added
- Elasticsearch parity test suite (`tests/Parity`, `composer parity`) run in CI
  against Elasticsearch 8.19 and 9.5 with the ICU and phonetic plugins.
- Native Porter stemmer (port of Lucene): `english` and `porter` stemmers, the
  `porter_stem` filter and the English analyzer now match Elasticsearch and no
  longer need `wamania/php-stemmer` (`porter2` is Snowball English).
- Java flags for patterns (`CASE_INSENSITIVE|COMMENTS|DOTALL|MULTILINE|UNICODE_CHARACTER_CLASS`)
  in the PHP API too.
- Native light stemmers for German, Spanish, Italian and Portuguese (ports of
  Lucene, identical on its test vocabularies) and `GermanNormalizationTokenFilter`
  (`german_normalization`).
- Normalizers: `AnalysisConfig::normalizer()` loads the `normalizer` section of
  Elasticsearch settings (and the built-in `lowercase` normalizer) into a
  `Normalizer`; `normalizerNames()`, `allowInNormalizers()`.
- Release workflow: pushing a version tag publishes a GitHub release with the
  matching `CHANGELOG.md` section (`.github/scripts/prepare-release.sh`).

### Changed
All changes below align the behaviour on Elasticsearch, as measured by the
parity suite.
- German, Spanish, Italian, Portuguese and Dutch analyzers use the same filter
  chains as Elasticsearch (light stemmers, `german_normalization`, Dutch stem
  overrides): their stems change, and only the Dutch analyzer still needs
  `wamania/php-stemmer`.
- `StandardTokenizer` segments again the pieces of words longer than
  `max_token_length` (`dell'` gives `dell`).
- Patterns follow Java semantics: `\w`, `\d`, `\s` and `\b` are ASCII only
  unless `UNICODE_CHARACTER_CLASS` or `(?U)` is used (`pattern` tokenizer,
  `pattern_replace`, `pattern_capture`, `keyword_marker` patterns).
- `WordDelimiterTokenFilter`: each part gets its own position; catenations
  follow the first part of their run; tokens without delimiters are kept as is;
  tokens made only of delimiters leave no position gap.
- `ShingleTokenFilter`: a leading filler is emitted for a gap at the start, at
  most `maxShingleSize - 1` fillers per gap, positions are renumbered, fillers
  take the offset of the next token.
- `PatternCaptureTokenFilter`: captures ordered by position in the term,
  duplicates kept, captures equal to the term skipped.
- `AsciiFoldingTokenFilter` with `preserveOriginal`: folded form first; letter
  numbers (`Ⅻ`) are no longer folded.
- `LowercaseTokenFilter` / `UppercaseTokenFilter`: simple (one character at a
  time) case mapping, so `ß` and `ﬁ` are kept by `uppercase`.
- `IcuFoldingTokenFilter` also folds decimal digits of every script.
- `FingerprintTokenFilter` spans the whole text; `StopTokenFilter` with
  `removeTrailing: false` keeps the last token only when the text ends right
  after it (new `FinalOffsetAwareInterface`).
- `metaphone` loaded from settings defaults to `max_code_len: 4`.

### Fixed
- Soundex ignored the H/W rule of American Soundex (`PHP` gave P100 instead of
  P000).

## [1.0.0] - 2026-11-06

Complete modernization. See [UPGRADE.md](UPGRADE.md) for the breaking changes.

### Added
- `Token` value object (term, position, offsets, type) and
  `TokenizerEngine::tokens()`; every tokenizer and token filter accepts
  `Token` objects and keeps positions and offsets.
- Ready to use analyzers (`Analyzers`): standard, simple, whitespace, keyword,
  stop, fingerprint, english, french, german, spanish, italian, portuguese, dutch.
- Elasticsearch settings loader (`TokenizerEngine::fromElasticsearch()`,
  `Elasticsearch\AnalysisConfig`) with a pluggable `ComponentRegistry` and a
  strict mode.
- `StemmerTokenFilter` (Snowball, via `wamania/php-stemmer`),
  `SynonymTokenFilter` (Solr format, multi-word), `EnglishPossessiveTokenFilter`.
- `KeywordTokenizer`, `LetterTokenizer`, `PatternTokenizer`.
- `MappingCharacterFilter`, `PatternReplaceCharacterFilter`.
- Stop word lists for German, Spanish, Italian, Portuguese and Dutch.
- Native French stemmers (`light_french`, `minimal_french`), ports of Apache
  Lucene: the French analyzer now matches Elasticsearch and no longer needs
  `wamania/php-stemmer`. Language analyzers accept `stemExclusion`.
- `KeywordMarkerTokenFilter`, `StemmerOverrideTokenFilter` (and a `keyword`
  flag on `Token`).
- `UaxUrlEmailTokenizer`, `NGramTokenizer`, `EdgeNGramTokenizer`,
  `PathHierarchyTokenizer`, `CharGroupTokenizer`.
- `ShingleTokenFilter`, `KeepWordsTokenFilter`, `KeepTypesTokenFilter`,
  `PatternReplaceTokenFilter`, `PatternCaptureTokenFilter`,
  `MultiplexerTokenFilter`, `ConditionalTokenFilter`, `DecimalDigitTokenFilter`.
- ICU components (ext-intl): `IcuNormalizerTokenFilter`,
  `IcuFoldingTokenFilter`, `IcuNormalizerCharacterFilter`.
- `PhoneticTokenFilter` with pluggable encoders: Soundex, Refined Soundex,
  Metaphone, Kölner Phonetik, French Soundex, Soundex2.
- `MinHashTokenFilter` with `MinHashTokenFilter::similarity()`.
- `Chunker\TextChunker`: sentence aware document splitting for RAG, with
  overlap and pluggable size measurement.
- `AsciiFoldingTokenFilter`: `preserveOriginal` option; ICU transliteration.
- `UniqueTokenFilter`: `onlyOnSamePosition` option.
- GitHub Actions (PHP 8.2 to 8.5, with and without ext-intl), PHPStan,
  PHP-CS-Fixer.

### Changed
- Requires PHP 8.2+; strict types and typed signatures everywhere.
- Filters always return lists (re-indexed arrays).
- `StandardTokenizer` follows Unicode word segmentation (UAX #29), like
  Elasticsearch: punctuation is no longer kept in tokens.
- `HTMLStripCharacterFilter` replaces block elements with a line break, decodes
  HTML entities and removes `<script>`/`<style>` contents.
- `_english_` is now Lucene's list (the one Elasticsearch uses); `_french_` is
  now the Snowball list. Directory renamed from `ressources/` to `resources/`.
- `ElisionTokenFilter` defaults to French articles and handles `’`.
- `AlphaNumericTokenFilter` keeps letters and digits of every script.

### Fixed
- UTF-8 handling in `ReverseTokenFilter`, `TruncateTokenFilter`,
  `NGramTokenFilter`, `EdgeNGramTokenFilter`, `LevenshteinTokenFilter`,
  `WordDelimiterTokenFilter`.
- `StopTokenFilter` matched stop words inside tokens (`the-end` was removed);
  `removeTrailing` had no effect.
- `AsciiFoldingTokenFilter` ignored its locale; `ß` now folds to `ss`.
- `ElisionTokenFilter` removed articles anywhere in the token.
- `EmptyTokenFilter` removed `"0"`.
- `WordDelimiterTokenFilter` ignored `protectedWords` and duplicated
  single-part tokens with `catenateWords`.
- Wrong `@return` types in `TokenizerEngine`.

### Removed
- Travis CI and Codacy configuration; HHVM and PHP < 8.2 support.

## [0.0.1] - 2016-12-16

First release.

[Unreleased]: https://github.com/lalbert/tokenizer-engine/compare/1.1.0...HEAD
[1.1.0]: https://github.com/lalbert/tokenizer-engine/compare/1.0.0...1.1.0
[1.0.0]: https://github.com/lalbert/tokenizer-engine/compare/0.0.1...1.0.0
[0.0.1]: https://github.com/lalbert/tokenizer-engine/releases/tag/0.0.1
