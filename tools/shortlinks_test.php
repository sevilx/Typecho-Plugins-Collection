<?php
// ShortLinks 纯逻辑回归：用最小桩模拟 Typecho 环境，不启动真实站点。
// 运行： php tools/shortlinks_test.php    （需要 PHP 8.1+，无需安装 Typecho）
// 覆盖：外链转换、站内/相对/危险协议排除、属性兼容、强力模式、
//       自定义字段、评论者链接、白名单空行、/go/ 目标安全校验。
define('__TYPECHO_ROOT_DIR__', __DIR__);

class Typecho_Widget_Helper_Form {}
interface Typecho_Plugin_Interface {}

class StubPluginConfig
{
    public $convert = 1;
    public $convertCommentLink = 1;
    public $target = 1;
    public $forceSwitch = 1;
    public $convertCustomField = '';
    public $nonConvertList = "b0.upaiyun.com\n\n";   // 末尾空行曾导致全放行
    public $refererList = '';
}

class StubOptions
{
    public $siteUrl = 'https://www.example.com/blog/';
    public $index = 'https://www.example.com/blog/'; // Typecho 的 options->index（rewrite 开启时是站点根）
    private $cfg;
    public function __construct() { $this->cfg = new StubPluginConfig(); }
    public function plugin($name) { return $this->cfg; }
    public function config() { return $this->cfg; }
}

class Typecho_Widget
{
    public static $options;
    public static function widget($name) { return self::$options; }
}
Typecho_Widget::$options = new StubOptions();

class Typecho_Router
{
    public static $routes = array('go' => array('url' => '/go/[key]/'));
    public static function get($name) { return isset(self::$routes[$name]) ? self::$routes[$name] : null; }
}

class Typecho_Common
{
    public static function url($path, $index)
    {
        // 与 Typecho\Common::url 一致
        return rtrim((string) $index, '/') . '/'
            . str_replace('//', '/', ltrim((string) $path, '/'));
    }
}

class StubFields implements ArrayAccess
{
    public $data;
    public function __construct(array $data) { $this->data = $data; }
    public function offsetExists($o): bool { return isset($this->data[$o]); }
    #[\ReturnTypeWillChange] public function offsetGet($o) { return $this->data[$o] ?? null; }
    public function offsetSet($o, $v): void { $this->data[$o] = $v; }
    public function offsetUnset($o): void { unset($this->data[$o]); }
}
class StubWidget
{
    public $fields;
    public function __construct($fields = null) { $this->fields = $fields; }
}

require dirname(__DIR__) . '/ShortLinks/Plugin.php';

$pass = 0; $fail = 0;
function check($name, $actual, $expected)
{
    global $pass, $fail;
    if ($actual === $expected) {
        $pass++;
        echo "  PASS  $name\n";
    } else {
        $fail++;
        echo "  FAIL  $name\n        expected: $expected\n        actual:   $actual\n";
    }
}
function has($name, $haystack, $needle)
{
    check($name, strpos($haystack, $needle) !== false, true);
}
function hasnt($name, $haystack, $needle)
{
    check($name, strpos($haystack, $needle) === false, true);
}

$widget = new StubWidget(new StubFields(array()));
$key = ShortLinks_Plugin::urlSafeB64Encode('https://external.com/path?a=1&b=2');
// 插件按惯例 rtrim 掉路由尾斜杠；Typecho Router\Parser 生成的正则带 [/]?，两种都能匹配
$go = 'https://www.example.com/blog/go/' . $key;

echo "== replace(): 外链转换 ==\n";
$r = ShortLinks_Plugin::replace('<a href="https://external.com/path?a=1&amp;b=2">x</a>', $widget, null);
has('外链转 /go/', $r, $go);
has('补 target', $r, 'target="_blank"');
has('补 rel', $r, 'rel="noopener noreferrer"');

echo "== replace(): 不该动的东西 ==\n";
check('站内绝对链接', ShortLinks_Plugin::replace('<a href="https://www.example.com/blog/archives/1/">x</a>', $widget, null), '<a href="https://www.example.com/blog/archives/1/">x</a>');
check('相对路径', ShortLinks_Plugin::replace('<a href="/about">x</a>', $widget, null), '<a href="/about">x</a>');
check('锚点', ShortLinks_Plugin::replace('<a href="#top">x</a>', $widget, null), '<a href="#top">x</a>');
check('mailto', ShortLinks_Plugin::replace('<a href="mailto:a@b.com">x</a>', $widget, null), '<a href="mailto:a@b.com">x</a>');
check('javascript', ShortLinks_Plugin::replace('<a href="javascript:alert(1)">x</a>', $widget, null), '<a href="javascript:alert(1)">x</a>');

echo "== replace(): 属性与引号兼容 ==\n";
$r = ShortLinks_Plugin::replace("<a class='l' href='https://external.com/' rel='nofollow'>x</a>", $widget, null);
has('单引号 href 也转换', $r, 'go/');
check('已有 rel 只出现一次', substr_count($r, 'rel='), 1);
$r = ShortLinks_Plugin::replace('<a href="https://external.com/" target="_self">x</a>', $widget, null);
check('已有 target 不重复', substr_count($r, 'target='), 1);
$r = ShortLinks_Plugin::replace('<p>text without links</p>', $widget, null);
check('无 href 的 HTML 原样', $r, '<p>text without links</p>');

