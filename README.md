# TokenizerEngine

[![CI](https://github.com/lalbert/tokenizer-engine/actions/workflows/ci.yml/badge.svg)](https://github.com/lalbert/tokenizer-engine/actions/workflows/ci.yml)

Elasticsearch-like text analysis in pure PHP: character filters, tokenizers
and token filters, chained into analyzers. UTF-8 aware, with positions and
offsets, and able to load the `analysis` section of Elasticsearch index
settings.

Typical uses, without running a search cluster:

- normalize and deduplicate strings (names, addresses, products);
- autocompletion and fuzzy matching on small datasets stored in SQL;
- detect near-duplicates (fingerprint, phonetic codes, MinHash);
- split documents into passages for embeddings / RAG (`TextChunker`);
- check offline what an Elasticsearch analyzer configuration produces.

> This library is not affiliated with Elastic. It mirrors the behaviour of
> Elasticsearch's analysis components, and the CI checks it against real
> Elasticsearch 8.19 and 9.5 nodes (see [Parity with Elasticsearch](#parity-with-elasticsearch)).

## Installation

```bash
composer require lalbert/tokenizer-engine
```

Requirements: PHP 8.2+ and `ext-mbstring`. Recommended:

- `ext-intl`, for Unicode word segmentation (UAX #29) and ASCII folding with ICU;
  a pure PHP fallback is used otherwise;
- `wamania/php-stemmer`, for Snowball stemming and the Dutch, Swedish,
  Norwegian, Danish, Finnish and Russian analyzers (English, French, German,
  Spanish, Italian and Portuguese stemming are built in):

```bash
composer require wamania/php-stemmer
```

## Quick start

```php
use TokenizerEngine\Analyzers;

Analyzers::french()->tokenize('L’école des chevaux, c’était hier !');
// ['ecol', 'cheval', 'hier']  (same output as Elasticsearch's french analyzer)

Analyzers::french(stemExclusion: ['chevaux'])->tokenize('Les chevaux');
// ['chevaux']

Analyzers::english()->tokenize("The QUICK brown foxes jumped over John's lazy dogs");
// ['quick', 'brown', 'fox', 'jump', 'over', 'john', 'lazi', 'dog']

Analyzers::fingerprint()->tokenize('paris, CAFE de, café');
// ['cafe de paris']  (same as for "Café de Paris")
```

Built-in analyzers: `standard`, `simple`, `whitespace`, `keyword`, `stop`,
`fingerprint`, `english`, `french`, `german`, `spanish`, `italian`,
`portuguese`, `dutch`, `swedish`, `norwegian`, `danish`, `finnish`, `russian`
(`Analyzers::get('french')`).

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
its offsets in the original text, in characters (Unicode code points). Like
Elasticsearch, offsets point into the text **before** character filters: with
`html_strip`, they point into the HTML source. `toArray()` gives the same shape
as an entry of Elasticsearch's `_analyze` response.

```php
foreach (Analyzers::standard()->tokens('Un été à Montréal') as $token) {
    echo json_encode($token->toArray()), "\n";
}
// {"token":"un","start_offset":0,"end_offset":2,"type":"<ALPHANUM>","position":0}
// {"token":"été","start_offset":3,"end_offset":6,"type":"<ALPHANUM>","position":1}
// ...
```

`analyze()` returns the output of every step, which is handy to debug a chain.

## Finding query terms in a text (highlighting)

`TermMatcher` analyzes a query and a text with the same analyzer (or a
distinct query analyzer, like Elasticsearch's `search_analyzer`) and returns
where the query terms appear, with offsets in the original text and the text
segments to wrap, which skip the markup removed by character filters. It does
no rendering: wrap the segments the way you need.

```php
use TokenizerEngine\Highlighting\TermMatcher;

$html = '<p>Les élèves de l’<b>École</b> arrivent à Montréal.</p>';
$analyzer = TokenizerEngine::fromElasticsearch($settings, 'french_html'); // html_strip + french filters

$out = '';
$cursor = 0;
foreach ((new TermMatcher($analyzer))->match($html, "l'ecole montreal") as $match) {
    // $match->terms, $match->start, $match->end, $match->text ('l’<b>École</b>')
    foreach ($match->segments as $segment) { // 'l’' then 'École': tags are skipped
        $out .= mb_substr($html, $cursor, $segment['start'] - $cursor).'<mark>'.$segment['text'].'</mark>';
        $cursor = $segment['end'];
    }
}
$out .= mb_substr($html, $cursor);
// <p>Les élèves de <mark>l’</mark><b><mark>École</mark></b> arrivent à <mark>Montréal</mark>.</p>
```

What matches is decided by the analyzers: with a French analyzer, "l'ecole",
"École" and "écoles" match "l’École", but "eco" does not. For
search-as-you-type, index the text with an `edge_ngram` analyzer and pass a
query analyzer without it. Like Elasticsearch, a passage is the whole token
("l’École", elided article included); phrases are not matched as a whole.

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

Normalizers (for `keyword` fields) are loaded the same way:

```php
$config->normalizer('my_normalizer')->normalize('  Café De PARIS ');
// 'cafe de paris' with the trim, lowercase and asciifolding filters
```

File parameters (`stopwords_path`, `synonyms_path`, `hyphenation_patterns_path`...)
are read as given (absolute, or relative to the working directory), while
Elasticsearch reads them relative to its config directory.

Unsupported parameters are ignored by default; with `strict: true` they raise
an exception. Custom components can be registered:

```php
use TokenizerEngine\Elasticsearch\{ComponentRegistry, Params};

$registry = ComponentRegistry::default()
    ->addTokenFilter('my_filter', fn (Params $p) => new MyFilter($p->int('size', 3)));

$config = new AnalysisConfig($settings, $registry);
```

## Near-duplicates: phonetic codes and MinHash

```php
use TokenizerEngine\TokenFilter\{MinHashTokenFilter, PhoneticTokenFilter, ShingleTokenFilter};

(new PhoneticTokenFilter('soundex2'))->filter(['Lefèvre', 'Lefeuvre']);
// ['LFVR', 'LFVR']

$signature = (new TokenizerEngine())
    ->addTokenizer(new StandardTokenizer())
    ->addTokenFilter(new LowercaseTokenFilter())
    ->addTokenFilter(new ShingleTokenFilter(2, 2, outputUnigrams: false))
    ->addTokenFilter(new MinHashTokenFilter(256));

MinHashTokenFilter::similarity($signature->tokenize($textA), $signature->tokenize($textB));
// 0.71 (estimated Jaccard similarity of the word pairs)
```

Phonetic encoders: `soundex`, `refined_soundex`, `metaphone` (English),
`cologne` (German, Kölner Phonetik), `soundex_fr` (Soundex with the French
letter table), `soundex2` (French, Frédéric BROUARD). Implement
`PhoneticEncoderInterface` to plug any other algorithm.

## Splitting documents for RAG

`TextChunker` is not an Elasticsearch component: it splits a text into
passages of a maximum size, at sentence boundaries when possible (then words,
then characters), with an optional overlap. Each `Chunk` keeps its offsets in
the original text.

```php
use TokenizerEngine\Chunker\TextChunker;

// At most 1000 characters per passage, 200 characters of overlap.
foreach ((new TextChunker(1000, 200, locale: 'fr'))->chunk($document) as $chunk) {
    $chunk->text; $chunk->startOffset; $chunk->endOffset; $chunk->size;
}

// Sizes measured in tokens of an analyzer, or with any callable
// (e.g. an LLM token counter): new TextChunker(512, 64, fn (string $s): int => $counter->count($s)).
TextChunker::byTokens(Analyzers::standard(), maxTokens: 300, overlapTokens: 50)->chunk($document);
```

## Components

| Class | Elasticsearch type | Notes |
|---|---|---|
| **Character filters** | | |
| `HTMLStripCharacterFilter` | `html_strip` | keeps words apart, decodes entities, drops `<script>`/`<style>` |
| `MappingCharacterFilter` | `mapping` | |
| `PatternReplaceCharacterFilter` | `pattern_replace` | |
| `IcuNormalizerCharacterFilter` | `icu_normalizer` (ICU plugin) | requires ext-intl |
| **Tokenizers** | | |
| `StandardTokenizer` | `standard` | UAX #29 via ICU, regex fallback |
| `WhitespaceTokenizer` | `whitespace` | |
| `LetterTokenizer` | `letter` | |
| `KeywordTokenizer` | `keyword` | |
| `PatternTokenizer` | `pattern` | |
| `UaxUrlEmailTokenizer` | `uax_url_email` | URLs need a scheme or `www.` |
| `NGramTokenizer`, `EdgeNGramTokenizer` | `ngram`, `edge_ngram` | `token_chars` supported |
| `PathHierarchyTokenizer` | `path_hierarchy` | |
| `CharGroupTokenizer` | `char_group` | |
| **Token filters** | | |
| `LowercaseTokenFilter`, `UppercaseTokenFilter` | `lowercase`, `uppercase` | |
| `AsciiFoldingTokenFilter` | `asciifolding` | optional locale rules (`de`: ä → ae) |
| `StopTokenFilter` | `stop` | `_english_`, `_french_`, `_german_`, `_spanish_`, `_italian_`, `_portuguese_`, `_dutch_`, `_swedish_`, `_norwegian_`, `_danish_`, `_finnish_`, `_russian_` |
| `ElisionTokenFilter` | `elision` | French articles by default, handles `’` |
| `StemmerTokenFilter` | `stemmer`, `snowball`, `porter_stem` | native `english` / `porter`, `light_french`, `minimal_french`, `light_german`, `light_spanish`, `light_italian`, `light_portuguese` (Lucene ports), Snowball otherwise |
| `GermanNormalizationTokenFilter` | `german_normalization` | |
| `ScandinavianFoldingTokenFilter`, `ScandinavianNormalizationTokenFilter` | `scandinavian_folding`, `scandinavian_normalization` | |
| `DictionaryDecompounderTokenFilter` | `dictionary_decompounder` | compound words from a dictionary; like Elasticsearch 9, settings keep only the longest match |
| `HyphenationDecompounderTokenFilter` | `hyphenation_decompounder` | hyphenation patterns in Apache FOP XML format (`HyphenationTree`), requires ext-dom |
| `KeywordMarkerTokenFilter` | `keyword_marker` | protects words from stemming |
| `StemmerOverrideTokenFilter` | `stemmer_override` | |
| `ShingleTokenFilter` | `shingle` | |
| `PhoneticTokenFilter` | `phonetic` (phonetic plugin) | encoders listed above |
| `MinHashTokenFilter` | `min_hash` | different algorithm (see Differences) |
| `IcuNormalizerTokenFilter`, `IcuFoldingTokenFilter` | `icu_normalizer`, `icu_folding` (ICU plugin) | require ext-intl |
| `DecimalDigitTokenFilter` | `decimal_digit` | |
| `KeepWordsTokenFilter`, `KeepTypesTokenFilter` | `keep`, `keep_types` | |
| `PatternReplaceTokenFilter`, `PatternCaptureTokenFilter` | `pattern_replace`, `pattern_capture` | |
| `MultiplexerTokenFilter` | `multiplexer` | |
| `ConditionalTokenFilter` | `condition` | PHP callable instead of a Painless script (not loadable from settings) |
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

## Examples

Runnable examples (SQLite through PDO), tested in CI:

- [Full-text search with plain SQL](docs/search-with-sql.md): inverted index in
  two tables, BM25 ranking in one SQL query, highlighting, search-as-you-type
  (`examples/search-with-sql.php`).
- [Retrieval augmented generation](docs/rag.md): passages, keyword retrieval
  and a prompt citing its sources, for any LLM (`examples/rag.php`).

## Performance

`composer bench` runs the PHPBench benchmarks of `benchmarks/`. Indicative
timings on 100 KB of French text or HTML (PHP 8.3, one core of a cloud VM):

| Operation | Time |
|---|---|
| Standard analyzer | 15 ms |
| French analyzer (light stemmer, stop words, elision) | 28 ms |
| `html_strip` with offset mapping | 7 ms |
| HTML analysis with offsets mapped to the source | 25 ms |
| `TermMatcher` on 100 KB of HTML | 27 ms |
| `TextChunker` (1,000 characters, 200 of overlap) | 2.4 ms |

Mapping offsets back to the original text costs almost nothing compared with
the filtering itself.

## Parity with Elasticsearch

`tests/Parity` sends a corpus of 19 texts (12 languages, URLs, paths, HTML,
digits...) to this library and to the `_analyze` API of a real Elasticsearch
node (with the ICU and phonetic plugins), and compares terms, positions and
offsets for 98 configurations: every built-in analyzer, tokenizer, character
filter, token filter and normalizer listed above, about 1,800 comparisons. The
CI runs it against Elasticsearch 8.19 and 9.5;
see `tests/Parity/README.md` to run it locally.

Every language analyzer (English, French, German, Spanish, Italian,
Portuguese, Dutch, Swedish, Norwegian, Danish, Finnish, Russian) and
normalizer matches exactly. Besides the pinned versions, a weekly workflow
compares the output with the latest Elasticsearch 8.x and 9.x releases. Known differences, all
covered by the suite in the scope below:

- Phonetic encoders fold accented letters before encoding ("Élodie" gives
  E430), whereas Elasticsearch leaves words containing them unchanged.
- `path_hierarchy` increments positions like Elasticsearch 9; Elasticsearch 8
  puts every level at position 0.
- Offsets are counted in Unicode code points; Elasticsearch counts UTF-16 code
  units, so they differ for characters outside the BMP, such as emoji.
- Custom character filters that only implement `CharacterFilterInterface` are
  assumed not to move characters; implement
  `OffsetMappingCharacterFilterInterface` to map offsets.

Not compared (different by design or not loadable from settings):

- `min_hash` uses k seeded hash functions instead of Elasticsearch's bucket
  approach: signatures are not interchangeable.
- `uax_url_email` does not recognize bare domain names (`example.com`).
- Synonym graphs: multi-word synonyms are emitted as consecutive tokens, not
  as a token graph.
- Regular expressions are PCRE with Java semantics for `\w`, `\d`, `\s` and
  `\b` (ASCII only unless `UNICODE_CHARACTER_CLASS` or `(?U)`); other Java
  specific syntax may not be supported.
- Not implemented: `hunspell`, Painless scripts (`condition`),
  ICU tokenizer and transforms, Beider-Morse and Double Metaphone encoders,
  CJK specific analysis.

## Data sources and licenses

- Stop words: English list from Apache Lucene (Apache-2.0, the list
  Elasticsearch uses for `_english_`); other languages from the
  [Snowball project](https://snowballstem.org/) (BSD-3-Clause). See
  `resources/stop-words/`.
- Porter, French light and French minimal stemmers: ports of Apache Lucene
  (Apache-2.0), algorithms by Martin PORTER and Jacques SAVOY (BSD); see the
  headers of `src/Stemmer/`. Test data from Lucene (Apache-2.0) in
  `tests/Fixtures/`.
- Soundex2: implemented from the description published by Frédéric BROUARD
  (https://sqlpro.developpez.com/cours/soundex/), which states "copyright
  Frédéric BROUARD". The code here is an independent implementation.
- ASCII folding fallback table derived from WordPress' `remove_accents()`.

## Development

```bash
composer install
composer test       # PHPUnit
composer analyse    # PHPStan
composer cs         # PHP-CS-Fixer (dry run), composer cs-fix to apply
composer bench      # PHPBench benchmarks
composer mutation   # Infection mutation testing (needs PHP 8.3+ and pcov or Xdebug)
```

## Backward compatibility promise

- **Public API**: classes, interfaces, public methods and their signatures
  follow [semantic versioning](https://semver.org/). A change that breaks
  code using them only happens in a major version. Classes and methods marked
  `@internal` (`TokenizerEngine\Util\*`) are not covered.
- **Analysis output** (terms, positions, offsets, types): the goal of the
  library is to produce the same output as Elasticsearch. A minor version may
  change it **only to match Elasticsearch more closely** (a stemmer ported from
  Lucene, a corrected offset...); such changes are listed under "Changed" in
  `CHANGELOG.md` and explained in `UPGRADE.md`. Reindex the data analyzed with
  the previous version when they affect you. Any other change of output is a
  bug, or waits for a major version.
- Patch versions only fix bugs, without changing the output of correct code.

## Releasing

1. Prepare the changelog on a branch and open a pull request:
   `.github/scripts/prepare-release.sh 1.2.0` turns the `[Unreleased]` section
   into `[1.2.0] - <today>` and updates the comparison links.
2. Once it is merged, either run the `Release` workflow from the Actions tab
   ("Run workflow", version `1.2.0`), which creates the tag on `master`, or
   push the tag yourself: `git tag -a 1.2.0 -m 1.2.0 && git push origin 1.2.0`.
3. The workflow checks the version and its changelog section, runs the tests
   and publishes the GitHub release with that section as notes. Packagist
   picks the tag up through its GitHub hook.

## License

MIT, see [LICENSE](LICENSE).
