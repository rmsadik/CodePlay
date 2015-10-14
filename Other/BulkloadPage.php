<?php

class BulkloadPage extends HydraPage
{
	protected $title=""; //page title
	protected $comments=""; //page comments
	protected $fileName = "";

	/**
	 * @var String $output - the output after the CSV file being processed.
	 */
	public $output;

	/**
	 * @var Array[] $allowUploadFormat - allowed for uploading file format
	 */
	protected $allowUploadFormat = array(".csv",".CSV",".txt");
	/**
	 * @var Int $maxFileSize - the max size of upload file
	 */
	protected $maxFileSize = 10000000;
	/**
	 * @var Array[] $templateFileHeader - the tempalte file header, which contains all fields that the upload file should have in the first row
	 */
	protected $templateFileHeader = null;

	protected $env_array = array();

	/**
	 * @var Int[] - only users who have "System Admin" feature can have the debug flag
	 */
	private $roles_allowToHaveDebugFlag = array("System Admin");
	protected $debugFlags = array(
									array("Debug - No","No"),
									array("Debug - Yes","Yes")
								);

   	public function __construct()
	{
		parent::__construct();
	}

	public function onInit($param)
	{
		parent::onInit($param);
		if($this->showDebugFlag())
		{
			$debugFlag = new TDropDownList();
			$debugFlag->setID("debugFlag");
			$this->bindList($debugFlag,$this->debugFlags);
			$this->debugPanel->Controls[] =$debugFlag;
		}
	}

	public function onLoad($param)
    {
    	parent::onLoad($param);

    	$this->setTitle("","");
    	$this->setErrorMessage("");

    	$size =$this->loadMaxFileSize();
    	if($size!=null){$this->maxFileSize = $size;}

    	$allowFormatArray = $this->loadAllowFormatArray();
    	if($allowFormatArray!=null){$this->allowUploadFormat = $allowFormatArray;}

    	$template = $this->loadTemplateFileHeader();
   		if($template!=null){$this->templateFileHeader = $template;}

   		// if there is no header has been set up for the upload file
   		if($this->templateFileHeader===null)
   		{
   			$this->BulkLoadForm->Visible=false;
   			$this->BulkLoadHeader->Text="NO Tempalte File Header has been set up!";
   			$this->BulkLoadHeader->Visible=true;
   		}

   		$this->Title->Text = $this->title;
       	$this->Comments->Text = $this->comments. "<br />File Format should be Comma Delimited File (".implode(",",$this->allowUploadFormat).") - Max File Size: ".$this->translateFileSize($this->maxFileSize);

       	$this->FileUploader->setMaxFileSize($this->maxFileSize);
    }

    /**
     * Called by the TFileUpload, on event: "OnFileUpload"
     * checking file format and file size
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
 	public function fileUploaded($sender,$param)
    {
    	$this->setEnvironment(array());
    	//store original PHP Environemt Values
    	$orginalEnv = $this->getPHPEnvi($this->env_array);

    	//change PHP Env
    	$this->setPHPEnvi($this->env_array);
    	$error ="";
    	if($sender->HasFile)
    	{
    		if($this->fileFormatAllow($sender->FileName))
    		{$this->loadFile($sender,$param);}
    		else
    		{
    			  $error="Error Ocurred during file uploading.
    				Please check file format(".implode(",",$this->allowUploadFormat).")";
    		}
    	}
    	else
    	{
    		$error="Error Ocurred during file uploading.
    				Please check file size (not greater than ".$this->translateFileSize($this->maxFileSize).")
    				and file format(".implode(",",$this->allowUploadFormat).")";
    	}

    	//set PHP Env to whatever it was
    	$this->setPHPEnvi($orginalEnv);
    	if($error>"")
    	{
    		$this->setErrorMessage($error);
    		$this->ResultPanel->Visible=false;
    	}
    }

    /**
     * Processing the uploaded file
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function loadFile($sender,$param)
    {
		$this->ResultPanel->Visible=true;
		$debugFlag=false;
		if($this->showDebugFlag())
			$debugFlag = ($this->MainContent->findControl("debugFlag")->getSelectedValue()==$this->debugFlags[1][1] ? true: false);

    	$this->output = "";
    	$file = fopen($sender->LocalName, "r");
    	$this->fileName = $sender->FileName;
    	if($file===false)
    		$this->setErrorMessage("Unable to open uploaded file!");
    	else
    		$this->importData($file,$this->output,$debugFlag);

		$this->Result->Text ="<div style='display:block; border:1px #cccccc solid; padding: 10px;'>".$this->output."</div>";
		fclose($file);
    }

    protected function getFileName()
    {
    		return $this->fileName;

    }

     protected function importData($file,&$output,$debug=false)
     {

     }

    /**
     * set Page Title
     *
     * @return String - wanted page title
     */
    public function setTitle($title,$comments="")
    {
    	$this->title = $title;
    	$this->comments = $comments;
    }

