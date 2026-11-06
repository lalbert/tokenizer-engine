<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Parity;

/**
 * Analysis configurations compared with Elasticsearch's `_analyze` API.
 *
 * Each case is either a built-in analyzer name ('analyzer') or an inline
 * custom analyzer ('tokenizer', 'char_filter', 'filter'), exactly as sent to
 * Elasticsearch. 'compare' restricts what is compared (default: terms,
 * positions, offsets) and 'texts' the texts used; every restriction must be
 * explained in 'reason' and documented in the README ("Parity with
 * Elasticsearch"). 'positions_since' compares positions only from that
 * Elasticsearch version on, and 'since' compares the whole case only from
 * that version on (behaviour changed between major versions).
 */
final class ParityCases
{
    /**
     * Path of the hyphenation patterns in the Elasticsearch config directory
     * (see Dockerfile); the library reads tests/Parity/hyphenation-test.xml.
     */
    public const HYPHENATION_PATTERNS = 'analysis/hyphenation-test.xml';

    public const COMPOUND_WORDS = ['donau', 'dampf', 'schiff', 'dampfschiff', 'haupt', 'bahn', 'hof', 'bahnhof', 'rat', 'haus', 'rathaus', 'platz', 'kaffee', 'tasse', 'katze', 'kinder', 'buch', 'stadt'];

    public const TEXTS = [
        'english' => "The 2 QUICK Brown-Foxes jumped over the lazy dog's bone.",
        'french' => 'L’école des chevaux, c’était hier ! Les élèves arrivent à Montréal pour l’été.',
        'german' => 'Die Katzen und die Häuser in München sind schön.',
        'german compounds' => 'Das Donaudampfschiff hält am Hauptbahnhof, beim Rathausplatz gibt es eine Kaffeetasse.',
        'spanish' => 'Los niños comían manzanas en la cocina.',
        'italian' => "Gli studenti dell'università leggevano libri antichi nelle biblioteche.",
        'portuguese' => 'As crianças brincavam alegremente nas praças das cidades portuguesas.',
        'dutch' => 'De kinderen fietsen met hun fietsen naar de bibliotheken van de steden.',
        'swedish' => 'Barnen läste böckerna på biblioteken i de största städerna under sommaren.',
        'norwegian' => 'Barna leste bøkene på bibliotekene i de største byene om sommeren, blåbærsyltetøy!',
        'danish' => 'Børnene læste bøgerne på bibliotekerne i de største byer, Aarhus og København.',
        'finnish' => 'Lapset lukivat kirjoja kaupunkien suurimmissa kirjastoissa kesällä.',
        'russian' => 'Дети читали книги в библиотеках крупнейших городов этим летом.',
        'mixed case' => "Wi-Fi PowerShot SD500 O'Neil's j2se",
        'url email' => 'Contact: luc@example.fr, https://example.fr/a?b=1 or www.example.org.',
        'path' => '/usr/local/bin/php',
        'html' => '<p>Hello <b>world</b> &amp; caf&eacute;</p><p>Second paragraph</p>',
        'html page' => '<div class="intro">Les élèves de l’<b>École</b> arrivent<br>à <a href="/mtl">Montréal</a>&nbsp;!<!-- note --><script>var x = 1;</script></div>',
        'digits' => '١٢٣ ４５ 3,14 ﬁnal Ⅻ',
        'spaces' => '  spaced   out  ',
    ];

    /**
     * Analysis settings of the test index holding the normalizers
     * (Elasticsearch only runs custom normalizers through an index, and index
     * settings do not accept inline component definitions).
     *
     * @return array<string, array<string, mixed>>
     */
    public static function normalizerSettings(): array
    {
        return [
            'char_filter' => [
                'no_dashes' => ['type' => 'mapping', 'mappings' => ['- => _', ': => _']],
                'single_spaces' => ['type' => 'pattern_replace', 'pattern' => '\\s+', 'replacement' => ' '],
            ],
            'filter' => [
                'no_vowels' => ['type' => 'pattern_replace', 'pattern' => '[aeiou]', 'replacement' => ''],
                'french_elision' => ['type' => 'elision', 'articles' => ['l', 'd', 'c'], 'articles_case' => true],
            ],
            'normalizer' => [
                'n_lowercase_folding' => ['type' => 'custom', 'filter' => ['lowercase', 'asciifolding']],
                'n_trim_upper' => ['type' => 'custom', 'filter' => ['trim', 'uppercase']],
                'n_mapping' => ['type' => 'custom', 'char_filter' => ['no_dashes'], 'filter' => ['lowercase']],
                'n_pattern' => ['type' => 'custom', 'char_filter' => ['single_spaces'], 'filter' => ['no_vowels']],
                'n_elision' => ['type' => 'custom', 'filter' => ['lowercase', 'french_elision']],
                'n_german' => ['type' => 'custom', 'filter' => ['lowercase', 'german_normalization', 'decimal_digit']],
            ],
        ];
    }

