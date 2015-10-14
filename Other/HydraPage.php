<?php
class HydraPage extends TPage
{
	/**
	 * The blogger for analyzing
	 * @var BlogProfiler
	 */
	private $_bp;
	/**
	 * the role locks for the page, which will determine whether the current user have the access to that page
	 * @var string
	 */
	protected $roleLocks = "";

	/**
	 * Dont Hardcode service
	 *
	 * @var unknown_type
	 */
	protected $dontHardcodeService;
	/**
	 * constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->_bp = BlogSession::getBlogProfiler();
		$this->_bp->setCaller(basename(__FILE__, '.php') . ":" . get_class($this) . ":instance");

		$this->_checkAutoLogout();

		$_SESSION['last_activity'] = time();
		$this->_bp->setDebugParam('last_activity', $_SESSION['last_activity']);

		$this->getPage()->setTitle($this->getPageTitle());
		$this->dontHardcodeService = Factory::service("DontHardcode");
	}
	/**
	 * (non-PHPdoc)
	 * @see TPage::render()
	 */
	public function onInit($param)
	{
		parent::onInit($param);
		try
		{
			//loading BSuite.js
			$applicationPath = Prado::getApplication()->getBasePath();
			$this->getPage()->getClientScript()->registerScriptFile('bsuiteJs', Prado::getApplication()->getAssetManager()->publishFilePath($applicationPath . '/../common/bsuiteJS/bsuite.9.js', true));

			//loading controller.js
			$className = get_class($this);
			$class = new ReflectionClass($className);
			$fileDir = dirname($class->getFileName()) . DIRECTORY_SEPARATOR;
			if (is_dir($fileDir))
			{
				$jsFiles = $cssFiles = array();
				//loop through the directory to find the last
				foreach(glob($fileDir . '*.{js,css}', GLOB_BRACE) as $file)
				{
					$fileName = str_replace($fileDir, '', $file);
					preg_match("/^" . $className . "\.([0-9]+\.)?(js|css)$/i", $fileName, $versionNo);
					if (!isset($versionNo[0]) || !isset($versionNo[1]) || !isset($versionNo[2]))
						continue;
					$type = trim(strtolower($versionNo[2]));
					$versionNo = ($versionNo = trim(strtolower($versionNo[1]))) === '' ? 0 : str_replace('.', '', $versionNo);
					if ($type === 'js') //if loading a javascript
						$jsFiles[$versionNo] = $fileName;
					else if ($type === 'css')
						$cssFiles[$versionNo] = $fileName;
				}

				if (count($jsFiles) > 0 )
				{
					ksort($jsFiles);
					$this->getPage()->getClientScript()->registerScriptFile('pageJs', $this->publishAsset(end($jsFiles)));
				}
				if (count($cssFiles) > 0 )
				{
					ksort($cssFiles);
					$this->getPage()->getClientScript()->registerStyleSheetFile('pageCss', $this->publishAsset(end($cssFiles)));
				}
			}
		}
		catch(Exception $e)
		{}
	}
	/**
	 * (non-PHPdoc)
	 * @see TControl::onLoad()
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);

		$this->roleLock();

		$this->getMaster()->MessagePanel->Visible = false;
	}
	/**
	 * Checing Auto logout
	 */
	private function _checkAutoLogout()
	{
		if (isset($_SESSION['last_activity']) && (time() - $_SESSION['last_activity'] > 3600) && Core::getRole()->getName() != "Worm")
		{
			Dao::prepareNewConnection(Dao::DB_MAIN_SERVER, true); //reconnect to main server

			$ua = Core::getUser();
			$ua->setIsOnline(0);
			Dao::save($ua);

			$auth = $this->Application->Modules['auth'];
			$auth->logout();

			Logging::LogUser($ua, AuthAction::LOGOUT, AuthDomain::NORMAL);

		   	$this->Response->Redirect("/login?start=".$_SERVER['REQUEST_URI']);
		}
	}
	/**
	 * Get the default page title
	 * @return string
	 */
	public function getPageTitle()
	{
		$params = array();
		foreach($this->getApplication()->getParameters() as $param)
		{$params[]= $param;}
		return str_replace("Controller","",get_class($this)). " - ".implode(" - ",$params);
	}
	/**
	 * redirect the page back to home page
	 */
	protected function redirectHome()
	{
		$this->Response->redirect('/' );
	}
	/**
	 * check whether the user has the access to the target the page. if not, redirect the page back to home page
	 */
	protected function roleLock()
	{
		if($this->roleLocks == "")
			return;

		$feature = $this->roleLocks;

		if(strpos($feature,',') !== false)
			$lockFeatures = explode(',',$feature);
		else
			$lockFeatures = array($feature);

		$role = Core::getRole();
		if($role == null)
			$this->redirectHome();

		if (Session::checkRoleFeatures($lockFeatures))
			return;

		$this->redirectHome();
	}
    /**
     * getter for the information label
     */
    public function getInfoMessage()
    {
    	return $this->getMaster()->InfoMessage->Text;
    }
	/**
	 * setter for the information label
	 *
	 * @param string $message
	 */
    public function setInfoMessage($message)
    {
    	$this->getMaster()->MessagePanel->Visible = true;
    	$this->getMaster()->InfoMessage->Text = $message;
    }
    /**
     * Setter for playing a info sound
     *
     * @param string $message The information message
     * @param string $src     The sound file path
     */
    public function setInfoMessageSound($message, $src='/themes/images/info.wav')
    {
    	if (preg_match('/(?i)msie [1-8]/',$this->getRequest()->getUserAgent())){
    		$sound = "<bgsound id=\"sound\" src=\"$src\">";
    	}else{
    		$sound = "<audio autoplay  id=\"beep-error\" preload=\"auto\">
    	    			<source src=\"$src\" controls></source>
        			 </audio>";
    	}

    	$this->setInfoMessage($message.$sound);
    }
	/**
	 * getter for the error label
	 */
    public function getErrorMessage()
    {
    	return $this->getMaster()->ErrorMessage->Text;
    }
    /**
     * Setter for playing an NOT error sound
     *
     * @param string $string The error message
     * @param string $src    The sound file path
     */
    public function setNotErrorSound($string=false, $src='/themes/images/info.wav')
    {

    	if (preg_match('/(?i)msie [1-8]/',$this->getRequest()->getUserAgent())){
    		$sound = "<bgsound id=\"sound\" src=\""+$src+"\">";
    	}else{
    		$sound = "<audio autoplay id=\"sound\" preload=\"auto\">
    	    			<source src=\""+$src+"\" controls></source>
        			 </audio>";
    	}
    	if($string){
    		return $sound;
    	}
    	$this->setErrorMessage($sound);
    }
    /**
     * Setter for playing an NOT error sound
     *
     * @param string $string The error message
     * @param string $src    The sound file path
     *
     * @return string
     */
    public function setErrorSound($string = false, $src = '/themes/images/info.wav')
    {

    	if (preg_match('/(?i)msie [1-8]/',$this->getRequest()->getUserAgent())){
    		$sound = "<bgsound id=\"sound\" src=\"$src\">";
    	}else{
    		$sound = "<audio autoplay id=\"sound\" preload=\"auto\">
        	    			<source src=\"$src\" controls></source>
            			 </audio>";
    	}
    	if($string){
    		return $sound;
    	}
    	$this->setErrorMessage($sound);
    }
    /**
     * Setter for playing an NOT error sound
     *
     * @param string $string The error message
     * @param string $src    The sound file path
     */
    public function setErrorMessageSound($message, $src = '/themes/images/warning.wav')
    {
    	if (preg_match('/(?i)msie [1-8]/',$this->getRequest()->getUserAgent())){
    		$sound = "<bgsound id=\"sound\" loop=\"2\" src=\"$src\">";
    	}else{
    		$sound = "<audio autoplay  id=\"sound\" preload=\"auto\">
    	    	    			<source src=\"$src\" controls></source>
    	        			 </audio>";
    	}

    	$this->setErrorMessage($message.$sound);
    }
    /**
     * Setter for message
     *
     * @param string $message
     */
    public function setErrorMessage($message)
    {
    	$this->getMaster()->MessagePanel->Visible = true;
    	$this->getMaster()->ErrorMessage->Text = str_replace("\\","",$message);
    }
    /**
     * prepareing the search string [deprecated]
     *
     * @param string $str The sql
     * @return string
     */
    public function prepareSearchString($str)
    {
    	// Do nothing, as this is handled by the Dao::search method automatically
    	return $str;
    }
}

?>