<?php
/**
 * PartInstance Alias Type Controller page
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class PartInstanceAliasTypeController extends CRUDPage
{
	/**
	 * @var $lueaoFinalArray
	 */
	private $lueaoFinalArray;
		
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext="logistics_partInstanceAliasType";
		$this->roleLocks = "pages_all,page_logistics_partInstanceAliasType";
		$this->lueaoFinalArray = array();
		$lueaoArray = Factory::service("Lu_EntityAccessOption")->findAll();
		foreach($lueaoArray as $leao)
		{
			$this->lueaoFinalArray[$leao->getId()] = $leao->getName();
		}
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
			$this->populateAddPanel();
        }
    }
    
    /**
     * Populate Add Panel
     *
     */
    private function populateAddPanel() // to populate the access mode drop down list in the add panel * MRAHMAN
    {
    	$this->accessOptionList->DataSource = $this->lueaoFinalArray;
    	$this->accessOptionList->dataBind();
    }
    
    /**
     * Create new entity
     *
     * @return unknown
     */
    protected function createNewEntity()
    {
    	return new PartInstanceAliasType();
    }
    
    /**
     * Populate Edit
     *
     * @param unknown_type $editItem
     */
	protected function populateEdit($editItem) 
    {
    	$editItem->accessOptionList->DataSource = $this->lueaoFinalArray;
    	$editItem->accessOptionList->dataBind();
    	
    	$piatObject = $editItem->getData();
    	$editItem->allowMultipleList->setSelectedValue($piatObject->getAllowMultiple());
    	$editItem->accessOptionList->setSelectedValue($piatObject->getLu_entityAccessOption()->getId());
    	$editItem->valueTypeList->setSelectedValue($editItem->getData()->getValueType());
    	
    	
    	if($piatObject->getActive()==1)
    	{
    		$editItem->active->checked = true;
    	}
    	else
    	{
    		$editItem->active->checked = false;
    	}
    	
    	if($piatObject->getLu_entityAccessOption()->getId()==3 || Core::getRole()->getName()!="System Admin")
    	{
    		$editItem->active->enabled = false;
    	}
    	
    }

    /**
     * Lookup Entity
     *
     * @param unknown_type $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("PartInstance")->getPartInstanceAliasType($id);
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
    	if($this->AddPanel->Visible)
    	{
    		//check for duplicate name
    		$partInstanceAliasType = Factory::service("PartInstanceAliasType")->findByCriteria("name=?", array($params->newPartInstanceAliasTypeName->Text),true);
    		if(count($partInstanceAliasType)>0 || $partInstanceAliasType instanceOf PartInstanceAliasType)
    		{
    			$this->setErrorMessage("Duplicate name " . $params->newPartInstanceAliasTypeName->Text);
    			return false;
    		}
    	}
    	
    	
    	$lueaoId = $params->accessOptionList->getSelectedValue();
    	$luObject = Factory::service("Lu_EntityAccessOption")->get($lueaoId);
    	$object->setName($params->newPartInstanceAliasTypeName->Text);
    	$object->setLu_entityAccessOption($luObject);
    	$object->setAllowMultiple($params->allowMultipleList->getSelectedValue());
    	$object->setValueType($params->valueTypeList->getSelectedValue());
    	if(!$this->AddPanel->Visible)
    	{
	    	if($lueaoId != 3 && Core::getRole()->getName()=="System Admin")
	    	{
	    		$object->setActive($params->active->checked);
	    	}
    	}
    	
    	Factory::service("PartInstance")->savePartInstanceAliasType($object);
    }

    /**
     * Save Entity
     *
     * @param unknown_type $object
     */
    protected function saveEntity(&$object)
    {
    	
    }
    
    /**
     * Reset Fields
     *
     * @param unknown_type $params
     */
    protected function resetFields($params)
    {
    	$params->newPartInstanceAliasTypeName->Text = "";
    }
    
    /**
     * Get all of entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	DAO::$AutoActiveEnabled = false;
    	$result = Factory::service("PartInstance")->getPartInstanceAliasTypes($pageNumber,$pageSize);
    	DAO::$AutoActiveEnabled = true;
    	usort($result, "PartInstanceAliasTypeController::sortById");
    	return $result; 
    }
    
    /**
     * Show Allow Multiple in DataList
     *
     * @param unknown_type $option
     * @return unknown
     */
    public function showAllowMultipleInDataList($option)
    {
    	if($option == 1)
    	{
    		return "Yes";
    	}
    	else if($option == 0)
    	{
    		return "No";
    	}
    	else
    	{
    		return "Undefined";
    	}
    }

    /**
     * Sort by Id
     *
     * @param unknown_type $a
     * @param unknown_type $b
     * @return unknown
     */
    protected static function sortById($a, $b)
    {
    	return ($a->getId() >= $b->getId() ? "1" : "-1");
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
    	return Factory::service("PartInstance")->searchPartInstanceAliasType($searchString);
    }
	
    /**
     * Redirect to PartInstance
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function redirectToPartInstance($sender, $param)
    {
   		$this->response->Redirect("/partinstances/true");
    }
	
    /**
     * Delete PartInstance alias type
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function deletePartInstanceAliasType($sender, $param)
    {
	    $partInstanceAliasTypeId = $this->DataList->DataKeys[$param->Item->ItemIndex];
	    $partInstanceAliasType = Factory::service("PartInstance")->getPartInstanceAliasType($partInstanceAliasTypeId);
	    Factory::service("PartInstance")->deletePartInstanceAliasType($partInstanceAliasType);
	    $this->onLoad("reload");
	} 
	
	/**
	 * Show Value type in Datalist
	 *
	 * @param unknown_type $option
	 * @return unknown
	 */
	public function showValueTypeInDataList($option)
	{
		if($option == "string")
		{
			return "String";
		}
		else if($option == "boolean")
		{
			return "Boolean";
		}
		else
		{
			return "Undefined";
		}
	}
}

?>