    /**
     * setting up the PHP environment for file loading
     *
     * @param array[] $env_array - i.e: array('max_execution_time'=>0)
     */
    public function setEnvironment($env_array)
    {
    	$this->env_array=$env_array;
    }

    /**
     * load Max Upload File Size
     *
     * @return int - the maxFileSize
     */
    public function loadMaxFileSize()
    {
    	return null;
    }

    /**
     * load Allowed uploading file format
     *
     * @return Array[] String
     */
    public function loadAllowFormatArray()
    {
    	return null;
    }

    /**
     * load the Template File Header(the first row of the upload file)
     *
     * @return Array[] String - the array of all fields that should in the first row of the upload file
     */
    public function loadTemplateFileHeader()
    {
    	return null;
    }


    /**
     * translate the file size to Human readable format
     *
     * @param int $fileSize
     * @return String
     */
    public function translateFileSize($fileSize)
    {
    	$extension = "Bytes";
    	if($fileSize>=1000000000)
    	{
	    	$extension = "G";
	    	$fileSize = $fileSize/1000000000;
    	}
    	else if($fileSize>=1000000)
    	{
    		$extension = "M";
    		$fileSize = $fileSize/1000000;
    	}
    	else if($fileSize>=1000)
    	{
    		$extension = "K";
    		$fileSize = $fileSize/1000;
    	}
    	return $fileSize.$extension;
    }

    /**
     * Checking whether the file format is allowed
     *
     * @param string $filename
     * @return boolean
     */
    public function fileFormatAllow ($filename)
	{
		$allow = false;
		$exts = split("[/\\.]", strtolower($filename)) ;
		$fileformat =  ".".$exts[count($exts)-1];
		foreach ($this->allowUploadFormat as $format)
		{
			if(strtolower($format)==$fileformat){$allow=true;}
		}
		return $allow;
	}

	public function downloadTemplate()
	{
		$content =implode(",",$this->templateFileHeader);
		$contentServer = new ContentServer();
		$assetId = $contentServer->registerAsset(ContentServer::TYPE_REPORT, str_replace("Controller","_template",get_class($this)).".csv", $content);
		$this->assetId->Value=$assetId;
	}

	public function setPHPEnvi($array)
    {
    	foreach($array as $name=>$value)
    	{ini_set($name,$value);}
    }

    public function getPHPEnvi($array)
    {
    	$temp = array();
    	foreach ($array as $name=>$value)
    	{
    		$temp[$name] = ini_get($name);
    	}
    	return $temp;
    }

 	protected function showDebugFlag()
    {
    	return  in_array(Core::getRole()->getName(),$this->roles_allowToHaveDebugFlag) ? true : false;
    }

	/**
	 * Bind data to a List
	 *
	 * @param TDropDownList $listToBind
	 * @param array[] HydraEntity $dataSource
	 * @param HydraEntity $selectedItem
	 * @param bool $enable
	 */
	protected function bindList(&$listToBind, $dataSource, $selectedValues = null, $enable = true)
	{
		$listToBind->DataSource = $dataSource;
        $listToBind->dataBind();

        if($selectedValues!=null)
        {
        	$listToBind->setSelectedValues($selectedValues);
        }
        $listToBind->Enabled=$enable;
	}

	protected function translateToArray($row,&$output,$debug=false)
    {
    	if(is_array($row))
	    	$fields = $row;
    	else
    		$fields = explode(',',$row);
		$no_of_fields = sizeof($this->templateFileHeader);

		if($debug)
		{
			$output .= "<h3>Input Array</h3> =><br />{";
			foreach($fields as $index=>$field)
			{$output .= "<li style='margin-left: 20px;'>$index=>$field </li>";}
			$output .= "}<br />";

			$output .= "<h3>header Array</h3> =><br />{";
			foreach($this->templateFileHeader as $index=>$field)
			{$output .= "<li style='margin-left: 20px;'>$index=>$field </li>";}
			$output .= "}<br />";
		}

		if(count($fields)!=$no_of_fields)
			return false;

		$array = array();
		for($i=0;$i<$no_of_fields;$i++)
		{
			$index = trim($this->templateFileHeader[$i]);
			$field = trim($fields[$i]);
			if($index != $field)
				$array[$index] = $field;
		}
		return $array;
    }

	protected function debugSql($sql,$debug=false)
	{
		if($debug==true)
    		return "<font style='color:white;background:red;'>$sql</font><br/><br />";
	}

 	protected function error($text)
    {
    	return "<font style='color:red; font-weight:bold;'>$text Skipping.</font>";
    }

	protected function escapeString($string)
	{
		if (!get_magic_quotes_gpc())
		{
			$string = addslashes($string);
		}
		return $string;
	}
}
?>