# Changelog

All notable changes to this project are documented in this file. The format
follows [Keep a Changelog](https://keepachangelog.com/en/1.1.0/) and the
project adheres to [Semantic Versioning](https://semver.org/).

## [1.0.0] - Unreleased

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

[1.0.0]: https://github.com/lalbert/tokenizer-engine/compare/0.0.1...HEAD
[0.0.1]: https://github.com/lalbert/tokenizer-engine/releases/tag/0.0.1
