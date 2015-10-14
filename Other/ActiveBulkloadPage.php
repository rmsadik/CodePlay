<?php

class ActiveBulkloadPage extends HydraPage
{
	private $debugFlags = array(
									array("Debug - No","No"),
									array("Debug - Yes","Yes")
								);
	private $downloadingTemplate=false;
	
	public function onInit($param)
	{
		parent::onInit($param);
		if(UserAccountService::isSystemAdmin())
		{
			$debugFlag = new TDropDownList();
			$debugFlag->setID("debugFlag");
			$this->bindList($debugFlag,$this->debugFlags);
			$this->updateFormPanel->getControls()->add($debugFlag);
		}
	}
	
	public function onLoad($param)
    {
    	if(!$this->Page->isPostBack && !$this->Page->isCallBack)
    	{
    	}
    	$comments = "";
    	$title = "";
    	$this->setPageTitle($title,$comments);
    	$this->Comments->getControls()->add("<h3>$title</h3><div style='width:100%'>$comments</div>");
    }
    
    public function downloadTemplate($sender,$param)
	{
		$this->activeJavascript->Text="";
		$content =implode(",",$this->getTemplateFileHeader());
		$contentServer = new ContentServer();
		$assetId = $contentServer->registerAsset(ContentServer::TYPE_REPORT, str_replace("Controller","_template",get_class($this)).".csv", $content);
		$this->activeJavascript->Text="<script type='text/javascript'>window.open('/report/download/$assetId');</script>";
	}
	
	public function fileUploaded($sender, $param)
	{
		$this->activeJavascript->Text="";
		$this->setErrorMessage("");
		$this->setInfoMessage("");
		
    	$debug = ($this->MainContent->findControl("debugFlag")->getSelectedValue()==$this->debugFlags[1][1] ? true: false);
    	if(!$sender->HasFile)
    	{
    		$this->setErrorMessage("No / invalid file uploaded or file size is greater than ".($sender->getMaxFileSize() / (1024 *1024)). "M");
    		return;
    	}
    	
    	$file = fopen($sender->LocalName, "r");
    	if($file===false)
    	{
    		$this->setErrorMessage("Unable to open uploaded file!");
    		return;
    	}
    	
    	//deactive all buttons
    	$this->updateFormPanel->Enabled=false;
    	
    	//generate result table
    	$table ="You've successfully uploaded the file '".$sender->FileName."', do you want to continue?";
    	$table .="<input type='button' id='yesBtn' value='uploading file' disabled='true' onclick=\"$('".$this->rowDataBtn->getClientId()."').click();$('yesBtn').disabled='true';$('noBtn').disabled='true';\"/>";
    	$table .="<input type='button' id='noBtn' value='No' onclick=\"window.location = window.location.href;\"/>";
    	$table .="<table  width='100%'>";
    	
    	//generat javascript
    	$javascript = array();
    	$javascript[]="<script type='text/javascript'>";
	    	$javascript[]="var processingIndex = 0;";
	    	$javascript[]="var data={};";
	    	try
	    	{
	    		$rowNo=0;
	    		while($row = fgetcsv($file))
	    		{
	    			$javascriptArray = array();
	    			$rowInfoTable = "<table width='100%'>";
	    			foreach($this->translateToArray($row) as $key=>$value)
	    			{
	    				$rowInfoTable .= "<tr>";
		    				$rowInfoTable .= "<td>$key</td>";
		    				$rowInfoTable .= "<td>=></td>";
		    				$rowInfoTable .= "<td>$value</td>";
	    				$rowInfoTable .= "</tr>";
	    				$javascriptArray[]="'$key': '$value'";
	    			}
	    			$rowInfoTable .= "</table>";
	    			
	    			$javascript[]="data[$rowNo] = {".implode(",",$javascriptArray)."};";
	    			$table .="<tr ".($rowNo %2==0? "style='background:#cccccc;'" : "'").">";
	    				$table .="<td width='10%'>Row $rowNo:</td>";
	    				if($debug)
	    					$table .="<td width='20%'>$rowInfoTable</td>";
	    				$table .="<td id='result_$rowNo'>";
	    					$table .="&nbsp;";
//	    					$table .="<img src='/themes/images/ajax-loader.gif' />";
    					$table .="</td>";
    				$table .="</tr>";
    				
	    			$rowNo++;
	    		}
	    	}
	    	catch(Exception $ex)
	    	{
	    		$this->setErrorMessage($ex->getMessage());
	    		return;
	    	}
	    	
	    	$javascript[]="function preSubmitRowData()";
	    	$javascript[]="{";
	    	$javascript[]="		var rowData =  {'index': processingIndex, 'data': data[processingIndex]};";
	    	$javascript[]="		$('".$this->rowData->getClientId()."').value = Object.toJSON(rowData);";
	    	$javascript[]="		$('result_'+ processingIndex).innerHTML = '<img src=\'/themes/images/ajax-loader.gif\' />';";
	    	$javascript[]="}";
	    	$javascript[]="function postSubmitRowData()";
	    	$javascript[]="{";
	    	$javascript[]="		if(processingIndex <= $rowNo){ processingIndex++; $('".$this->rowDataBtn->getClientId()."').click();}";
	    	$javascript[]="		else{alert('$rowNo record(s) loaded successfully');}";
	    	$javascript[]="}";
	    	$javascript[]="function jsHtmlDecode(msg)";
	    	$javascript[]="{";
	    	$javascript[]="		return  msg.replace(/&lt;/g,'<').replace(/&gt;/g,'>');";
	    	$javascript[]="}";
	    	$javascript[]="function finishUploadingFile()";
	    	$javascript[]="{";
	    	$javascript[]="		$('yesBtn').value='Yes';";
	    	$javascript[]="		$('yesBtn').disabled='';";
	    	$javascript[]="}";
	    	
	    	$javascript[]="setTimeout(finishUploadingFile,0);";
	    	
    	$javascript[]="</script>";
    	$table .="</table>";
		
		$this->ResultPanel->getControls()->add($table.implode("\n",$javascript));
		$this->ResultPanel->Visible=true;
		
		$this->debugflag->Value = $debug;
		fclose($file);
	}
	
