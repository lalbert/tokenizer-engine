<?php

declare(strict_types=1);

namespace TokenizerEngine\TokenFilter;

use TokenizerEngine\Phonetic;
use TokenizerEngine\Phonetic\PhoneticEncoderInterface;
use TokenizerEngine\Token;

/**
 * Replaces terms with their phonetic code, like the `phonetic` filter of
 * Elasticsearch's phonetic plugin. With $replace = false the original term
 * is kept and the code is added at the same position.
 *
 * Built-in encoders: soundex, refined_soundex, metaphone (English),
 * cologne / koelnerphonetik (German), soundex_fr (French table of Soundex),
 * soundex2 (French, Frédéric BROUARD). Any PhoneticEncoderInterface can be
 * passed instead.
 */
final class PhoneticTokenFilter extends AbstractTermTokenFilter
{
    private readonly PhoneticEncoderInterface $encoder;

    public function __construct(
        string|PhoneticEncoderInterface $encoder = 'metaphone',
        private readonly bool $replace = true,
        int $maxCodeLength = 0,
    ) {
        $this->encoder = $encoder instanceof PhoneticEncoderInterface ? $encoder : self::create($encoder, $maxCodeLength);
    }

    public static function create(string $name, int $maxCodeLength = 0): PhoneticEncoderInterface
    {
        return match (strtolower($name)) {
            'soundex' => new Phonetic\SoundexEncoder(),
            'refined_soundex', 'refinedsoundex' => new Phonetic\RefinedSoundexEncoder(),
            'metaphone' => new Phonetic\MetaphoneEncoder($maxCodeLength),
            'cologne', 'koelnerphonetik' => new Phonetic\ColognePhoneticEncoder(),
            'soundex_fr', 'french_soundex' => new Phonetic\FrenchSoundexEncoder(),
            'soundex2' => new Phonetic\Soundex2Encoder(),
            default => throw new \InvalidArgumentException(\sprintf('Unknown phonetic encoder "%s" (available: soundex, refined_soundex, metaphone, cologne, soundex_fr, soundex2).', $name)),
        };
    }

    protected function filterTerm(string $term, Token $token): string|array
    {
        $code = $this->encoder->encode($term);

        if ('' === $code) {
            return $term;
        }

        if ($this->replace || $code === $term) {
            return $code;
        }

        return [$term, $code];
    }
}
