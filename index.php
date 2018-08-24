<?php

/**
 * @author WILO
 * QQ music file analyze and downloader
 * Just for fun
 */
header('Content-type:text/html; charset=utf-8');

/**
 * 数据变量分析
 * SEARCH GET请求中
 * p    =>页数
 * n    =>条数
 * w    =>关键词
 * jsonpCallback    =>json返回数据封包头
 * format           =>json返回数据格式(jsonp json)
 * inCharset: utf8
 * outCharset: utf-8
 * 
 * 在SEARCH json结果中
 * code                 =>状态码
 * data ->  keyword     =>搜索关键词
 * data ->  curpage     =>当前页数
 * data ->  curnum      =>当前页数数据量
 * data ->  totalnum    =>总条数
 * data ->  list        =>搜索结果
 */
/*
  $spider = curl_init();
  curl_setopt($spider, CURLOPT_URL, $testUrl);
  curl_setopt($spider, CURLOPT_RETURNTRANSFER, TRUE);
  curl_setopt($spider, CURLOPT_SSL_VERIFYPEER, FALSE);
  curl_setopt($spider, CURLOPT_HEADER, TRUE);
  curl_setopt($spider, CURLOPT_NOBODY, true);
  curl_setopt($spider, CURLOPT_POST, false);
  $spiderResult = curl_exec($spider);
  var_dump($spiderResult);
  curl_close($spider);
 */


//调试模式
$search = new QmusicSearch('了不起');
$search->show();


/**
 * Music Json Callback数据除杂整理
 * @return array/stdClass decode多维数组/stdClass
 */
function musicCallback($string) {
    $pregStr = '/^Music.*?\(|\)$/';
    $result = preg_replace($pregStr, '', $string);
    $pregStr = '/<(\/)?em>/';
    $result = preg_replace($pregStr, '', $result);
    $result = json_decode($result);
    return $result;
}

/**
 * QQ music搜索结果类
 */
class QmusicSearch {
    //GET请求变量
    /** 页码 */
    public $page;
    /** 单页结果数量 */
    public $number;
    
    
    //返回json数据解析变量
    /** json数据中的搜索关键词 */
    public $js_keyword;
    
    /** 返回状态码 */
    public $js_code;
    /** 当前搜索结果页码 */
    public $js_curpage;
    /** 当前搜索结果页数据量(array count) */
    public $js_curnum;
    /** 总数据量 */
    public $js_totalnum;
    /** 总页数 */
    public $js_totalage;
    
    /** 详细数据集 */
    public $js_list;
    
    private static $searchStatement = 'https://c.y.qq.com/soso/fcgi-bin/client_search_cp?new_json=1&searchid=57841467114235795&t=0&aggr=1&cr=1&lossless=0&flag_qc=0&loginUin=0&hostUin=0&format=json&inCharset=utf8&outCharset=utf-8';
    
    
    /**
     * 构造函数(关键词)
     * @param string $word 搜索关键词
     */
    public function __construct($word, $p = 1, $n = 20) {
        $this->page = $p;
        $this->number = $n;
        $spider = curl_init();
        $spiderUrl = QmusicSearch::$searchStatement . '&p=' . $this->page .'&n=' . $this->number  .  '&w=' . urlencode($word);
        curl_setopt($spider, CURLOPT_URL, $spiderUrl);
        curl_setopt($spider, CURLOPT_RETURNTRANSFER, TRUE);
        curl_setopt($spider, CURLOPT_SSL_VERIFYPEER, FALSE);
        $spiderResult = curl_exec($spider);
        if(curl_errno($spider))
        {
            die("<b>" . curl_error($spider) . "</b>");
        }
        curl_close($spider);
        $spiderResult = musicCallback($spiderResult);       
        $this->js_keyword = $spiderResult->data->keyword;
        $this->js_curpage = $spiderResult->data->song->curpage;
        $this->js_totalnum = $spiderResult->data->song->totalnum;
        $this->js_curnum = count($spiderResult->data->song->list);
        //TODO 检查页码计算是否正确
        $this->js_totalage = $this->js_totalnum / $this->js_curnum;
        $this->js_list = array();
        for ($i = 0; $i < $this->js_curnum; $i++) {
            $this->js_list[$i] = new QmucicSong($spiderResult->data->song->list[$i]);
        }
    }

    /**
     * 输出测试信息
     */
    public function show() {
        echo "<h1>Keyword:{$this->js_keyword}</h1>\n";
        echo "<h3>Current count:{$this->js_curnum}</h3>\n";
        echo "<h3>Total count:{$this->js_totalnum}</h3>\n";
        for ($i = 0; $i < $this->js_curnum; $i++) {
            echo "song $i<br/>\n";
            $this->js_list[$i]->show();
            echo "<br />\n";
            echo '<audio controls src="' . $this->js_list[$i]->getPlayUrl() . '"></audio>';
            echo "<br /><br />\n";
        }
    }

}

/**
 * QQ music song json数据解析类
 */
class QmucicSong {

    public $title;
    public $singer;
    public $vkey;
    public $songmid;
    public $filename;
    private static $getVkeyUrl = 'https://c.y.qq.com/base/fcgi-bin/fcg_music_express_mobile3.fcg?loginUin=0&format=json&inCharset=utf8&outCharset=utf-8&cid=205361747&guid=0';

    /**
     * 通过list数组解析构造数据<br />
     * SEARCH JSON 中 data -> song -> list ->[x]-> file -> media_mid =>filename<br />
     * SEARCH JSON 中 data -> song -> list ->[x]-> mid  =>songmid<br />
     * @param stdClass $list_x SEARCH json数据中的单条list数据
     */
    public function __construct($list_x) {
        $this->title = $list_x->title;
        foreach($list_x->singer as $singers){
            $this->singer .= $singers->name . ' / ';
        }
        $this->singer = preg_replace('/\/\s$/', '', $this->singer);
        //喵喵喵？TODO检查一下C400和m4a的作用
        $this->filename = 'C400' . $list_x->file->media_mid . '.m4a';
        $this->songmid = $list_x->mid;
        if (!$this->filename || !$this->songmid) {
            echo '<script>console.log(\'Error:Song info building[ER001]\')</script>';
            return FALSE;
        }
        
        $spider = curl_init();
        curl_setopt($spider, CURLOPT_SSL_VERIFYPEER, FALSE);
        $vkeyUrl = QmucicSong::$getVkeyUrl . '&filename=' . $this->filename . '&songmid=' . $this->songmid;
        curl_setopt($spider, CURLOPT_URL, $vkeyUrl);
        curl_setopt($spider, CURLOPT_RETURNTRANSFER, TRUE);
        $spiderResult = curl_exec($spider);
        curl_close($spider);
        $spiderResult = musicCallback($spiderResult);
        $this->vkey = $spiderResult->data->items[0]->vkey;
    }
    
    public function show() {
        echo "Title: {$this->title}<br />\n";
        echo "Singger: {$this->singer}<br />\n";
        //echo "Songmid: {$this->songmid}<br />\n";
        //echo "Filename: {$this->filename}<br />\n";
        //echo "Vkey: {$this->vkey}";
    }
    
    public function getPlayUrl() {
        $url = 'http://dl.stream.qqmusic.qq.com/';
        $url = $url . $this->filename . '?vkey=' . $this->vkey . '&guid=0&uin=0';
        return $url;
    }

}