	public function loadRowData($sender,$param)
	{
		$debug = ( trim($this->debugflag->Value)==1 ? true: false);
		$rowData = json_decode(trim($this->rowData->Value));
		$historyResult = "";
		
		if(!isset($rowData->index))
			return;
			
		try
		{
			$index = $rowData->index;
			if(!isset($rowData->data))
				throw new Exception("No Data!");
				
				
			$data = $rowData->data;
			$rowData = array();
			foreach ($data as $key => $fields)
			{
				$rowData[$key] = $fields;
			}
			
			if($index==0)
				$result="<b>Header Row, Skipping...</b>";
			else
				$result = htmlentities($this->importRowData($index,$rowData,$historyResult,$debug),ENT_QUOTES);
			$this->activeJavascript->Text="<script type='text/javascript'>$('result_$index').innerHTML=jsHtmlDecode('$result');</script>";
		}
		catch(Exception $ex)
		{
			$error = "<h3 style='color:red;'>Error:".htmlentities($ex->getMessage(),ENT_QUOTES)."</h3>";
			if($debug)
			{
				$error = $historyResult.$error;
				$error .= htmlentities("<div>".implode("<br />",explode("\n",$ex->getTraceAsString())."<div>"),ENT_QUOTES);
			}
			$this->activeJavascript->Text="<script type='text/javascript'>$('result_$index').innerHTML=jsHtmlDecode(\"$error\");</script>";
		}
	}
	
	protected function importRowData($index,$dataArray,&$output,$debug=false)
	{
		throw new Exception("Default importRowData...");
	}
    
    protected function getTemplateFileHeader()
    {
    	return array();
    }
    
    protected function bindList(&$list, $source)
    {
    	$list->DataSource = $source;
    	$list->DataBind();
    }
    
	protected function translateToArray($row)
    {
    	if(is_array($row))
	    	$fields = $row;
    	else
    		$fields = explode(',',$row);
    		
    	$header = $this->getTemplateFileHeader();
		if(count($fields)!=count($header))
			throw new Exception("
						Data Error- Number didn't match: 
						<table style='width:100%;text-align:left;font-size:10px;'>
							<tr valign='top'>
								<td>Expecting: ".count($header)." filed(s)<table style='width:90%;margin-left:5%'><tr><td>".implode("</td></tr><tr><td>",$header)."</td></tr></table></td> 
								<td>Got: ".count($fields)." filed(s)<table style='width:90%;margin-left:5%'><tr><td>".implode("</td></tr><tr><td>",$fields)."</td></tr></table></td> 
							</tr>
						</table>");
		
		$array = array();
		for($i=0;$i<count($header);$i++)
		{
			$index = trim($header[$i]);
			$field = trim($fields[$i]);
			if($index != $field)
				$array[$index] = $field;
		}
		return $array;
    }
    
	protected function debugSql($sql,$debug=false)
    {
    	if(!$debug)
    		return;
    	return "<table border=1><tr><td>\$sql:</td><td>".str_replace("\r\n"," ",$sql)."</td></tr></table>";
    }
}
?>