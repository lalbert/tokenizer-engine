# Upgrading to the next version (unreleased)

- Token offsets now refer to the original text, before character filters,
  like Elasticsearch. Code that used offsets to cut the *filtered* text (for
  example the output of `HTMLStripCharacterFilter::filter()`) must cut the
  original text instead.
- `HTMLStripCharacterFilter` replaces unknown tags, `<script>` and `<style>`
  with a line break (they were removed) and `&nbsp;` with a space (it gave a
  no-break space), like Elasticsearch.
- Custom character filters that change the length of the text should
  implement `OffsetMappingCharacterFilterInterface` to keep offsets right.

# Upgrading from 0.0.1 to 1.0

## Platform

- PHP 8.2 or later is required.
- Install `wamania/php-stemmer` if you use stemming or language analyzers.
- `ext-intl` is recommended (better word segmentation and ASCII folding).

## Custom components

The interfaces now have native types. Update your implementations:

```php
// CharacterFilterInterface
public function filter(string $text): string;

// TokenizerInterface
public function tokenize(string|array $input): array;

// TokenFilterInterface
public function filter(array $tokens): array;
```

Token filters and tokenizers may receive `Token` objects (when used through
`TokenizerEngine`). Extending `AbstractTermTokenFilter`, `AbstractTokenFilter`
or `AbstractTokenizer` handles this for you.

## Behaviour changes

| Component | Before | Now |
|---|---|---|
| All filters | could return arrays with gaps in keys | always return lists |
| `TokenizerEngine::tokenize()` without tokenizer | returned the string | returns `[$text]` |
| `TokenizerEngine::analyze()` | `final` only | `final` (list of terms) and `tokens` (`_analyze` like) |
| `StandardTokenizer` | split on whitespace and `-`, kept punctuation (`bone.`) | Unicode word segmentation (`bone`) |
| `HTMLStripCharacterFilter` | `strip_tags()` only | also line breaks for block tags, entity decoding, script/style removal; accepts `['p', 'b']` as well as `'<p><b>'` |
| `StopTokenFilter` `_english_` | 173 words (ranks.nl) | 33 words (Lucene / Elasticsearch) |
| `StopTokenFilter` `_french_` | 115 words (ranks.nl) | 154 words (Snowball) |
| `StopTokenFilter` matching | stop word found anywhere in the token | whole token only |
| `StopTokenFilter` missing file | message "does not exists" | "does not exist" |
| `ElisionTokenFilter` | no default, removes `l'` anywhere | French articles by default, start of token only, case sensitive unless `articlesCase: true` |
| `ApostropheTokenFilter` | trimmed the result | no trim; also handles `’` |
| `AlphaNumericTokenFilter` | ASCII only (`école` gave `cole`) | any script |
| `EmptyTokenFilter` | removed `"0"` | keeps `"0"` |
| `NGramTokenFilter` / `EdgeNGramTokenFilter` with `preserveOriginal` | original could be emitted twice | emitted once |
| `LevenshteinTokenFilter` | byte distance, last word wins on ties | character distance, first word wins |
| `WordDelimiterTokenFilter` `preserveOriginal` | always added the original | only when the token was split |
| `FingerprintTokenFilter` | accepted an empty separator | exactly one character |
| `AsciiFoldingTokenFilter` | `ß` gave `s`, `£` was removed, `€` gave `E` | `ß` gives `ss`, currency symbols are kept |

To keep a larger English stop word list, pass your own list or file:

```php
new StopTokenFilter(null, '/path/to/english-stopwords.txt');
```
