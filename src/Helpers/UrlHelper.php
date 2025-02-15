<?php
/**
 * Copyright (c) 2020 LKK All rights reserved
 * User: kakuilan
 * Date: 2020/2/20
 * Time: 17:03
 * Desc: URL助手类
 */

namespace Kph\Helpers;


/**
 * Class UrlHelper
 * @package Kph\Helpers
 */
class UrlHelper {


    /**
     * 中文urlencode(对URL中有中文的部分进行编码处理)
     * 如: http://www.abc3210.com/s?wd=博客
     * 编码结果: http://www.abc3210.com/s?wd=%E5%8D%9A%20%E5%AE%A2
     * @param string $url
     * @return string
     */
    public static function cnUrlencode(string $url): string {
        //if (preg_match_all(RegularHelper::$patternChineseChar, $url, $matchArray)) {//匹配中文，返回数组
        if (preg_match_all(RegularHelper::$patternWidthChar, $url, $matchArray)) {//匹配双字节字符，返回数组
            foreach ($matchArray[0] as $key => $val) {
                $url = str_replace($val, urlencode($val), $url); //将转译替换中文
            }
            if (strpos($url, ' ')) {//若存在空格
                $url = str_replace(' ', '%20', $url);
            }
        }
        return $url;
    }


    /**
     * 中文urldecode
     * @param string $url
     * @return string
     */
    public static function cnUrldecode(string $url): string {
        $res = "";
        $pos = 0;
        $len = strlen($url);
        while ($pos < $len) {
            $charAt = substr($url, $pos, 1);
            if ($charAt == '%') {
                $pos++;
                $charAt = substr($url, $pos, 1);
                if ($charAt == 'u') {
                    // we got a unicode character
                    $pos++;
                    $unicodeHexVal = substr($url, $pos, 4);
                    $unicode       = hexdec($unicodeHexVal);
                    $entity        = "&#" . $unicode . ';';
                    $res           .= utf8_encode($entity);
                    $pos           += 4;
                } else {
                    // we have an escaped ascii character
                    $hexVal = substr($url, $pos, 2);
                    $res    .= chr(hexdec($hexVal));
                    $pos    += 2;
                }
            } else {
                $res .= $charAt;
                $pos++;
            }
        }
        return $res;
    }


    /**
     * 根据键值对数组,组建uri(带?的url参数串).
     * 若$replaceKeys非空,而$replaceVals为空时,则是删除$replaceKeys包含的键(参数名).
     * @param array $params 参数数组,最多二维
     * @param array $replaceKeys 要替换的键
     * @param array $replaceVals 要替换的值
     * @return string
     */
    public static function buildUriParams(array $params, array $replaceKeys = [], array $replaceVals = []) {
        foreach ($replaceKeys as $key) {
            unset($params[$key]);
        }

        $res = '';
        foreach ($params as $k => $v) {
            if (is_array($v)) {
                foreach ($v as $k2 => $v2) {
                    if (is_int($k2)) {
                        $k2 = '';
                    }
                    $res .= "&{$k}[{$k2}]={$v2}";
                }
            } else {
                $res .= "&{$k}={$v}";
            }
        }

        if (!empty($replaceVals)) {
            foreach ($replaceVals as $k => $val) {
                $key = $replaceKeys[$k] ?? '';
                if (!empty($key)) {
                    $res .= "&{$key}={$val}";
                }
            }
        }

        $res[0] = '?';
        return $res;
    }


    /**
     * 格式化URL(替换重复的//)
     * @param string $url
     * @return string
     */
    public static function formatUrl(string $url): string {
        if (!stripos($url, '://')) {
            $url = 'http://' . $url;
        }
        $url = str_replace("\\", "/", $url);
        return preg_replace('/([^:])[\/]{2,}/', '$1/', $url);
    }


    /**
     * 检查URL是否正常存在
     * @param string $url
     * @return bool
     */
    public static function checkUrlExists(string $url): bool {
        if (empty($url)) {
            return false;
        }

        if (!stripos($url, '://')) {
            $url = 'http://' . $url;
        }
        if (!ValidateHelper::isUrl($url)) {
            return false;
        }

        $header = @get_headers($url, true);

        return isset($header[0]) && (strpos($header[0], '200') || strpos($header[0], '304'));
    }


