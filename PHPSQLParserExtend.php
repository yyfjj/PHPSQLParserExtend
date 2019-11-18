<?php
/**
 * $parsesql = new \Parsesql\Parsesql($message);
 * $new_message = $parsesql->doIt();
 *
 * $limit = array('insert','update');
 * if(in_array($parsesql->getAction(),$limit)){
 * $message = '[INFO]'.$message."<br>";
 * $message .= '[解析]'.$new_message."<br>";
 * }else{
 * $message = '[INFO]'.$message."<br>";
 * }
 *
 * self::writeLog($message,$fileName.".html");
 */

namespace PHPSQLParserExtend;
class PHPSQLParserExtend
{
    /**
     * @var string 需要解析的sql语句
     */
    private $_sql;
    /**
     * @var 以小写字母方式返回sql语句操作动作，例如insert等
     */
    private $_sqlAction;
    /**
     * @var 表名
     */
    private $_tableName;
    /**
     * @var sql语句解析后的数组信息
     */
    private $_sqlInfo = array();

    private $_debug = false;
    private $_debugFile = 'parsesql.log';
    private $_exception = false;//当为true表示发生异常，所有数据不通过cache处理

    function __construct(string $sql)
    {
        $this->_sql = $sql;
        try {
            $this->_init();
        } catch (Exception $e) {
            $this->_exception = true;
            $this->_debugInfo(print_r($e->__toString(), true), '异常内容');
        }
    }

    private function _init()
    {
        #格式化sql
        $this->_formate();
        $this->_debugInfo("/***********************************************/\n");
        $this->_debugInfo($this->_sql, "sql:");
        #解析sql
        $parse = new \PHPSQLParser\PHPSQLParser($this->_sql);
        $this->_sqlInfo = $parse->parsed;
        #获取动作
        $this->_getActionFromSql();
        #获取表名
        $this->getTableName();

        $this->_debugInfo($this->_sqlAction, "action:");
    }

    private function _formate()
    {
        return $this->_sql = trim(str_replace(PHP_EOL, '', $this->_sql));
    }

    #首尾去空格并去除回车和换行符

    private function _debugInfo($var, $tips = "提示:")
    {
        $this->_debug && error_log($tips . print_r($var, true) . "\n", 3, $this->_debugFile);
    }

    /**
     * 选取第一个单词作为动作
     * @return unknown
     * @throws Exception
     * @example select * from table //select
     *          INSERT INTO tbl_name (col1,col2) VALUES(15,col1*2); //insert
     *          update table set name='rose'//update
     */
    private function _getActionFromSql()
    {
        $current_key = key($this->_sqlInfo);
        $this->_sqlAction = strtolower($current_key);

        return $this->_sqlAction;
    }

    function getTableName()
    {
        $current = current($this->_sqlInfo);
        if ($this->_sqlAction == 'insert') {
            $this->_tableName[] = $current[1]['no_quotes']['parts'][0];
        } else {
            $this->_tableName[] = $current[0]['no_quotes']['parts'][0];
        }

        return $this->_tableName;
    }

    function getRunStatus()
    {
        if ($this->_exception) {
            return false;
        } else {
            return true;
        }
    }

    function getAction()
    {
        return $this->_sqlAction;
    }

    function doIt()
    {
        if ($this->_sqlAction == 'update') {
            return $this->parseUpdate();
        } elseif ($this->_sqlAction == 'insert') {
            return $this->parseInsert();
        } else {
            return $this->_sql;
        }
    }

