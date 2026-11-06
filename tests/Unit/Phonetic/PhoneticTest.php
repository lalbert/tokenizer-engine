<?php

declare(strict_types=1);

namespace TokenizerEngine\Tests\Unit\Phonetic;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use TokenizerEngine\Phonetic\ColognePhoneticEncoder;
use TokenizerEngine\Phonetic\FrenchSoundexEncoder;
use TokenizerEngine\Phonetic\MetaphoneEncoder;
use TokenizerEngine\Phonetic\PhoneticEncoderInterface;
use TokenizerEngine\Phonetic\RefinedSoundexEncoder;
use TokenizerEngine\Phonetic\Soundex2Encoder;
use TokenizerEngine\Phonetic\SoundexEncoder;
use TokenizerEngine\TokenFilter\PhoneticTokenFilter;

final class PhoneticTest extends TestCase
{
    /**
     * Examples published with each algorithm.
     *
     * @return iterable<string, array{PhoneticEncoderInterface, string, string}>
     */
    public static function examples(): iterable
    {
        // Frédéric BROUARD, https://sqlpro.developpez.com/cours/soundex/
        foreach (['MARTIN' => 'MRTN', 'BERNARD' => 'BRNR', 'FAURE' => 'FR', 'PEREZ' => 'PRZ', 'GROS' => 'GR', 'CHAPUIS' => 'CHP', 'BOYER' => 'BYR', 'GAUTHIER' => 'KTR', 'REY' => 'RY', 'BARTHELEMY' => 'BRTL', 'HENRY' => 'ANR', 'MOULIN' => 'MLN', 'ROUSSEAU' => 'RS'] as $word => $code) {
            yield 'soundex2 '.$word => [new Soundex2Encoder(), $word, $code];
        }
        // https://fr.wikipedia.org/wiki/Soundex
        foreach (['Robert' => 'R163', 'Rupert' => 'R163', 'Rubin' => 'R150'] as $word => $code) {
            yield 'soundex_fr '.$word => [new FrenchSoundexEncoder(), $word, $code];
        }
        // https://de.wikipedia.org/wiki/Kölner_Phonetik
        foreach (['Müller-Lüdenscheidt' => '65752682', 'Wikipedia' => '3412', 'Breschnew' => '17863'] as $word => $code) {
            yield 'cologne '.$word => [new ColognePhoneticEncoder(), $word, $code];
        }
        // Apache Commons Codec RefinedSoundexTest
        foreach (['testing' => 'T6036084', 'The' => 'T60', 'quick' => 'Q503', 'brown' => 'B1908', 'fox' => 'F205', 'jumped' => 'J408106', 'over' => 'O0209', 'lazy' => 'L7050', 'dogs' => 'D6043'] as $word => $code) {
            yield 'refined_soundex '.$word => [new RefinedSoundexEncoder(), $word, $code];
        }
        // American Soundex as in Apache Commons Codec (H/W rule).
        foreach (['Robert' => 'R163', 'Rupert' => 'R163', 'Ashcraft' => 'A261', 'Tymczak' => 'T522', 'Pfister' => 'P236', 'PHP' => 'P000', 'Honeyman' => 'H555'] as $word => $code) {
            yield 'soundex '.$word => [new SoundexEncoder(), $word, $code];
        }
        yield 'soundex accents' => [new SoundexEncoder(), 'Élodie', 'E430'];
        // PHP's metaphone() encodes "TH" as "0".
        yield 'metaphone Thompson' => [new MetaphoneEncoder(), 'Thompson', '0MPSN'];
    }

    #[DataProvider('examples')]
    public function testEncoders(PhoneticEncoderInterface $encoder, string $word, string $expected): void
    {
        self::assertSame($expected, $encoder->encode($word));
    }

    public function testEmptyInput(): void
    {
        foreach (['soundex', 'refined_soundex', 'metaphone', 'cologne', 'soundex_fr', 'soundex2'] as $name) {
            self::assertSame('', PhoneticTokenFilter::create($name)->encode('42'), $name);
        }
    }

    public function testFilter(): void
    {
        self::assertSame(['LFVR', 'LFVR'], (new PhoneticTokenFilter('soundex2'))->filter(['Lefèvre', 'Lefeuvre']));
        self::assertSame(['Martin', 'MRTN', '42'], (new PhoneticTokenFilter('soundex2', false))->filter(['Martin', '42']));
        self::assertSame(['0M'], (new PhoneticTokenFilter('metaphone', true, 2))->filter(['Thompson']));
    }

    public function testCustomEncoder(): void
    {
        $encoder = new class implements PhoneticEncoderInterface {
            public function encode(string $word): string
            {
                return strtoupper($word[0]);
            }
        };

        self::assertSame(['A'], (new PhoneticTokenFilter($encoder))->filter(['abc']));
    }

    public function testUnknownEncoder(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        new PhoneticTokenFilter('beider_morse');
    }
}