    /**
     * 将URL转换为链接标签
     * @param string $url 含URL的字符串
     * @param array $protocols 要转换的协议, http/https, ftp/ftps, mail
     * @param string $target 是否新页面打开:_blank,_self,默认为空
     * @return string
     */
    public static function url2Link(string $url, array $protocols = ['http', 'https'], string $target = ''): string {
        if (!empty($url)) {
            if (!empty(array_intersect($protocols, ['http', 'https']))) {
                $pattern = '@(http(s)?)?(://)?(([a-zA-Z])([-\w]+\.)+([^\s\.]+[^\s]*)+[^,.\s])@i';
                $url     = preg_replace($pattern, "<a href=\"http$2://$4\" target=\"{$target}\">$0</a>", $url);
            }

            if (!empty(array_intersect($protocols, ['ftp', 'ftps']))) {
                $pattern = '/(ftp|ftps)\:\/\/[-a-zA-Z0-9@:%_+.~#?&\/=]+(\/\S*)?/i';
                $url     = preg_replace($pattern, "<a href=\"$0\" target=\"{$target}\">$0</a>", $url);
            }

            if (in_array('mail', $protocols)) {
                $pattern = '/([^\s<]+?@[^\s<]+?\.[^\s<]+)(?<![\.,:])/';
                $url     = preg_replace($pattern, "<a href=\"mailto:$0\" target=\"{$target}\">$0</a>", $url);
            }
        }

        return $url;
    }


    /**
     * 获取域名
     * @param string $url
     * @param bool $firstLevel 是否获取一级域名,如:abc.test.com取test.com
     * @param array $server server信息
     * @return string
     */
    public static function getDomain(string $url, bool $firstLevel = false, array $server = []): string {
        if (empty($server)) {
            $server = $_SERVER;
        }
        if (empty($url)) {
            $url = $server['HTTP_HOST'] ?? '';
        }

        if (!stripos($url, '://')) {
            $url = 'http://' . $url;
        }

        $parse  = parse_url(strtolower($url));
        $domain = '';
        if (isset($parse['host'])) {
            $domain = $parse['host'];
        }

        if ($firstLevel) {
            $arr  = explode('.', $domain);
            $size = count($arr);
            if ($size >= 2) {
                $domain = $arr[$size - 2] . '.' . end($arr);
            }
        }

        return $domain;
    }


    /**
     * 获取当前页面完整URL地址
     * @param array $server server信息
     * @return string
     */
    public static function getUrl(array $server = []): string {
        if (empty($server)) {
            $server = $_SERVER;
        }

        $protocal  = ($server['SERVER_PORT'] ?? '') == '443' ? 'https://' : 'http://';
        $phpSelf   = $server['PHP_SELF'] ?? $server['SCRIPT_NAME'];
        $pathInfo  = $server['PATH_INFO'] ?? '';
        $relateUrl = $server['REQUEST_URI'] ?? ltrim($phpSelf, '/') . (isset($server['QUERY_STRING']) ? '?' . $server['QUERY_STRING'] : $pathInfo);
        return $protocal . ($server['HTTP_HOST'] ?? '') . $relateUrl;
    }


    /**
     * 获取URI
     * @param array $server
     * @return string
     */
    public static function getUri(array $server = []): string {
        if (empty($server)) {
            $server = $_SERVER;
        }

        if (isset($server['REQUEST_URI'])) {
            return $server['REQUEST_URI'];
        }

        $uri = ($server['PHP_SELF'] ?? '') . "?" . ($server['QUERY_STRING'] ?? ($server['argv'][0] ?? ''));
        return $uri;
    }


    /**
     * 获取站点URL
     * @param string $url 网址
     * @param array $server server信息
     * @return string
     */
    public static function getSiteUrl(string $url, array $server = []): string {
        if (empty($url)) {
            $url = self::getUrl($server);
        }

        if (!stripos($url, '://')) {
            $url = 'http://' . $url;
        }

        if (!ValidateHelper::isUrl($url)) {
            return '';
        }

        $arr    = parse_url($url);
        $port   = $arr['port'] ?? null;
        $scheme = $arr['scheme'] ?? null;
        if (!empty($port) && !in_array($port, [80, 443])) {
            $url = "{$scheme}://{$arr['host']}:{$port}/";
        } else {
            $url = "{$scheme}://{$arr['host']}/";
        }

        $url = strtolower($url);

        return self::formatUrl($url);
    }

}