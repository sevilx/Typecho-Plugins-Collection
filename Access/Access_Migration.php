<?php
if (!defined('__ACCESS_PLUGIN_ROOT__')) {
    throw new Exception('Bootstrap file not found');
}

class Access_Migration {
    /**
     * 允许访问的业务函数名及其对应的允许访问方法
     * @var string[]
     */
    private static $rpcTypes = [
        'overview' => 'GET',
        'migrate' => 'POST',
    ];

    private $config;
    private $db;
    private $request;

    public function __construct($request) {
        $this->db = Typecho_Db::get();
        $this->request = $request;
        $this->config = Typecho_Widget::widget('Widget_Options')->plugin('Access');
    }

    /**
     * 跨数据库判断数据表是否存在。
     *
     * 兼容性修复：原实现使用 MySQL 专用的 `SHOW TABLES LIKE`，在 SQLite /
     * PgSQL 上会直接抛 SQL 语法错误。而 exists() 又被 beforeRender 钩子在
     * 每一次前台请求里调用，一旦启用插件整个站点就会 500。
     *
     * 注意不能用 `SELECT 1 FROM <table> LIMIT 1` 试探：表不存在时
     * SQLite3::prepare() 会先打一条 PHP warning（并非异常，try/catch 抓不到），
     * 在开启 display_errors 的站点上会把 "no such table" 直接印进页面 HTML，
     * 冲坏标签。改为查各适配器的系统目录表，不触碰业务表就不会有 warning。
     */
    private function tableExists($name)
    {
        $table = $this->db->getPrefix() . $name;
        if (!preg_match('/^[A-Za-z0-9_]+$/', $table)) {
            return false;
        }
        try {
            $adapter = strtolower((string) $this->db->getAdapterName());
            if (strpos($adapter, 'sqlite') !== false) {
                $sql = "SELECT name FROM sqlite_master WHERE type='table' AND name='{$table}'";
            } elseif (strpos($adapter, 'pgsql') !== false) {
                $sql = "SELECT tablename FROM pg_tables WHERE schemaname='public' AND tablename='{$table}'";
            } else {
                $sql = "SHOW TABLES LIKE '{$table}'";
            }
            $row = $this->db->fetchRow($this->db->query($sql, Typecho_Db::READ));
            return !empty($row);
        } catch (\Throwable $e) {
            // 未知适配器：退回通用探测，用 @ 抑制驱动层 warning
            try {
                $this->db->fetchRow(
                    @$this->db->query('SELECT 1 FROM ' . $table . ' LIMIT 1', Typecho_Db::READ)
                );
                return true;
            } catch (\Throwable $e2) {
                return false;
            }
        }
    }

    /**
     * 显示是否存在旧版数据
     *
     * @access public
     * @return bool
     * @throws Exception
     */
    public function exists()
    {
        return $this->tableExists('access') || $this->tableExists('access_log');
    }

    /**
     * 显示旧版本数据数量
     *
     * @access private
     * @return ?array
     * @throws Exception
     */
    private function overview()
    {
        $resp = [ 'total' => 0 ];

        // 统计 v1 版本数据
        if ($this->tableExists('access')) {
            $resp['v1'] = $this->db->fetchRow($this->db->select('COUNT(1) AS cnt')->from('table.access'))['cnt'];
            $resp['total'] += $resp['v1'];
        }

        // 统计 v2 版本数据
        if ($this->tableExists('access_log')) {
            $resp['v2'] = intval($this->db->fetchRow($this->db->select('COUNT(1) AS cnt')->from('table.access_log'))['cnt']);
            $resp['total'] += $resp['v2'];
        }

        return $resp;
    }