echo "== replace(): noshort 自定义字段 ==\n";
$noShortWidget = new StubWidget(new StubFields(array('noshort' => '1')));
check('勾选 noshort 后完全跳过', ShortLinks_Plugin::replace('<a href="https://external.com/">x</a>', $noShortWidget, null), '<a href="https://external.com/">x</a>');

echo "== 同一链接出现多次 ==\n";
$r = ShortLinks_Plugin::replace('<a href="https://external.com/">a</a> and <a href="https://external.com/">b</a>', $widget, null);
check('两处都转换', substr_count($r, 'go/'), 2);

echo "== convertLink() ==\n";
check('路由缺失时静默降级', (function () {
    Typecho_Router::$routes = array();
    $v = ShortLinks_Plugin::convertLink('https://external.com/');
    Typecho_Router::$routes = array('go' => array('url' => '/go/[key]/'));
    return $v;
})(), 'https://external.com/');
check('白名单域名不转换', ShortLinks_Plugin::convertLink('https://cdn.b0.upaiyun.com/a.jpg'), 'https://cdn.b0.upaiyun.com/a.jpg');
check('图片不转换', ShortLinks_Plugin::convertLink('https://external.com/a.png'), 'https://external.com/a.png');
has('协议相对链接补全 https', ShortLinks_Plugin::urlSafeB64Decode(basename(trim(parse_url(ShortLinks_Plugin::convertLink('//cdn.external.com/a'), PHP_URL_PATH), '/'))), 'https://cdn.external.com/a');

echo "== autoLink(): 强力模式 ==\n";
$r = ShortLinks_Plugin::autoLink('看这个 https://external.com/page 吧');
has('站外裸链接被包成 a', $r, '<a href=');
has('站外裸链接走 /go/', $r, 'go/');
has('带 rel', $r, 'rel="noopener noreferrer"');
check('站内裸链接不处理', ShortLinks_Plugin::autoLink('看这个 https://www.example.com/blog/a 吧'), '看这个 https://www.example.com/blog/a 吧');
$r = ShortLinks_Plugin::autoLink('已有 <a href="https://external.com/">链接</a> 不再包');
check('已有 a 标签不嵌套', substr_count($r, '<a '), 1);
$r = ShortLinks_Plugin::autoLink('代码 <code>https://external.com/x</code> 不动');
hasnt('code 块内不处理', $r, 'go/');
$r = ShortLinks_Plugin::autoLink('Markdown [标题](https://external.com/md) 不动');
hasnt('Markdown 链接目标不处理', $r, 'go/');
$r = ShortLinks_Plugin::autoLink('裸域名 www.external.com/a 试试');
has('www. 形式补 https 并转换', $r, 'go/');
has('尾随标点保留在标签外', ShortLinks_Plugin::autoLink('见 https://external.com/a.'), '</a>.');

echo "== textareaToArr / checkDomain ==\n";
check('空行被丢弃', ShortLinks_Plugin::textareaToArr("a.com\n\nb.com\n"), array('a.com', 'b.com'));
check('全空返回 null', ShortLinks_Plugin::textareaToArr("\n\n"), null);
check('空串不再命中所有域名', ShortLinks_Plugin::checkDomain('https://x.com/', array('')), false);
check('正常白名单仍生效', ShortLinks_Plugin::checkDomain('https://cdn.b0.upaiyun.com/a', array('b0.upaiyun.com')), true);

echo "== fieldsConvert(): 自定义字段 ==\n";
$cfg = Typecho_Widget::$options->plugin('ShortLinks');
$cfg->convertCustomField = "myField\n";
$fields = new StubFields(array('myField' => '<a href="https://external.com/f">f</a>'));
ShortLinks_Plugin::fieldsConvert(new StubWidget($fields), null);
has('自定义字段里的 a 被转换', $fields['myField'], 'go/');

echo "== authorUrlConvert() ==\n";
$v = ShortLinks_Plugin::authorUrlConvert(array('url' => 'https://external.com/me'), new StubWidget(), null);
has('评论者外链转 /go/', $v['url'], 'go/');
hasnt('不再往 URL 里拼 target', $v['url'], 'target');
check('站内评论者链接不变', ShortLinks_Plugin::authorUrlConvert(array('url' => 'https://www.example.com/blog/x'), new StubWidget(), null)['url'], 'https://www.example.com/blog/x');
check('空 url 不报错', ShortLinks_Plugin::authorUrlConvert(array(), new StubWidget(), null), array());
check('javascript: 不转换', ShortLinks_Plugin::authorUrlConvert(array('url' => 'javascript:alert(1)'), new StubWidget(), null)['url'], 'javascript:alert(1)');

echo "== isSafeRedirectTarget(): 安全防线保持 ==\n";
check('公网 http', ShortLinks_Plugin::isSafeRedirectTarget('https://external.com/a'), true);
check('javascript', ShortLinks_Plugin::isSafeRedirectTarget('javascript:alert(1)'), false);
check('localhost', ShortLinks_Plugin::isSafeRedirectTarget('http://localhost/admin'), false);
check('内网 IP', ShortLinks_Plugin::isSafeRedirectTarget('http://192.168.1.1/'), false);
check('公网 IP', ShortLinks_Plugin::isSafeRedirectTarget('http://8.8.8.8/'), true);
check('单标签主机名', ShortLinks_Plugin::isSafeRedirectTarget('http://crowdsec/'), false);
check('控制字符', ShortLinks_Plugin::isSafeRedirectTarget("https://external.com/\r\nX"), false);

echo "\n结果: $pass 通过, $fail 失败\n";
exit($fail ? 1 : 0);