    /**
     * @return array<string, array<string, mixed>>
     */
    public static function all(): array
    {
        $standard = static fn (array $filter, array $extra = []): array => ['tokenizer' => 'standard', 'filter' => $filter] + $extra;
        $decompounderSince = [
            'since' => '9.0.0',
            'reason' => 'Elasticsearch 8 returns every dictionary match (or the longest one at each position without skipping it); Elasticsearch 9 (and this library) keeps the longest match and skips past it.',
        ];
        $pathPositions = [
            'positions_since' => '9.0.0',
            'reason' => 'Elasticsearch 8 puts every level at position 0; Elasticsearch 9 (and this library) increments positions.',
        ];
        $phonetic = [
            'plugin' => 'analysis-phonetic',
            'texts' => ['english', 'mixed case', 'url email', 'path', 'html', 'spaces'],
            'reason' => 'Accented letters are folded before encoding here; Elasticsearch leaves such words unchanged.',
        ];

        return [
            // Built-in analyzers
            'analyzer standard' => ['analyzer' => 'standard'],
            'analyzer simple' => ['analyzer' => 'simple'],
            'analyzer whitespace' => ['analyzer' => 'whitespace'],
            'analyzer keyword' => ['analyzer' => 'keyword'],
            'analyzer stop' => ['analyzer' => 'stop'],
            'analyzer fingerprint' => ['analyzer' => 'fingerprint'],
            'analyzer english' => ['analyzer' => 'english'],
            'analyzer french' => ['analyzer' => 'french'],
            'analyzer german' => ['analyzer' => 'german'],
            'analyzer spanish' => ['analyzer' => 'spanish'],
            'analyzer italian' => ['analyzer' => 'italian'],
            'analyzer portuguese' => ['analyzer' => 'portuguese'],
            'analyzer dutch' => ['analyzer' => 'dutch'],
            'analyzer swedish' => ['analyzer' => 'swedish'],
            'analyzer norwegian' => ['analyzer' => 'norwegian'],
            'analyzer danish' => ['analyzer' => 'danish'],
            'analyzer finnish' => ['analyzer' => 'finnish'],
            'analyzer russian' => ['analyzer' => 'russian'],

            // Tokenizers
            'tokenizer standard' => ['tokenizer' => 'standard'],
            'tokenizer standard max_token_length' => ['tokenizer' => ['type' => 'standard', 'max_token_length' => 5]],
            'tokenizer whitespace' => ['tokenizer' => 'whitespace'],
            'tokenizer letter' => ['tokenizer' => 'letter'],
            'tokenizer keyword' => ['tokenizer' => 'keyword'],
            'tokenizer pattern' => ['tokenizer' => 'pattern'],
            'tokenizer pattern comma' => ['tokenizer' => ['type' => 'pattern', 'pattern' => ',']],
            'tokenizer pattern unicode' => ['tokenizer' => ['type' => 'pattern', 'pattern' => '\\W+', 'flags' => 'UNICODE_CHARACTER_CLASS']],
            'tokenizer pattern group' => ['tokenizer' => ['type' => 'pattern', 'pattern' => '(\\p{Lu})\\p{Ll}+', 'group' => 1]],
            'tokenizer uax_url_email' => ['tokenizer' => 'uax_url_email'],
            'tokenizer ngram' => ['tokenizer' => ['type' => 'ngram', 'min_gram' => 2, 'max_gram' => 3, 'token_chars' => ['letter', 'digit']]],
            'tokenizer edge_ngram' => ['tokenizer' => ['type' => 'edge_ngram', 'min_gram' => 1, 'max_gram' => 4, 'token_chars' => ['letter']]],
            'tokenizer path_hierarchy' => ['tokenizer' => 'path_hierarchy'] + $pathPositions,
            'tokenizer path_hierarchy reverse' => ['tokenizer' => ['type' => 'path_hierarchy', 'reverse' => true]] + $pathPositions,
            'tokenizer path_hierarchy skip' => ['tokenizer' => ['type' => 'path_hierarchy', 'skip' => 1]] + $pathPositions,
            'tokenizer char_group' => ['tokenizer' => ['type' => 'char_group', 'tokenize_on_chars' => ['whitespace', '-', 'punctuation']]],

            // Character filters
            'char_filter html_strip' => ['tokenizer' => 'standard', 'char_filter' => ['html_strip']],
            'char_filter mapping' => ['tokenizer' => 'standard', 'char_filter' => [['type' => 'mapping', 'mappings' => ['é => e', 'ü => ue']]]],
            'char_filter pattern_replace' => ['tokenizer' => 'standard', 'char_filter' => [['type' => 'pattern_replace', 'pattern' => '(\d+),(\d+)', 'replacement' => '$1.$2']]],

            // Token filters
            'filter lowercase' => $standard(['lowercase']),
            'filter uppercase' => $standard(['uppercase']),
            'filter asciifolding' => $standard(['asciifolding']),
            'filter asciifolding preserve_original' => $standard([['type' => 'asciifolding', 'preserve_original' => true]]),
            'filter stop english' => $standard(['lowercase', 'stop']),
            'filter stop french' => $standard(['lowercase', ['type' => 'stop', 'stopwords' => '_french_']]),
            'filter stop custom keep trailing' => $standard([['type' => 'stop', 'stopwords' => ['the', 'bone', 'été'], 'remove_trailing' => false]]),
            'filter elision' => $standard([['type' => 'elision', 'articles_case' => true, 'articles' => ['l', 'm', 't', 'qu', 'n', 's', 'j', 'd', 'c', 'jusqu', 'quoiqu', 'lorsqu', 'puisqu']]]),
            'filter ngram' => ['tokenizer' => 'whitespace', 'filter' => [['type' => 'ngram', 'min_gram' => 1, 'max_gram' => 2]]],
            'filter edge_ngram' => $standard([['type' => 'edge_ngram', 'min_gram' => 2, 'max_gram' => 4]]),
            'filter reverse' => $standard(['reverse']),
            'filter truncate' => $standard([['type' => 'truncate', 'length' => 3]]),
            'filter limit' => $standard([['type' => 'limit', 'max_token_count' => 3]]),
            'filter unique' => $standard(['lowercase', 'unique']),
            'filter length' => $standard([['type' => 'length', 'min' => 3, 'max' => 5]]),
            'filter trim' => ['tokenizer' => 'keyword', 'filter' => ['trim']],
            'filter fingerprint' => $standard(['lowercase', 'fingerprint']),
            'filter word_delimiter' => ['tokenizer' => 'whitespace', 'filter' => ['word_delimiter']],
            'filter word_delimiter catenate' => ['tokenizer' => 'whitespace', 'filter' => [['type' => 'word_delimiter', 'catenate_words' => true, 'catenate_numbers' => true]]],
            'filter word_delimiter catenate_all preserve_original' => ['tokenizer' => 'whitespace', 'filter' => [['type' => 'word_delimiter', 'catenate_all' => true, 'catenate_words' => true, 'preserve_original' => true]]],
            'filter word_delimiter no parts' => ['tokenizer' => 'whitespace', 'filter' => [['type' => 'word_delimiter', 'generate_word_parts' => false, 'generate_number_parts' => false, 'catenate_all' => true]]],
            'filter apostrophe' => $standard(['apostrophe']),
            'filter possessive_english' => $standard([['type' => 'stemmer', 'language' => 'possessive_english']]),
            'filter stemmer english' => $standard(['lowercase', ['type' => 'stemmer', 'language' => 'english']]),
            'filter stemmer light_french' => $standard(['lowercase', ['type' => 'stemmer', 'language' => 'light_french']]),
            'filter stemmer minimal_french' => $standard(['lowercase', ['type' => 'stemmer', 'language' => 'minimal_french']]),
            'filter stemmer light_german' => $standard(['lowercase', 'german_normalization', ['type' => 'stemmer', 'language' => 'light_german']]),
            'filter stemmer light_spanish' => $standard(['lowercase', ['type' => 'stemmer', 'language' => 'light_spanish']]),
            'filter stemmer light_italian' => $standard(['lowercase', ['type' => 'stemmer', 'language' => 'light_italian']]),
            'filter stemmer light_portuguese' => $standard(['lowercase', ['type' => 'stemmer', 'language' => 'light_portuguese']]),
            'filter german_normalization' => $standard(['lowercase', 'german_normalization']),
            'filter dictionary_decompounder' => $standard(['lowercase', ['type' => 'dictionary_decompounder', 'word_list' => self::COMPOUND_WORDS]]) + $decompounderSince,
            'filter dictionary_decompounder only_longest_match' => $standard(['lowercase', ['type' => 'dictionary_decompounder', 'word_list' => self::COMPOUND_WORDS, 'only_longest_match' => true, 'min_subword_size' => 3]]) + $decompounderSince,
            'filter hyphenation_decompounder' => $standard(['lowercase', ['type' => 'hyphenation_decompounder', 'hyphenation_patterns_path' => self::HYPHENATION_PATTERNS, 'word_list' => self::COMPOUND_WORDS]]),
            'filter hyphenation_decompounder only_longest_match' => $standard(['lowercase', ['type' => 'hyphenation_decompounder', 'hyphenation_patterns_path' => self::HYPHENATION_PATTERNS, 'word_list' => self::COMPOUND_WORDS, 'only_longest_match' => true]]),
            'filter hyphenation_decompounder no_sub_matches' => $standard(['lowercase', ['type' => 'hyphenation_decompounder', 'hyphenation_patterns_path' => self::HYPHENATION_PATTERNS, 'word_list' => self::COMPOUND_WORDS, 'no_sub_matches' => true]]),
            'filter hyphenation_decompounder no_overlapping_matches' => $standard(['lowercase', ['type' => 'hyphenation_decompounder', 'hyphenation_patterns_path' => self::HYPHENATION_PATTERNS, 'word_list' => self::COMPOUND_WORDS, 'no_overlapping_matches' => true]]),
            'filter scandinavian_folding' => $standard(['scandinavian_folding']),
            'filter scandinavian_normalization' => $standard(['scandinavian_normalization']),
            'filter stemmer french snowball' => $standard(['lowercase', ['type' => 'snowball', 'language' => 'French']]),
            'filter porter_stem' => $standard(['lowercase', 'porter_stem']),
            'filter keyword_marker' => $standard(['lowercase', ['type' => 'keyword_marker', 'keywords' => ['chevaux', 'jumped']], 'porter_stem']),
            'filter stemmer_override' => $standard(['lowercase', ['type' => 'stemmer_override', 'rules' => ['chevaux => horse', 'jumped => jump']], 'porter_stem']),
            'filter synonym' => $standard(['lowercase', ['type' => 'synonym', 'synonyms' => ['quick, fast', 'lazy => idle', 'école, school']]]),
            'filter shingle' => $standard(['lowercase', 'shingle']),
            'filter shingle 3 no unigrams' => $standard(['lowercase', ['type' => 'shingle', 'max_shingle_size' => 3, 'output_unigrams' => false]]),
            'filter shingle after stop' => $standard(['lowercase', 'stop', 'shingle']),
            'filter keep' => $standard(['lowercase', ['type' => 'keep', 'keep_words' => ['quick', 'école', 'katzen']]]),
            'filter keep_types' => ['tokenizer' => 'uax_url_email', 'filter' => [['type' => 'keep_types', 'types' => ['<EMAIL>', '<URL>']]]],
            'filter pattern_replace' => $standard([['type' => 'pattern_replace', 'pattern' => '[aeiou]', 'replacement' => '']]),
            'filter pattern_capture' => ['tokenizer' => 'whitespace', 'filter' => [['type' => 'pattern_capture', 'patterns' => ['(\p{Ll}+)', '(\d+)']]]],
            'filter multiplexer' => $standard([['type' => 'multiplexer', 'filters' => ['lowercase', 'lowercase, porter_stem']]]),
            'filter decimal_digit' => ['tokenizer' => 'whitespace', 'filter' => ['decimal_digit']],

            // Normalizers
            'normalizer lowercase (built-in)' => ['normalizer' => 'lowercase'],
            'normalizer lowercase + asciifolding' => ['normalizer' => 'n_lowercase_folding'],
            'normalizer trim + uppercase' => ['normalizer' => 'n_trim_upper'],
            'normalizer mapping' => ['normalizer' => 'n_mapping'],
            'normalizer pattern_replace' => ['normalizer' => 'n_pattern'],
            'normalizer elision' => ['normalizer' => 'n_elision'],
            'normalizer german_normalization + decimal_digit' => ['normalizer' => 'n_german'],

            // Plugins
            'filter icu_normalizer' => $standard(['icu_normalizer'], ['plugin' => 'analysis-icu']),
            'filter icu_folding' => $standard(['icu_folding'], ['plugin' => 'analysis-icu']),
            'char_filter icu_normalizer' => ['tokenizer' => 'standard', 'char_filter' => ['icu_normalizer'], 'plugin' => 'analysis-icu'],
            'filter phonetic soundex' => $standard([['type' => 'phonetic', 'encoder' => 'soundex']], $phonetic),
            'filter phonetic refined_soundex' => $standard([['type' => 'phonetic', 'encoder' => 'refined_soundex']], $phonetic),
            'filter phonetic metaphone' => $standard([['type' => 'phonetic', 'encoder' => 'metaphone']], $phonetic),
            'filter phonetic cologne' => $standard([['type' => 'phonetic', 'encoder' => 'cologne']], $phonetic),
        ];
    }
}
