<?php
if (!defined('__TYPECHO_ROOT_DIR__')) { exit; }


/**
 * 把外部链接转换为指定内部链接
 *
 * @package ShortLinks
 * @author Ryan
 * @version 1.2.1
 * @link https://github.com/benzBrake/ShortLinks
 */

class ShortLinks_Plugin implements Typecho_Plugin_Interface
{
    /**
     * 激活插件方法,如果激活失败,直接抛出异常
     *
     * @access public
     * @return String
     * @throws Typecho_Plugin_Exception
     * @throws \Typecho\Db\Exception
     */
    /**
     * 校验跳转目标是否为可安全公开的 http(s) 地址。
     * 拦截非 http(s) 协议、控制字符、回环/内网/保留 IP、localhost、
     * 无点号的单标签主机名（如 crowdsec、localhost:8080）与 IPv6 字面量。
     * 用于修复 /go/ 开放重定向被用作内网探测的问题。
     */
    public static function isSafeRedirectTarget($url)
    {
        $url = trim((string) $url);
        if ($url === '' || !preg_match('~^https?://~i', $url)
            || preg_match('/[\x00-\x1f\x7f]/', $url)) {
            return false;
        }
        $parts = parse_url($url);
        $host = isset($parts['host']) ? strtolower(rtrim($parts['host'], '.')) : '';
        if ($host === '' || strpos($host, '[') !== false) {
            return false;
        }
        $blocked = array('localhost', '.local', '.internal', '.lan', '.localdomain', '.home.arpa');
        foreach ($blocked as $suffix) {
            if ($host === $suffix || substr($host, -strlen($suffix)) === $suffix) {
                return false;
            }
        }
        // 公网域名必含点；无点号的单标签主机名一律视为内网名称
        if (strpos($host, '.') === false) {
            return false;
        }
        // DNS rebinding：127.0.0.1.nip.io / 169.254.169.254.sslip.io 等
        // 把 IP 字面量伪装成域名前缀，解析后会落到内网或元数据地址。
        if (preg_match('/^\d{1,3}(?:\.\d{1,3}){3}\./', $host)) {
            return false;
        }
        // 纯数字/点形式视为 IP 字面量，拒绝私有段、保留段与进制变形
        if (preg_match('/^[0-9.]+$/', $host)) {
            $ip = filter_var($host, FILTER_VALIDATE_IP, FILTER_FLAG_IPV4);
            if ($ip === false) {
                return false;
            }
            return filter_var($ip, FILTER_VALIDATE_IP,
                FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false;
        }
        return true;
    }

    public static function activate()
    {
        $db = self::db();
        $tableName = $db->getPrefix() . 'shortlinks';
        $adapter = $db->getAdapterName();
        if ("Pdo_SQLite" === $adapter || "SQLite" === $adapter) {
            $db->query(" CREATE TABLE IF NOT EXISTS " . $tableName . " (
			   id INTEGER PRIMARY KEY,
			   key TEXT,
			   target TEXT,
			   count NUMERIC)");
        }
        if ("Pdo_Mysql" === $adapter || "Mysql" === $adapter) {
            $dbConfig = null;
            if (class_exists('\Typecho\Db')) {
                $dbConfig = $db->getConfig($db::READ);
            } else {
                $dbConfig = $db->getConfig()[0];
            }
            $charset = $dbConfig->charset;
            $db->query("CREATE TABLE IF NOT EXISTS " . $tableName . " (
				  `id` int(8) NOT NULL AUTO_INCREMENT,
				  `key` varchar(64) NOT NULL,
				  `target` varchar(10000) NOT NULL,
				  `count` int(8) DEFAULT '0',
				  PRIMARY KEY (`id`)
				) DEFAULT CHARSET=$charset AUTO_INCREMENT=1");
        }
        if ("Pdo_Pgsql" === $adapter || "Pgsql" === $adapter) {
            $db->query("CREATE TABLE IF NOT EXISTS " . $tableName . " (
                id SERIAL PRIMARY KEY,
                key TEXT NOT NULL,
                target TEXT NOT NULL,
                count INTEGER DEFAULT 0)");
        }

        Helper::addAction('shortlinks', 'ShortLinks_Action');
        Helper::addRoute('go', '/go/[key]/', 'ShortLinks_Action', 'shortlink');
        Helper::addPanel(2, 'ShortLinks/panel.php', '短链管理', '短链接管理', 'administrator');

        if (class_exists('\Widget\Base\Contents')) {
            Typecho\Plugin::factory('\Widget\Base\Contents')->contentEx = array('ShortLinks_Plugin', 'replace');
            Typecho\Plugin::factory('\Widget\Base\Contents')->excerptEx = array('ShortLinks_Plugin', 'replace');
            Typecho\Plugin::factory('\Widget\Base\Contents')->filter = array('ShortLinks_Plugin', 'forceConvert');
            Typecho\Plugin::factory('\Widget\Base\Comments')->contentEx = array('ShortLinks_Plugin', 'replace');
            Typecho\Plugin::factory('\Widget\Base\Comments')->filter = array('ShortLinks_Plugin', 'authorUrlConvert');
            Typecho\Plugin::factory('\Widget\Archive')->singleHandle = array('ShortLinks_Plugin', 'fieldsConvert');
            Typecho\Plugin::factory('admin/write-post.php')->option = array('ShortLinks_Plugin', 'addOption');
            Typecho\Plugin::factory('admin/write-page.php')->option = array('ShortLinks_Plugin', 'addOption');
            Typecho_Plugin::factory('\Widget\Contents\Post\Edit')->finishPublish = array(__CLASS__, "applyField");
            Typecho_Plugin::factory('\Widget\Contents\Page\Edit')->finishPublish = array(__CLASS__, "applyField");
        } else {
            Typecho_Plugin::factory('Widget_Abstract_Contents')->contentEx = array('ShortLinks_Plugin', 'replace');
            Typecho_Plugin::factory('Widget_Abstract_Contents')->excerptEx = array('ShortLinks_Plugin', 'replace');
            Typecho_Plugin::factory('Widget_Abstract_Contents')->filter = array('ShortLinks_Plugin', 'forceConvert');
            Typecho_Plugin::factory('Widget_Abstract_Comments')->contentEx = array('ShortLinks_Plugin', 'replace');
            Typecho_Plugin::factory('Widget_Abstract_Comments')->filter = array('ShortLinks_Plugin', 'authorUrlConvert');
            Typecho_Plugin::factory('Widget_Archive')->singleHandle = array('ShortLinks_Plugin', 'fieldsConvert');
            Typecho_Plugin::factory('admin/write-post.php')->option = array('ShortLinks_Plugin', 'addOption');
            Typecho_Plugin::factory('admin/write-page.php')->option = array('ShortLinks_Plugin', 'addOption');
            Typecho_Plugin::factory('Widget_Contents_Post_Edit')->finishPublish = array(__CLASS__, "applyField");
            Typecho_Plugin::factory('Widget_Contents_Page_Edit')->finishPublish = array(__CLASS__, "applyField");
        }

        return ('数据表 ' . $tableName . ' 创建成功，插件已经成功激活！');
    }

    /**
     * 禁用插件方法,如果禁用失败,直接抛出异常
     *
     * @static
     * @access public
     * @return String
     * @throws Typecho_Plugin_Exception
     * @throws \Typecho\Plugin\Exception
     */
    public static function deactivate()
    {
        $config = self::options('ShortLinks');
        $db = self::db();
        $adapter = $db->getAdapterName();
        $dropTableSql = '';

        Helper::removeRoute('go');
        Helper::removeAction('shortlinks');
        Helper::removePanel(2, 'ShortLinks/panel.php');

        if ($config->isDrop == 0) {
            if ("Pdo_SQLite" === $adapter || "SQLite" === $adapter) {
                $dropTableSql = "DROP TABLE '{$db->getPrefix()}shortlinks'";
            }
            if ("Pdo_Mysql" === $adapter || "Mysql" === $adapter) {
                $dropTableSql = "DROP TABLE `{$db->getPrefix()}shortlinks`";
            }
            if ("Pdo_Pgsql" === $adapter || "Pgsql" === $adapter) {
                $dropTableSql = "DROP TABLE \"{$db->getPrefix()}shortlinks\"";
            }
            $db->query($dropTableSql, Typecho_Db::WRITE);
            return (_t('短链接插件已被禁用，其表（%s）已被删除！', $db->getPrefix() . 'shortlinks'));
        } else {
            return (_t('短链接插件已被禁用，但是其表（%s）并没有被删除！', $db->getPrefix() . 'shortlinks'));
        }
    }

    /**
     * 获取插件配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form 配置面板
     * @return void
     */
    public static function config(Typecho_Widget_Helper_Form $form)
    {
        $radio = new Typecho_Widget_Helper_Form_Element_Radio('convert', array('1' => _t('开启'), '0' => _t('关闭')), '1', _t('外链转内链'), _t('开启后会帮你把外链转换成内链'));
        $form->addInput($radio);
        $radio = new Typecho_Widget_Helper_Form_Element_Radio('convertCommentLink', array('1' => _t('开启'), '0' => _t('关闭')), '1', _t('转换评论者链接'), _t('开启后会帮你把评论者链接转换成内链'));
        $form->addInput($radio);
        $template_files = scandir(dirname(__FILE__) . DIRECTORY_SEPARATOR . 'templates');
        $goTemplates = array('NULL' => '禁用');
        foreach ($template_files as $item) {
            if (PATH_SEPARATOR !== ':') {
                $item = mb_convert_encoding($item, "UTF-8", "GBK");
            }

            $name = mb_split("\.", $item)[0];
            if (empty($name)) {
                continue;
            }

            $goTemplates[$name] = $name;
        }
        $edit = new Typecho_Widget_Helper_Form_Element_Select('goTemplate', $goTemplates, 'NULL', _t('跳转页面模板'));
        $form->addInput($edit);
        $edit = new Typecho_Widget_Helper_Form_Element_Text('goDelay', null, _t('3'), _t('跳转延时'), _t('跳转页面停留时间（秒）'));
        $form->addInput($edit);
        $edit = new Typecho_Widget_Helper_Form_Element_Text('siteCreatedYear', null, _t('2020'), _t('建站年份'), _t('建站年份，用于模板内容替换模板中使用 <code>{{siteCreatedYear}}</code> 来代表建站年份'));
        $form->addInput($edit);

        $radio = new Typecho_Widget_Helper_Form_Element_Radio('target', array('1' => _t('开启'), '0' => _t('关闭')), '1', _t('新窗口打开文章中的链接'), _t('开启后给文章中的链接新增 target 属性'));
        $form->addInput($radio);

        // 原「新窗口打开评论者链接」靠在 URL 字符串尾部拼接 '" target="_blank' 实现。
        // Typecho 1.3 的 Common::safeUrl() 会剥掉引号，DUX 等主题也会 htmlspecialchars，
        // 结果是把 target=_blank 塞进 href 变成一个坏链接。该项已移除，
        // 新窗口行为交给主题（DUX 的 threadedComments 已输出 target + rel）。

        $radio = new Typecho_Widget_Helper_Form_Element_Radio('forceSwitch', array('1' => _t('开启'), '0' => _t('关闭')), '0', _t('强力模式'), _t('主要为了支持 editor.md / vditor 等前台解析<b style="color:red">（实验性功能）</b>'));
        $form->addInput($radio);

        $textarea = new Typecho_Widget_Helper_Form_Element_Textarea('convertCustomField', null, null, _t('需要处理的自定义字段'), _t('在这里设置需要处理的自定义字段，一行一个<b style="color:red">（实验性功能）</b>'));
        $form->addInput($textarea);
        $radio = new Typecho_Widget_Helper_Form_Element_Radio('nullReferer', array('1' => _t('开启'), '0' => _t('关闭')), '1', _t('允许空 referer'), _t('开启后会允许空 referer'));
        $form->addInput($radio);
        $refererList = new Typecho_Widget_Helper_Form_Element_Textarea('refererList', null, null, _t('referer 白名单'), _t('在这里设置 referer 白名单，一行一个'));
        $form->addInput($refererList);
        $nonConvertList = new Typecho_Widget_Helper_Form_Element_Textarea('nonConvertList', null, _t("b0.upaiyun.com" . PHP_EOL . "glb.clouddn.com" . PHP_EOL . "qbox.me" . PHP_EOL . "qnssl.com"), _t('外链转换白名单'), _t('在这里设置外链转换白名单（评论者链接不生效）'));
        $form->addInput($nonConvertList);
        $isDrop = new Typecho_Widget_Helper_Form_Element_Radio('isDrop', array('0' => '删除', '1' => '不删除'), '1', '彻底卸载(<b style="color:red">请慎重选择</b>)', '请选择是否在禁用插件时，删除数据表');
        $form->addInput($isDrop);
    }

    /**
     * 个人用户的配置面板
     *
     * @access public
     * @param Typecho_Widget_Helper_Form $form
     * @return void
     */
    public static function personalConfig(Typecho_Widget_Helper_Form $form) {}

    /**
     * 外链转内链
     *
     * @access public
     * @param string $text
     * @param mixed $widget
     * @param mixed $lastResult
     * @return array|string|string[] $content
     * @throws \Typecho\Plugin\Exception
     */
    public static function replace($text, $widget, $lastResult)
    {
        $text = empty($lastResult) ? $text : $lastResult;
        // filter 链可能传入 null 或上一个插件的非字符串返回值，统一兜底。
        $text = is_scalar($text) ? (string) $text : '';
        $pluginOption = self::options('ShortLinks'); // 插件选项
        if ($pluginOption->convert != 1) {
            return $text;
        }

        // 评论 widget 没有 fields 属性，Widget::__get 会安静返回 null；
        // 直接读 $widget->fields->noshort 会在 PHP 8 触发未定义属性告警。
        $fields = is_object($widget) ? $widget->fields : null;
        $noShort = null;
        if ($fields instanceof \ArrayAccess) {
            $noShort = $fields['noshort']; // Config::offsetGet 对不存在的键返回 null
        } elseif (is_object($fields)) {
            $vars = get_object_vars($fields);
            $noShort = isset($vars['noshort']) ? $vars['noshort'] : null;
        }
        if ($noShort !== null && $noShort !== '' && $noShort !== '0' && $noShort !== 0) {
            return $text; // 该文章在编辑器里勾选了「禁止转换链接为内链」
        }

        return self::convertHtmlLinks($text, $pluginOption);
    }

    /**
     * 统一处理一段 HTML 里的 <a href>。
     *
     * 原实现用 str_replace('href="..."', ...) 全量替换，有三个问题：
     *  - 只认双引号，单引号 href 不生效；
     *  - 把 /about、#top、mailto: 这类相对 / 非 http 链接也塞进 /go/，
     *    跳过去会因目标不安全而回落首页；
     *  - 追加 target="_blank" 时不检查已有属性，也不补 rel，重复且不安全。
     * 改为单次 preg_replace_callback，按标签精确改写。
     */
    private static function convertHtmlLinks($html, $pluginOption)
    {
        if (stripos((string) $html, 'href') === false) {
            return $html;
        }
        return preg_replace_callback(
            '/(<a\b[^>]*?\bhref\s*=\s*)(["\'])(.*?)(\2)([^>]*)(>)/is',
            function ($matches) use ($pluginOption) {
                $link = trim(html_entity_decode($matches[3], ENT_QUOTES | ENT_HTML5, 'UTF-8'));
                if (strpos($link, '//') === 0) {
                    $link = 'https:' . $link; // 协议相对链接补全，否则 /go/ 解出来没有 scheme
                }
                if (!self::isConvertibleLink($link)) {
                    return $matches[0];
                }
                $converted = self::convertLink($link);
                if ($converted === $link || $converted === '') {
                    return $matches[0];
                }

                $attrs = $matches[1] . $matches[5];
                $extra = '';
                if ($pluginOption->target && !preg_match('/\btarget\s*=/i', $attrs)) {
                    $extra .= ' target="_blank"';
                    if (!preg_match('/\brel\s*=/i', $attrs)) {
                        $extra .= ' rel="noopener noreferrer"';
                    }
                }

                return $matches[1] . $matches[2]
                    . htmlspecialchars($converted, ENT_QUOTES, 'UTF-8') . $matches[4]
                    . $matches[5] . $extra . $matches[6];
            },
            (string) $html
        );
    }

    /**
     * 只把「站外、绝对、http(s)」的链接送进 /go/。
     * 相对路径、锚点、mailto:/tel:/javascript:/data: 一律原样保留。
     */
    private static function isConvertibleLink($link)
    {
        $link = trim((string) $link);
        if ($link === '' || !preg_match('~^(?:https?:)?//~i', $link)) {
            return false;
        }
        return !self::isInternalUrl($link);
    }

    /**
     * 判断链接是否属于本站。
     *
     * 原实现是 strpos($link, $siteUrl) 子串匹配：
     * https://evil.example/?from=https://本站 会被误判成站内而漏转，
     * 装在子目录的站点又会把同域其它路径误判。这里按 host + port + 路径前缀判断。
     */
    private static function isInternalUrl($link)
    {
        $site = parse_url((string) self::options()->siteUrl);
        $target = parse_url((string) $link);
        if (empty($target['host']) || empty($site['host'])) {
            return false;
        }
        $sitePort = isset($site['port']) ? (int) $site['port'] : (strtolower((string) ($site['scheme'] ?? '')) === 'https' ? 443 : 80);
        $targetPort = isset($target['port']) ? (int) $target['port'] : (strtolower((string) ($target['scheme'] ?? '')) === 'https' ? 443 : 80);
        if (strcasecmp((string) $site['host'], (string) $target['host']) !== 0 || $sitePort !== $targetPort) {
            return false;
        }
        $basePath = isset($site['path']) ? rtrim($site['path'], '/') : '';
        if ($basePath !== '') {
            $path = isset($target['path']) ? $target['path'] : '';
            return $path === $basePath || strpos($path, $basePath . '/') === 0;
        }
        return true;
    }

    /**
     * 自定义字段处理
     *
     * @param Widget_Archive $widget
     * @param Typecho_Db_Query $select
     * @return void
     * @throws \Typecho\Plugin\Exception
     */
    public static function fieldsConvert($widget, $select)
    {
        $pluginOption = self::options('ShortLinks'); // 插件选项
        if ($pluginOption->convert != 1 || !is_object($widget)) {
            return;
        }
        $fieldsList = self::textareaToArr($pluginOption->convertCustomField);
        if (!$fieldsList) {
            return;
        }

        // 原实现写成 isset($text->fields[$field])：$text 从未定义，isset 恒为 false，
        // 整个「自定义字段」功能其实是死代码。Typecho 的 fields 是 Typecho\Config
        // （实现 ArrayAccess），__get('fields') 会按需加载并缓存。
        $fields = $widget->fields;
        if (!($fields instanceof \ArrayAccess)) {
            return;
        }
        foreach ($fieldsList as $field) {
            $field = trim((string) $field);
            if ($field === '' || !isset($fields[$field])) {
                continue;
            }
            $value = (string) $fields[$field];
            if ($value === '') {
                continue;
            }
            $fields[$field] = self::convertHtmlLinks($value, $pluginOption);
            if ($pluginOption->forceSwitch == 1) {
                $fields[$field] = self::autoLink($fields[$field]);
            }
        }
    }

    /**
     * 用户链接转换
     *
     * @param Array $value
     * @param Widget_Abstract_Comments $widget
     * @param mixed $lastResult
     * @return void
     * @throws \Typecho\Plugin\Exception
     */
    public static function authorUrlConvert($value, $widget, $lastResult)
    {
        $value = empty($lastResult) ? $value : $lastResult;
        if (!is_array($value)) {
            return $value;
        }
        $pluginOption = self::options('ShortLinks'); // 插件选项
        if ($pluginOption->convert != 1 || $pluginOption->convertCommentLink != 1) {
            return $value;
        }
        $url = isset($value['url']) ? trim((string) $value['url']) : '';
        if ($url === '' || !self::isConvertibleLink($url)) {
            return $value; // 空值、站内、相对地址或危险协议都不动
        }
        $value['url'] = self::convertLink($url, false);

        // 不再拼接 '" target="_blank'：Typecho 1.3 的 Common::safeUrl() 会剥掉
        // 引号，DUX 的 threadedComments 也会 htmlspecialchars，原写法只会把
        // "target=_blank" 塞进 href，生成一个坏链接。主题自己负责 target/rel。
        return $value;
    }

    /**
     * 转换链接形式
     *
     * @access public
     * @param $link
     * @param bool $check
     * @return mixed $string
     * @throws \Typecho\Plugin\Exception
     */
    public static function convertLink($link, bool $check = true)
    {
        $pluginOption = self::options('ShortLinks');
        $link = trim(html_entity_decode((string) $link, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
        if (strpos($link, '//') === 0) {
            $link = 'https:' . $link;
        }
        if ($check) {
            if (!self::isConvertibleLink($link)) {
                return $link;
            }
            // 白名单中的域名不处理
            if (self::checkDomain($link, self::textareaToArr($pluginOption->nonConvertList))) {
                return $link;
            }
            // 图片不处理（只在扩展名后紧跟分隔符时才算，避免 /foo.png-2 误判）
            if (preg_match('/\.(jpg|jpeg|png|ico|bmp|gif|tiff|webp|svg|avif|heic|heif)(?:[?#]|$)/i', $link)) {
                return $link;
            }
        }
        $route = Typecho_Router::get('go');
        if (!is_array($route) || empty($route['url'])) {
            return $link; // 路由未注册（例如插件刚复制进目录）时静默降级，不再抛 TypeError
        }
        $linkBase = ltrim(rtrim($route['url'], '/'), '/'); // 防止链接形式修改后不能用
        return Typecho_Common::url(
            str_replace('[key]', self::urlSafeB64Encode($link), $linkBase),
            self::options()->index
        );
    }

    /**
     * 强力转换
     *
     * @param Array $value
     * @param mixed $widget
     * @param mixed $lastResult
     * @return Array
     * @throws \Typecho\Plugin\Exception
     */
    public static function forceConvert($value, $widget, $lastResult): array
    {
        $value = empty($lastResult) ? $value : $lastResult;
        // filter 链可能传入 null 或非数组，统一兜底后再改写 text。
        if (!is_array($value)) {
            $value = array();
        }
        $pluginOption = self::options('ShortLinks');
        if ($pluginOption->convert == 1 && $pluginOption->forceSwitch == 1) {
            $value['text'] = self::autoLink($value['text'] ?? '');
        }
        return $value;
    }

    /**
     * 文本链接转A标签
     *
     * @param string $content
     * @return string
     * @throws \Typecho\Plugin\Exception
     */
    public static function autoLink($content)
    {
        $pluginOption = self::options('ShortLinks');
        // 原实现读的是 self::options()->target（站点 option，恒为 null），
        // 应读插件配置；且条件写反了——只把「包含本站地址」的裸链接包成 <a>，
        // 真正需要处理的站外裸链接反而原样返回，强力模式等于没开。
        $attrs = $pluginOption->target ? ' target="_blank" rel="noopener noreferrer"' : '';
        $url = '~(?:https?://[^\s<>"\'\)]+|www\.[^\s<>"\'\)]+\.[^\s<>"\'\)]+)~i';

        // 把已有 a 标签、代码块、HTML 标签、Markdown 链接目标单独切成片段跳过，
        // 否则会把 <a href="..."> 里的 URL 再包一层，产出嵌套/坏 HTML。
        $chunks = preg_split(
            '/(<a\b[^>]*>.*?<\/a>|<pre\b[^>]*>.*?<\/pre>|<code\b[^>]*>.*?<\/code>|<[^>]+>|\]\([^)]*\))/is',
            (string) $content,
            -1,
            PREG_SPLIT_DELIM_CAPTURE
        );
        if (!is_array($chunks)) {
            return $content;
        }
        foreach ($chunks as &$chunk) {
            if ($chunk === '' || $chunk[0] === '<' || strpos($chunk, '](') === 0) {
                continue;
            }
            $chunk = preg_replace_callback($url, function ($matches) use ($attrs) {
                $raw = $matches[0];
                $tail = '';
                if (preg_match('/[.,;:!?]+$/', $raw, $trimmed)) {
                    $tail = $trimmed[0];
                    $raw = substr($raw, 0, -strlen($tail));
                }
                // www. 形式没有 scheme，补上 https，否则 /go/ 解出的目标会被判为不安全
                $href = strpos($raw, '://') === false ? 'https://' . $raw : $raw;
                if (!self::isConvertibleLink($href)
                    || preg_match('/\.(jpg|jpeg|png|ico|bmp|gif|tiff|webp|svg|avif|heic|heif)(?:[?#]|$)/i', $href)) {
                    return $matches[0];
                }
                $converted = self::convertLink($href);
                if ($converted === $href) {
                    return $matches[0];
                }
                $shown = htmlspecialchars($raw, ENT_QUOTES, 'UTF-8');
                return '<a href="' . htmlspecialchars($converted, ENT_QUOTES, 'UTF-8')
                    . '" title="' . $shown . '"' . $attrs . '>' . $shown . '</a>' . $tail;
            }, $chunk);
        }
        unset($chunk);
        return implode('', $chunks);
    }

    /**
     * 检查域名是否在数组中存在
     *
     * @access public
     * @param string $url
     * @param array|null $arr
     * @return boolean
     */
    public static function checkDomain(string $url, ?array $arr): bool
    {
        if ($arr === null) {
            return false;
        }

        if (count($arr) === 0) {
            return false;
        }

        foreach ($arr as $a) {
            $a = trim((string) $a);
            // 文本框末尾换行会产生空串，strpos($url, '') 恒为 0，
            // 原实现因此会把整份白名单变成「所有链接都放行」。
            if ($a !== '' && stripos((string) $url, $a) !== false) {
                return true;
            }
        }
        return false;
    }

    /**
     * 一行一个文本框转数组
     *
     * @access public
     * @param string|null $textarea
     * @return array
     */
    public static function textareaToArr(?string $textarea): ?array
    {
        $lines = preg_split('/\r\n|\r|\n/', (string) ($textarea ?? ''));
        $out = array();
        foreach ($lines as $line) {
            $line = trim($line);
            if ($line !== '') {
                $out[] = $line; // 丢掉空行，避免空串命中所有域名
            }
        }
        return $out ? $out : null;
    }

    /**
     * Base64 解码
     *
     * @param string $str
     * @return string
     * @date 2020-05-01
     */
    public static function urlSafeB64Decode($str)
    {
        $data = str_replace(array('-', '_'), array('+', '/'), $str);
        $mod = strlen($data) % 4;
        if ($mod) {
            $data .= substr('====', $mod);
        }
        return base64_decode($data);
    }

    /**
     * Base64 编码
     *
     * @param string|null $str
     * @return string
     * @date 2020-05-01
     */
    public static function urlSafeB64Encode(?string $str): string
    {
        $data = base64_encode($str ?? "");
        return str_replace(array('+', '/', '='), array('-', '_', ''), $data);
    }

    public static function addOption($post)
    {
        $db = self::db();
        $row = $db->fetchRow($db->select()->from('table.fields')->where('name = ?', 'noshort'));
        $disableConvert = isset($row);
        ?>
        <p>
            <input id="noshort" name="noshort" type="checkbox" value="1" <?php if ($disableConvert) echo ' checked="checked"'; ?>>
            <label for="noshort"><?php _e("禁止转换链接为内链"); ?></label>
        </p>
        <script>
            document.addEventListener('DOMContentLoaded', function () {
                let field = document.querySelector('input[name="fieldNames[]"][value="noshort"]');
                if (field) {
                    field.closest('tr').style.display = 'none';
                }
            });
        </script>
        <?php
    }

    public static function applyField($content, $post)
    {
        $field = $post->request->get('noshort', false);
        if ($field) {
            self::setField('noshort', 'str', '', $post->cid);
        } else {
            $db = self::db();
            $db->query($db->delete('table.fields')->where('name = ? and cid = ?', 'noshort', $post->cid));
        }
    }

    /**
     * 设置单个字段
     *
     * @param string $name
     * @param string $type
     * @param string $value
     * @param integer $cid
     * @access private
     * @return integer
     */
    private static function setField($name, $type, $value, $cid)
    {
        $db = Typecho_Db::get();
        if (empty($name) || !in_array($type, array('str', 'int', 'float'))) {
            return false;
        }

        $exist = $db->fetchRow($db->select('cid')->from('table.fields')
            ->where('cid = ? AND name = ?', $cid, $name));

        if (empty($exist)) {
            return $db->query($db->insert('table.fields')
                ->rows(array(
                    'cid' => $cid,
                    'name' => $name,
                    'type' => $type,
                    'str_value' => 'str' == $type ? $value : null,
                    'int_value' => 'int' == $type ? intval($value) : 0,
                    'float_value' => 'float' == $type ? floatval($value) : 0,
                )));
        } else {
            return $db->query($db->update('table.fields')
                ->rows(array(
                    'type' => $type,
                    'str_value' => 'str' == $type ? $value : null,
                    'int_value' => 'int' == $type ? intval($value) : 0,
                    'float_value' => 'float' == $type ? floatval($value) : 0,
                ))
                ->where('cid = ? AND name = ?', $cid, $name));
        }
    }

    /**
     * 获得配置信息
     *
     * @return Typecho\Config|Typecho_Config
     * @throws \Typecho\Plugin\Exception
     */
    public static function options($plugin = null)
    {
        $options = null;
        if (function_exists('\Widget\Options::alloc')) {
            $options = \Widget\Options::alloc();
        } else {
            $options = Typecho_Widget::widget('Widget_Options');
        }
        if ($plugin) {
            $options = $options->plugin($plugin);
        }
        return $options;
    }

    /**
     * 获取数据库对象
     * @return mixed
     * @throws \Typecho\Db\Exception
     */
    public static function db()
    {
        if (class_exists('Typecho\Db')) {
            return Typecho\Db::get();
        } else {
            return Typecho_Db::get();
        }
    }
}