    function parseUpdate()
    {
        foreach ($this->_sqlInfo['SET'] as $k => $v) {
            //字段名
            $param_name = $v['sub_tree'][0]['no_quotes']['parts'][0];
            //字段值
            $param_value = $v['sub_tree'][2]['base_expr'];
            $t[$param_name] = $param_value;
        }

        /*$parseSql = preg_split("#\s+#", $this->_sql, -1, PREG_SPLIT_NO_EMPTY);*/
//        $r['table'] = $tmp[2];
//        $r['action'] = $tmp[0];
//        $sql = "UPDATE `haoyebao_admin` AS `admin` SET admin_id = '1', admin_login_num = '606', admin_login_time = '1525310479' WHERE admin_id = '1'";

//        $sql ="UPDATE `haoyebao_store_member_recharge_order` SET `status`='20',`updated`='1524908518' WHERE ( `id` = '375' )";

//        Array
//        (
//            [0] => UPDATE
//            [1] => `haoyebao_store_member_recharge_order`
//            [2] => SET
//            [3] => `status`='20',`updated`='1524908518'
//            [4] => WHERE
//            [5] => (
//            [6] => `id`
//            [7] => =
//            [8] => '375'
//            [9] => )
//        )


        /*$value = explode(",",$parseSql[3]);
        foreach($value as $k=>$v){
            list($a1,$a2) = explode("=",$v);
            $a1 = trim($a1,"`");
            $a2 = trim($a2,"'");
            $t[$a1] = $a2;

        }*/

        $tmp = $this->getComment($this->_tableName[0]);

        $table = "更新表{$this->_tableName[0]}({$tmp['table']})<table border='1' cellspacing='0' cellpadding='1'>";

        $table .= "  <tr>";
        foreach ($tmp['param'] as $k => $v) {
            $table .= "    <td>" . $k . "</td>";
        }
        $table .= "  </tr>";

        $table .= "  <tr>";
        foreach ($tmp['param'] as $k => $v) {
            if (isset($t[$k])) {
                $table .= "    <td>" . $t[$k] . "</td>";
            } else {
                $table .= "    <td></td>";
            }
        }

        $table .= "  </tr>";

        $table .= "  <tr>";
        foreach ($tmp['param'] as $k => $v) {
            $table .= "    <td>" . $v . "</td>";
        }
        $table .= "  </tr>";
        $table .= "</table>";
        return $table;
    }

    function getComment($tableName)
    {
        $p = $this->getCreateTableSql($tableName);

        $param = preg_split("#\n\s#", $p[0]['Create Table'], -1, PREG_SPLIT_NO_EMPTY);
        $paramComment = array();
        #第一步取出字段和注释对照,尚未解决默认值存在情况
        foreach ($param as $k => $v) {
            if (strpos(trim($v), 'CREATE') === 0) {
                continue;
            }
            if (strpos(trim($v), 'PRIMARY') === 0) {
                continue;
            }
            if (strpos(trim($v), 'UNIQUE') === 0) {
                continue;
            }
            if (strpos(trim($v), 'KEY') === 0) {
                continue;
            }
            preg_match_all("/`([^`]+)`([^']+)(.*)/", $v, $matches);
            $paramComment[$matches[1][0]] = trim($matches[3][0], ",'");
        }
        if (strpos(trim($v), 'ENGINE') !== false) {
            preg_match("/.*COMMENT='(.+)'/", $v, $m);
            $tableComment = trim($m[1], ",'");
        }

        return array('table' => $tableComment, 'param' => $paramComment);
    }

    private function getCreateTableSql($tableName)
    {
        $sql = "SHOW CREATE TABLE `" . $tableName . "`";
        $queryObj = Model()->query($sql);
        return $queryObj;
    }

    function parseInsert()
    {
        $tmp = preg_split("#\s+#", $this->_sql, -1, PREG_SPLIT_NO_EMPTY);
//        $r['table'] = $tmp[2];
//        $r['action'] = $tmp[0];
        $param = trim(trim($tmp[3], "("), ")");
        $value = trim(trim($tmp[5], "("), ")");
        $paramArr = explode(",", $param);
        $valueArr = explode(",", $value);
        $comment = $this->getComment($this->_tableName[0]);
        $table = "插入表{$this->_tableName[0]}({$comment['table']})<table border='1' cellspacing='0' cellpadding='1'>";
        $table .= "  <tr>";
        foreach ($paramArr as $k => $v) {
            $table .= "    <th>" . trim($v, "	`") . "</th>";
        }
        $table .= "  </tr>";
        $table .= "  <tr>";
        foreach ($valueArr as $k => $v) {
            $table .= "    <td>" . trim($v, "	'") . "</td>";
        }
        $table .= "  </tr>";

        $table .= "  <tr>";
        foreach ($paramArr as $k => $v) {
            if (isset($comment['param'][trim($v, "	`")])) {
                $table .= "    <td>" . $comment['param'][trim($v, "	`")] . "</td>";
            }
        }
        $table .= "  </tr>";
        $table .= "</table>";
        return $table;
    }

    function setSql($sql)
    {
        $this->_sql = $sql;
        return $this;
    }

}