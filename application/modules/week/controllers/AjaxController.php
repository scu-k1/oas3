<?php
/**
 * Month_AjaxController.php
 * 月報模組下處理部份ajax請求流程
 * 
 * @date 2011-11-10
 * @author k1
 * 1. 初始化程式。
 * 
 * --------------------------------------------------
 */


class Week_AjaxController extends Zend_Controller_Action
{
	private $_logger;
	
	function init()
	{
		$this->_logger = $this->getInvokeArg('bootstrap')->getResource('logger');
	}    
	
	/**
	 * feature::
	 * called by::
	 * /month/xxxx/xxxxx 
	 */
	public function tempAction()
	{
	}
	public function setweekqueryAction()//選擇周次切換
	{
		
	}
     public function adquery()//條件查詢的query
	{
		
	}
	
}
?>