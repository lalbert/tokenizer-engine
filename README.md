# TokenizerEngine

[![CI](https://github.com/lalbert/tokenizer-engine/actions/workflows/ci.yml/badge.svg)](https://github.com/lalbert/tokenizer-engine/actions/workflows/ci.yml)

Elasticsearch-like text analysis in pure PHP: character filters, tokenizers
and token filters, chained into analyzers. UTF-8 aware, with positions and
offsets, and able to load the `analysis` section of Elasticsearch index
settings.

Typical uses, without running a search cluster:

- normalize and deduplicate strings (names, addresses, products);
- autocompletion and fuzzy matching on small datasets stored in SQL;
- prepare text before computing embeddings or chunking for RAG;
- check offline what an Elasticsearch analyzer configuration produces.

> This library is not affiliated with Elastic. It mirrors the behaviour of
> Elasticsearch's analysis components; results can differ in edge cases
> (see [Differences with Elasticsearch](#differences-with-elasticsearch)).

## Installation

```bash
composer require lalbert/tokenizer-engine
```

Requirements: PHP 8.2+ and `ext-mbstring`. Recommended:

- `ext-intl`, for Unicode word segmentation (UAX #29) and ASCII folding with ICU;
  a pure PHP fallback is used otherwise;
- `wamania/php-stemmer`, required by `StemmerTokenFilter` and the language analyzers:

```bash
composer require wamania/php-stemmer
```

## Quick start

```php
use TokenizerEngine\Analyzers;

Analyzers::french()->tokenize('L’école des chevaux, c’était hier !');
// ['écol', 'cheval', 'hi']

Analyzers::english()->tokenize("The QUICK brown foxes jumped over John's lazy dogs");
// ['quick', 'brown', 'fox', 'jump', 'over', 'john', 'lazi', 'dog']

Analyzers::fingerprint()->tokenize('paris, CAFE de, café');
// ['cafe de paris']  (same as for "Café de Paris")
```

Built-in analyzers: `standard`, `simple`, `whitespace`, `keyword`, `stop`,
`fingerprint`, `english`, `french`, `german`, `spanish`, `italian`,
`portuguese`, `dutch` (`Analyzers::get('french')`).

## Building your own analyzer

```php
use TokenizerEngine\TokenizerEngine;
use TokenizerEngine\CharacterFilter\HTMLStripCharacterFilter;
use TokenizerEngine\Tokenizer\StandardTokenizer;
use TokenizerEngine\TokenFilter\{AsciiFoldingTokenFilter, EdgeNGramTokenFilter, LowercaseTokenFilter};

$autocomplete = (new TokenizerEngine())
    ->addCharacterFilter(new HTMLStripCharacterFilter())
    ->addTokenizer(new StandardTokenizer())
    ->addTokenFilter(new LowercaseTokenFilter())
    ->addTokenFilter(new AsciiFoldingTokenFilter())
    ->addTokenFilter(new EdgeNGramTokenFilter(minGram: 2, maxGram: 10, preserveOriginal: false));

$autocomplete->tokenize('<b>Élan</b> vital');
// ['el', 'ela', 'elan', 'vi', 'vit', 'vita', 'vital']
```

The array constructor of previous versions still works:

```php
new TokenizerEngine([
    'characterFilters' => [new HTMLStripCharacterFilter()],
    'tokenizers' => [new StandardTokenizer()],
    'tokenFilters' => [new LowercaseTokenFilter()],
]);
```

## Positions and offsets

`tokens()` returns `Token` objects (immutable) with the term, its position and
its offsets in the text, in characters (Unicode code points). `toArray()`
gives the same shape as an entry of Elasticsearch's `_analyze` response.

```php
foreach (Analyzers::standard()->tokens('Un été à Montréal') as $token) {
    echo json_encode($token->toArray()), "\n";
}
// {"token":"un","start_offset":0,"end_offset":2,"type":"<ALPHANUM>","position":0}
// {"token":"été","start_offset":3,"end_offset":6,"type":"<ALPHANUM>","position":1}
// ...
```

Offsets make highlighting straightforward:

```php
$text = 'Un été à Montréal';
foreach ($analyzer->tokens($text) as $token) {
    if ('montreal' === $token->term) {
        $match = mb_substr($text, $token->startOffset, $token->endOffset - $token->startOffset); // "Montréal"
    }
}
```

`analyze()` returns the output of every step, which is handy to debug a chain.

## Using Elasticsearch settings

The same JSON can configure your index and your PHP code:

```php
use TokenizerEngine\TokenizerEngine;
use TokenizerEngine\Elasticsearch\AnalysisConfig;

$json = <<<'JSON'
{
  "settings": {
    "analysis": {
      "filter": {
        "autocomplete_filter": { "type": "edge_ngram", "min_gram": 2, "max_gram": 10 }
      },
      "analyzer": {
        "autocomplete": {
          "type": "custom",
          "char_filter": ["html_strip"],
          "tokenizer": "standard",
          "filter": ["lowercase", "asciifolding", "autocomplete_filter"]
        }
      }
    }
  }
}
JSON;

$analyzer = TokenizerEngine::fromElasticsearch($json, 'autocomplete');

// or, to inspect the configuration or load it from a file:
$config = AnalysisConfig::fromFile('index-settings.json', strict: true);
$config->analyzerNames();
$config->analyzer('autocomplete');
```

Unsupported parameters are ignored by default; with `strict: true` they raise
an exception. Custom components can be registered:

```php
use TokenizerEngine\Elasticsearch\{ComponentRegistry, Params};

$registry = ComponentRegistry::default()
    ->addTokenFilter('my_filter', fn (Params $p) => new MyFilter($p->int('size', 3)));

$config = new AnalysisConfig($settings, $registry);
```

## Components

| Class | Elasticsearch type | Notes |
|---|---|---|
| **Character filters** | | |
| `HTMLStripCharacterFilter` | `html_strip` | keeps words apart, decodes entities, drops `<script>`/`<style>` |
| `MappingCharacterFilter` | `mapping` | |
| `PatternReplaceCharacterFilter` | `pattern_replace` | |
| **Tokenizers** | | |
| `StandardTokenizer` | `standard` | UAX #29 via ICU, regex fallback |
| `WhitespaceTokenizer` | `whitespace` | |
| `LetterTokenizer` | `letter` | |
| `KeywordTokenizer` | `keyword` | |
| `PatternTokenizer` | `pattern` | |
| **Token filters** | | |
| `LowercaseTokenFilter`, `UppercaseTokenFilter` | `lowercase`, `uppercase` | |
| `AsciiFoldingTokenFilter` | `asciifolding` | optional locale rules (`de`: ä → ae) |
| `StopTokenFilter` | `stop` | `_english_`, `_french_`, `_german_`, `_spanish_`, `_italian_`, `_portuguese_`, `_dutch_` |
| `ElisionTokenFilter` | `elision` | French articles by default, handles `’` |
| `StemmerTokenFilter` | `stemmer`, `snowball`, `porter_stem` | Snowball stemmers |
| `EnglishPossessiveTokenFilter` | `stemmer` (`possessive_english`) | |
| `SynonymTokenFilter` | `synonym`, `synonym_graph` | Solr format, multi-word |
| `NGramTokenFilter`, `EdgeNGramTokenFilter` | `ngram`, `edge_ngram` | |
| `WordDelimiterTokenFilter` | `word_delimiter` | |
| `FingerprintTokenFilter` | `fingerprint` | |
| `UniqueTokenFilter` | `unique` | |
| `LengthTokenFilter` | `length` | |
| `LimitTokenCountTokenFilter` | `limit` | |
| `TruncateTokenFilter` | `truncate` | |
| `TrimTokenFilter` | `trim` | |
| `ReverseTokenFilter` | `reverse` | |
| `ApostropheTokenFilter` | `apostrophe` | |
| `AlphaNumericTokenFilter` | none | removes non letters/digits |
| `EmptyTokenFilter` | none | removes empty tokens |
| `LevenshteinTokenFilter` | none (`levenshtein` in configs) | closest word of a dictionary |

Every token filter accepts either a list of strings (and returns strings) or a
list of `Token` objects (and returns tokens, keeping positions and offsets), so
they can also be used on their own:

```php
(new StopTokenFilter('_french_'))->filter(['le', 'chat', 'et', 'la', 'souris']);
// ['chat', 'souris']
```

### Writing a component

Extend `AbstractTermTokenFilter` for a filter working term by term (return a
string, a list of strings or `null` to drop the token), `AbstractTokenFilter`
to work on the whole stream, and `AbstractTokenizer` for a tokenizer (yield
`[term, offset]` pairs).

## Differences with Elasticsearch

- Stemming uses the Snowball algorithms. Elasticsearch's language analyzers use
  "light" stemmers for some languages (French, German, Spanish, Italian,
  Portuguese), so stems can differ.
- Offsets are counted in Unicode code points (Elasticsearch counts UTF-16 code
  units) and refer to the text after character filters (Elasticsearch maps them
  back to the original text).
- Filters that split a token (n-grams, word delimiter, synonyms) emit
  sub-tokens at the position of the original token instead of building a token
  graph.
- Regular expressions are PCRE, not Java.
- Not implemented: keyword marker, stemmer override, normalizers, ICU and
  phonetic plugins, CJK specific analysis.

## Data sources and licenses

- Stop words: English list from Apache Lucene (Apache-2.0, the list
  Elasticsearch uses for `_english_`); other languages from the
  [Snowball project](https://snowballstem.org/) (BSD-3-Clause). See
  `resources/stop-words/`.
- ASCII folding fallback table derived from WordPress' `remove_accents()`.

## Development

```bash
composer install
composer test       # PHPUnit
composer analyse    # PHPStan
composer cs         # PHP-CS-Fixer (dry run), composer cs-fix to apply
```

## License

MIT, see [LICENSE](LICENSE).
