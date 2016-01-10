<?php
/**
 *  PartType Alias Controller 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class PartTypeAliasController extends CRUDPage
{
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext="logistics_partTypeAlias";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_partTypeAlias";
	}
	
	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
       	parent::onLoad($param);
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
				foreach($selectedPartType->getPartTypeAlias() as $partTypeAlias) 
				{
					if(strtoupper($partTypeAlias->getPartTypeAliasType())=='CODE NAME') 
						$partCode = $partTypeAlias->getAlias();  
					    
					$valueType = $partTypeAlias->getPartTypeAliasType()->getValueType();
				}
				
				if(count($this->DataList->DataSource) > 0)
				{
					$this->PartTypeAliasLabel->Text="For - " . $selectedPartType->getName() . " ( " . $partCode . " ) ";
				}
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
    	return new PartTypeAlias();
    }

    /**
     * Lookup entity
     *
     * @param unknown_type $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("PartType")->getPartTypeAlias($id);
    }
    
    /**
     * Set Entity
     *
     * @param unknown_type $object
     * @param unknown_type $params
     * @param unknown_type $focusObject
     */
    protected function setEntity(&$object,$params,&$focusObject = null)
    {
        if ($params->newPartTypeAliasAlias->Text!='')
        {
    	    $object->setAlias($params->newPartTypeAliasAlias->Text);
        }
        else if ($params->newPartTypeAliasAliasChk)
        {
            if ($params->newPartTypeAliasAliasChk->Checked == 'true')
            {
                $object->setAlias(true);
            }
            else
            {
                $object->setAlias(false);
            }
        }
        
    	$PartTypeAliasType = Factory::service("PartType")->getPartTypeAliasType($params->newPartTypeAliasType->getSelectedValue());
    	
    	$object->setPartTypeAliasType($PartTypeAliasType);
    	$object->setPartType($focusObject);
    	
    }
  
    /**
     * Save Entity
     *
     * @param unknown_type $object
     */
    protected function saveEntity(&$object)
    {
    	if(Factory::service("PartType")->checkPartTypeAliasesForDuplicate($object->getPartType()->getId(),$object->getPartTypeAliasType()->getId(),$object->getAlias())){
    		$this->setErrorMessage("Duplicate found for Alias: " . $object->getAlias() . ", Failed to add!<br />");	
    	}else{
			Factory::service("PartType")->savePartTypeAlias($object);
    	}
    }
    
    /**
     * Populate Add
     *
     */
    protected function populateAdd()
    {        
    	$data=array();
    	$data = $this->generateDropDownList("add"); 
    	$valueType = $data[0]["valueType"];
    	if ($valueType === StringUtils::VALUE_TYPE_BOOL)
    	{
    		$this->chkBoxPanel->setStyle("display:block");
            $this->textBoxPanel->setStyle("display:none");
        }
    	else if ($valueType === StringUtils::VALUE_TYPE_STRING)
    	{
    	    $this->chkBoxPanel->setStyle("display:none");
            $this->textBoxPanel->setStyle("display:block");
    	}
    	$this->newPartTypeAliasType->DataSource = $data;
    	$this->newPartTypeAliasType->dataBind();
    }

    /**
     * Populate Edit
     *
     * @param unknown_type $editItem
     */
    protected function populateEdit($editItem)
    {
    	//$this->DataList->getEditItem()->newPartTypeAliasType->DataSource = $this->getPartTypeAliasTypeList();
    	$this->DataList->getEditItem()->newPartTypeAliasType->DataSource = $this->generateDropDownList("edit", $editItem->getData()->getPartTypeAliasType()->getId()); 
    	$this->DataList->getEditItem()->newPartTypeAliasType->dataBind();
    	
		$editItem->newPartTypeAliasType->setSelectedValue($editItem->getData()->getPartTypeAliasType()->getId());
		$id = $editItem->getData()->getPartTypeAliasType()->getId();
		$valueType = $editItem->getData()->getPartTypeAliasType()->getValueType();
		
        if ($valueType === StringUtils::VALUE_TYPE_BOOL)
    	{
    		$editItem->chkBoxEditPanel->setAttribute("style", "display:block");
            $editItem->textBoxEditPanel->setAttribute("style", "display:none");
        }
    	else if ($valueType === StringUtils::VALUE_TYPE_STRING)
    	{
    	    $editItem->chkBoxEditPanel->setAttribute("style", "display:none");
            $editItem->textBoxEditPanel->setAttribute("style", "display:block");
    	}
		//$editItem->newPartTypeAliasAlias
    }

    /**
     * Generate DropDown List
     *
     * @param unknown_type $option
     * @param unknown_type $partTypeAliasTypeID
     * @return unknown
     */
    protected function generateDropDownList($option, $partTypeAliasTypeID = null)
    {
    	$excludedIds = array();
    	$excludedPTATarray = array();
    	$secondArray = array();
    	$finalArray = array();
    	$query = "";
    	
		$excludedIds[] = 3;
		if (UserAccountService::isSystemAdmin() == false)
		{
			$excludedIds[] = 2;
		}
		
		$partTypeId = $this->focusObject->getValue();
		$query = "select distinct pta.partTypeAliasTypeId from parttypealias pta INNER JOIN parttypealiastype ptat ON pta.partTypeAliasTypeId = ptat.id AND ptat.active = 1 
					where pta.active = 1 and pta.partTypeId = $partTypeId and ptat.allowMultiple = 0 
				 ";
		$result = Dao::getResultsNative($query);
		foreach($result as $row)
		{
			$excludedPTATarray[] = $row[0];
		}
		
		if($option == "add")
		{
			$query = "select * from parttypealiastype ptat 
						INNER JOIN lu_entityaccessoption lueao ON ptat.lu_entityaccessoptionId = lueao.id AND lueao.active = 1
						WHERE ptat.active = 1 ";
			if(count($excludedPTATarray) > 0)
				$query .= " and ptat.id NOT IN (".implode(",", $excludedPTATarray).") ";
			
			if(count($excludedIds) > 0)	
				$query .= " AND lueao.id NOT IN (".implode(",", $excludedIds).") ";
			
		}
    	else if($option == "edit" && $partTypeAliasTypeID != null)
    	{
    		if(in_array($partTypeAliasTypeID, $excludedPTATarray))
    		{
    			for($i = 0; $i < count($excludedPTATarray); $i++)
    			{
    				if($excludedPTATarray[$i] != $partTypeAliasTypeID)
    				{
    					$secondArray[] = $excludedPTATarray[$i];
    				}
    			}
    		}
    		
    		$query = "select * from parttypealiastype ptat 
						INNER JOIN lu_entityaccessoption lueao ON ptat.lu_entityaccessoptionId = lueao.id AND lueao.active = 1
						WHERE ptat.active = 1 ";
    		
    		if(count($secondArray)>0)
    			$query .= " and ptat.id NOT IN (".implode(",", $secondArray).") ";
    			
    		if(count($excludedIds)>0)
    			$query .= " AND lueao.id NOT IN (".implode(",", $excludedIds).") ";
    		
    	}
    	
    	$result = Dao::getResultsNative($query);
    	foreach($result as $row)
    	{
    		$ptatId = $row[0];
    		$ptatName = $row[1];
    		$valueType = $row[4];
    		$finalArray[] = array("id"=>$ptatId, "name"=>$ptatName, "valueType"=>$valueType);
    	}
    	return $finalArray;
    }
    
	/**
     * Get the PartTypeAliasTypes except Barcode, Code Name.
     *
     * @return unknown
     */
    public function getPartTypeAliasTypeList()
    {
    	$data = array();
    	$partTypeAliasTypes = Factory::service("PartType")->getPartTypeAliasTypesForCreationEdit();
    	foreach($partTypeAliasTypes as $partTypeAliasType)
    	{
   			$data[]=array("id"=>$partTypeAliasType->getId(),"name"=>$partTypeAliasType->getName());
    	}
    	return $data;	
    }

    /**
     * Reset Fields
     *
     * @param unknown_type $params
     */
    protected function resetFields($params)
    {
    	$params->newPartTypeAliasAlias->Text = "";
    	$params->newPartTypeAliasAliasChk->Checked = false;
    	if (isset($params->chkBoxEditPanel))
    	{
    	    $params->chkBoxEditPanel->setAttribute("style", "display:none");
    	    $params->textBoxEditPanel->setAttribute("style", "display:block");
    	    
    	}
    	else if (isset($params->chkBoxPanel))
    	{
    	    $params->textBoxPanel->setAttribute("style", "display:block");
    	    $params->chkBoxPanel->setAttribute("style", "display:none");
    	}
    	
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
    	if($focusObject != null && $focusObject instanceof PartType)
    	{
    		$res = Factory::service("PartType")->getPartTypeAliasesForPartType($focusObject->getId(),$pageNumber,$pageSize);
    		return $res;
    	}
    	return array();
    }
    
    /**
     * Search Entity
     *
     * @param unknown_type $searchString
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function searchEntity($searchString,&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	if(count(Factory::service("PartType")->searchPartTypeAliasesForPartTypeByName($focusObject->getId(),$searchString)) > 0) 
    	{
    		$this->PartTypeAliasPanel->Visible = true;
    			return Factory::service("PartType")->searchPartTypeAliasesForPartTypeByName($focusObject->getId(),$searchString);
    	}
    	else
    	{
    		$this->PartTypeAliasPanel->Visible = false;
    		$this->setErrorMessage('No PartTypeAlias found as per search criteria');
    		return;
    	}
    }
	
    /**
     * Redirect To PartType
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function redirectToPartType($sender, $param)
    {
   		$this->response->Redirect("/parttypes/true");
    }

    /**
     * Delete PartType Alias
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function deletePartTypeAlias($sender, $param)
    {
		$partTypeAliasId = $param->CommandParameter;
    	$partTypeAlias = Factory::service("PartType")->getPartTypeAlias($partTypeAliasId);
  		Factory::service("PartType")->deletePartTypeAlias($partTypeAlias);
		$this->onLoad("reload");
    }
	
    /**
     * Show This
     *
     * @param unknown_type $partTypeAliasType
     * @return unknown
     */
    public function showThis($partTypeAliasType)
    {
    	$aliasType = strtoupper($partTypeAliasType->getName());
    	if(!strcmp($aliasType,'BARCODE') || !strcmp($aliasType, 'CODE NAME')) return false;
    	
    	$lueaoId = $partTypeAliasType->getLu_entityAccessOption()->getId();
    	if($lueaoId == 3) // if part type alias type is system generated - block it
    	{
    		return false;
    	}
    	else if($lueaoId == 2 && UserAccountService::isSystemAdmin() == false) // if parttypealiastype is for sys admin only and user not sys admin - block it 
    	{
    		return false;
    	}
    	return true;
    }
    
    /**
     * Get Value Type
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function getValueType($sender,$param)
    {
        $id = $sender->getSelectedValue();
        $this->chkBoxPanel->setStyle("display:none");
        $this->textBoxPanel->setStyle("display:none");
        $ptat = Factory::service("PartTypeAliasType")->get($id);
        
        if ($ptat instanceof PartTypeAliasType)
        {
            $ptatValueType = $ptat->getValueType();
            if ($ptatValueType === StringUtils::VALUE_TYPE_BOOL)
            {
    		    $this->chkBoxPanel->setStyle("display:block");
    		    $this->textBoxPanel->setStyle("display:none");
    		}
        	else if ($ptatValueType === StringUtils::VALUE_TYPE_STRING)
        	{
        		$this->chkBoxPanel->setStyle("display:none");
    		    $this->textBoxPanel->setStyle("display:block");
        	}
        }
    }
    
    /**
     * Get Value Type on Edit
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function getValueTypeOnEdit($sender,$param)
    {
        $editItem = $this->DataList->getEditItem();
        $id = $sender->getSelectedValue();
        $editItem->chkBoxEditPanel->setStyle("display:none");
        $editItem->textBoxEditPanel->setStyle("display:none");
        $ptat = Factory::service("PartTypeAliasType")->get($id);
        if ($ptat instanceof PartTypeAliasType)
        {
            $ptatValueType = $ptat->getValueType();
            if ($ptatValueType === StringUtils::VALUE_TYPE_BOOL)
            {
                $editItem->chkBoxEditPanel->setStyle("display:block");
    		    $editItem->textBoxEditPanel->setStyle("display:none");
                $editItem->newPartTypeAliasAlias->Text = '';
            }
            else if ($ptatValueType === StringUtils::VALUE_TYPE_STRING)
            {
                $editItem->textBoxEditPanel->setStyle("display:block");
                $editItem->chkBoxEditPanel->setStyle("display:none");
            }
        }
    }
}
?>