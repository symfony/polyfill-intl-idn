<?php

/*
 * This file is part of the Symfony package.
 *
 * (c) Fabien Potencier <fabien@symfony.com>
 *
 * For the full copyright and license information, please view the LICENSE
 * file that was distributed with this source code.
 */

use Symfony\Polyfill\Intl\Idn as p;

if (!function_exists('idn_to_ascii')) {
    define('IDNA_DEFAULT', 0);
    define('IDNA_ALLOW_UNASSIGNED', 1);
    define('IDNA_USE_STD3_RULES', 2);

    if (\PHP_VERSION_ID >= 50400) { define('IDNA_CHECK_BIDI', 4); }
    if (\PHP_VERSION_ID >= 50400) { define('IDNA_CHECK_CONTEXTJ', 8); }
    if (\PHP_VERSION_ID >= 50400) { define('IDNA_NONTRANSITIONAL_TO_ASCII', 16); }
    if (\PHP_VERSION_ID >= 50400) { define('IDNA_NONTRANSITIONAL_TO_UNICODE', 32); }

    define('INTL_IDNA_VARIANT_2003', 0);
    define('INTL_IDNA_VARIANT_UTS46', 1);

    if (\PHP_VERSION_ID < 70400) { define('INTL_IDNA_VARIANT_DEFAULT', INTL_IDNA_VARIANT_2003); }
    if (\PHP_VERSION_ID >= 70400) { define('INTL_IDNA_VARIANT_DEFAULT', INTL_IDNA_VARIANT_UTS46); }

    function idn_to_ascii($domain, $options = IDNA_DEFAULT, $variant = INTL_IDNA_VARIANT_DEFAULT, $idna_info = array()) { return p\Idn::idn_to_ascii($domain, $options, $variant, $idna_info); }
    function idn_to_utf8($domain, $options = IDNA_DEFAULT, $variant = INTL_IDNA_VARIANT_DEFAULT, $idna_info = array()) { return p\Idn::idn_to_utf8($domain, $options, $variant, $idna_info); }
}
