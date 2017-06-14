<?php

namespace Huasituo\Es;

use phpQuery;
use Illuminate\Contracts\Foundation\Application as ApplicationContract;

class EsQuery
{
    /**
     * Container.
     *
     * @var \Illuminate\Contracts\Foundation\Application
     */
    protected $app;

    public $data;
    public $html = '';          //页面内容
    public $url = '';           //页面内容
    private $queryHtml = '';
    private $outputEncoding = null;         //【输出编码格式】指要以什么编码输出(UTF-8,GB2312,.....)，防止出现乱码,如果设置为 假值 则不改变原字符串编码
    private $inputEncoding = null;              //【输入编码格式】明确指定输入的页面编码格式(UTF-8,GB2312,.....)，防止出现乱码,如果设置为 假值 则自动识别
    private $htmlEncoding;                      //采集对象编码格式
    private $removeHead = false;

    /**
     *  Create EsQuery instance.
     *
     * @param \Illuminate\Contracts\Foundation\Application $app
     * @author Seven Du <shiweidu@outlook.com>
     */
    public function __construct(ApplicationContract $app)
    {
        $this->app = $app;
    }

    /**
     * Run ext.
     *
     * @param string $abstract
     * @param array $arguments
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public static function run($abstract, array $arguments = [])
    {
        return static::getInstance($abstract)->run($arguments);
    }

    /**
     * Get or create class instance.
     *
     * @param string $abstract
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public static function getInstance($abstract = 'es')
    {
        $arguments = func_get_args();
        array_shift($arguments);

        return $this->app->makeWith($abstract, $arguments);
    }

    /**
     * 获取目标页面源码(主要用于调试).
     * @param bool|true $rel
     * @return string
     */
    public function getHtml($rel = true)
    {
        return $rel ? $this->queryHtml : $this->html;
    }

    /**
     * 获取采集结果数据.
     * @param callback $callback
     * @return array
     */
    public function getData($callback = null)
    {
        if (is_callable($callback)) {
            return array_map($callback, $this->data);
        }

        return $this->data;
    }

    public static function getTable($data = [])
    {
        $data = $data ? $data : $this->data;
        if (! $data) {
            return '<table border="1"><tr><td>暂无内容</td></tr></table>';
        }
        $html = '<table border="1"><tr>';
        foreach ($data[0] as $k => $val) {
            $html .= '<td>'.$k.'</td>';
        }
        $html .= '</tr>';
        foreach ($data as $key => $value) {
            $html .= '<tr>';
            foreach ($value as $ks => $vs) {
                $html .= '<td>'.$vs.'</td>';
            }
            $html .= '</tr>';
        }
        $html .= '</table>';

        return $html;
    }

    public function getContentTable($data = 'nulls')
    {
        $data = $data != 'nulls' ? $data : $this->data;
        if (! $data) {
            return '<table border="1"><tr><td>暂无内容</td></tr></table>';
        }
        $html = '<table border="1">';
        foreach ($data as $k => $val) {
            $html .= '<tr><td>'.$k.'</td>';
            $html .= '<td>'.$val.'</td></tr>';
        }
        $html .= '</table>';

        return $html;
    }

    /**
     * 重新设置选择器.
     * @param $rules
     * @param string $range
     * @param string $outputEncoding
     * @param string $inputEncoding
     * @param bool|false $removeHead
     * @return QueryList
     */
    public function setQuery($outputEncoding = null, $inputEncoding = null, $removeHead = false)
    {
        return $this->query($this->html, $outputEncoding, $inputEncoding, $removeHead);
    }

    /**
     * Request page.
     *
     * @param string $page url
     * @param string|null $outputEncoding
     * @param string|null $inputEncoding
     * @param bool $removeHead
     * @return $this
     */
    public function query($page, $outputEncoding = null, $inputEncoding = null, $removeHead = false)
    {
        $this->data = [];
        $this->url = $this->_isURL($page) ? $page : '';
        $this->html = $this->_isURL($page) ? $this->_request($page) : $page;
        $outputEncoding && $this->outputEncoding = $outputEncoding;
        $inputEncoding && $this->inputEncoding = $inputEncoding;
        $removeHead && $this->html = $this->_removeHead($this->html);
        $this->queryHtml = '';
        if (empty($this->html)) {
            trigger_error('The received content is empty!', E_USER_NOTICE);
        }
        //获取编码格式
        $this->htmlEncoding = $this->inputEncoding ? $this->inputEncoding : $this->_getEncode($this->html);

        return $this;
    }

