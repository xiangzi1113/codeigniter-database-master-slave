<?php
	/**
	 * 扩展父类CI_DB_driver，支持读写分离
	 * @author "i@huanglixiang.com" / 2013-5-17 上午11:26:16
	 *
	 */
	class CI_DB_for_master_slave extends CI_DB_PRE{
		
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
					$this->_master_slave_connect($sql);
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
		function _master_slave_connect($sql){
			//检查sql语句中是否有写操作
			$is_write = $this->is_write_type($sql);
		
			if ($is_write == true && !empty($this->conn_id_master)){
				//如果已经连接过，则使用旧的连接
				$master_or_slave = 'master';
					
			}elseif ($is_write == false && !empty($this->conn_id_slave)){
				//如果已经连接过，则使用旧的连接
				$master_or_slave = 'slave';
					
			}else{
		
				if (empty($this->slave_cfg['cur_master_cfg'])){
					//把master配置备份到slave配置中，因为如果用slave配置时会把原来主配置覆盖了
					$this->slave_cfg['cur_master_cfg']['hostname'] = $this->hostname;
					$this->slave_cfg['cur_master_cfg']['username'] = $this->username;
					$this->slave_cfg['cur_master_cfg']['password'] = $this->password;
				}
					
				$connect_master = $is_write;
					
				if ($connect_master == false){//无写操作的情况下，检查一下slave的配置是否正确
					if(empty($this->slave_cfg['enable'])){
						//如果没有开启使用slave，则强制改成使用master
						$connect_master = true;
		
					}else{
						//如果slave配置列表不存在，则强制改成使用master
						if (empty($this->slave_cfg['list'])){
							$connect_master = true;
							
						}else{

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
							$this->slave_cfg['cur_slave_cfg']['hostname'] = !empty($_slave['hostname'])?$_slave['hostname']:$this->slave_cfg['cur_master_cfg']['hostname'];
							$this->slave_cfg['cur_slave_cfg']['username'] = !empty($_slave['username'])?$_slave['username']:$this->slave_cfg['cur_master_cfg']['username'];
							$this->slave_cfg['cur_slave_cfg']['password'] = !empty($_slave['password'])?$_slave['password']:$this->slave_cfg['cur_master_cfg']['password'];
							
						}
					}
				}
				$master_or_slave = $connect_master?'master':'slave';
			}
		
		
			switch ($master_or_slave) {
				case 'slave':
					if (empty($this->conn_id_slave)){
						$this->conn_id_slave = ($this->pconnect == FALSE) ? $this->db_connect() : $this->db_pconnect();
					}
					
					$this->conn_id = $this->conn_id_slave;
					break;

				case 'master':
					if (empty($this->conn_id_master)){
						$this->conn_id_master = ($this->pconnect == FALSE) ? $this->db_connect() : $this->db_pconnect();
					}
					
					$this->conn_id = $this->conn_id_master;
					break;

				default:
					exit('$master_or_slave error');
					break;
			}
			

			//修改几个变量，防止echo $this->db->hostname的时候显示不正确的
			$this->hostname = $this->slave_cfg['cur_'.$master_or_slave.'_cfg']['hostname'];
			$this->username = $this->slave_cfg['cur_'.$master_or_slave.'_cfg']['username'];
			$this->password = $this->slave_cfg['cur_'.$master_or_slave.'_cfg']['password'];
		}
		
		
	}