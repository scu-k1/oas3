<?php

class Bootstrap extends Zend_Application_Bootstrap_Bootstrap
{
    /**
     * @var const 設定目前系統使用的角色/身分資料表
     */
    const NOW_ROLE_TABLE = 'sys_role';
    /**
     * @var const 設定目前系統使用的資源/選單資料表
     */
    const NOW_RESOURCE_TABLE = 'sys_resource';

	private $_session;	//session
	
	private $_config;	//資料表

	private $_db;		//Zend_Db from Application_Model_Configure

	public function _initConstruct()
	{
		//直接確認啟動db部份！
		//本專案不使用mail ->bootstrap('mail');
		$this->bootstrap('db')->bootstrap('session');

		$this->_session = new Zend_Session_Namespace( APPLICATION_SESSION );
		$this->_config = new Application_Model_Configure();
		$this->_db = $this->getPluginResource('db')->getDbAdapter();
	}
    
    
    /**
     * 2012-03-26
     * @Author San
     * 
     * feature::初始化系統角色功能
     * 1. 檢查資料庫是否存在身分資料表(此處程式只需檢查資料庫，由別的地方處理當系統身分需要做更新時的邏輯)
     * 2. 若是則掠過，否則建立系統身分資料表
     */
    protected function _initRole()
    {
        $select = $this->_db->select();
        $select->from(self::NOW_ROLE_TABLE);
        $result = $this->_db->fetchRow($select);
        
        if( !$result ){
            $acl = new Zend_Config_Xml( realpath(dirname(__FILE__)).'/configs/acl.xml', 'default' );
            // $acl->locate [多語系使用:zh_TW]
            
            $data = $acl->role->toArray();
            foreach( $data as $k => $v){
                $data = array(
                                "roleName"=>$v['roleName'],
                                "roleKey"=>$v['roleKey'],
                                "description"=>$v['description'],
                                "disable"=>$v['disable']
                             );
                            
                if( !empty($v['parent']) ){
                    
                    //利用reset()來清除原本設定的where條件式
                    $select->reset(Zend_Db_Select::WHERE)
                           ->where("roleName = ?", $v['parent']);
                    // echo $select->__toString();
                    $pResult = $this->_db->fetchRow($select);
                    $data['roleParentId'] = $pResult['roleId'];
                }
                
                if( !($roleId = $this->_db->insert(self::NOW_ROLE_TABLE, $data)) ){
                    throw new Zend_Db_Exception(__FILE__.' '.__LINE__."行 資料寫入錯誤！");    
                }
            }
        
        }elseif( is_array($result) ){
            return true;
        }else{
            throw new Zend_Application_Bootstrap_Exception("初始化系統角色發生問題，變數回傳型態不為空或陣列。");
            Zend_Debug::dump($result);
            die();
        }
    }   
	

	/**
	 * feature::處理系統設定[SESSION]常數
	 */
	protected function _initSessionConst()
	{
		//2011-01-08 San:加入判斷登入後多久設定AUTH驗證失效！
		// if( !is_null($this->_session->AUTH) ){
			// $this->_session->setExpirationSeconds(600, 'AUTH');
		// }
		
		$data = $this->_config->fetchAll("configType = 'APPLICATION'")->toArray();
		
		if( !is_array($this->_session->CONST->APPLICATION) ){
			if( count($data) == 0){
				//先寫入初始化設定值部份！
				$this->_initConfigure();
				$data = $this->_config->fetchAll("configType = 'APPLICATION'")->toArray();
			}
			
			foreach($data as $key => $value){
				$this->_session->CONST->APPLICATION[$value['configkey']]=$value['value'];
			}
		}elseif(count($data) > 0){

			foreach($data as $key => $value){
				if( $this->_session->CONST->APPLICATION[$value['configkey']] !=$value['value']){
					$this->_session->CONST->APPLICATION[$value['configkey']]=$value['value'];
				}
			}
		}			
	}