    public function queryList($regArr, $regRange = '')
    {
        $this->inputEncoding && phpQuery::$defaultCharset = $this->inputEncoding;
        $document = phpQuery::newDocumentHTML($this->html);
        $this->queryHtml = $document->htmlOuter();
        if (! empty($regRange)) {
            $robj = pq($document)->find($regRange);
            $i = 0;
            $unsets = [];
            foreach ($robj as $item) {
                while (list($key, $reg_value) = each($regArr)) {
                    if ($key == 'callback') {
                        continue;
                    }
                    $tags = isset($reg_value[3]) ? $reg_value[3] : '';
                    $config = isset($reg_value[2]) ? $reg_value[2] : [];
                    $iobj = pq($item)->find($reg_value[0]);
                    $x = 0;
                    $yappear = true;
                    $nappear = true;
                    foreach ($iobj as $items) {
                        switch ($reg_value[1]) {
                            case 'text':
                                $this->data[$i][$x][$key] = $this->_allowTags(pq($items)->html(), $tags);
                                break;
                            case 'html':
                                $this->data[$i][$x][$key] = $this->_stripTags(pq($items)->html(), $tags);
                                break;
                            default:
                                $this->data[$i][$x][$key] = pq($items)->attr($reg_value[1]);
                                break;
                        }
                        if (isset($config['trimall'])) {
                            $this->data[$i][$x][$key] = $this->trimall($this->data[$i][$x][$key]);
                        }
                        if (isset($config['find']) && isset($config['replace'])) {
                            $this->data[$i][$x][$key] = $this->str_replaces($config['find'], $config['replace'], $this->data[$i][$x][$key]);
                        }
                        if (isset($config['ftype'])) {
                            $this->data[$i][$x][$key] = $this->ftype($config['ftype'], $this->data[$i][$x][$key]);
                        }
                        //检测是否出现关键词－必须出现才要
                        if (isset($config['yappear']) && $config['yappear'] && $yappear) {
                            $yappear = $this->appears($config['yappear'], $this->data[$i][$x][$key]);
                        }
                        //检测是否出现关键词-出现就不要
                        if (isset($config['nappear']) && $config['nappear'] && $nappear) {
                            $nappear = $this->appears($config['nappear'], $this->data[$i][$x][$key]);
                        }
                        if (isset($reg_value[4])) {
                            $this->data[$i][$x][$key] = call_user_func($reg_value[4], $this->data[$i][$x][$key], $key);
                        } elseif (isset($regArr['callback'])) {
                            $this->data[$i][$x][$key] = call_user_func($regArr['callback'], $this->data[$i][$x][$key], $key);
                        }
                        if (isset($config['yappear']) && $config['yappear'] && $yappear) {
                            $unsets[$i][$x] = 1;
                        }
                        if (isset($config['nappear']) && $config['nappear'] && ! $nappear) {
                            $unsets[$i][$x] = 1;
                        }
                        if (isset($config['isrequired']) && $config['isrequired'] && ! $this->data[$i][$x][$key]) {
                            $unsets[$i][$x] = 1;
                        }
                        $x++;
                    }
                }
                //重置数组指针
                reset($regArr);
                $i++;
            }
            if ($unsets) {
                foreach ($unsets as $u => $v) {
                    unset($this->data[$u]);
                }
            }
        } else {
            $unsets = [];
            while (list($key, $reg_value) = each($regArr)) {
                if ($key == 'callback') {
                    continue;
                }
                $document = phpQuery::newDocumentHTML($this->html);
                $tags = isset($reg_value[3]) ? $reg_value[3] : '';
                $config = isset($reg_value[2]) ? $reg_value[2] : [];
                $lobj = pq($document)->find($reg_value[0]);
                $i = 0;
                foreach ($lobj as $item) {
                    $yappear = true;
                    $nappear = true;
                    switch ($reg_value[1]) {
                        case 'text':
                            $this->data[$i][$key] = $this->_allowTags(pq($item)->html(), $tags);
                            break;
                        case 'html':
                            $this->data[$i][$key] = $this->_stripTags(pq($item)->html(), $tags);
                            break;
                        default:
                            $this->data[$i][$key] = pq($item)->attr($reg_value[1]);
                            break;
                    }
                    if (isset($config['trimall'])) {
                        $this->data[$i][$key] = $this->trimall($this->data[$i][$key]);
                    }
                    if (isset($config['find']) && isset($config['replace'])) {
                        $this->data[$i][$key] = $this->str_replaces($config['find'], $config['replace'], $this->data[$i][$key]);
                    }
                    if (isset($config['ftype'])) {
                        $this->data[$i][$key] = $this->ftype($config['ftype'], $this->data[$i][$key]);
                    }
                    //检测是否出现关键词－必须出现才要
                    if (isset($config['yappear']) && $config['yappear'] && $yappear) {
                        $yappear = $this->appears($config['yappear'], $this->data[$i][$key]);
                    }
                    //检测是否出现关键词-出现就不要
                    if (isset($config['nappear']) && $config['nappear'] && $nappear) {
                        $nappear = $this->appears($config['nappear'], $this->data[$i][$key]);
                    }
                    if (isset($reg_value[4])) {
                        $this->data[$i][$key] = call_user_func($reg_value[4], $this->data[$i][$key], $key);
                    } elseif (isset($regArr['callback'])) {
                        $this->data[$i][$key] = call_user_func($regArr['callback'], $this->data[$i][$key], $key);
                    }
                    if (isset($config['yappear']) && $config['yappear'] && $yappear) {
                        $unsets[$i] = 1;
                    }
                    if (isset($config['nappear']) && $config['nappear'] && ! $nappear) {
                        $unsets[$i] = 1;
                    }
                    if (isset($config['isrequired']) && $config['isrequired'] && ! $this->data[$i][$key]) {
                        $unsets[$i] = 1;
                    }
                    $i++;
                }
            }
            if ($unsets) {
                foreach ($unsets as $u => $v) {
                    unset($this->data[$u]);
                }
            }
        }
        if ($this->outputEncoding) {
            //编码转换
            $this->data = $this->_arrayConvertEncoding($this->data, $this->outputEncoding, $this->htmlEncoding);
        }
        phpQuery::$documents = [];

        return $this;
    }

