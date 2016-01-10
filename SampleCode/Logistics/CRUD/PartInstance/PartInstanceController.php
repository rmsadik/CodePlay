<?php
/**
 * Part Instances Alias Lookup Page
 * This page is only for SYSADMIN!!!!
 * @package Hydra-Web
 * @subpackage Controller-Page
 */
class PartInstanceController extends CRUDPage 
{
	/**
	 * @var totalCount
	 */
	protected $totalCount;
		
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = 'partInstance';
		$this->totalCount = 0;
		$this->roleLocks = "pages_all,page_logistics_partInstance";
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
			$piId = $this->Request['partinstanceid'];
			$searchstring = $this->Request['searchstring'];
			if (!empty($searchstring))
			{
				$this->SearchText->setText($searchstring);
				$this->SearchString->setValue(serialize(array($searchstring, 1)));
				$this->dataLoad();
			}
			$this->DataList->EditItemIndex = -1;
			$this->AddPanel->Visible = false;
			
			$aliasTypes = Factory::service("PartInstanceAliasType")->findAll();
			usort($aliasTypes, create_function('$a, $b', 'return $a->getId() - $b->getId();'));
			$this->aliasType->DataSource = $aliasTypes;
			$this->aliasType->DataBind();
        }
    }

    /**
     * Get WarehouseService
     *
     * @return unknown
     */
    protected function getWarehouseService()
    {
    	return Factory::service("Warehouse");
    }
    
    /**
     * Get PartCode
     *
     * @param unknown_type $item
     * @return unknown
     */
    protected function getPartcode($item)
    {
    	$a = $item->getPartType()->getPartTypeAlias();
    	$string = "";
    	foreach($a as $row)
    	{
    		$type = $row->getPartTypeAliasType();
    		if($type->getId() == 1)
    			$string .= $row->getAlias();
    	}
    	return $string;
    }
    
    /**
     * Get Owner Client Name
     *
     * @param unknown_type $clid
     * @return unknown
     */
    protected function getOwnerClientName($clid)
    {
    	$client = Factory::service("Client")->get($clid);
    	if (!empty($client))
    		return $client->getClientName();
    	return "";
    }    

    /**
     * Populate Add
     *
     */
   	public function populateAdd()
	{	
	}
    
	/**
	 * Populate Edit
	 *
	 * @param unknown_type $editItem
	 */
	public function populateEdit($editItem)
	{
	} 
	
	/**
	 * Suggest PartName
	 *
	 * @param unknown_type $text
	 * @return unknown
	 */
    public function suggestPartName($text)
    {
    	$ptWithSimilarCodes = array();
    	if (preg_match("/^(\d+) - (.+)$/", $text, $tmpArr))
    	{
    		$partCode = $tmpArr[1];
    		$text = $tmpArr[2];
    	}
    	else
    	{
    		$partCode = $text;
    	}
    	
    	$query = new DaoReportQuery("PartTypeAlias");
    	$query->column("pta.partTypeId");
    	$query->where("pta.partTypeAliasTypeId = 1");
    	$query->where("pta.alias LIKE '%$partCode%'");
    	$query->page(1, 20);
    	$res = $query->execute();
    	foreach ($res as $r)
    	{
    		$ptWithSimilarCodes[] = $r[0];
    	}
    	
    	// only get the first 20 parttypes found!
    	$query = new DaoReportQuery("PartType");
    	$query->setAdditionalJoin("LEFT OUTER JOIN parttypealias pta ON pt.id=pta.partTypeId");
    	$query->column("pt.id");		// 0
    	$query->column("pt.name");		// 1
    	$query->column("pta.alias");	// 2
    	$query->where("pta.partTypeAliasTypeId = 1");
    	$query->page(1, 20);
    	if (!empty($ptWithSimilarCodes))
    	{
    		$query->where("pt.name LIKE '%$text%' OR pt.id IN (".join(",", $ptWithSimilarCodes).")");
    	}
    	else
    	{
    		$query->where("pt.name LIKE '%$text%'");
    	}
    	$result = $query->execute();
    	$mappedRes = array();
    	foreach ($result as $r)
    	{
    		$mappedRes[] = array($r[0], $r[2].' - '.$r[1]);
    	}
    	return $mappedRes;
    }    

    /**
     * Populate Shared Contracts
     *
     * @param unknown_type $id
     */
	public function populateSharedContracts($id)
	{
    	if($this->AddPanel->Visible == true)  
	    {
    		$sharedObj = $this->sharedContracts;
	    }
	    else
	    {
    		$sharedObj = $this->DataList->getEditItem()->sharedContracts;
	    }
		$prevValue = $sharedObj->getSelectedValue();
		$partType = Factory::service("PartType")->get($id);
		$sharedObj->Items->clear();
		$contracts = $partType->getContracts();
		foreach($contracts as $contract)
		{
		    $listItem = new TListItem();
		    $listItem->setText($contract->__toString());
		    $listItem->setValue($contract->getId());
		    $sharedObj->Items->insertAt(0,$listItem);			
		}
		
		if (!empty($prevValue))
		{
			// see if this contract is in the choice options
			if ($sharedObj->getItems()->findIndexByValue($prevValue) >= 0) // found the item, select it!
			{
				$sharedObj->setSelectedValue($prevValue);
			}
		}
		
    	if($this->AddPanel->Visible == true)  // add
		{
			// enable/disable quantity field automatically based on parttype
			if ($partType->getSerialised())
    		{
				$this->newQuantity->setText(1);
				$this->newQuantity->setEnabled(false);
    		}
    		else
    		{
				$this->newQuantity->setEnabled(true);
    		}
		}
	}

	/**
	 * Add Contract
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function addContract($sender,$param)
    {
    	if($this->AddPanel->Visible == true)  // add
	    {
    		$obj = $this->contract;
    		$sharedObj = $this->sharedContracts;
	    }
	    else
	    {
    		$obj = $this->DataList->getEditItem()->contract;
    		$sharedObj = $this->DataList->getEditItem()->sharedContracts;
	    }
    	$value = $obj->getSelectedValue();
    	$label = $obj->getText();
    	$prevValue = $sharedObj->getSelectedValue(); 
        if($value != null)
    	{
		    $listItem = new TListItem();
		    $listItem->setText($label);
		    $listItem->setValue($value);
	    	$sharedObj->Items->insertAt(0,$listItem);
	    	
	    	if (!empty($prevValue))
	    		$sharedObj->setSelectedValue($prevValue);
    	} 
    }
    
    /**
     * Remove Contract
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function removeContract($sender,$param)
    {   
	    if($this->AddPanel->Visible == true)  // add
	    {
    		$obj = $this->sharedContracts;
	    }
	    else
	    {
    		$obj = $this->DataList->getEditItem()->sharedContracts;
	    }
    	$item = $obj->getSelectedItem();
    	if($item == null)
    	{
    		return;    		
    	}
    	$obj->Items->Remove($item);
    }       

    /**
     * Ajax Check Serial No
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function ajaxCheckSerialNo($sender, $param)
    {
    	$proposedSerialNo = $this->newSerialNo->Text;
        if (empty($proposedSerialNo))
    	{
			$this->resultCheckSerialNo->Text = "";
			$this->SubmitPanel->setVisible(true);
    		return;
    	}
    	$sysHasSerialNo = $this->systemHasSerialNo($proposedSerialNo);
    	
    	$passCheckSum = Factory::service("PartInstance")->passSerialCheckSum($proposedSerialNo);
		
		if (!$sysHasSerialNo && $passCheckSum)
		{
			$this->resultCheckSerialNo->Text = "<span style=\"color: green\">Pass check</span>";
			$this->SubmitPanel->setVisible(true);
		}
		else
		{
			if ($sysHasSerialNo)
				$this->resultCheckSerialNo->Text = "<span style=\"color: red\">Already in use</span>";
			else
				$this->resultCheckSerialNo->Text = "<span style=\"color: red\">Invalid barcode: failed checksum</span>";
			$this->SubmitPanel->setVisible(false);
		}    	
    }
    
    /**
     * System has serial No
     *
     * @param unknown_type $proposedSerialNo
     * @return unknown
     */
    private function systemHasSerialNo($proposedSerialNo)
    {
    	if (empty($proposedSerialNo))
    	{
    		return true;
    	}
    	$checkRes = true;
    	$query = new DaoReportQuery("PartInstanceAlias");
    	$query->column("pia.id");
    	$query->where("pia.partInstanceAliasTypeId=1");
    	$query->where("pia.active=1");
    	$query->where("pia.alias='$proposedSerialNo'");
    	$result = $query->execute();
    	if (empty($result))
			$checkRes = false;
		
		return $checkRes;
    }
    
    /**
     * How big was that query
     *
     * @return unknown
     */
	protected function howBigWasThatQuery()
    {
    	return $this->totalCount;
    }
    
    /**
     * Search
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function search($sender,$param)
    {
     	$searchQueryString = $this->SearchText->Text;
		$aliasType = $this->aliasType->getSelectedValue();
		$inputArr = array($searchQueryString, $aliasType);
		$this->SearchString->Value = serialize($inputArr);
     	
		$this->DataList->EditItemIndex = -1;
		$this->dataLoad();
    }    
    
    /**
     * searching for partinstances on "part type name", "partcode", or "part instance alias BCS / box label"
     *
     * @param String $searchString
     * @param unknown_type $focusObject
     * @param int $pageNumber
     * @param int $pageSize
     * @return array()
     */
	protected function searchEntity($searchStringArr, &$focusObject = null, $pageNumber=null, $pageSize=null)
    {
    	list($searchString, $aliasType) = unserialize($searchStringArr);
    	$qFilter = "";
    	if (!empty($searchString))
    	{
	    	$this->listPartInstancesLabel->Text = "Search result for \"".$searchString."\"";
	    	$query = new DaoReportQuery("PartTypeAlias");
	    	$query->column("pta.partTypeId");
	    	$query->where("pta.partTypeAliasTypeId=1");
	    	$query->where("pta.alias LIKE '%$searchString%'");
	    	$query->where("pta.active=1");
	    	$res = $query->execute();
	    	
	    	$ptIdsWithSimilarAliasArr = array();
	    	foreach ($res as $r)
	    	{
	    		$ptIdsWithSimilarAliasArr[] = $r[0];
	    	}
			$query = new DaoReportQuery("PartInstanceAlias");
	    	$query->column("pia.partInstanceId");
	    	// partinstancealiastypeid = 1 -> serial no
	    	// partinstancealiastypeid = 9 -> box label
	    	if($aliasType != null)
	    		$query->where("pia.partInstanceAliasTypeId = ".$aliasType);
	    	$query->where("pia.alias LIKE '%$searchString%'");
	    	$query->where("pia.active=1");
	    	$res = $query->execute();
	    	
	    	$piIdsWithSimilarSerialArr = array();
	    	foreach ($res as $r)
	    	{
	    		$piIdsWithSimilarSerialArr[] = $r[0];
	    	}
	    	
    	   	$extraWhereArr = array();
	    	if (!empty($ptIdsWithSimilarAliasArr))
		    	$extraWhereArr[] = "pi.partTypeId IN (".join(",", $ptIdsWithSimilarAliasArr).")";
			if (!empty($piIdsWithSimilarSerialArr))
				$extraWhereArr[] = "pi.id IN (".join(",", $piIdsWithSimilarSerialArr).")";
			if (!empty($ptIdsWithSimilarNameArr))
		    	$extraWhereArr[] = "pi.partTypeId IN (".join(",", $ptIdsWithSimilarNameArr).")";
		    
		    if (!empty($extraWhereArr))
	    		$qFilter = join(" OR ", $extraWhereArr);
	    	else   // purposely return nothing!
    			$qFilter = "pi.id = -1";
    	}
    	else if (empty($searchString) && !empty($focusObject))
    	{
    		// search based on part instance id
    		$qFilter = "pi.id = ".$focusObject;
    	}
    	else
    	{
    		// purposely return nothing!
    		$qFilter = "pi.id = -1";
    	}
		
    	$query = new DaoReportQuery("PartType");
    	$query->column("pt.id");
    	$query->where("pt.name LIKE '%$searchString%'");
    	$query->where("pt.active=1");
    	$res = $query->execute();
    	$ptIdsWithSimilarNameArr = array();
    	foreach ($res as $r)
    	{
    		$ptIdsWithSimilarNameArr[] = $r[0];
    	}   
    	
    	$query = new DaoReportQuery("PartInstance",true);
    	$query->page($pageNumber, $pageSize);
		
    	$query->column("pi.id");											// 0
    	$query->column("(SELECT GROUP_CONCAT(pta.alias SEPARATOR '<br/>') 		
    					 FROM parttypealias pta
    					 WHERE pta.parttypeid=pi.partTypeId
    					 AND pta.parttypealiastypeid = 1
    					 AND pta.active=1)", "partcode");   				// 1
    	$query->column("(SELECT pt.name FROM parttype pt WHERE pt.id=pi.partTypeId)", "category");								// 2
    	$query->column("(SELECT GROUP_CONCAT(pia.alias separator '<br/>') 		
    					 FROM partinstancealias pia 
    					 WHERE pia.partinstanceid=pi.id 
    					 AND pia.partinstancealiastypeid = 1
    					 AND pia.active=1)", "serialno"); 					// 3
    	$query->column("pi.quantity");										// 4
    	$query->column("(SELECT pis.name 								
    					 FROM partinstancestatus pis 
    					 WHERE pi.partinstancestatusid = pis.id)", "serialno");	// 5
    	$query->column("pi.warehouseId");									// 6
    	$query->column("pi.partInstanceStatusId", "statusId");				// 7
    	$query->column("pi.partTypeId", "parttypeid");    					// 8
    	$query->column("pt.ownerClientId", "ownerClient");					// 9
    	$query->column("pi.kitTypeId", "kittypeid");						// 10
 
    	$query->setAdditionalJoin("inner join parttype pt on pt.id=pi.parttypeid");
   		$query->where($qFilter);
    	$query->where("pi.active=1");
    	
		//contract filters  	
    	$filter = FilterService::getFilterArray(FilterService::$CONTRACT_FILTER_ID);
		if (count($filter)>0) $query->where("pi.partTypeId IN (select distinct cpt.partTypeId from contract_parttype cpt where cpt.contractId in (" . implode(",",$filter) . "))");
		//contract filters
		
		//Debug::inspect($query->generate());
    	$result = $query->execute();  	
    	$this->totalCount = $query->TotalRows;   	
    	usort($result, "PartInstanceController::sortByPartCodePartInstance");
    	
    	if (empty($result))
    	{
    		$this->setErrorMessageSound("Search returns no result.");
    	}
		return $result;
    }
    
    /**
     * Sort By PartCode partInstance
     *
     * @param unknown_type $a
     * @param unknown_type $b
     * @return unknown
     */
    public static function sortByPartCodePartInstance($a, $b)
    {
    	$cmp = strcmp($a[1], $b[1]);
    	if ($cmp == 0)
    	{
    		$cmp = strcmp($a[3], $b[3]);
    		if ($cmp == 0)
    		{
    			$cmp = strcmp($a[7], $b[7]);
				if ($cmp == 0)
					$cmp = $a[4] - $b[4];    			
    		}
    	}
    	return $cmp;
    }

    /**
     * By default, do NOT load any data on page load, to ease the bandwidth and loading speed
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return null
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
		return null;
    }
    
    /**
     * Save
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function save($sender,$param)
    {
    	if($this->AddPanel->Visible == true)
    	{
    		$entity = $this->createNewEntity();
    		$params = $this;
    	}
    	else
    	{
    		$params = $param->Item;
			$entity = $this->lookupEntity($this->DataList->DataKeys[$params->ItemIndex]);
    	}

    	try {
	       	$focusObject = $this->focusObject->Value;
	       	$focusObjectArgument = $this->focusObjectArgument->Value;
	     	if($focusObject == "")
	     		$focusObject = null;
	     	else
	     		$focusObject = $this->getFocusEntity($focusObject,$focusObjectArgument);    	
	
	    	$this->setEntity($entity,$params,$focusObject);
	    	$this->saveEntity($entity);
	    	
	    	$this->resetFields($params);
	        if($this->AddPanel->Visible == true)
		        $this->AddPanel->Visible = false;
	    	else
		        $this->DataList->EditItemIndex = -1;
    	}
    	catch (Exception $e)
    	{
			$this->onLoad("reload");
    		$this->setErrorMessage($e->getMessage());
    		$this->dataLoad();
    	}
    }
    
    /**
     * Reset Fields
     *
     * @param unknown_type $params
     */
    protected function resetFields($params)
    {
    	$params->suggestPartName->Text = '';
		$params->sharedContracts->Items->clear();
    	$params->contract->Text = '';
    	$params->newQuantity->Text = '';
    	$params->newOwner->Text = '';
    	$params->warehouseid->Value = '';
    	if($this->AddPanel->Visible == true)
    	{
    		$params->newSerialNo->Text = '';
			$params->resultCheckSerialNo->Text = '';    		
    	}
    }
    
    /**
     * Lookup entity
     *
     * @param unknown_type $id
     * @return unknown
     */
	protected function lookupEntity($id)
    {
		return Factory::service("PartInstance")->get($id);    	
    }
    
    /**
     * Create new entity
     *
     * @return unknown
     */
	protected function createNewEntity()
    {
    	return new PartInstance();
    }

    /**
     * Set entity
     *
     * @param unknown_type $object
     * @param unknown_type $params
     * @param unknown_type $focusObject
     */
	protected function setEntity(&$object,$params,&$focusObject=null)
    {
		// setting parttype
		$pt = Factory::service("PartType")->get($params->suggestPartName->getSelectedValue());
    	// need to have valid part type!
    	if (empty($pt))
    	{
    		throw new Exception("Invalid part type.");
    	}
		// setting status
		$object->setPartInstanceStatus(Factory::service("PartInstanceStatus")->get($params->newStatus->getSelectedValue()));

		// set kit type
		$ktId = $params->newKitType->getSelectedValue();
		if (!empty($ktId))
			$object->setKitType(Factory::service("KitType")->get($ktId));
		else
			$object->setKitType(null);
		
		// set valid part type
		$object->setPartType($pt);
		
		
		$isSerialised = $pt->getSerialised();
		// setting quantity
		if ($isSerialised && $params->newQuantity->getText() != 1)
		{
			throw new Exception("Serialised part can only have part quantity of one.");
		}
		$object->setQuantity($params->newQuantity->getText());
		
		// setting warehouse
		$warehouseId_arr = explode('/', $params->warehouseid->getValue());
		$warehouseId = end($warehouseId_arr);
		
		$object->setWarehouse(Factory::service("Warehouse")->getWarehouse($warehouseId));

		
		// for new serialised part instance, we ENFORCE serial number!
    	if($this->AddPanel->Visible == true)  
    	{
    		$newSerialNo = $params->newSerialNo->getText();
			$sysHasSerialNo = $this->systemHasSerialNo($newSerialNo);
			$passCheckSum = Factory::service("PartInstance")->passSerialCheckSum($newSerialNo);    		
    		if (empty($newSerialNo))
				throw new Exception("Serial Number can not be empty.");    			
    		else if ($sysHasSerialNo)
    			throw new Exception("Serial Number has been used by other part.");
    		else if (!$passCheckSum)
    			throw new Exception("Invalid Serial Number: unmatched checksum."); 
    	}
    	
        $message = '';
    	Factory::service("PartInstance")->save($object);
    	
    	if($this->AddPanel->Visible == true)  // add, can have bcs!
    	{
    		$newSerialNo = $params->newSerialNo->getText();
			$sysHasSerialNo = $this->systemHasSerialNo($newSerialNo);
			$passCheckSum = Factory::service("PartInstance")->passSerialCheckSum($newSerialNo);    		
    		if (!empty($newSerialNo) && !$sysHasSerialNo && $passCheckSum)
    		{
    			$piat = Factory::service("PartInstanceAliasType")->get(1);   // get type with id = 1  (partinstancealiastypeid=1)
    			$newPIAlias = new PartInstanceAlias();
    			$newPIAlias->setAlias($newSerialNo);
    			$newPIAlias->setPartInstanceAliasType($piat);
				$newPIAlias->setPartInstance($object);
    			// set alias/bcs !
    			Factory::service("PartInstanceAlias")->save($newPIAlias);
    		}
    		else if (!empty($newSerialNo) && $sysHasSerialNo)
    		{
    			throw new Exception("Part instance created. However, serial number has been in used by other parts in the system.");
    		}
    		else if (!empty($newSerialNo) && !$passCheckSum)
    		{
    			throw new Exception("Part instance created. However, serial number has been rejected due to invalid checksum.");
    		}
    	}		
    }
    
    /**
     * Save entity
     *
     * @param unknown_type $object
     */
 	protected function saveEntity(&$object)
    {
    	if($this->AddPanel->Visible == true)  // addition
    		$message = "Part Instance created.";
    	else
    		$message = "Part Instance updated.";
    	$this->onLoad("reload");
    	$this->setInfoMessageSound($message);
    	$this->dataLoad();
    }
    
    /**
     * Redirect Part Instance Alias
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function redirectPartInstanceAlias($sender,$param)
	{
		$id = $this->DataList->DataKeys[$sender->Parent->ItemIndex];
		if($this->Request['searchparttext'] != Null)
		{
			$this->Response->redirect('/partinstancealias/'.$this->Request['searchparttext'].'/'.$id.'/');		
		}
		else
		{
			$this->Response->redirect('/partinstancealias/'.$id.'/'.$this->SearchText->getText().'/');		
		}
	}
	
	/**
	 * Redirect Part History
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
    public function redirectPartHistory($sender,$param)
	{
		$id = $this->DataList->DataKeys[$sender->Parent->ItemIndex];
		if($this->Request['searchparttext'] != Null)
		{
			$this->Response->redirect('/parthistory/'.$this->Request['searchparttext'].'/'.$id.'/');		
		}
		else
		{
			$this->Response->redirect('/parthistory/'.$id.'/');		
		}
	}
}

?>