	/**
	 * feature::讀入模組內configure.xml屬bootstrap區塊
	 * 判斷資料庫內是否存在該筆資料，若未存在新增
	 */
	protected function _initConfigure()
	{
		//若已有設定值，則不再處理此區塊！
		if( is_array($this->_session->CONST->APPLICATION) ){
			return TRUE;
		}
		
		//從設定檔內取出針對一般使用者提供的選單清單寫入access
		$xml = new Zend_Config_Xml( realpath(dirname(__FILE__)).'/configs/application.xml', 'bootstrap' );
		$data = $xml->toArray();
		if( is_array($data['key']['item']) && count($data['key']['item']) > 0){
			foreach( $data['key']['item'] as $k => $v){
				if( preg_match('/FRONTMENUS/', $v['name']) ){
					//取出寫入資料
					$array = Zend_Json::decode($v['extenddata']);
					$array['moduleName'] = $data['key']['moduleName'];
					
					//寫入sys_access
					$sql = $this->_db->select()->from($v['table'],'*')->where("resourceName = '{$array['resourceName']}'");
					$result = $this->_db->query($sql)->fetchAll();
					if( count($result) == 0){
						try{
							//寫入Vocabulary
							$this->_db->insert($v['table'], $array);
						}catch(Zend_Db_Exception $zde){
							echo "FILE:".__FILE__.": LINE:".__LINE__." MESSAGE:".$zde->getMessage();
							die($this->_db->__toString());
						}
					}
				}elseif( $k !=0 ){
					//避免重複寫入Configure!
					$row = $this->_config->fetchRow("configkey = '{$v['name']}'");
					if( !is_object($row)){
						$this->_config->save(array(
							'configType'	=>	$data['key']['moduleName'],
							'configkey'		=>	$v['name'],
							'value'			=>	$v['default'],
							'description'	=>	$v['description'], 
						));
					}else{
						$this->_config->save(array(
							'configureId'		=>	$row->configureId,
							'configType'	=>	$data['key']['moduleName'],
							'configkey'		=>	$v['name'],
							'value'			=>	$v['default'],
							'description'	=>	$v['description'], 
						));
					}
				}
			}
		}
    }	
	

    protected function _initLogger()
    {
        // Create the Zend_Log object
        $logger = new Zend_Log();

        //設定log紀錄資料表的[logtype]欄位值
        $DBwriter = new Zend_Log_Writer_Db( $this->_db, 'apps_logger', 
                                            array(
                                                'loggerType'    =>  'defineType',
                                                'event'         =>  'priorityName',
                                                'refer'         =>  'refer',
                                                'href'          =>  'href',
                                                'input'         =>  'params',
                                                'output'        =>  'return',
                                                'messages'      =>  'message',
                                                'initTime'      =>  'timestamp'
                                            ) );
        
        $logger->setEventItem('defineType', 'AUTH'); //自訂事件類型寫入資料庫欄位logType ( AUTH/MANAGE/OPERATOR/IO/CONFIG )
        if( isset($_SERVER['HTTP_REFERER']))
        $logger->setEventItem('refer', $_SERVER['HTTP_REFERER']);
        $logger->setEventItem('href', "http://{$_SERVER['HTTP_HOST']}{$_SERVER['REQUEST_URI']}");
        
        $logger->addWriter($DBwriter);
        
        // 處理顯示Firebug Logger - Development only
        if ($this->_application->getEnvironment() != 'online') {
            // Init firebug logging
            $fbWriter = new Zend_Log_Writer_Firebug();
            $logger->addWriter($fbWriter);

            // Create the resource for the error log file
            // if you dont want to use the error suppressor "@"
            // you can always use: is_writable
            // http://php.net/manual/function.is-writable.php
            $stream = @fopen(ini_get(APPLICATION_PATH.'/error.log'), 'a');
            if ($stream) {
                $stdWritter = new Zend_Log_Writer_Stream($stream);
                $stdWritter->addFilter(Zend_Log::DEBUG);
                $stdWritter->addFilter(Zend_Log::INFO);
                $logger->addWriter($stdWritter);
            }
        }
        
        $this->bootstrap('frontController');
        $front = $this->getResource('frontController');
        $front->registerPlugin( new OAS_Plugin_Logger( $logger ) );

        return $logger;
    }


	/**
     * feature::初始化系統內Action_Plugin
     */
    protected function _initActionPlugins()
    {
        $this->bootstrap('frontController');
        
        $this->getResource('frontController')
             
             //於Zend_Controller_Plugin_ErrorHandler內已經自動判斷從$response抓取出若有Exception則啟動。
             ->throwExceptions(true)
             ->registerPlugin( new Zend_Controller_Plugin_ErrorHandler(array(
                                                                            'module'     => 'default',
                                                                            'controller' => 'error',
                                                                            'action'     => 'index'
                                                                      )) 
                              );
    }
    
    
	/**
	 * 2011-06-10
	 * San
	 * 加入處理設定所有Action設定Helper library!
	 */
	protected function _initActionHelper()
	{
		//存取MVC架構所有Action()清單功能
		Zend_Controller_Action_HelperBroker::addHelper(new OAS_Helper_ResourceList() );
		//引入集合匯入jQuery套件功能
		Zend_Controller_Action_HelperBroker::addHelper(new OAS_Helper_ImportJavascripts() );
		
		// $asset = new OAS_Helper_ResourceList();
    }	
}