    /**
     * 迁移旧版本数据，单次1000条
     *
     * @access private
     * @return ?array
     * @throws Exception
     */
    private function migrate()
    {
        $resp = [ 'count' => 0, 'remain' => 0 ];
        $step = 1000;
        $prefix = $this->db->getPrefix();
        $adapterName = $this->db->getAdapterName();

        // 迁移 v1 版本数据
        if ($this->tableExists('access')) {
            $remain = intval($this->db->fetchRow($this->db->select('COUNT(1) AS cnt')->from('table.access'))['cnt']);
            if ($resp['count'] === 0) {
                $rows = $this->db->fetchAll($this->db->select()->from('table.access')->limit($step));
                foreach ($rows as $row) {
                    $id = $row['id'];
                    unset($row['id']);
                    $ua = new Access_UA($row['ua']);
                    $row['browser_id'       ] = $ua->getBrowserID();
                    $row['browser_version'  ] = $ua->getBrowserVersion();
                    $row['os_id'            ] = $ua->getOSID();
                    $row['os_version'       ] = $ua->getOSVersion();
                    $row['path'             ] = parse_url($row['url'], PHP_URL_PATH);
                    $row['query_string'     ] = parse_url($row['url'], PHP_URL_QUERY);
                    $row['ip'               ] = $row['ip'];
                    $row['entrypoint'       ] = $row['referer'];
                    $row['entrypoint_domain'] = $row['referer_domain'];
                    $row['time'             ] = $row['date'];
                    $row['robot'            ] = $ua->isRobot() ? 1 : 0;
                    $row['robot_id'         ] = $ua->getRobotID();
                    $row['robot_version'    ] = $ua->getRobotVersion();
                    unset($row['date']);
                    $this->db->query($this->db->insert('table.access_logs')->rows($row));
                    $resp['count'] += 1;
                    $this->db->query($this->db->delete('table.access')->where('id = ?', $id));
                    $remain -= 1;
                }
            }
            if ($remain === 0) {
                $dropTableQuery = strpos($adapterName, 'Pgsql') !== false ?
                    "DROP TABLE IF EXISTS {$prefix}access;" :
                    "DROP TABLE `{$prefix}access`;";
                $this->db->query($dropTableQuery, Typecho_Db::WRITE);
            }
            $resp['remain'] += $remain;
        }

        // 迁移 v2 版本数据
        if ($this->tableExists('access_log')) {
            $remain = intval($this->db->fetchRow($this->db->select('COUNT(1) AS cnt')->from('table.access_log'))['cnt']);
            if ($resp['count'] === 0) {
                $rows = $this->db->fetchAll($this->db->select()->from('table.access_log')->limit($step));
                foreach ($rows as $row) {
                    $id = $row['id'];
                    unset($row['id']);
                    $row['ip'] = long2ip($row['ip']);
                    $this->db->query($this->db->insert('table.access_logs')->rows($row));
                    $resp['count'] += 1;
                    $this->db->query($this->db->delete('table.access_log')->where('id = ?', $id));
                    $remain -= 1;
                }
            }
            if ($remain === 0) {
                $dropTableQuery = strpos($adapterName, 'Pgsql') !== false ?
                    "DROP TABLE IF EXISTS {$prefix}access_log;" :
                    "DROP TABLE `{$prefix}access_log`;";
                $this->db->query($dropTableQuery, Typecho_Db::WRITE);
            }
            $resp['remain'] += $remain;
        }
        return $resp;
    }

    /**
     * 业务调度入口
     *
     * @access public
     * @param string rpcType 调用过程类型
     * @return ?array
     * @throws Exception
     */
    public function invoke(string $rpcType): ?array {
        if(!method_exists($this, $rpcType) || !array_key_exists($rpcType, Access_Migration::$rpcTypes))
            throw new Exception('Bad Request', 400);
        $method = Access_Migration::$rpcTypes[$rpcType];
        if (
            ($method === 'GET' && !$this->request->isGet())
            || ($method === 'POST' && !$this->request->isPost())
            || ($method === 'PUT' && !$this->request->isPut())
        ) {
            throw new Exception('Method Not Allowed', 405);
        }
        return $this->$rpcType();
    }
}
