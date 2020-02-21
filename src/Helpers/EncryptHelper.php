<?php
/**
 * Copyright (c) 2020 LKK/lanq.net All rights reserved
 * User: kakuilan@163.com
 * Date: 2020/2/21
 * Time: 10:15
 * Desc: 加解密助手类
 */

namespace Kph\Helpers;

use Kph\Consts;

class EncryptHelper {


    /**
     * url安全的base64_encode
     * @param string $data
     * @return string
     */
    public static function base64UrlEncode(string $data): string {
        return rtrim(strtr(base64_encode($data), '+/', '-_'), '=');
    }


    /**
     * url安全的base64_decode
     * @param string $data
     * @return string
     */
    public static function base64UrlDecode(string $data): string {
        return base64_decode(str_pad(strtr($data, '-_', '+/'), strlen($data) % 4, '=', STR_PAD_RIGHT));
    }


    /**
     * 授权码生成及解码.返回结果为数组,分别是加密/解密的字符串和有效期时间戳.
     * @param string $data 数据
     * @param string $key 密钥
     * @param bool $encode 操作:true时为加密,false时为解密
     * @param int $expiry 有效期/秒,0为不限制
     * @return array
     */
    public static function authcode(string $data, string $key, bool $encode = true, int $expiry = 0): array {
        if ($data == '') {
            return ['', 0];
        } elseif (!$encode && strlen($data) < Consts::DYNAMIC_KEY_LEN) {
            return ['', 0];
        }

        $now = time();

        //密钥
        $key = md5($key);
        // 密钥a会参与加解密
        $keya = md5(substr($key, 0, 16));
        // 密钥b会用来做数据完整性验证
        $keyb = md5(substr($key, 16, 16));
        // 密钥c用于变化生成的密文
        $keyc      = $encode ? substr(md5(microtime()), -Consts::DYNAMIC_KEY_LEN) : substr($data, 0, Consts::DYNAMIC_KEY_LEN);
        $keyd      = md5($keya . $keyc);
        $cryptkey  = $keya . $keyd;
        $keyLength = strlen($cryptkey);

        if ($encode) {
            if ($expiry != 0) {
                $expiry = $expiry + $now;
            }
            $expMd5 = substr(md5($data . $keyb), 0, 16);
            $data   = sprintf('%010d', $expiry) . $expMd5 . $data;
        } else {
            $data = self::base64UrlDecode(substr($data, Consts::DYNAMIC_KEY_LEN));
        }

        $dataLen = strlen($data);
        $res     = '';
        $box     = range(0, 255);
        $rndkey  = [];
        for ($i = 0; $i <= 255; $i++) {
            $rndkey[$i] = ord($cryptkey[$i % $keyLength]);
        }

        for ($j = $i = 0; $i < 256; $i++) {
            $j       = ($j + $box[$i] + $rndkey[$i]) % 256;
            $tmp     = $box[$i];
            $box[$i] = $box[$j];
            $box[$j] = $tmp;
        }

        for ($a = $j = $i = 0; $i < $dataLen; $i++) {
            $a       = ($a + 1) % 256;
            $j       = ($j + $box[$a]) % 256;
            $tmp     = $box[$a];
            $box[$a] = $box[$j];
            $box[$j] = $tmp;
            $res     .= chr(ord($data[$i]) ^ ($box[($box[$a] + $box[$j]) % 256]));
        }

        if ($encode) {
            $res = $keyc . self::base64UrlEncode($res);
            return [$res, $expiry];
        } else {
            if (strlen($res) > 26) {
                $expTime = intval(substr($res, 0, 10));
                if (($expTime == 0 || $expTime - $now > 0) && substr($res, 10, 16) == substr(md5(substr($res, 26) . $keyb), 0, 16)) {
                    return [substr($res, 26), $expTime];
                }
            }

            return ['', 0];
        }
    }


    /**
     * 简单加密
     * @param string $data 数据
     * @param string $key 密钥
     * @return string
     */
    public static function easyEncrypt(string $data, string $key): string {
        if ($data == '') {
            return '';
        }

        $key     = md5($key);
        $dataLen = strlen($data);
        $keyLen  = strlen($key);
        $x       = 0;
        $str     = $char = '';
        for ($i = 0; $i < $dataLen; $i++) {
            if ($x == $keyLen) {
                $x = 0;
            }

            $str .= chr(ord($data[$i]) + (ord($key[$x])) % 256);
            $x++;
        }

        return substr($key, 0, Consts::DYNAMIC_KEY_LEN) . self::base64UrlEncode($str);
    }


    /**
     * 简单解密
     * @param string $data 数据
     * @param string $key 密钥
     * @return string
     */
    public static function easyDecrypt(string $data, string $key): string {
        if (strlen($data) < Consts::DYNAMIC_KEY_LEN) {
            return '';
        }

        $key = md5($key);
        if (substr($key, 0, Consts::DYNAMIC_KEY_LEN) != substr($data, 0, Consts::DYNAMIC_KEY_LEN)) {
            return '';
        }

        $data = self::base64UrlDecode(substr($data, Consts::DYNAMIC_KEY_LEN));
        if (empty($data)) {
            return '';
        }

        $dataLen = strlen($data);
        $keyLen  = strlen($key);
        $x       = 0;
        $str     = $char = '';
        for ($i = 0; $i < $dataLen; $i++) {
            if ($x == $keyLen) {
                $x = 0;
            }

            $c = ord($data[$i]);
            $k = ord($key[$x]);
            if ($c < $k) {
                $str .= chr(($c + 256) - $k);
            } else {
                $str .= chr($c - $k);
            }

            $x++;
        }

        return $str;
    }


}