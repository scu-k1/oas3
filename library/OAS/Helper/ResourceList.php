<?php
/**
 * @author k1
 * @link http://blog.srmklive.com/2011/01/06/get-list-of-all-modules-with-respective-controllers-and-actions-in-zend-framework/
 * 
 * @desc 處理於Bootstrap.php階段取得整個Application架構的MVC結構項目初始化resource
 * 
 * 1. 處理判斷個別模組下的所有Actions
 * 2. 處理判斷個別模組下的特定Controllers所有Actions
 * 3. 處理查詢是否特定Actions存在於特定模組
 * 4. 
 */
 
class OAS_Helper_ResourceList extends Zend_Controller_Action_Helper_Abstract 
{
	var $_path;
	
    public function direct() {}
	
	/**
	 * feature::處理判斷個別模組下的所有Actions
	 */
	public function getLists()
	{
	}
	
	
	/**
	 * feature::處理判斷個別模組下的所有Actions
	 */
	public function getActionByName()
	{
		
	}
	
	/**
	 * feature::處理判斷個別模組下的特定Controllers所有Actions
	 */
    public function getList($module=null, $controller=null) {
        $module_dir = $this->getFrontController()->getControllerDirectory();
        $resources = array();

        foreach($module_dir as $dir=>$dirpath) {
            $diritem = new DirectoryIterator($dirpath);
            foreach($diritem as $item) {
                if($item->isFile()) {
                    if(strstr($item->getFilename(),'Controller.php')!=FALSE) {
                        include_once $dirpath.'/'.$item->getFilename();
                    }
                }
            }

            foreach(get_declared_classes() as $class){
                if(is_subclass_of($class, 'Zend_Controller_Action')) {
                    $functions = array();

                    foreach(get_class_methods($class) as $method) {
                        if(strstr($method, 'Action')!=false) {
                            array_push($functions,substr($method,0,strpos($method,"Action")));
                        }
                    }
                    $c = strtolower(substr($class,0,strpos($class,"Controller")));
                    $resources[$dir][$c] = $functions;
                }
            }
        }
        return $resources;
    }
}