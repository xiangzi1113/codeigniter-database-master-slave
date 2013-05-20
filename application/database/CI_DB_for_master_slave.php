<?php
	/**
	 * 扩展父类CI_DB_driver，支持读写分离
	 * @author "i@huanglixiang.com" / 2013-5-17 上午11:26:16
	 *
	 */
	class CI_DB_for_master_slave extends CI_DB_PRE{
		
		var $smater_or_slave = 'master';
		
		/**
		 * 重写initialize()
		 * 
		 * @author "i@huanglixiang.com" / 2013-5-17 下午3:12:15
		 *
		 * @param string $sql
		 * @return boolean
		 */
		function initialize($sql=''){
			if (!empty($this->slave_cfg['enable'])){
				if ($sql){
					//只有最后执行sql脚本方法simple_query()才会传入$sql变量，这样做意义是在查询前强制初始化connect
					$this->_MS_connect($sql);
				}else{
					return TRUE;
				}
			}
		
			return parent::initialize();
		}
		
		/**
		 * 重写simple_query()
		 * 
		 * @author "i@huanglixiang.com" / 2013-5-17 下午3:12:25
		 *
		 * @param string $sql
		 */
		function simple_query($sql){
			if (!empty($this->slave_cfg['enable'])){ //如果开启了slave，则每次查询前强制初始化一次连接
				$this->initialize($sql);
			}
		
			return parent::simple_query($sql);
		}
		
		/**
		 * 实现读写分离
		 * 
		 * @author "i@huanglixiang.com" / 2013-5-17 下午3:12:36
		 *
		 * @param string $sql
		 */
		function _MS_connect($sql){
			
			//检查sql语句中是否有写操作
			$is_write = $this->is_write_type($sql);

			if ($this->_MS_connect_check($is_write)){
				return ;
			}
			
			//先备份主库配置
			if (empty($this->slave_cfg['cur_master_cfg'])){
				//把master配置备份到slave配置中，因为如果用slave配置时会把原来主配置覆盖了
				$this->slave_cfg['cur_master_cfg']['hostname'] = $this->hostname;
				$this->slave_cfg['cur_master_cfg']['username'] = $this->username;
				$this->slave_cfg['cur_master_cfg']['password'] = $this->password;
			}
			
				
			if ($is_write == false && !empty($this->slave_cfg['enable'])){//无写操作，检查一下slave的配置是否正确
				
				//去除无效的slave
				if (function_exists('get_cache')){
					foreach ($this->slave_cfg['list'] as $k=>$v){
						$cache_key = __FILE__.__FUNCTION__.'invalid_slave_'.$v['hostname'];
						if (get_cache($cache_key)){
							unset($this->slave_cfg['list'][$k]);
						}
					}
				}
				
				while (!empty($this->slave_cfg['list'])) {
					$this->_MS_select_slave();

					$this->hostname = $this->slave_cfg['cur_slave_cfg']['hostname'];
					$this->username = $this->slave_cfg['cur_slave_cfg']['username'];
					$this->password = $this->slave_cfg['cur_slave_cfg']['password'];
					
					//php.ini里可以设置超时时间，mysql.connect_timeout
					$this->conn_id_slave = ($this->pconnect == FALSE) ? $this->db_connect() : $this->db_pconnect();
					if (!empty($this->conn_id_slave)){
						$this->_MS_set_connect('slave',true);
						return ;
					}else{
						//设置缓存，多久之内都不再去连接此slave
						if (function_exists('save_cache')){
							$cache_key = __FILE__.__FUNCTION__.'invalid_slave_'.$this->slave_cfg['cur_slave_cfg']['hostname'];
							$cache_time = empty($this->slave_cfg['ignore_invalid_slave_time'])?60:max(1,$this->slave_cfg['ignore_invalid_slave_time']);
							save_cache($cache_key,1, $cache_time);
						}
					}
				}
				//如果所有slave连接都不正常，则用下面的master吧
				$this->slave_cfg['cur_slave_cfg'] = array();
			}
			

			$this->hostname = $this->slave_cfg['cur_master_cfg']['hostname'];
			$this->username = $this->slave_cfg['cur_master_cfg']['username'];
			$this->password = $this->slave_cfg['cur_master_cfg']['password'];
			$this->conn_id_master = ($this->pconnect == FALSE) ? $this->db_connect() : $this->db_pconnect();
			$this->_MS_set_connect('master',true);

		}
		
		/**
		 * 先择一个slave配置
		 * @author "i@huanglixiang.com" / 2013-5-20 下午12:55:04
		 *
		 * @return unknown
		 */
		function _MS_select_slave(){

			//根据weights取一个slave配置
			$_weight_arr = array();
			foreach ($this->slave_cfg['list'] as $k=>$v){
				$weights = empty($v['weights'])?10:$v['weights'];
				$weights = max($v['weights'],1);//校正益处的weights值
				$weights = min($v['weights'],10);
				for ($j=1;$j<=$weights;$j++){
					$_weight_arr[] = $k; //$k在$_weight_arr数组中占的数量比例即权重
				}
			}
			shuffle($_weight_arr);//乱序
			
			$_slave = $this->slave_cfg['list'][$_weight_arr[0]];
			unset($this->slave_cfg['list'][$_weight_arr[0]]);
			
			$slave['hostname'] = !empty($_slave['hostname'])?$_slave['hostname']:$this->slave_cfg['cur_master_cfg']['hostname'];
			$slave['username'] = !empty($_slave['username'])?$_slave['username']:$this->slave_cfg['cur_master_cfg']['username'];
			$slave['password'] = !empty($_slave['password'])?$_slave['password']:$this->slave_cfg['cur_master_cfg']['password'];
				
			$this->slave_cfg['cur_slave_cfg'] = $slave;
		}
		
		/**
		 * 根据$is_write判断是否已经连接过数据库
		 * @author "i@huanglixiang.com" / 2013-5-20 上午11:39:22
		 *
		 * @param unknown_type $is_write
		 * @return boolean
		 */
		function _MS_connect_check($is_write){

			if ($is_write == true && !empty($this->conn_id_master)){
				$this->_MS_set_connect('master');
				return true;
					
			}
				
			if ($is_write == false && !empty($this->conn_id_slave)){
				$this->_MS_set_connect('slave');
				return true;
			}
			
			return false;
		}
		
		/**
		 * 修改$this->conn_id
		 * @author "i@huanglixiang.com" / 2013-5-20 下午12:55:24
		 *
		 * @param unknown_type $master_or_slave
		 */
		function _MS_set_connect($master_or_slave, $select_db=false){
			if(in_array($master_or_slave, array('master', 'slave'))){
				$this->conn_id = $this->{'conn_id_'.$master_or_slave};
				
				if($select_db){
					if ($this->database != ''){
						if ( ! $this->db_select()){
							log_message('error', 'Unable to select database: '.$this->database);
					
							if ($this->db_debug)
							{
								$this->display_error('db_unable_to_select', $this->database);
							}
							return FALSE;
						}else{
							// We've selected the DB. Now we set the character set
							if ( ! $this->db_set_charset($this->char_set, $this->dbcollat)){
								return FALSE;
							}
					
							//return TRUE;
						}
					}
				}

				//几个对象变量，防止echo $this->db->hostname的时候显示不正确的
				$this->hostname = $this->slave_cfg['cur_'.$master_or_slave.'_cfg']['hostname'];
				$this->username = $this->slave_cfg['cur_'.$master_or_slave.'_cfg']['username'];
				$this->password = $this->slave_cfg['cur_'.$master_or_slave.'_cfg']['password'];
				$this->smater_or_slave = $master_or_slave;


				return TRUE;
			}else{
				exit('$master_or_slave error:');
			}
		}
	}