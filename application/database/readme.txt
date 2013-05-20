@author "i@huanglixiang.com" / 2013-5-17

数据库（主从）读写分离扩展程序基于ci v2.1.3开发，其它版本不保证能正常运行

相关文件：
application/config/database.php  配置文件
application/core/MY_Loader.php  修改require路径
application/database/DB.php  修改CI_DB类生成
application/database/CI_DB_for_master_slave.php  读写分离代码实现




==================附1：config/database.php 配置=====================

/**
 * 从库配置
 * 如果主库也参与查询，请将主库的配置也写一份到slave的list中
 * 从库配置hostname/username/password，如果不填则默认与主库一样
 * 从库配置weights，范围为1-10，默认为10，数字越大表示权重越大，命中率也越大，总权重=所有从库的weights总和
 * 
 */
$db['default']['slave_cfg']['enable'] = TRUE; //是否启用从库
$db['default']['slave_cfg']['ignore_invalid'] = 30 ;//单位：秒,最小1秒。db连接不上时，多久之内都不再去连接（需要有自定义函数save_cache和get_cache支持）
$db['default']['slave_cfg']['list'][] = array('hostname'=>$db['default']['hostname'],'weights'=>2);
$db['default']['slave_cfg']['list'][] = array('hostname'=>'192.168.18.132:3306','weights'=>5);
$db['default']['slave_cfg']['list'][] = array('hostname'=>'192.168.18.132:123456','weights'=>5);//无效的slave