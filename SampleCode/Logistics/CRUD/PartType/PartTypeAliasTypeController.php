<?php
/**
 *  PartType AliasType Controller 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class PartTypeAliasTypeController extends CRUDPage
{
	/**
	 * @var lueaoFinalArray
	 */
	private $lueaoFinalArray;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext="logistics_PartTypeAliasType";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_PartTypeAliasType";
		$this->lueaoFinalArray = array();
        $lueaArray = Factory::service("Lu_EntityAccessOption")->findAll();
        foreach($lueaArray as $lue)
        {   
	        $this->lueaoFinalArray[$lue->getId()] = $lue->getName();
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
			$this->populateFields();
        }
    }

    /**
     * Populate Fields
     *
     */
    public function populateFields() 
    {	
    	$this->accessOptionList->DataSource = $this->lueaoFinalArray;
    	$this->accessOptionList->dataBind();
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
    	$editItem->allowMultipleList->setSelectedValue($editItem->getData()->getAllowMultiple());
    	$editItem->accessOptionList->setSelectedValue($editItem->getData()->getLu_entityAccessOption()->getId());
    	$editItem->valueTypeList->setSelectedValue($editItem->getData()->getValueType());
    	
    	
    	
    	if($editItem->getData()->getActive()==1)
    	{
    		$editItem->active->checked = true;
    	}
    	else
    	{
    		$editItem->active->checked = false;
    	}
    	 
    	if($editItem->getData()->getLu_entityAccessOption()->getId()==3 || Core::getRole()->getName()!="System Admin")
    	{
    		$editItem->active->enabled = false;
    	}
    	 
    	
    	
    	
    }
    
    /**
     * Create New Entity
     *
     * @return unknown
     */
    protected function createNewEntity()
    {
    	return new PartTypeAliasType();
    }

    /**
     * Lookup Entity
     *
     * @param unknown_type $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("PartType")->getPartTypeAliasType($id);
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
    	
    	if($this->AddPanel->Visible)
    	{
    		//check for duplicate name
    		$partTypeAliasType = Factory::service("PartTypeAliasType")->findByCriteria("name=?", array($params->newPartTypeAliasName->Text),true);
    		if(count($partTypeAliasType)>0 || $partTypeAliasType instanceOf PartTypeAliasType)
    		{
    			$this->setErrorMessage("Duplicate name " . $params->newPartTypeAliasName->Text);
    			return false;
    		}
    	}

    	$amObject = Factory::service("Lu_EntityAccessOption")->get($params->accessOptionList->getSelectedValue());
    	
    	$object->setName($params->newPartTypeAliasName->Text);
    	$object->setLu_entityAccessOption($amObject);
    	$object->setAllowMultiple($params->allowMultipleList->getSelectedValue());
    	$object->setValueType($params->valueTypeList->getSelectedValue());
    	
    	if(!$this->AddPanel->Visible)
    	{
    		if($amObject->getId() != 3 && Core::getRole()->getName()=="System Admin")
    		{
    			$object->setActive($params->active->checked);
    		}
    	}
    	
    	Factory::service("PartType")->savePartTypeAliasType($object);
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
    	$params->newPartTypeAliasName->Text = "";
    }
    
    /**
     * Get all of Entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	DAO::$AutoActiveEnabled = false;
    	$partTypeAliasTypes = Factory::service("PartType")->getPartTypeAliasTypes();
    	DAO::$AutoActiveEnabled = true;
    	return $partTypeAliasTypes;
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
    	return  Factory::service("PartType")->searchPartTypeAliasType($searchString);
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
     * Delete PartType Alias Type
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function deletePartTypeAliasType($sender, $param)
    {
    	$partTypeAliasTypeId = $id = $this->DataList->DataKeys[$param->Item->ItemIndex];
    	$partTypeAliasType = Factory::service("PartType")->getPartTypeAliasType($partTypeAliasTypeId);
    	Factory::service("PartType")->deletePartTypeAliasType($partTypeAliasType);
    	$this->onLoad("reload");
    }
    
    /**
     * Show Allow Multiple In DataList
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
     * Show Value Type In DataList
     *
     * @param unknown_type $option
     * @return unknown
     */
    public function showValueTypeInDataList($option)
    {
    	if($option === StringUtils::VALUE_TYPE_STRING)
    	{
    		return "String";
    	}
    	else if($option === StringUtils::VALUE_TYPE_BOOL)
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