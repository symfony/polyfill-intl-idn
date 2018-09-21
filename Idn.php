<?php

/*
 * Copyright (c) 2014 TrueServer B.V.
 *
 * Permission is hereby granted, free of charge, to any person obtaining a copy
 * of this software and associated documentation files (the "Software"), to deal
 * in the Software without restriction, including without limitation the rights
 * to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
 * copies of the Software, and to permit persons to whom the Software is furnished
 * to do so, subject to the following conditions:
 *
 * The above copyright notice and this permission notice shall be included in all
 * copies or substantial portions of the Software.
 *
 * THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
 * IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
 * FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
 * AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
 * LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
 * OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN
 * THE SOFTWARE.
 *
 * Originally forked from
 * https://github.com/true/php-punycode/blob/v2.1.1/src/Punycode.php
 */

namespace Symfony\Polyfill\Intl\Idn;

/**
 * Partial intl implementation in pure PHP.
 *
 * Implemented:
 * - idn_to_ascii - Convert domain name to IDNA ASCII form
 * - idn_to_utf8  - Convert domain name from IDNA ASCII to Unicode
 *
 * @author Renan Gonçalves <renan.saddam@gmail.com>
 * @author Sebastian Kroczek <sk@xbug.de>
 * @author Dmitry Lukashin <dmitry@lukashin.ru>
 * @author Laurent Bassin <laurent@bassin.info>
 *
 * @internal
 */
final class Idn
{
    const BASE = 36;
    const TMIN = 1;
    const TMAX = 26;
    const SKEW = 38;
    const DAMP = 700;
    const INITIAL_BIAS = 72;
    const INITIAL_N = 128;
    const PREFIX = 'xn--';
    const DELIMITER = '-';

    private static $encodeTable = array(
        'a', 'b', 'c', 'd', 'e', 'f', 'g', 'h', 'i', 'j', 'k', 'l',
        'm', 'n', 'o', 'p', 'q', 'r', 's', 't', 'u', 'v', 'w', 'x',
        'y', 'z', '0', '1', '2', '3', '4', '5', '6', '7', '8', '9',
    );

    private static $decodeTable = array(
        'a' => 0, 'b' => 1, 'c' => 2, 'd' => 3, 'e' => 4, 'f' => 5,
        'g' => 6, 'h' => 7, 'i' => 8, 'j' => 9, 'k' => 10, 'l' => 11,
        'm' => 12, 'n' => 13, 'o' => 14, 'p' => 15, 'q' => 16, 'r' => 17,
        's' => 18, 't' => 19, 'u' => 20, 'v' => 21, 'w' => 22, 'x' => 23,
        'y' => 24, 'z' => 25, '0' => 26, '1' => 27, '2' => 28, '3' => 29,
        '4' => 30, '5' => 31, '6' => 32, '7' => 33, '8' => 34, '9' => 35,
    );

    public static function idn_to_ascii($domain, $options, $variant, &$idna_info = array())
    {
        if (\PHP_VERSION_ID >= 70200 && INTL_IDNA_VARIANT_2003 === $variant) {
            @trigger_error('idn_to_ascii(): INTL_IDNA_VARIANT_2003 is deprecated', E_USER_DEPRECATED);
        }

        $input = $domain;
        if (INTL_IDNA_VARIANT_UTS46 === $variant) {
            $input = mb_strtolower($input, 'utf-8');
        }

        $parts = explode('.', $input);

        foreach ($parts as &$part) {
            $length = \strlen($part);
            if ($length < 1) {
                return false;
            }
            $part = self::encodePart($part);
            if (false === $part) {
                return false;
            }
        }

        $output = implode('.', $parts);
        $length = \strlen($output);
        if ($length > 255) {
            return false;
        }

        return $output;
    }

    public static function idn_to_utf8($domain, $options, $variant, &$idna_info = array())
    {
        if (\PHP_VERSION_ID >= 70200 && INTL_IDNA_VARIANT_2003 === $variant) {
            @trigger_error('idn_to_utf8(): INTL_IDNA_VARIANT_2003 is deprecated', E_USER_DEPRECATED);
        }

        $parts = explode('.', $domain);
        foreach ($parts as &$part) {
            $length = \strlen($part);
            if ($length > 63 || $length < 1) {
                continue;
            }
            if (0 !== strpos($part, self::PREFIX)) {
                continue;
            }

            $part = substr($part, \strlen(self::PREFIX));
            $part = self::decodePart($part);
        }
        $output = implode('.', $parts);
        $length = \strlen($output);
        if ($length > 255) {
            return false;
        }

        return $output;
    }