    public function queryContent($regArr, $regRange = '', $sysconfig = [])
    {
        $this->inputEncoding && phpQuery::$defaultCharset = $this->inputEncoding;
        $document = phpQuery::newDocumentHTML($this->html);
        $this->queryHtml = $document->htmlOuter();
        $isUrl = isset($sysconfig['isUrl']) ? $sysconfig['isUrl'] : true;
        if (! empty($regRange)) {
            $robj = pq($document)->find($regRange);
            $i = 0;
            foreach ($robj as $item) {
                $isUrl && $this->data[$i]['url'] = $this->url;
                $yappear = true;
                $nappear = true;
                while (list($key, $reg_value) = each($regArr)) {
                    if ($key == 'callback') {
                        continue;
                    }
                    $tags = isset($reg_value[3]) ? $reg_value[3] : '';
                    $config = isset($reg_value[2]) ? $reg_value[2] : [];
                    $iobj = pq($item)->find($reg_value[0]);
                    switch ($reg_value[1]) {
                        case 'text':
                            $this->data[$i][$key] = $this->_allowTags(pq($iobj)->html(), $tags);
                            break;
                        case 'html':
                            $this->data[$i][$key] = $this->_stripTags(pq($iobj)->html(), $tags);
                            break;
                        default:
                            $this->data[$i][$key] = pq($iobj)->attr($reg_value[1]);
                            break;
                    }
                    //是否替换空格
                    if (isset($config['trimall'])) {
                        $this->data[$i][$key] = $this->trimall($this->data[$i][$key]);
                    }
                    //关键词替换
                    if (isset($config['find']) && isset($config['replace'])) {
                        $this->data[$i][$key] = $this->str_replaces($config['find'], $config['replace'], $this->data[$i][$key]);
                    }
                    //字段类型
                    if (isset($config['ftype'])) {
                        $this->data[$i][$key] = $this->ftype($config['ftype'], $this->data[$i][$key]);
                    }
                    //检测是否出现关键词－必须出现才要
                    if (isset($config['yappear']) && $yappear) {
                        $yappear = $this->appears($config['yappear'], $this->data[$i][$key]);
                    }
                    //检测是否出现关键词-出现就不要
                    if (isset($config['nappear']) && $nappear) {
                        $nappear = $this->appears($config['nappear'], $this->data[$i][$key]);
                    }
                    if (isset($reg_value[4])) {
                        $this->data[$i][$key] = call_user_func($reg_value[3], $this->data[$i][$key], $key);
                    } elseif (isset($this->regArr['callback'])) {
                        $this->data[$i][$key] = call_user_func($regArr['callback'], $this->data[$i][$key], $key);
                    }
                    if (isset($config['yappear']) && $yappear) {
                        $this->data[$i] = [];
                        break;
                    }
                    if (isset($config['nappear']) && ! $nappear) {
                        $this->data[$i] = [];
                        break;
                    }
                    if (isset($config['isrequired']) && $config['isrequired'] && ! $this->data[$i][$key]) {
                        $this->data = [];
                        break;
                    }
                }
                //重置数组指针
                reset($regArr);
                $i++;
            }
        } else {
            $isUrl && $this->data['url'] = $this->url;
            while (list($key, $reg_value) = each($regArr)) {
                if ($key == 'callback') {
                    continue;
                }
                $document = phpQuery::newDocumentHTML($this->html);
                $tags = isset($reg_value[3]) ? $reg_value[3] : '';
                $config = isset($reg_value[2]) ? $reg_value[2] : [];
                $lobj = pq($document)->find($reg_value[0]);
                $yappear = true;
                $nappear = true;
                switch ($reg_value[1]) {
                    case 'text':
                        $this->data[$key] = $this->_allowTags(pq($lobj)->html(), $tags);
                        break;
                    case 'html':
                        $this->data[$key] = $this->_stripTags(pq($lobj)->html(), $tags);
                        break;
                    default:
                        $this->data[$key] = pq($lobj)->attr($reg_value[1]);
                        break;
                }
                //是否替换空格
                if (isset($config['trimall'])) {
                    $this->data[$key] = $this->trimall($this->data[$key]);
                }
                //关键词替换
                if (isset($config['find']) && isset($config['replace'])) {
                    $this->data[$key] = $this->str_replaces($config['find'], $config['replace'], $this->data[$key]);
                }
                //字段类型
                if (isset($config['ftype'])) {
                    $this->data[$key] = $this->ftype($config['ftype'], $this->data[$key]);
                }
                //检测是否出现关键词－必须出现才要
                if (isset($config['yappear']) && $config['yappear'] && $yappear) {
                    $yappear = $this->appears($config['yappear'], $this->data[$key]);
                }
                //检测是否出现关键词-出现就不要
                if (isset($config['nappear']) && $config['nappear'] && $nappear) {
                    echo $nappear = $this->appears($config['nappear'], $this->data[$key]);
                }
                //回调
                if (isset($reg_value[4])) {
                    $this->data[$key] = call_user_func($reg_value[4], $this->data[$key], $key);
                } elseif (isset($regArr['callback'])) {
                    $this->data[$key] = call_user_func($regArr['callback'], $this->data[$key], $key);
                }
                if (isset($config['yappear']) && $config['yappear'] && $yappear) {
                    $this->data = [];
                    break;
                }
                if (isset($config['nappear']) && $config['nappear'] && ! $nappear) {
                    $this->data = [];
                    break;
                }
                if (isset($config['isrequired']) && $config['isrequired'] && ! $this->data[$key]) {
                    $this->data = [];
                    break;
                }
            }
        }
        if ($this->outputEncoding) {
            $this->data = $this->_arrayConvertEncoding($this->data, $this->outputEncoding, $this->htmlEncoding);
        }
        phpQuery::$documents = [];

        return $this;
    }

