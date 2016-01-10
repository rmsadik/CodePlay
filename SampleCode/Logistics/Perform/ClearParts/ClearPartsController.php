<?php
/**
 * Clear Parts Controller Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class ClearPartsController extends HydraPage
{
	/**
	 * @var menuContext
	 */
	public $menuContext;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = 'clearparts';
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_clearParts";
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
    	parent::onLoad($param);
    	if(!$this->IsPostBack)
    	{
    		//logic function to return part instance status list
    		$statusList = DropDownLogic::getPartInstanceStatusList();

    		$this->SearchStatus->DataSource = $statusList;
    		$this->SearchStatus->DataBind();

    		$this->movingPartNewStatus->DataSource = $statusList;
    		$this->movingPartNewStatus->DataBind();
    	}
    }

    /**
     * Load Confirm Panel
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function loadConfirmPanel($sender, $param)
    {
    	try
    	{
    		$this->setErrorMessage("");
    		$this->setInfoMessage("");

    		$this->loadedConfirmPanel->Value="false";
    		$this->MoveParts->Enabled=true;
	    	$fromWarehouseId = trim($this->fromwarehouseid->Value);
	    	$temp = explode("/",$fromWarehouseId);
	    	$fromWarehouseId = $temp[count($temp)-1];

	    	$toWarehouseId = trim($this->towarehouseid->Value);
	    	$temp = explode("/",$toWarehouseId);
	    	$toWarehouseId = $temp[count($temp)-1];

	    	$fromWarehouse = Factory::service("Warehouse")->getWarehouse($fromWarehouseId);
	    	$toWarehouse = Factory::service("Warehouse")->getWarehouse($toWarehouseId);

	    	if(!$fromWarehouse instanceof Warehouse)
	    		throw new Exception("Invalid 'From Warehouse'!");
	    	if(!$toWarehouse instanceof Warehouse)
	    		throw new Exception("Invalid 'To Warehouse'!");
	    	if(!$toWarehouse->getParts_allow())
	    		throw new Exception("'".$toWarehouse->getName()."' is not allowed to contain parts! Please contact Bytecraft Techology, if you want to put parts into this warehouse!");
	    	if(!$toWarehouse->getParts_allow())
	    		throw new Exception("'".$toWarehouse->getName()."' is not allowed to contain parts! Please contact Bytecraft Techology, if you want to put parts into this warehouse!");
	    	if(strlen($fromWarehouse->getPosition())<= 1 && $this->clearDownWards->Checked)
	    		throw new Exception("You can't clear part from '".$fromWarehouse->getName()."' downwards, as it's the above 1st level!");
	    	try{Factory::service("Warehouse")->checkAccessToWarehouse($toWarehouse);}
	    	catch(Exception $ex)
	    	{
	    		throw new Exception("You don't have access to '".$toWarehouse->getName()."'!");
	    	}

	    	$this->fromWarehouseName->Text ="";
    		$this->fromWarehouseName->Text=$fromWarehouse->getName();

	    	$this->toWarehouseName->Text = $toWarehouse instanceof Warehouse ? $toWarehouse->getName() : "";

	    	$partTypeName = trim($this->SearchPartType->getText());
	    	$this->movingPartType->Text = $partTypeName=="" ? "All" : $partTypeName;

	    	$where = "";
	    	$partTypeId = $this->SearchPartType->getSelectedValue();
	    	if($partTypeId!="") $where .=" AND pi.partTypeId = $partTypeId";

	    	$serialisedFlag = trim($this->SearchSerialisedFlag->SelectedItem->Text);
	    	$this->movingPartSerialised->Text = $serialisedFlag;

	    	$ownerName = trim($this->SearchOwnerClient->getText());
	    	$this->movingPartOwnerClient->Text = $ownerName=="" ? "All" : $ownerName;

	    	$ownerWhere = '';
	    	$clientId = $this->SearchOwnerClient->getSelectedValue();
	    	if($clientId!="") $ownerWhere .=" AND pt.ownerClientId = $clientId";

	    	$contractName = trim($this->SearchContract->getText());
	    	$this->movingPartContract->Text = $contractName=="" ? "All" : $contractName;

	    	$contractId = $this->SearchContract->getSelectedValue();
	    	if($contractId!="") $where .=" AND pi.partTypeId in (select cpt.partTypeId from contract_parttype cpt where cpt.contractId = $contractId)";

	    	$aliasFormat = trim($this->aliasFormat->Text);
	    	$this->movingAliasFormat->Text = $aliasFormat=="" ? "All" : $aliasFormat;
	    	if($aliasFormat!="") $where .=" AND pi.id in (select distinct pia.partInstanceId from partinstancealias pia where pia.active = 1 and pia.partInstanceAliasTypeId = 1 and pia.alias like '$aliasFormat')";

	    	$statusId = $this->SearchStatus->getSelectedValue();
	    	$this->movingPartStatus->Text = $statusId==""? "All": $this->SearchStatus->SelectedItem->Text;

	    	if($statusId!="") $where .=" AND pi.partInstanceStatusId = $statusId";

	    	$warehouseIds = $this->clearDownWards->Checked ? "select distinct ware.id from warehouse ware where ware.active and ware.position like '".$fromWarehouse->getPosition()."%'" : $fromWarehouse->getId();

    		$sql = "select distinct pi.id,pi.quantity
	    				from partinstance pi
	    				inner join parttype pt on (pi.partTypeId = pt.id ".(strtoupper($serialisedFlag)!="ALL" ? " AND pt.serialised = ".(strtoupper($serialisedFlag)=="YES" ? "1" : "0") : "")."$ownerWhere)
	    				where pi.active = 1
	    				$where and pi.warehouseId in ($warehouseIds)";
    		$result = Dao::getResultsNative($sql);

    		$ids= array();
    		$sum = 0;
    		foreach($result as $row)
    		{
    			$ids[] = $row[0];
    			$sum += $row[1];
    		}
    		$this->partsCount->Text = $sum;
    		$this->partInstanceIdsToBeMoved->Value = implode(",",$ids);

    		if(count($ids)==0)
    			$this->MoveParts->Enabled=false;

	    	$this->loadedConfirmPanel->Value="true";
    	}
    	catch(Exception $ex)
    	{
    		$this->loadedConfirmPanel->Value="false";
    		$this->setErrorMessage($ex->getMessage());
    	}
    }

    /**
     * Move Parts
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function moveParts($sender, $param)
    {
    	$currentPartInstanceId = trim($this->currentProcessingPartInstanceId->Value);
    	try
    	{
	    	$fromWarehouseId = trim($this->fromwarehouseid->Value);
	    	$temp = explode("/",$fromWarehouseId);
	    	$fromWarehouseId = $temp[count($temp)-1];

	    	$toWarehouseId = trim($this->towarehouseid->Value);
	    	$temp = explode("/",$toWarehouseId);
	    	$toWarehouseId = $temp[count($temp)-1];

	    	$fromWarehouse = Factory::service("Warehouse")->get($fromWarehouseId);
	    	$toWarehouse = Factory::service("Warehouse")->get($toWarehouseId);
	    	if(!$fromWarehouse instanceof Warehouse)
	    		throw new Exception("Invalid Warehouse To Move Parts From!");
	    	if(!$toWarehouse instanceof Warehouse)
	    		throw new Exception("Invalid Warehouse To Move Parts To!");

	    	$partinstance = Factory::service("PartInstance")->getPartInstance($currentPartInstanceId);
	    	if(!$partinstance instanceof PartInstance)
	    		throw new Exception("Invalid Part Instance!");

			$newStatusId = trim($this->movingPartNewStatus->getSelectedValue())=="" ? "" : trim($this->movingPartNewStatus->getSelectedValue());
			$newStatus = Factory::service("PartInstanceStatus")->get($newStatusId);

	    	Factory::service("PartInstance")->movePartInstanceToWarehouse($partinstance,$partinstance->getQuantity(),$toWarehouse,false,$newStatus,"Moved Via 'Clear Parts'.");

			if($this->clearPartsLogId->Value >1)
			{
				$this->clearPartsLogId->Value=1;
		    	$this->noOfpartsMoved->Text = $partinstance->getQuantity();
		    	$this->quantityProcessed->Text = $partinstance->getQuantity();
			}
			else
			{
		    	$this->noOfpartsMoved->Text +=$partinstance->getQuantity();
		    	$this->quantityProcessed->Text += $partinstance->getQuantity();
			}
    	}
    	catch(Exception $e)
    	{
    		$this->procesingErrors->Text .="Error ($currentPartInstanceId): ".$e->getMessage()."<br />";
    	}
    }

    /**
     * Set Info Message
     *
     * @param unknown_type $msg
     */
	public function setInfoMessage($msg)
	{
		$this->activeInfoLabel->Text = $msg;
	}

	/**
	 * Set Error Messagee
	 *
	 * @param unknown_type $msg
	 */
	public function setErrorMessage($msg)
	{
		$this->activeErrorLabel->Text = $msg;
	}

	/**
	 * Suggest PartType Search
	 *
	 * @param unknown_type $partType
	 * @return unknown
	 */
	public function suggestPartTypeSearch($partType)
    {
    	$this->SearchSerialisedFlag->Enabled=true;
    	$this->SearchSerialisedFlag->setSelectedValue("All");
    	$qry = "select distinct pt.id, concat(pta.alias,' - ',pt.name)
    			from parttype pt
    			left join parttypealias pta on (pta.partTypeId = pt.id and pta.active = 1 and pta.partTypeAliasTypeId = 1)
    			where pt.active = 1
    			and concat(pta.alias,' - ',pt.name) like '%$partType%'";
    	return Dao::getResultsNative($qry);
    }

    /**
     * Handle Selected PartType
     *
     */
	public function handleSelectedPartType()
    {
    	$partTypeId = $this->SearchPartType->getSelectedValue();
    	$partType = Factory::service("PartType")->get($partTypeId);
    	if($partType instanceof PartType)
    	{
	    	$this->SearchSerialisedFlag->setSelectedValue($partType->getSerialised()? "Yes" : "No");
	    	$this->SearchSerialisedFlag->Enabled=false;
    	}
    }
}
?>