    private static function encodePart($input)
    {
        $codePoints = self::listCodePoints($input);

        $n = self::INITIAL_N;
        $bias = self::INITIAL_BIAS;
        $delta = 0;
        $h = $b = \count($codePoints['basic']);

        $output = '';
        foreach ($codePoints['basic'] as $code) {
            $output .= mb_chr($code, 'utf-8');
        }
        if ($input === $output) {
            return $output;
        }
        if ($b > 0) {
            $output .= self::DELIMITER;
        }

        $codePoints['nonBasic'] = array_unique($codePoints['nonBasic']);
        sort($codePoints['nonBasic']);

        $i = 0;
        $length = mb_strlen($input, 'utf-8');
        while ($h < $length) {
            $m = $codePoints['nonBasic'][$i++];
            $delta = $delta + ($m - $n) * ($h + 1);
            $n = $m;

            foreach ($codePoints['all'] as $c) {
                if ($c < $n || $c < self::INITIAL_N) {
                    ++$delta;
                }
                if ($c === $n) {
                    $q = $delta;
                    for ($k = self::BASE;; $k += self::BASE) {
                        $t = self::calculateThreshold($k, $bias);
                        if ($q < $t) {
                            break;
                        }

                        $code = $t + (($q - $t) % (self::BASE - $t));
                        $output .= self::$encodeTable[$code];

                        $q = ($q - $t) / (self::BASE - $t);
                    }

                    $output .= self::$encodeTable[$q];
                    $bias = self::adapt($delta, $h + 1, ($h === $b));
                    $delta = 0;
                    ++$h;
                }
            }

            ++$delta;
            ++$n;
        }
        $out = self::PREFIX.$output;
        $length = \strlen($out);
        if ($length > 63 || $length < 1) {
            return false;
        }

        $out = strtolower($out);

        return $out;
    }

    private static function listCodePoints($input)
    {
        $codePoints = array(
            'all' => array(),
            'basic' => array(),
            'nonBasic' => array(),
        );

        $length = mb_strlen($input, 'utf-8');
        for ($i = 0; $i < $length; ++$i) {
            $char = mb_substr($input, $i, 1, 'utf-8');
            $code = mb_ord($char, 'utf-8');
            if ($code < 128) {
                $codePoints['all'][] = $codePoints['basic'][] = $code;
            } else {
                $codePoints['all'][] = $codePoints['nonBasic'][] = $code;
            }
        }

        return $codePoints;
    }

    private static function calculateThreshold($k, $bias)
    {
        if ($k <= $bias + self::TMIN) {
            return self::TMIN;
        } elseif ($k >= $bias + self::TMAX) {
            return self::TMAX;
        }
        return $k - $bias;
    }

    private static function adapt($delta, $numPoints, $firstTime)
    {
        $delta = (int) ($firstTime ? $delta / self::DAMP : $delta / 2);
        $delta += (int) ($delta / $numPoints);

        $k = 0;
        while ($delta > ((self::BASE - self::TMIN) * self::TMAX) / 2) {
            $delta = (int) ($delta / (self::BASE - self::TMIN));
            $k = $k + self::BASE;
        }

        return $k + (int) (((self::BASE - self::TMIN + 1) * $delta) / ($delta + self::SKEW));
    }

    private static function decodePart($input)
    {
        $n = self::INITIAL_N;
        $i = 0;
        $bias = self::INITIAL_BIAS;
        $output = '';

        $pos = strrpos($input, self::DELIMITER);
        if (false !== $pos) {
            $output = substr($input, 0, $pos++);
        } else {
            $pos = 0;
        }

        $outputLength = \strlen($output);
        $inputLength = \strlen($input);
        while ($pos < $inputLength) {
            $oldi = $i;
            $w = 1;

            for ($k = self::BASE;; $k += self::BASE) {
                $digit = self::$decodeTable[$input[$pos++]];
                $i = $i + ($digit * $w);
                $t = self::calculateThreshold($k, $bias);

                if ($digit < $t) {
                    break;
                }

                $w = $w * (self::BASE - $t);
            }

            $bias = self::adapt($i - $oldi, ++$outputLength, (0 === $oldi));
            $n = $n + (int) ($i / $outputLength);
            $i = $i % ($outputLength);
            $output = mb_substr($output, 0, $i, 'utf-8').mb_chr($n, 'utf-8').mb_substr($output, $i, $outputLength - 1, 'utf-8');

            ++$i;
        }

        return $output;
    }
}
