<?php
/**
 * Compulsory PartInstance Alias Type Controller 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class CompulsoryPartInstanceAliasTypeController extends CRUDPage
{
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext="logistics_compulsoryPartInstanceAliasPattern";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_partType";
	}
	
	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
       	parent::onLoad($param);
       	$this->jsLbl->Text = "";
       	$this->AddButton->Enabled=true;
       	if(!$this->IsPostBack || $param == "reload")
        {        
        	$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
			$this->dataLoad();
			
			if(isset($this->Request['id']))
			{
				$selectedPartType = Factory::service("PartType")->getPartType($this->request['id']);
			}

			$partCode = "";
			if (!empty($selectedPartType))
			{
				$partCode = $selectedPartType->getName(); 
				$luPtPiPattern = Factory::service("Lu_PartType_PartInstanceAliasPattern")->getMandatoryUniquePatternsForPtPiat($selectedPartType,null,null,null);
				
				if(count($this->DataList->DataSource) == 0)
				{
					$this->jsLbl->Text = "<script type=\"text/javascript\">alert('Compulsory Part Aliases need to be added!');</script>";
				}
				
				$this->PartTypeAliasLabel->Text="For - " . $selectedPartType->getName() . " ( " . $partCode . " ) ";
			}
			else
			{
				$this->AddButton->Enabled = false; 
			}
        }
    }
    
    /**
     * Create New Entity
     *
     * @return unknown
     */
    protected function createNewEntity()
    {
    	return new Lu_PartType_PartInstanceAliasPattern();
    }

    /**
     * Lookup Entity
     *
     * @param unknown_type $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("Lu_PartType_PartInstanceAliasPattern")->get($id);
    }
    
    /**
     * Set entity
     *
     * @param unknown_type $object
     * @param unknown_type $params
     * @param unknown_type $focusObject
     */
    protected function setEntity(&$object,$params,&$focusObject = null)
    {
    	$clientId = array();
    	if($this->focusObject->getValue()!='')
    	{
    		$partType = Factory::service("PartType")->getPartType($this->focusObject->getValue());
	        $object->setPartType($partType);
	        $contracts = $partType->getContracts();
	        foreach ($contracts as $contract)
	        {
	        	$cg = $contract->getContractGroup();
	        	$client = $cg->getClient();
	        	if (!in_array($client->getId(), $clientId))
	        	   	$clientId[] = $client->getId();
	        }
    	}
    	try
    	{
	    	if ($params->newPartInstanceAliasType->getSelectedValue() != '')
	    	{
	    		$partInstanceAliasType = Factory::service("PartInstance")->getPartInstanceAliasType($params->newPartInstanceAliasType->getSelectedValue());
	    		$object->setPartInstanceAliasType($partInstanceAliasType);
	    		if ((count($clientId)>1)&&($partInstanceAliasType->getId()==PartInstanceAliasType::ID_CLIENT_ASSET_NUMBER))
	    		{
	    			throw new Exception('Client Asset Number can NOT be shared between clients! Please split the part types into two!');
	    		}
	    	}
	    	
	        if ($params->mandatoryChk)
	        {
	            if ($params->mandatoryChk->Checked == 'true')
	            {
	                $object->setIsMandatory(true);
	            }
	            else
	            {
	                $object->setIsMandatory(false);
	            }
	        }
	        if ($params->uniqueChk)
	        {
	        	if ($params->uniqueChk->Checked == 'true')
	        	{
	        		$object->setIsUnique(true);
	        	}
	        	else
	        	{
	        		$object->setIsUnique(false);
	        	}
	        }
	        
	        if ($params->format->Text != '')
	        {
	        	$object->setSampleFormat($params->format->Text);
	        }
	        else
	        	$object->setSampleFormat('');
	        
	        if (UserAccountService::isSystemAdmin()&&($params->format->Text != ''))
	        {
	        	if ($params->pattern->Text != '')
	        		$object->setPattern($params->pattern->Text);
	        	else
	        		throw new Exception("Regex pattern required!");        	
	        }        
	        else
	        {
	        	if ($params->pattern->Text != '')
	        		$object->setPattern($params->pattern->Text);
	        	else
		        	$object->setPattern('');
	        }
	    	$object->setActive(1);
	    	
    		Factory::service("Lu_PartType_PartInstanceAliasPattern")->addEditLu_PartType_PartInstanceAliasPattern($object);
    	}
    	catch (Exception $e)
    	{
    		$this->setErrorMessage($e->getMessage());
    	}
    }
  
    /**
     * Save entity
     *
     * @param unknown_type $object
     */
    protected function saveEntity(&$object)
    {
    	//save
    }
    
    /**
     * Populate Add
     *
     */
    protected function populateAdd()
    {   
    	$this->AddButton->Enabled=false;
    	$lbl = $this->titleLbl->Text;
    	$ptId = $this->focusObject->getValue();
    	$lbl .= Factory::service("PartType")->getPartType($ptId)->getName();
    	$this->titleLbl->Text = $lbl;
    	$data=array();
    	$data = $this->generateDropDownList($ptId,"add"); 
    	
    	$this->newPartInstanceAliasType->DataSource = $data;
    	$this->newPartInstanceAliasType->dataBind();
    	
    	if (UserAccountService::isSystemAdmin())
    		$this->regexPanel->style='display:block';
    }
    
    /**
     * Populate Edit
     *
     * @param unknown_type $editItem
     */
    protected function populateEdit($editItem)
    {
    	$item = $editItem->getData();
    	if (UserAccountService::isSystemAdmin())
	    	$this->regexPanel->style='display:block';
    	
    	$luPtPiAlias = Factory::service("Lu_PartType_PartInstanceAliasPattern")->get($item['id']);
    	$partInstanceAliasTypeId = $luPtPiAlias->getPartInstanceAliasType()->getId();
    	$this->DataList->getEditItem()->newPartInstanceAliasType->DataSource = $this->generateDropDownList($luPtPiAlias->getPartType()->getId(),"edit"); // MRAHMAN
    	$this->DataList->getEditItem()->newPartInstanceAliasType->dataBind();
    	
		$editItem->newPartInstanceAliasType->setSelectedValue($partInstanceAliasTypeId);
		
		$editItem->uniqueChk->Checked = $luPtPiAlias->getIsUnique();
		$editItem->mandatoryChk->Checked = $luPtPiAlias->getIsMandatory();
		$editItem->format->Text = $luPtPiAlias->getSampleFormat();
		$editItem->pattern->Text = $luPtPiAlias->getPattern(); 		
    }

    /**
     * Generate DropDown List
     *
     * @param unknown_type $partTypeId
     * @param unknown_type $command
     * @return unknown
     */
    protected function generateDropDownList($partTypeId,$command) 
    {
    	$mandatoryIds = array();
    	$clientIds = array();
    	$partType = Factory::service("PartType")->getPartType($partTypeId);
    	$countClientIds = $this->getClientIds($partTypeId);
    		    
    	$piatIds = Factory::service("Lu_PartType_PartInstanceAliasPattern")->getMandatoryUniquePatternsForPtPiat($partType,null,null,null);
		foreach($piatIds as $row)
		{
			$mandatoryIds[] = $row->getPartInstanceAliasType()->getId();
		}
		
		$partInstanceAliasTypes = Factory::service("PartInstance")->getPartInstanceAliasTypes();
		foreach ($partInstanceAliasTypes as $piat)
		{
			if ($piat->getId()!=PartInstanceAliasType::ID_SERIAL_NO)
			{
				if (!(($countClientIds>1)&&($piat->getId()==PartInstanceAliasType::ID_CLIENT_ASSET_NUMBER)))
				{
					if ($command=='add')
					{
						if (!in_array($piat->getId(),$mandatoryIds))
							$finalArray[] = array("id"=>$piat->getId(), "name"=>$piat->getName());
					}
					else 
						$finalArray[] = array("id"=>$piat->getId(), "name"=>$piat->getName());
				}
			}
		}
    	    	
    	return $finalArray;
    }
    
    /**
     * Get Client Ids
     *
     * @param unknown_type $partTypeId
     * @return unknown
     */
    private function getClientIds($partTypeId)
    {
    	$clientIds = array();
    	$partType = Factory::service("PartType")->getPartType($partTypeId);
    	$contracts = $partType->getContracts();
    	foreach ($contracts as $contract)
    	{
    		$cg = $contract->getContractGroup();
    		$client = $cg->getClient();
    		if (!in_array($client->getId(), $clientIds))
    		$clientIds[] = $client->getId();
    	}
    	return count($clientIds);
    }

    /**
     * Reset Fields
     *
     * @param unknown_type $params
     */
    protected function resetFields($params)
    {
    	$this->AddButton->Enabled=true;	
    	$this->mandatoryChk->checked = false;
    	$this->uniqueChk->checked = false;
    	$this->format->Text = "";
    	$this->pattern->Text = "";
    }
    
    /**
     * Get Focus Entity
     *
     * @param unknown_type $id
     * @param unknown_type $type
     * @return unknown
     */
	protected function getFocusEntity($id,$type="")
    {
    	return Factory::service("PartType")->getPartType($id);
    }
    
    /**
     * Get All Of Entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$result = array();
    	$admin = (UserAccountService::isSystemAdmin())?'yes':'no';
    	$edit = 'yes';
    	if($focusObject != null && $focusObject instanceof PartType)
    	{
    		$res = Factory::service("Lu_PartType_PartInstanceAliasPattern")->getMandatoryUniquePatternsForPtPiat($focusObject,null,null,null,$pageNumber,$pageSize);
    		$cntClient = $this->getClientIds($focusObject->getId());
    		foreach($res as $luPtPiPattern)
    		{
    			if (($cntClient>1)&&($luPtPiPattern->getPartInstanceAliasType()->getId()==PartInstanceAliasType::ID_CLIENT_ASSET_NUMBER))
    				$edit = 'no';
    			$result[] = array("id"=>$luPtPiPattern->getId(),"name"=>$luPtPiPattern->getPartInstanceAliasType()->getName(),"unique"=>$luPtPiPattern->getIsUnique(),"mandatory"=>$luPtPiPattern->getIsMandatory(),"format"=>$luPtPiPattern->getSampleFormat(),"pattern"=>$luPtPiPattern->getPattern(),"admin"=>$admin,"editable"=>$edit);
    		}
    		return $result;
    	}
    	return array();
    }

    /**
     * Redirect to PartType
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function redirectToPartType($sender, $param)
    {
    	$id = $this->Request['id'];
    	if ($id != '')
    	{
    		$this->response->Redirect("/parttypes/search/".$id);
    	}
    	else
    	{
    		$this->response->Redirect("/parttypes/#");
    	}
   		
    }

    /**
     * Delete PartType Instance Pattern
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function deletePartTypePartInstancePattern($sender, $param)
    {
		$luPtPiId = $param->CommandParameter;
		$luPtPi = Factory::service("Lu_PartType_PartInstanceAliasPattern")->get($luPtPiId);
		$luPtPi->setActive(0);
		Factory::service("Lu_PartType_PartInstanceAliasPattern")->save($luPtPi);
		$this->onLoad("reload");
    }
	
}
?>