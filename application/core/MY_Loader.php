<?php if (!defined('BASEPATH')) exit('No direct access allowed.');
 
// ------------------------------------------------------------------------

/**
 * 重写loader，使支持扩展的db驱动
 * @author "i@huanglixiang.com" / 2013-5-16 下午2:46:08
 *
 */
class MY_Loader extends CI_Loader 
{

	 /**
	 * 构造函数
	 * 
	 * @access public
	 * @return void
	 */
    public function __construct() 
    {
		parent::__construct();
    }

	/**
	 * Database Loader For SAE
	 *
	 * @param	string	the DB credentials
	 * @param	bool	whether to return the DB object
	 * @param	bool	whether to enable active record (this allows us to override the config setting)
	 * @return	object
	 */
	public function database($params = '', $return = FALSE, $active_record = NULL)
	{
		// Grab the super object
		$CI =& get_instance();

		// Do we even need to load the database class?
		if (class_exists('CI_DB') AND $return == FALSE AND $active_record == NULL AND isset($CI->db) AND is_object($CI->db))
		{
			return FALSE;
		}

		require_once(APPPATH.'database/DB.php');

		if ($return === TRUE)
		{
			return DB($params, $active_record);
		}

		// Initialize the db variable.  Needed to prevent
		// reference errors with some configurations
		$CI->db = '';

		// Load the DB class
		$CI->db =& DB($params, $active_record);
	}
}

/* End of file SAE_Loader.php */
/* Location: ./application/libraries/SAE_Loader.php */
