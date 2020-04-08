<?php

/**
 * Copyright © OXID eSales AG. All rights reserved.
 * See LICENSE file for license details.
 */

namespace OxidEsales\EshopCommunity\Core;

/**
 * Class oxDecryptor
 */
class Decryptor
{
    /**
     * Decrypts string with given key.
     *
     * @param string $string string
     * @param string $key    key
     *
     * @return string
     */
    public function decrypt($string, $key)
    {
        $key = $this->_formKey($key, $string);

        $string = substr($string, 3);
        $string = str_replace('!', '=', $string);
        $string = base64_decode($string);
        $string = $string ^ $key;

        return substr($string, 2, -2);
    }
    /**
     * @deprecated use self::formKey instead
     */
    protected function _formKey($key, $string) // phpcs:ignore PSR2.Methods.MethodDeclaration.Underscore
    {
        return self::formKey($key, $string);
    }

    /**
     * Forms key for use in encoding.
     *
     * @param string $key
     * @param string $string
     *
     * @return string
     */
    protected function formKey($key, $string)
    {
        $key = '_' . $key;
        $keyLength = (strlen($string) / strlen($key)) + 5;

        return str_repeat($key, $keyLength);
    }
}
