<?php
/**
 * WarehouseAlias Controller
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class WarehouseAliasController extends CRUDPage
{
	/**
	 * @var unknown_type
	 */
	protected $totalCount;

	/**
	 * @var String
	 */
	private $_editWarehouses;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext="logistics_storageLocationAlias";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_storageLocationAlias";
		$this->totalCount = 0;
		$editWarehouse = Factory::service("UserAccountFilter")->getFilterValue(Core::getUser(),'AdminWarehouse',Core::getRole());
		$this->_editWarehouses = WarehouseLogic::getWarehousesUnderWarehouse($editWarehouse);
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
			if(count($this->DataList->DataSource) < 1)
			{
				$this->setErrorMessage('No Alias For ' . $this->getFocusEntity($this->focusObject->Value));
				$this->StorageLocationAliasPanel->Visible=false;
			}
			else
			{
				$this->setErrorMessage('');
				$this->StorageLocationAliasPanel->Visible=true;
				$this->WarehouseLabel->Text="  For  " . $this->getFocusEntity($this->focusObject->Value);
			}
        }
        if(in_array(intval($this->Request['id']),$this->_editWarehouses)|| UserAccountService::isSystemAdmin())
        	$this->AddButton->Visible=true;
        else
        	$this->AddButton->Visible=false;

    }

    /**
     * Create new Entity
     *
     * @return unknown
     */
    protected function createNewEntity()
    {
    	return new WarehouseAlias();
    }

    /**
     * Lookup Entity
     *
     * @param integer $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("Warehouse")->getWarehouseAlias($id);
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
    	$object->setAlias($params->newWarehouseAliasAlias->Text);
    	$warehouseAliasType = Factory::service("Warehouse")->getWarehouseAliasType($params->newWarehouseAliasType->getSelectedValue());
    	$object->setWarehouseAliasType($warehouseAliasType);
    	$object->setWarehouse($focusObject);
    }

    /**
     * Save Entity
     *
     * @param unknown_type $object
     */
    protected function saveEntity(&$object)
    {
    	$hasId = $object->getId();
		Factory::service("Warehouse")->saveWarehouseAlias($object);
		if ($hasId)
			$this->setInfoMessage("Warehouse alias updated.");
		else
			$this->setInfoMessage("New Warehouse Alias created.");
    }

    /**
     * Get DropdownList Options
     *
     * @param unknown_type $curItemId
     * @return unknown
     */
    private function getDropDownListOptions($curItemId = null)
    {
    	$whAliasTypeIds = array();
    	$wh = $this->getFocusEntity($this->focusObject->Value);
    	$sql = "SELECT DISTINCT warehousealiastypeid
				FROM warehousealias wa
				INNER JOIN warehousealiastype wat ON wat.id=wa.warehousealiastypeid AND wat.active=1 AND wat.allowmultiple=0
				WHERE wa.active=1 AND wa.warehouseid={$wh->getId()}";

    	$res = Dao::getResultsNative($sql);
    	foreach ($res as $r)
    	{
    		if ($curItemId != $r[0])
    			$whAliasTypeIds[] = $r[0];
    	}

    	$where = array("wat.lu_entityaccessoptionId!=3"); //exclude system generated
    	if (count($whAliasTypeIds) > 0)
    		$where[] = "wat.id not in (" . implode(',', $whAliasTypeIds) . ")";

    	if (UserAccountService::isSystemAdmin() == false)
			$where[] = 'wat.lu_entityaccessoptionId NOT IN (2)';  //filter out sysadmin only types

    	$res = Factory::service("WarehouseAliasType")->findByCriteria(implode(' AND ', $where), array(), false, null, null, array('WarehouseAliasType.name' => 'ASC'));
    	return $res;
    }

    /**
     * Populate Add
     *
     */
    protected function populateAdd()
    {
    	$this->newWarehouseAliasAlias->Enabled = true;
    	$this->newWarehouseAliasAlias->Text = '';

    	$this->newWarehouseAliasType->DataSource = $this->getDropDownListOptions();
    	$this->newWarehouseAliasType->dataBind();
    }

    /**
     * Sort by id
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
     * Sort By Alias type id
     *
     * @param unknown_type $a
     * @param unknown_type $b
     * @return unknown
     */
    public static function sortByAliasTypeId($a, $b)
    {
    	return $a->getWarehouseAliasType()->getId() - $b->getWarehouseAliasType()->getId();
    }

    /**
     * Populate Edit
     *
     * @param unknown_type $editItem
     */
    protected function populateEdit($editItem)
    {
    	$this->DataList->getEditItem()->newWarehouseAliasType->DataSource = $this->getDropDownListOptions($editItem->getData()->getWarehouseAliasType()->getId());
    	$this->DataList->getEditItem()->newWarehouseAliasType->dataBind();

    	try
    	{
    		$editItem->newWarehouseAliasType->setSelectedValue($editItem->getData()->getWarehouseAliasType()->getId());
    	}
    	catch(Exception $ex)
    	{
    		$this->DataList->getEditItem()->newWarehouseAliasType->DataSource = Factory::service("WarehouseAliasType")->findAll();
    		$this->DataList->getEditItem()->newWarehouseAliasType->dataBind();
    		$editItem->newWarehouseAliasType->setSelectedValue($editItem->getData()->getWarehouseAliasType()->getId());
    		$editItem->newWarehouseAliasType->Enabled=false;
    	}
    }

    /**
     * Reset Fields
     *
     * @param unknown_type $params
     */
    protected function resetFields($params)
    {
    	$params->newWarehouseAliasAlias->Text = "";
    }

    /**
     * Get focus entity
     *
     * @param unknown_type $id
     * @param unknown_type $type
     * @return unknown
     */
    protected function getFocusEntity($id,$type="")
    {
    	return Factory::service("Warehouse")->getWarehouse($id);
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
    	$res = Factory::service("Warehouse")->getWarehouseAliasesForWarehouse($focusObject->getId(),$pageNumber,$pageSize);
    	usort($res, "WarehouseAliasController::sortByAliasTypeId");
    	$this->totalCount = count($res);
    	return $res;
    }

    /**
     * Search Entity
     *
     * @param string $searchString
     * @param unknown_type $focusObject
     * @param int $pageNumber
     * @param int $pageSize
     * @return unknown
     */
    protected function searchEntity($searchString,&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$res = Factory::service("Warehouse")->searchWarehouseAliasesForWarehouseByName($focusObject->getId(),$searchString);
    	usort($res, "WarehouseAliasController::sortByAliasTypeId");
    	if(count($res) > 0)
    	{
    		$this->totalCount = count($res);
    		$this->StorageLocationAliasPanel->Visible = true;
    		return $res;
    	}
    	else
    	{
    		$this->StorageLocationAliasPanel->Visible = false;
    		$this->setErrorMessage('No Alias found as per search criteria');
    		return;
    	}
    }

    /**
     * Delete Warehousealias
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function deleteWarehouseAlias($sender, $param)
    {
    	$locationAliasId = $this->DataList->DataKeys[$param->Item->ItemIndex];
    	$locationAlias = Factory::service("Warehouse")->getWarehouseAlias($locationAliasId);
    	Factory::service("Warehouse")->deleteWarehouseAlias($locationAlias);
    	$this->onLoad("reload");
    }

    /**
     * Show Edit or Delete Button
     *
     * @param unknown_type $alias
     * @return unknown
     */
    public function showEditOrDeleteButton($alias)
    {
    	if(!$alias instanceof WarehouseAlias)
    		return false;

    	$wat = $alias->getWarehouseAliasType();
    	if ($wat instanceof WarehouseAliasType)
    	{
    		$editMode = $wat->getLu_entityAccessOption()->getId();
    		if(in_array(intval($this->Request['id']),$this->_editWarehouses)||UserAccountService::isSystemAdmin())
    		{
    			if($editMode == 1 || ($editMode == 2 && UserAccountService::isSystemAdmin())) //editable by all or sys admin only
    				return true;
    		}
    	}
    	return false;
    }

    /**
     * Generate BCL
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function generateBCL($sender, $param)
    {
    	$this->newWarehouseAliasAlias->Enabled=true;
    	if($this->newWarehouseAliasType->getSelectedValue() == 2)
    	{
	    	$this->newWarehouseAliasAlias->Text="";

	    	$this->dataLoad();

			$sequence = Factory::service("Sequence")->get(5);
			if($sequence instanceof Sequence)
			{
				$bcl = Factory::service("Sequence")->getNextNumberAsBarcode($sequence);
	    		$this->newWarehouseAliasAlias->Text=$bcl;
				$this->newWarehouseAliasAlias->Enabled=false;
			}
    	}
    }

    /**
     * Enable Dropdown
     *
     * @param string $type
     * @return boolean
     */
    public function enableDropDown($type)
    {
    	if(trim($type)=="Email Address")
    		{
    			$id=$this->focusObject->Value;
    			$sql="select max(id) from parttypeminimumlevel where supplyingwarehouseid=$id and active=1";
    			$result=Dao::getResultsNative($sql);
    			if(!empty($result[0][0])) return false;
    			else return true;
    		}
    	else return true;
    }
}

?>