<?php
/**
 * Part Delivery Lookup Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class PartDeliveryLookupController extends CRUDPage
{
	private $_editWarehouses;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();

		$this->roleLocks = "pages_all,pages_logistics_partDeliveryLookup";
		$this->menuContext = 'PartDeliveryLookup';
		$this->allowOutPutToExcel = true;

		$editWarehouse = Factory::service("UserAccountFilter")->getFilterValue(Core::getUser(),'EditWarehouse',Core::getRole());
		$this->_editWarehouses = WarehouseLogic::getWarehousesUnderWarehouse($editWarehouse);
	}

// 	public function onInit($param)
// 	{
// 		parent::onInit($param);

// 		if($this->allowOutPutToExcel == true)
// 		{
// 			$this->ListingPanel->findControl('OutputToExcelTable')->findControl('OutputToExcelRow')->findControl('OutputToExcelCell')->findControl('OutputToExcelButton')->setAttribute('onclick', "mb.showLoading('generating excel');");
// 		}
// 	}

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

        	$this->bindWorkTypeList($this->search_workTypeList);
        	$this->bindZoneSetList($this->search_zoneSetList);

     //   	$this->dataLoad();
        }

       	$this->ShowOptionLabel->setStyle("display:none;");
        $this->HideOptionLabel->setStyle("display:block;");

		$this->resetMessages();

        $this->Page->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('mb.hide()'));
    }

    /**
     *  Reset info or error messages.
     */
    public function resetMessages()
    {
		$this->setInfoMessage('');
		$this->setErrorMessage('');

        $this->activeInfoMessage->Text = " ";
        $this->activeErrorMessage->Text = " ";
    }

    /**
     * Create new entity
     *
     * @return unknown
     */
	protected function createNewEntity()
    {
    	return new Lu_PartDelivery();
    }

    /**
     * Lookup Entity
     *
     * @param int $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("Lu_PartDelivery")->get($id);
    }

    /**
     * Save new
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function saveNew($sender, $param)
    {
    	$this->resetMessages();

     	$errorMessage = "";
    	$courierControlId = $this->courierControlList->getSelectedValue();
    	$courierControl = Factory::service("CourierControl")->get($courierControlId);
    	if(!$courierControl instanceof CourierControl)
    		$courierControl = null;

    	$serviceCompanyId = $this->serviceCompanyList->getSelectedValue();
    	$serviceCompany = Factory::service("Company")->get($serviceCompanyId);
    	if(!$serviceCompany instanceof Company)
    		$serviceCompany =null;

    	$facilityWarehouse = null;
    	$facilityWarehouseId = $this->facilityWarehouse->getSelectedValue();

    	if($facilityWarehouseId =="" || empty($facilityWarehouseId))
    	{
    		$this->activeErrorMessage->Text = "Please provide Issuing Warehouse!";
    		$this->dataLoad();
    		return;
    	}
    	else
    	{
    		$facilityWarehouse = Factory::service("Warehouse")->get($facilityWarehouseId);
    		if(!$facilityWarehouse instanceof Warehouse)
    			$facilityWarehouse = null;

    		$facility = $facilityWarehouse->getFacility();
    		if(!$facility instanceof Facility)
    		{
    			$this->activeErrorMessage->Text = "Facility Warehouse '$facilityWarehouse' is not a facility!";

    			$this->dataLoad();
    			return;
    		}
    	}

    	$partToWarehouse = null;
    	$partToWarehouseId = $this->partToWarehouse->getSelectedValue();
    	if($partToWarehouseId =="" || empty($partToWarehouseId))
    	{
    		$this->activeErrorMessage->Text = "Pleae provide Recipient Warehouse!";
    		$this->dataLoad();
    		return;

    	}
    	else
    	{
    		$partToWarehouse = Factory::service("Warehouse")->get($partToWarehouseId);
    		if(!$partToWarehouse instanceof Warehouse)
		    	$partToWarehouse = null;

    	}


    	$lu_WorkType_Zonesets =array();
    	foreach($this->workTypeList->getSelectedValues() as $workTypeId)
    	{
	    	foreach($this->zoneSetList->getSelectedValues() as $zoneSetId)
	    	{
		    	$sql = "select id from lu_worktype_zoneset where workTypeId =$workTypeId and zoneSetId=$zoneSetId and active = 1";
		    	$result = Dao::getResultsNative($sql);
		    	if(count($result)==0)
		    	{
		    		$userAccountId = Core::getUser()->getId();
		    		$sql = "insert into lu_worktype_zoneset(`workTypeId`,`zoneSetId`,`created`,`createdById`,`updated`,`updatedById`)
		    						value('$workTypeId','$zoneSetId',NOW(),$userAccountId,NOW(),$userAccountId)";
		    		Dao::execSql($sql);
		    		$lu_workType_zoneSetId = Dao::$lastInsertId;
		    	}
		    	else
		    		$lu_workType_zoneSetId = $result[0][0];

		    	$lu_WorkType_Zoneset=Factory::service("Lu_WorkType_Zoneset")->get($lu_workType_zoneSetId);
	    		$sql = "select * from lu_partdelivery where lu_WorkType_ZonesetId = $lu_workType_zoneSetId and active = 1";
	    		if(count(Dao::getResultsNative($sql))==0)
		    		$lu_WorkType_Zonesets[] = $lu_WorkType_Zoneset;
		    	else
		    	{
		    		$workType = $lu_WorkType_Zoneset->getWorkType();
		    		$workType = $workType->getContract()." - ".$workType;
		    		$errorMessage .= "Zoneset('".$lu_WorkType_Zoneset->getZoneSet()."') and WorkType('$workType') is already in the list! skipping... <br />";
		    	}
	    	}
    	}
    	$newEntryinfo="";
    	foreach($lu_WorkType_Zonesets as $lu_WorkType_Zoneset)
    	{
    		$lu = new Lu_PartDelivery();
    		$lu->setLu_WorkType_Zoneset($lu_WorkType_Zoneset);
    		if($courierControl instanceof CourierControl)
    			$lu->setCourierControl($courierControl);

    		if($serviceCompany instanceof Company)
    			$lu->setServiceCompany($serviceCompany);

    		if($facilityWarehouse instanceof Warehouse)
    			$lu->setFacilityWarehouse($facilityWarehouse);

    		if($partToWarehouse instanceof Warehouse)
    			$lu->setRemoteWarehouse($partToWarehouse);


    		Factory::service("Lu_PartDelivery")->save($lu);
    		$newEntryinfo .= $lu_WorkType_Zoneset->getWorkType()->getLongName()." : ".$lu_WorkType_Zoneset->getZoneSet()->getName();
    		$newEntryinfo .= " - Added Successfully <br/>";
    	}
		$this->activeErrorMessage->Text = $errorMessage;


		if(!empty($newEntryinfo)&& $newEntryinfo>"")
			$this->activeInfoMessage->Text = $newEntryinfo;

    	$focusObject = $this->focusObject->Value;
       	$focusObjectArgument = $this->focusObjectArgument->Value;
     	if($focusObject == "")
     		$focusObject = null;
     	else
     		$focusObject = $this->getFocusEntity($focusObject,$focusObjectArgument);

        if($this->AddPanel->Visible == true)
	        $this->AddPanel->Visible = false;
    	else
	        $this->DataList->EditItemIndex = -1;

		$this->dataLoad();
     }

    /**
     * Get all of Entity
     *
     * @param unknown_type $focusObject
     * @param int $pageNumber
     * @param int $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$result = Factory::service("Lu_PartDelivery")->findAll(false,$pageNumber,$pageSize);
    	return $result;
    }

    /**
     * Search Entity
     *
     * @param string $searchString
     * @param FocusEntityObject $focusObject
     * @param int $pageNumber
     * @param int $pageSize
     * @return unknown
     */
	protected function searchEntity($searchString,&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$this->ListingPanel->Visible=true;
    	$this->resetMessages();

    	if($this->AddPanel->Visible == true)
    		return;


    	$workTypeIds = $this->search_workTypeList->getSelectedValues();
    	$zoneSetIds = $this->search_zoneSetList->getSelectedValues();
    	$facilityWarehouseId = $this->search_FacilityWarehouse->getSelectedValue();
    	$remoteWarehouseId = $this->search_RemoteWarehouse->getSelectedValue();

    	if(count($workTypeIds)==0 && count($zoneSetIds)==0 && $facilityWarehouseId==null && $remoteWarehouseId==null)
    	{
    		 $this->activeErrorMessage->Text = "Nothing to search!";
    		return array();
    	}

    	$sql = "select distinct lup.id
    			from lu_partdelivery lup
    			inner join lu_worktype_zoneset lwz on (lwz.id = lup.lu_WorkType_ZoneSetId)
    			where lup.active =1
    			".(count($workTypeIds)==0 ? "" : "and lwz.workTypeId in (".implode(",",$workTypeIds).")")."
    			".(count($zoneSetIds)==0 ? "" : "and lwz.zoneSetId in (".implode(",",$zoneSetIds).")")."
    			".($facilityWarehouseId==null ? "" : "and lup.facilityWarehouseId = $facilityWarehouseId")."
    			".($remoteWarehouseId==null ? "" : "and lup.remoteWarehouseId = $remoteWarehouseId");

    	$result = Dao::getResultsNative($sql);

    	$ids = array();
    	foreach($result as $row)
    	{
    		$ids[] = $row[0];
    	}

    	if(count($ids)==0)
    	{
    		 $this->activeErrorMessage->Text = "No Results Found!";
    		return array();
    	}

    	$result = Factory::service("Lu_PartDelivery")->findByCriteria("id in (".implode(",",$ids).")",array(),true,$pageNumber,$pageSize);
    	return $result;
    }

    /**
     * To Perform Search
     *
     * @return unknown
     */
	protected function toPerformSearch()
    {
    	$workTypeIds = $this->search_workTypeList->getSelectedValues();
    	$zoneSetIds = $this->search_zoneSetList->getSelectedValues();
    	$facilityWarehouseId = $this->search_FacilityWarehouse->getSelectedValue();
    	$remoteWarehouseId = $this->search_RemoteWarehouse->getSelectedValue();

    	if(count($workTypeIds)==0 && count($zoneSetIds)==0 && $facilityWarehouseId==null && $remoteWarehouseId==null)
    		return true;
    	else return false;
    }

    /**
     * Populate Add
     *
     */
    public function populateAdd()
    {
    	$this->bindWorkTypeList($this->workTypeList);
    	$this->bindZoneSetList($this->zoneSetList);

    	$this->facilityWarehouse->Text="";
    	$this->partToWarehouse->Text="";

    	$this->bindCourierControlList($this->courierControlList);
    	$this->courierControlList->Enabled=false;

    	$this->bindServiceCompanyList($this->serviceCompanyList);
    	$this->serviceCompanyList->Enabled=false;
//     	$this->dataLoad();

     	$this->ListingPanel->Visible=false;

    }

    /**
     * Populate Edit
     *
     * @param unknown_type $editItem
     */
    public function populateEdit($editItem)
    {

    	$record = $editItem->getData();
    	$this->bindWorkTypeList($editItem->workTypeList);
    	$editItem->workTypeList->setSelectedValue($record->getLu_WorkType_Zoneset()->getWorkType()->getId());
    	$editItem->workTypeList->Enabled=false;

    	$this->bindZoneSetList($editItem->zoneSetList);
    	$editItem->zoneSetList->setSelectedValue($record->getLu_WorkType_Zoneset()->getZoneSet()->getId());
    	$editItem->zoneSetList->Enabled=false;

    	$this->bindCourierControlList($editItem->courierControlList);
    	$courierControl = $record->getCourierControl();
    	if($courierControl instanceof CourierControl)
    		$editItem->courierControlList->setSelectedValue($courierControl->getId());
    	$editItem->courierControlList->Enabled=false;

    	$this->bindServiceCompanyList($editItem->serviceCompanyList);
    	$serviceCompany = $record->getServiceCompany();
    	if($serviceCompany instanceof Company)
    		$editItem->serviceCompanyList->setSelectedValue($serviceCompany->getId());
    	$editItem->serviceCompanyList->Enabled=false;

    	$facilityWarehouse = $record->getFacilityWarehouse()->getId();

	    $editItem->facilityWarehouse->Text = "";
	    $editItem->facilityWarehouseLabel->Text = Factory::service("Warehouse")->getWarehouse($facilityWarehouse);
	    $editItem->facilityWarehouseId->Value = $facilityWarehouse;

    	$partToWarehouse = $record->getRemoteWarehouse()->getId();

	    $editItem->partToWarehouse->Text = "";
	    $editItem->partToWarehouseLabel->Text = Factory::service("Warehouse")->getWarehouse($partToWarehouse);
	    $editItem->partToWarehouseId->Value = $partToWarehouse;

		$editItem->Updated->Text 	= $record->getUpdated();
		$editItem->UpdatedById->Text = $record->getUpdatedBy()->getPerson();

		$editItem->Created->Text 	= $record->getCreated();
		$editItem->CreatedById->Text = $record->getCreatedBy()->getPerson();

		//toggle save button based on editWarehouse from `useraccountfilter`
		if(isset($this->_editWarehouses)&& count($this->_editWarehouses)>0)
		{
			if(in_array(intval($facilityWarehouse),$this->_editWarehouses))
				$editItem->EditButton->Visible = true;
			else
				$editItem->EditButton->Visible = false;
		}
		else
			$editItem->EditButton->Visible = true;

    }

    /**
     * Delete
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function delete($sender, $param)
    {
     	$this->workTypeList->clearSelection();
     	$this->zoneSetList->clearSelection();
		$luId = $param->CommandParameter;
		$lu = Factory::service("Lu_PartDelivery")->get($luId);
		if(!$lu instanceof Lu_PartDelivery)
		{
			 $this->activeErrorMessage->Text = "Invalide Record to Deactivate!";
			$this->dataLoad();
			return;
		}
		$lu->setActive(false);
		Factory::service("Lu_PartDelivery")->save($lu);
     	$this->dataLoad();

    	$focusObject = $this->focusObject->Value;
       	$focusObjectArgument = $this->focusObjectArgument->Value;
     	if($focusObject == "")
     		$focusObject = null;
     	else
     		$focusObject = $this->getFocusEntity($focusObject,$focusObjectArgument);

     	$this->activeErrorMessage->Text = "";
		$this->activeInfoMessage->Text = "Deactivated Successfully!";
    }

    /**
     * Get Warehouse Breadcrumb
     *
     * @param unknown_type $warehouse
     * @return unknown
     */
    public function getWarehouseBreadCrumb($warehouse)
    {
    	if(!$warehouse instanceof Warehouse)
    		return;

    	return Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,"/");
    }

    /**
     * Get Warehouse Address
     *
     * @param Warehouse $warehouse
     * @return unknown
     */
    public function getWarehouseAddress($warehouse)
    {
    	if(!$warehouse instanceof Warehouse)
    		return;

    	$address="";
    	$facility = $warehouse->getFacility();
    	if($facility instanceof Facility )
    		$facilityName=$warehouse->getAlias(12);
    	else
    	{
    		$facility=$warehouse->getNearestFacility();
    		$facilityName=$warehouse->getAlias(12);
    	}

    	$facilityAddress = ((isset($facility) && $facility instanceof Facility)?$facility->getAddress():'');
    	$address =  (isset($facilityName)?$facilityName.", ":" ")."".$facilityAddress;
    	return $address;
    }

    /**
     * Bind WorkType list
     *
     * @param unknown_type $list
     */
    private function bindWorkTypeList(&$list)
    {
    	Factory::service("WorkType")->getFocusEntityDAOQuery()->eagerLoad("WorkType.contract");
    	$this->bindDropDownList($list, Factory::service("WorkType")->findByCriteria("", array(), false, null, null, array("Contract.contractName" => "asc", "WorkType.typeName" => "asc")));
    }

    /**
     * Bind ZoneSet List
     *
     * @param unknown_type $list
     */
    private function bindZoneSetList(&$list)
    {
    	$this->bindDropDownList($list, Factory::service("ZoneSet")->findByCriteria("", array(), false, null, null, array("ZoneSet.name" => "asc")));
    }

    /**
     * Bind CourierControl List
     *
     * @param unknown_type $list
     */
    private function bindCourierControlList(&$list)
    {
    	$this->bindDropDownList($list, Factory::service("CourierControl")->findByCriteria("", array(), false, null, null));
    }

    /**
     * Bind ServiceCompany list
     *
     * @param unknown_type $list
     */
    private function bindServiceCompanyList(&$list)
    {
    	$this->bindDropDownList($list, Factory::service("Company")->findByCriteria("", array(), false, null, null, array("Company.name" => "asc")));
    }

    /**
     * Update
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function update($sender, $param)
    {
		$this->resetMessages();
    	$param = $this->DataList->getEditItem();
		$lu = $this->lookupEntity($this->DataList->DataKeys[$param->ItemIndex]);

    	$courierControlId = $param->courierControlList->getSelectedValue();
    	$courierControl = Factory::service("CourierControl")->get($courierControlId);
    	if(!$courierControl instanceof CourierControl)
    		$courierControl = null;

    	$serviceCompanyId = $param->serviceCompanyList->getSelectedValue();
    	$serviceCompany = Factory::service("Company")->get($serviceCompanyId);
    	if(!$serviceCompany instanceof Company)
    		$serviceCompany =null;

    	$facilityWarehouse = null;
		$facilityWarehouseId = $this->facilityWarehouse->getSelectedValue();
    	if($facilityWarehouseId =="1")
    	{
    		 $this->activeErrorMessage->Text = "Invalid Facility Warehouse!";
    		$this->dataLoad();
    		return;
    	}
    	else
    	{
    		$facilityWarehouseId = ($param->facilityWarehouse->getSelectedValue() >""&& !is_null($param->facilityWarehouse->getSelectedValue()) ? $param->facilityWarehouse->getSelectedValue():$param->facilityWarehouseId->Value);
    		$facilityWarehouse = Factory::service("Warehouse")->get($facilityWarehouseId);
    		if(!$facilityWarehouse instanceof Warehouse)
    			$facilityWarehouse = null;

    		$facility = $facilityWarehouse->getFacility();
    		if(!$facility instanceof Facility)
    		{
    			 $this->activeErrorMessage->Text = "Facility Warehouse '$facilityWarehouse' is not a facility!";
    			$this->dataLoad();
    			return;
    		}
    	}


    	$partToWarehouse = null;
    	$partToWarehouseId = ($param->partToWarehouse->getSelectedValue() >""&& !is_null($param->partToWarehouse->getSelectedValue()) ? $param->partToWarehouse->getSelectedValue():$param->partToWarehouseId->Value);
    	if($partToWarehouseId !="1")
    	{
    		$partToWarehouse = Factory::service("Warehouse")->get($partToWarehouseId);
    		if(!$partToWarehouse instanceof Warehouse)
		    	$partToWarehouse = null;

    	 }

    	$lu_WorkType_Zonesets =array();
    	$workTypeId = $param->workTypeList->getSelectedValue();
    	$zoneSetId = $param->zoneSetList->getSelectedValue();
    	$sql = "select id from lu_worktype_zoneset where workTypeId =$workTypeId and zoneSetId=$zoneSetId and active = 1";
    	$result = Dao::getResultsNative($sql);
    	if(count($result)==0)
    	{
    		$userAccountId = Core::getUser()->getId();
    		$sql = "insert into lu_worktype_zoneset(`workTypeId`,`zoneSetId`,`created`,`createdById`,`updated`,`updatedById`)
    						value('$workTypeId','$zoneSetId',NOW(),$userAccountId,NOW(),$userAccountId)";
    		Dao::execSql($sql);
    		$lu_workType_zoneSetId = Dao::$lastInsertId;
    	}
    	else
    		$lu_workType_zoneSetId = $result[0][0];

    	$lu_WorkType_Zoneset=Factory::service("Lu_WorkType_Zoneset")->get($lu_workType_zoneSetId);
    	$lu->setLu_WorkType_Zoneset($lu_WorkType_Zoneset);

    	$lu->setCourierControl($courierControl);
    	$lu->setServiceCompany($serviceCompany);
    	$lu->setFacilityWarehouse($facilityWarehouse);
    	$lu->setRemoteWarehouse($partToWarehouse);
    	$lu_saved = Factory::service("Lu_PartDelivery")->save($lu);
    	$focusObject = $this->focusObject->Value;
       	$focusObjectArgument = $this->focusObjectArgument->Value;
     	if($focusObject == "")
     		$focusObject = null;
     	else
     		$focusObject = $this->getFocusEntity($focusObject,$focusObjectArgument);

        if($this->AddPanel->Visible == true)
	        $this->AddPanel->Visible = false;
    	else
	        $this->DataList->EditItemIndex = -1;

		$this->dataLoad();
		$this->activeInfoMessage->Text = $lu_WorkType_Zoneset->getWorkType()->getLongName()." / ".$lu_WorkType_Zoneset->getZoneset()->getName()." (IW: ".$lu_saved->getFacilityWarehouse()->getName().", RW: ".$lu_saved->getRemoteWarehouse()->getName().") - Updated Successfully";
    }

	/**
	 *	Suggest recieving part delivery lookup warehouses.
	 */
	public function suggestRecipientWarehouses($searchString)
	{
		$result =  WarehouseLogic::getPartDeliveryLookupWarehouses($searchString,false);

		if(count($result)>0)
			return $result;
		else
			return array(array("-1", "No Results Found..."));
	}

	/**
	 *	Suggest recieving part delivery lookup warehouses.
	 */
	public function suggestEditRecipientWarehouses($searchString)
	{
		$result =  WarehouseLogic::getPartDeliveryLookupWarehouses($searchString,false,true);

		if(count($result)>0)
			return $result;
		else
			return array(array("-1", "No Results Found..."));
	}

	/**
	 *	Suggest Issuing Bytecraft warehouses.
	 */
	public function suggestIssuingWarehouses($searchString)
	{
		$result = WarehouseLogic::getBytecraftWarehouseTypeInfo($searchString, array('MainFacility','MinorFacility','FR_PushList'));
		if(count($result)>0)
			return $result;
		else
			return array(array("-1", "No Results Found..."));
	}

	/**
	 *	Suggest recieving warhouse linked to MSL.
	 */
	public function suggestMSLRecipientWarehouses($searchString)
	{
		$result = WarehouseLogic::getPartDeliveryLookupWarehouses($searchString,true);
		if(count($result)>0)
			return $result;
		else
			return array(array("-1", "No Results Found..."));

	}

	/**
	 * Output to excel
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function outputToExcel($sender, $param)
	{
	   	$columnHeaderArray = array("WorkType",
							"ZoneSet",
							"Issuing Warehouse",
							"Issuing Warehouse Address",
							"Recipient Warehouse",
							"Recipient Warehouse Address");

    	$totalSize = $this->DataList->VirtualItemCount;
    	$title="Part Delivery Lookup";
    	$errMsg = " (Can't Output Full Data List To Excel, as Data Size Is Too Big. So only Exported Current Page of the Data List.)";
    	if($totalSize <= 0 )
    	{
    		 $this->activeErrorMessage->Text = "Can't Output To Excel, as There is No Data.";
    	}
    	else if($totalSize > 2500)
    	{
    		 $this->activeInfoMessage->Text = $errMsg;
    		$title .= $errMsg;
	    	$allData = $this->dataLoad();
    	}
    	else
    	{
        	$allData = $this->dataLoad(1,$totalSize);
    	}

    	if(isset($allData))
    	{
	    	$columnDataArray = array();
			foreach ($allData as $row)
				array_push($columnDataArray, $this->buildDataRowForExcel($row));

	    	$this->toExcel($title,"","",$columnHeaderArray,$columnDataArray);
    	}
	}

	/**
	 * Build data row for excel
	 *
	 * @param unknown_type $row
	 * @return unknown
	 */
    public function buildDataRowForExcel($row)
    {
    	$data = array();
    	//Worktype
		array_push($data, $row->getLu_WorkType_Zoneset()->getWorkType()->getLongName());
		//ZoneSet
		array_push($data, $row->getLu_WorkType_Zoneset()->getZoneSet()->getName());
		//Facility Warehouse
		array_push($data, $row->getfacilityWarehouse()->getName());
		//Facility Warehouse Address
		array_push($data, $this->getWarehouseAddress($row->getfacilityWarehouse()));
		//Recieving Warehouse
		array_push($data, $row->getremoteWarehouse()->getName());
		//Recieving Warehouse Address
		array_push($data, $this->getWarehouseAddress($row->getremoteWarehouse()));

		return $data;
    }

    /**
     * Results per page changed
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function resultsPerPageChanged($sender, $param)
    {
    	$this->AddPanel->Visible = false;
    	$this->DataList->EditItemIndex = -1;
		$this->DataList->pageSize = $param -> NewPageResults;
      	$this->dataLoad();
    }

    /**
     * Reset the fields
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function reset($sender,$param)
	{
		$url = $this->Request->getUrl()->getPath();
		$this->Response->redirect($url);
	}

	/**
	 * Handle selected Issuing Warehouse
	 *
	 */
	public function handleSelectedIssuingWarehouse()
	{
		$facilityWarehouseId = intval($this->facilityWarehouse->getSelectedValue());
		if($facilityWarehouseId)
		{
			if(isset($this->_editWarehouses)&& count($this->_editWarehouses)>0)
			{
				if(in_array(intval($facilityWarehouseId),$this->_editWarehouses))
				{
					$this->EditButton_Save->Visible =true;
				}
				else
				{
					$this->EditButton_Save->Visible =false;
				}
			}
		}
	}

    /**
     * Show Edit/Delete Button based on Edit Warehouse feature(userPreference)
     *
     * @param unknown_type $warehouse
     * @return unknown
     */
	public function showEditOrDeleteButton($lu_partDelivery)
    {
    	if(!$lu_partDelivery instanceof Lu_PartDelivery)
    	{
    		return;
    	}

	    $role  = Core::getRole();
	    if($role->getName() == 'System Admin')
    	{
	    	return true;
    	}
    	else
    	{
	    	$warehouse=$lu_partDelivery->getFacilityWarehouse();
	    	if($warehouse instanceof Warehouse)
	    	{
		     	if(isset($this->_editWarehouses) && count($this->_editWarehouses)>0)
		    	{
				    if(in_array(intval($warehouse->getId()),$this->_editWarehouses))
				    	return true;
		    	}

	    	}
	    	return false;
    	}
    }


}

?>