    /**
     * 简单的判断一下参数是否为一个URL链接.
     * @param  string  $str
     * @return bool
     */
    private function _isURL($str)
    {
        if (preg_match('/^http(s)?:\\/\\/.+/', $str)) {
            return true;
        }

        return false;
    }

    /**
     * URL请求
     * @param $url
     * @return string
     */
    private function _request($url)
    {
        if (function_exists('curl_init')) {
            $ch = curl_init();
            curl_setopt($ch, CURLOPT_URL, $url);
            curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
            curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, false);
            curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
            curl_setopt($ch, CURLOPT_AUTOREFERER, true);
            curl_setopt($ch, CURLOPT_REFERER, $url);
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $result = curl_exec($ch);
            curl_close($ch);
        } elseif (version_compare(PHP_VERSION, '5.0.0') >= 0) {
            $opts = [
                'http' => [
                    'header' => "Referer:{$url}",
                ],
            ];
            $result = file_get_contents($url, false, stream_context_create($opts));
        } else {
            $result = file_get_contents($url);
        }

        return $result;
    }

    /**
     * 移除页面head区域代码
     * @param $html
     * @return mixed
     */
    private function _removeHead($html)
    {
        return preg_replace('/<head.+?>.+<\/head>/is', '<head></head>', $html);
    }

    /**
     * 获取文件编码
     * @param $string
     * @return string
     */
    private function _getEncode($string)
    {
        return mb_detect_encoding($string, ['ASCII', 'GB2312', 'GBK', 'UTF-8']);
    }

    /**
     * 转换数组值的编码格式.
     * @param  array $arr
     * @param  string $toEncoding
     * @param  string $fromEncoding
     * @return array
     */
    private function _arrayConvertEncoding($arr, $toEncoding, $fromEncoding)
    {
        eval('$arr = '.iconv($fromEncoding, $toEncoding.'//IGNORE', var_export($arr, true)).';');

        return $arr;
    }

    /**
     * 去除特定的html标签.
     * @param  string $html
     * @param  string $tags_str 多个标签名之间用空格隔开
     * @return string
     */
    private function _stripTags($html, $tags_str)
    {
        $tagsArr = $this->_tag($tags_str);
        $html = $this->_removeTags($html, $tagsArr[1]);
        $p = [];
        foreach ($tagsArr[0] as $tag) {
            $p[] = "/(<(?:\/".$tag.'|'.$tag.')[^>]*>)/i';
        }
        $html = preg_replace($p, '', trim($html));

        return $html;
    }

    /**
     * 保留特定的html标签.
     * @param  string $html
     * @param  string $tags_str 多个标签名之间用空格隔开
     * @return string
     */
    private function _allowTags($html, $tags_str)
    {
        $tagsArr = $this->_tag($tags_str);
        $html = $this->_removeTags($html, $tagsArr[1]);
        $allow = '';
        foreach ($tagsArr[0] as $tag) {
            $allow .= "<$tag> ";
        }

        return strip_tags(trim($html), $allow);
    }

    private function _tag($tags_str)
    {
        $tagArr = preg_split("/\s+/", $tags_str, -1, PREG_SPLIT_NO_EMPTY);
        $tags = [[], []];
        foreach ($tagArr as $tag) {
            if (preg_match('/-(.+)/', $tag, $arr)) {
                array_push($tags[1], $arr[1]);
            } else {
                array_push($tags[0], $tag);
            }
        }

        return $tags;
    }

    /**
     * 移除特定的html标签.
     * @param  string $html
     * @param  array  $tags 标签数组
     * @return string
     */
    private function _removeTags($html, $tags)
    {
        $tag_str = '';
        if (count($tags)) {
            foreach ($tags as $tag) {
                $tag_str .= $tag_str ? ','.$tag : $tag;
            }
            phpQuery::$defaultCharset = $this->inputEncoding ? $this->inputEncoding : $this->htmlEncoding;
            $doc = phpQuery::newDocumentHTML($html);
            pq($doc)->find($tag_str)->remove();
            $html = pq($doc)->htmlOuter();
            $doc->unloadDocument();
        }

        return $html;
    }

    /**
     * 删除空格
     * @param  string $str
     * @return string
     */
    private function trimall($str)
    {
        $find = [' ', '　', "\t", "\n", "\r"];
        $replace = ['', '', '', '', ''];

        return str_replace($find, $replace, $str);
    }

    /**
     * 替换内容.
     * @param  string $find
     * @param  string $replace
     * @param  string $str
     * @return string
     */
    private function str_replaces($find, $replace, $str)
    {
        $finds = explode('#|#', $find);
        $replace = explode('#|#', $replace);

        return str_replace($finds, $replace, $str);
    }

    /**
     * 字段类型.
     * @param  string $ftype
     * @param  string $str
     * @return string
     */
    private function ftype($type, $str)
    {
        switch ($type) {
            case 'int':
                return (int) $str;
                break;
            case 'round1':
                return sprintf('%.1f', round($str, 1));
                break;
            case 'round2':
                return sprintf('%.2f', round($str, 2));
                break;
            default:
                return $str;
                break;
        }
    }

    /**
     * 检测是否出现.
     * @param  string $keyword
     * @param  string $str
     * @return string
     */
    private function appears($keyword, $str)
    {
        if (! $keyword) {
            return $str;
        }
        $keywords = explode('#|#', $keyword);
        $appears = true;
        foreach ($keywords as $key => $val) {
            $substr_count = substr_count($str, $val);
            if ($substr_count) {
                $appears = false;
                break;
            }
        }

        return $appears;
    }

    /**
     * Call static method.
     *
     * @param string $method
     * @param array $arguments
     * @return mixed
     * @author Seven Du <shiweidu@outlook.com>
     */
    public static function __callStatic($method, array $arguments = [])
    {
        $methods = ['query'];
        if (in_array($method, $methods)) {
            return call_user_func_array([static::getInstance(), $method], $arguments);
        }

        throw new \RuntimeException("The method \"{$method}\" not static.");
    }
}
