<?php
/**
 * Warehouse Alias Type Controller 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class WarehouseAliasTypeController extends CRUDPage
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
		$this->menuContext="logistics_storageLocationAliasType";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_storageLocationAliasType";
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
        	$this->bindDropDownList($this->accessModeList, $this->lueaoFinalArray); 
			$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
			$this->dataLoad();
        }
    }
    
    /**
     * Populate Add
     *
     */
    protected function populateAdd()
    {
    	$this->resetFields(null);
    }
    
    /**
     * Populate Edit
     *
     * @param unknown_type $editItem
     */
    protected function populateEdit($editItem)
    {
    	$this->bindDropDownList($editItem->accessModeList, $this->lueaoFinalArray); 
    	$editItem->newWarehouseAliasTypeName->Text = $editItem->getData()->getName();
    	$editItem->accessModeList->setSelectedValue($editItem->getData()->getLu_entityAccessOption()->getId());
    	$editItem->allowMultipleList->setSelectedValue($editItem->getData()->getAllowMultiple());
    }
    
    /**
     * Create New Entity
     *
     * @return unknown
     */
    protected function createNewEntity()
    {
    	return new WarehouseAliasType();
    }

    /**
     * Lookup Entity
     *
     * @param unknown_type $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("Warehouse")->getWarehouseAliasType($id);
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
    	$lueaoId = $params->accessModeList->getSelectedValue();
    	$luObject = Factory::service("Lu_EntityAccessOption")->get($lueaoId);
    	
    	$object->setName($params->newWarehouseAliasTypeName->Text);
    	$object->setLu_entityAccessOption($luObject);
    	$object->setAllowMultiple($params->allowMultipleList->getSelectedValue());
    }
    
    /**
     * Save Entity
     *
     * @param unknown_type $object
     */
    protected function saveEntity(&$object)
    {
    	$hasId = $object->getId();
    	
    	$sql = "SELECT id FROM warehousealiastype WHERE active=1 AND name='{$object->getName()}'";
    	$r = Dao::getSingleResultNative($sql);
    	if ($r !== false && $hasId == null)
    	{
    		$this->setErrorMessage("An alias type already exists with the name ({$object->getName()}), select another name...");
    		return;	
    	}
    	
    	Factory::service("Warehouse")->saveWarehouseAliasType($object);
    	$this->onLoad("reload");
    	if ($hasId)
	    	$this->setInfoMessage("Warehouse Alias Type updated.");
		else	    	
	    	$this->setInfoMessage("Added new Warehouse Alias Type.");
    }
    
    /**
     * Reset Fields
     *
     * @param unknown_type $params
     */
    protected function resetFields($params)
    {
    	$this->newWarehouseAliasTypeName->Text = '';
    	$this->accessModeList->setSelectedValue(1);
    	$this->allowMultipleList->setSelectedValue(1);
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
    	$res = Factory::service("Warehouse")->getWarehouseAliasTypes($pageNumber,$pageSize);
    	usort($res, "WarehouseAliasTypeController::sortById");
    	return $res; 
    }
    
    /**
     * Sort by Id
     *
     * @param unknown_type $a
     * @param unknown_type $b
     * @return unknown
     */
    public static function sortById($a, $b)
    {
    	return $a->getId() - $b->getId(); 
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
    	return Factory::service("Warehouse")->searchWarehouseAliaseTypeByName($searchString);
    }
	
    /**
     * Redirect to Storage Location
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function redirectToStorageLocation($sender, $param)
    {
   		$this->response->Redirect("/storagelocation/true");
    }
	
    /**
     * Delete Warehouse Alias Type
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function deleteWarehouseAliasType($sender, $param)
    {
    	$locationAliasTypeId = $this->DataList->DataKeys[$param->Item->ItemIndex];
    	$locationAliasType = Factory::service("Warehouse")->getWarehouseAliasType($locationAliasTypeId);
    	Factory::service("Warehouse")->deleteWarehouseAliasType($locationAliasType);
    	$this->onLoad("reload");
    }
}

?>