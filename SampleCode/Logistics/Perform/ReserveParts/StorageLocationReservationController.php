<?php
/**
 * Pending Parts Screen
 * More details see here {@link http://hydra.bytecraft.internal/wiki/index.php/Pending_and_Reserve_Parts}
 *
 * @package Hydra-Web
 * @subpackage Controller-BulkloadPage
 * @author Lin He<lhe@bytecraft.com.au>
 */
class StorageLocationReservationController extends HydraPage
{
	/**
	 * Item selector for the dropdown list on the menu
	 * @var string
	 */
	public $menuContext = 'reserveparts';

	/**
	 * The warehouse that the current user is using
	 * @var Warehouse
	 */
	private $_ownerWarehouse;

	/**
	 * The warehouse that we should look under for stock counts etc
	 * @var Warehouse
	 */
	private $_stockWarehouse;

	/**
	 * the number of rows show per page
	 */
	const NO_ROWS_PER_PAGE = 15;

	/**
	 * option for ALL. Used in all serach dropdown list
	 */
	const OPTION_FOR_ALL = "ALL";

	/**
	 * option for ALL open. Used in all serach dropdown list
	 */
	const OPTION_FOR_OPEN= "OPEN";

	/**
	 * preference parameter name for view preference group
	 */
	const PREFERENCE_PARAM_NAME = "fr_viewgroup";

	/**
	 * preference parameter name for owner warehouse
	 */
	const OWNERWAREHOUSE_PARAM_NAME = "facilityRequestOwnerWarehouses";

	/**
	 * The part instance status ids that won't be counted for available parts and reserved parts
	 */
	private $_notCountPIStatusIds = array();

	/**
	 * The warehouse category Ids that won't be counted for available parts and reserved parts
	 */
	private $_notCountWarehouseCategoryIds = array();

	/**
	 * Warehouse ids to ignore counting stock in
	 */
	private $_ignoreStockCountWhIds = array();


	//Holds the refreshing time in seconds
	private $_refreshTime;

	//Holds the push list ids
	private $_pushList;

	//Holds the max Execution Time in seconds
	private $_maxExecutionTime;

	//How many results to show, 0 for all
	private $_limitResults;

	/**
	 * @var Facility Request Priorities from Don't hard code table.
	 */
	private $_frPriorities;

	/**
	 * @var Facility Request Email WarehouseAliasType Ids from Don't hard code table.
	 */
	private $_frEmailWarehouseAliasTypeIds;

	/**
	 * Whether or not we are redirecting to the report server
	 * @var unknown
	 */
	private $_reportServerRedirectEnabled;

	/**
	* constructor
	*/
	public function __construct()
	{
		parent::__construct();
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_storageLocationReservation";
		$this->_frPriorities = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName('FacilityRequestLogic','FR_Graph_Priorities');
		$this->_frEmailWarehouseAliasTypeIds = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName('FacilityRequestLogic','FR_Email_WarehouseAliasTypeIds');
		$this->_frEmailWarehouseAliasTypeIds = ((!is_null($this->_frEmailWarehouseAliasTypeIds) && $this->_frEmailWarehouseAliasTypeIds > '') ?$this->_frEmailWarehouseAliasTypeIds:'6');
		$this->_notCountPIStatusIds = array(PartInstanceStatus::ID_PART_INSTANCE_STATUS_BER);
		$this->_notCountWarehouseCategoryIds = array(WarehouseCategoryService::$categoryId_TransitNote);
		$this->setTitle("Reserve Parts");

		try
		{
			$this->_reportServerRedirectEnabled = (bool)Config::getAdminConf('ReportRedirection', 'Enable_' . __CLASS__);
		}
		catch (Exception $e) {}

		if ($this->_reportServerRedirectEnabled)
		{
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON
		}
	}

	/**
	 * (non-PHPdoc)
	 * @see TPage::onPreInit()
	 */
	public function onPreInit($param)
	{
		if ($this->Request['for']=='workshop')
			$this->getPage()->setMasterClass("Application.layouts.WorkshopLayout");
		else
			$this->getPage()->setMasterClass("Application.layouts.LogisticsLayout");
	}

	/**
	 * get the default warehouse of the user
	 *
	 * @return Warehouse The user's default warehouse
	 * @throws Exception When user has invalid default warehouse or the default warehouse is NOT a facility
	 */
	private function _getDefaultWarehouse()
	{
		try
		{
			$defaultWarehouse = Factory::service("Warehouse")->getDefaultWarehouse(Core::getUser());
		}
		catch(Exception $e)
		{
			throw new Exception("Invalid / No Default Warehouse Found!");
		}
		if(!$defaultWarehouse instanceof Warehouse)
		{
			throw new Exception("Invalid / No Default Warehouse Found!");
		}
		return $defaultWarehouse;
	}

	/**
	 * Get Refresh time
	 *
	 * @return unknown
	 */
	public function getRefreshTime()
	{
		if(!isset($this->_refreshTime))
		{
			$this->_refreshTime = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'refreshTime',true);
		}
		if(is_numeric($this->_refreshTime) && $this->_refreshTime> 29)
		{
			return $this->_refreshTime;
		}
		else
		{
			return 300;
		}
	}

	/**
	 * Get FieldTask Edit Base URL
	 *
	 * @return unknown
	 */
	public function getFieldTaskEditBaseURL()
	{
		$roleMenus = json_decode($_SESSION['role_menu']);
		$link = '';

		foreach($roleMenus as $roleMenu)
		{
			if(isset($roleMenu[1]))
			{
				if($roleMenu[1] == '/fieldtaskstatus/')
				{
					return '/fieldtaskstatus/edit/';
				}

				if($roleMenu[1] == '/task/workshop')
				{
					$link = '/task/edit/workshop/';
				}

				if($roleMenu[1] == '/task/staging')
				{
					$link = '/task/edit/staging/';
				}
			}
		}
		return $link;
	}

	/**
	 * Bind Owner WarehouseList
	 *
	 */
	private function _bindOwnerWarehouseList()
	{
		$result = array();
		$ownerWarehouses = trim(Factory::service("UserPreference")->getOption(Core::getUser(), self::OWNERWAREHOUSE_PARAM_NAME));
		if ($ownerWarehouses !== null)
		{
			$ownerWarehouseArray = 	explode(",", $ownerWarehouses);
			if(count($ownerWarehouseArray)>0)
			{
				$pushListWhIds = array();
				$pushListWhs = WarehouseLogic::getFacilityRequestPushListWarehouses();
				foreach ($pushListWhs as $wh)
					$pushListWhIds[] = $wh[0];

				foreach($ownerWarehouseArray as $warehouseId)
				{
					$warehouse = Factory::service("Warehouse")->get($warehouseId);
					if ($warehouse instanceOf Warehouse && $warehouse->getActive() && in_array($warehouse->getId(), $pushListWhIds)) //only if we are active and is one of the push list warehouses
							$result[] = array($warehouse->getId(), $warehouse->getName());
						}
					}
				}

		if (empty($result))
		{
			$this->setErrorMessage('There are no valid Owner Warehouse preferences set.<br />' . MessageLogic::getContactTechnologyMsg());
			$this->MainContent->Enabled = false;
			return;
		}

		$this->ownerWarehouseList->DataSource = $result;
		$this->ownerWarehouseList->DataBind();
// 		if (isset($_SESSION['currentOwnerWarehouse']))
// 		{
// 			try
// 			{
// 				$this->ownerWarehouseList->selectedValue = 	$_SESSION['currentOwnerWarehouse'];
// 			}
// 			catch(Exception $e){}
// 		}
	}

	/**
	 * Bind the field task statuses
	 * @param TList $list
	 */
	private function _bindFieldtaskStatuses(TDropDownList $list)
	{
		$array = array(array(self::OPTION_FOR_ALL));
		$frStatuses = array('TAKEN','PENDING','AVAILABLE','TRANSIT');
		foreach($frStatuses as $status)
		{
			if($status !='BILLING')
				$array[] = array($status);
		}
		$list->DataSource = $array;
		$list->DataBind();
		try{
			$list->setSelectedValue('PENDING');
		}catch(Exception $e){}

	}

	/**
	 * Bind the attend statuses on the a dropdown list
	 * @param TList $list
	 */
	private function _bindAttendStatuses(TListControl $list)
	{
		$array = array();
		$statuses = Factory::service("FacilityRequest")->getAllFRStatuses();
		sort($array);
		foreach($statuses as $status)
		{
			$array[] = array($status);
		}
		array_unshift($array, array(self::OPTION_FOR_ALL));
//		array_unshift($array, array(self::OPTION_FOR_OPEN));

		$list->DataSource = $array;
		$list->DataBind();
	}

	/**
	 * Bind the work types on the a dropdown list
	 * @param TList $list
	 */
	private function _bindWorkTypes(TListControl $list)
	{
		$array = array();
		$wtList = WorkTypeLogic::getWorkTypeList();

		//removing ALL option to reduce the chances of long query!
		$list->DataSource = $wtList;
		$list->DataBind();
		$this->countWorkTypes->Value = count($wtList);
	}

	/**
	 * Bind the zone sets on the a dropdown list
	 * @param TList $list
	 */
	private function _bindZoneSets(TListControl $list)
	{
		$array = array();
		$zss = Factory::service("ZoneSet")->findByCriteria("", array(), false, null, null, array("ZoneSet.name" => 'asc'));
		$list->DataSource = $zss;
		$list->DataBind();
		$this->countZonesets->Value = count($zss);
	}

	/**
	 * Get Owner Warehouse
	 *
	 * @return unknown
	 */
	public function getOwnerWarehouse()
	{
		try
		{
// 			$warehouseId = $_SESSION['currentOwnerWarehouse'];
			$warehouseId = $this->ownerWarehouseList->getSelectedValue();
			$warehouse = Factory::service("Warehouse")->get($warehouseId);
			if ($warehouse instanceOf Warehouse)
			{
				$this->_ownerWarehouse = $warehouse;
			}
		}
		catch(Exception $e){}

		try{
			if(!$this->_ownerWarehouse instanceOf Warehouse){
				$this->_ownerWarehouse = $this->_getDefaultWarehouse();
			}
		}
		catch(Exception $e){
			$this->_ownerWarehouse = $e->getMessage();
		}
		return $this->_ownerWarehouse;
	}

	/**
	 * Enter description here...
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function changeOwnerWarehouse($sender,$param)
	{
		//$warehouseId = $this->ownerWarehouseList->getSelectedValue();
		//$_SESSION['currentOwnerWarehouse'] = $warehouseId;
	}

	/**
	 * (non-PHPdoc)
	 * @see CRUDPage::onLoad()
	 */
	public function onLoad($param)
	{

		parent::onLoad($param);
		$this->Page->jsLbl->Text = "";
		$this->getPage()->getClientScript()->registerScriptFile('barcodeJs', $this->publishAsset('Barcode.js'));

		$this->_getDefaultWarehouse();
		try
		{
			$defaultWarehouse = Factory::service("Warehouse")->getDefaultWarehouse(Core::getUser());
			if ($defaultWarehouse instanceof Warehouse && !$defaultWarehouse->getNearestFacility() instanceof Facility)
				throw new Exception('The Default Warehouse is not a Facility!');
		}
		catch (Exception $e)
		{
			$this->setErrorMessage($e->getMessage());
			$this->MainContent->Enabled = false;
			return;
		}

		$facilityRequestWarhouse = Factory::service("UserPreference")->getOption(Core::getUser(), 'facilityRequestOwnerWarehouses');
		if(isset($facilityRequestWarhouse) && $facilityRequestWarhouse >"")
		{
			//find the default warehouse
			$this->_ownerWarehouse = $this->getOwnerWarehouse();
			if(!$this->_ownerWarehouse instanceof Warehouse)
			{
				$this->setErrorMessage($this->_ownerWarehouse);
				$this->MainContent->Enabled = false;
				return;
			}
		}
		else
		{
			$this->setErrorMessage('No `Facility Request Warehouse` found, please contact technology.');
			$this->MainContent->Enabled = false;
			return;
		}
		//initialising the first load
		if(!$this->IsPostBack && !$this->IsCallBack)
		{
			$this->_bindFieldtaskStatuses($this->ftStatus);
			$this->_bindAttendStatuses($this->frStatus);
			$this->frStatus->setSelectedValues(array('new','attended','reserved'));

			//if url passed in the field task number
			if(isset($this->Request['searchby']) && trim($this->Request['searchby']) === 'fieldtask' && isset($this->Request['id']) && trim($this->Request["id"]) !== '')
			{
				$this->taskNumber->Text = $this->Request["id"];
				$this->search($this->SearchButton, null);
			}

			//Binding the preference group the preference list
			$this->_bindUPGroupList();
			//Binding the worktype list
			$this->_bindWorkTypes($this->workType);
			//Binding the zoneset list
			$this->_bindZoneSets($this->zoneSet);
			$this->_bindOwnerWarehouseList();

			//$warehouseId = $this->ownerWarehouseList->getSelectedValue();
			//$_SESSION['currentOwnerWarehouse'] = $warehouseId;
		}

		$this->priorityLegend->getControls()->add(FacilityRequestLogic::getFRPriorityLegends());
	}

	/**
	 * Get FR Hat Details
	 * @param unknown $warehouseId
	 * @return string
	 */
	public function getFRHatDetails($sender,$param)
	{
		$result=$errors=array();
		try
		{
			if(!empty($this->selctedHatId->Value) && $this->selctedHatId->Value >'')
				$warehouseId = $this->selctedHatId->Value;
			else
				$warehouseId = $this->ownerWarehouseList->getSelectedValue();

	 		$warehouse = Factory::service('Warehouse')->get($warehouseId);
			if($warehouse instanceof Warehouse)
			{
				$result['id']=$warehouse->getId();
				$result['name']=$warehouse->getName();
				$result['position']=$warehouse->getPosition();
			}
			else
			{
				$result['id']=$warehouseId;
				$result['name']='';
				$result['position']='';
			}
		}
		catch(Exception $ex)
		{
			$errors[] = $this->_getErrorMsg($ex->getMessage());
		}
		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}

	/**
	 * Binding the preference group the preference list
	 */
	private function _bindUPGroupList()
	{
		$result = array();
		foreach($this->_getUPGroups() as $key => $content){
			$result[] = array($key, $key);
		}
		$result[] = array("chg", "** Show All **");
		$this->preferencesList->DataSource = $result;
		$this->preferencesList->DataBind();
	}

	/**
	 * get view user preference groups
	 */
	private function _getUPGroups()
	{
		try{
			$preferences = trim(Factory::service("UserPreference")->getOption(Core::getUser(), self::PREFERENCE_PARAM_NAME));
			$return = ($preferences === '' ? array() : json_decode($preferences,true));
			return $return;
		}
		catch(Exception $ex){
			return array();
		}
	}

	/**
	 * Get Limit
	 *
	 * @return unknown
	 */
	private function _getLimit()
	{
		if(!isset($this->_limitResults)){
			$this->_limitResults = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'limitResults',true);
		}

		if(is_numeric($this->_limitResults)){
			return $this->_limitResults;
		}
		else{
			return 0;
		}
	}

	/**
	 * Get Maximum Execution time
	 *
	 * @return unknown
	 */
	public function getMaxExecutionTime()
	{
		if(!isset($this->_maxExecutionTime)){
			$this->_maxExecutionTime = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'maxExecutionTime',true);
		}

		if(is_numeric($this->_maxExecutionTime) && $this->_maxExecutionTime > 44){
			return $this->_maxExecutionTime;
		}
		else{
			return 120;
		}
	}

	/**
	 * search event triggered by SearchButton on the .page file
	 * @see CRUDPage::search()
	 */
	public function search($sender,$param)
	{
		$result = '';
		$errors = array();
		try{
			if(!isset($param->CallbackParameter->searchParams))
				throw new Exception("No search params passed in!");

			$searchParams = $param->CallbackParameter->searchParams;

			if(!isset($searchParams->sortingField) || ($sortingField = trim($searchParams->sortingField)) === '' || ($sortingField = trim($searchParams->sortingField)) ==='frpriority')
				$sortingField = 'slaEnd';
			if(!isset($searchParams->sortingDirection) || ($sortingDirection = trim($searchParams->sortingDirection)) === '')
				$sortingDirection = 'asc';
			if(!isset($searchParams->pageNo) || ($pageNo = trim($searchParams->pageNo)) === '')
				$pageNo = 1;

			$totalRows = 0;
			$pageSize = self::NO_ROWS_PER_PAGE;
			$result = $this->_getData(false, $pageNo, $totalRows, $sortingField, $sortingDirection);
			$this->totalRows->Text = $totalRows;

			$now = new HydraDate();
			$this->timeOfSearch->Value = $now->__toString();
			$this->showingReloadMessage->Value = '';
		}
		catch(Exception $ex){
			$errors[] = $ex->getMessage();
		}
		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

	/**
	 * Gathering the search criterias, used for _getData() and addOrDelPreference()
	 *
	 * @return multitype:string
	 */
	private function _getSearchCriteria()
	{
		$fieldTaskNo = trim($this->taskNumber->Text);
		$clientRefNo = trim($this->clientRefNumber->Text);
		$serialNo = trim($this->serialNumber->Text);
		$searchPartTypeId = trim($this->partType->getSelectedValue());
		$searchAttendedById = trim($this->attendedBy->getSelectedValue());
		$recipientTechId = trim($this->recipientTech->getSelectedValue());
		$attendedStatus = $this->frStatus->getSelectedValues();
		$taskStatus =  trim($this->ftStatus->getSelectedValue());
		$hasReserve =  trim($this->hasReserve->getSelectedValue());
		$sentToWarehouseId = trim($this->sendTo->getSelectedValue());
		$sentToTechId = Factory::service('Warehouse')->getDefaultMobileWarehouse(trim($this->recipientTech->getSelectedValue()));
		$workTypeIds = $this->workType->getSelectedValues();
		$zoneSetIds = $this->zoneSet->getSelectedValues();

		$return = array($fieldTaskNo, $clientRefNo, $searchPartTypeId, $searchAttendedById, $attendedStatus, $taskStatus, $hasReserve, $sentToWarehouseId, $sentToTechId, $workTypeIds, $zoneSetIds, $serialNo,$recipientTechId);
		foreach($return as $value)
		{
			$checkingValue = (is_array($value) ?  implode(",", $value) : $value);

			if(trim($checkingValue) !== self::OPTION_FOR_ALL && $checkingValue !== '')
			return $return;
		}

		throw new Exception("Please provide at least one search criteria!");
	}

	/**
	 * Get Status Where
	 *
	 * @param unknown_type $attendedStatus
	 * @return unknown
	 */
	private function getStatusWhere($attendedStatus)
	{
		$where = "";
		if(count($attendedStatus)>0 && sizeof($attendedStatus) >"")
		{
			$statuses = implode("', '",$attendedStatus);
			if(!in_array(self::OPTION_FOR_ALL,$attendedStatus)){
				$where .= " AND facireq.status in('".$statuses."')";
			}
		}
		else
		{
			$where .= " AND (facireq.status != '" . FacilityRequest::STATUS_CLOSED . "' and facireq.status != '" . FacilityRequest::STATUS_CANCEL . "' and facireq.status != '" . FacilityRequest::STATUS_COMPLETE . "') ";
		}
		return $where;
	}

	private function _setIgnoreInStockCountWhIds()
	{
		$whIds = array();
		$sql = "SELECT w.id FROM warehouse w
				INNER JOIN warehousealias wa ON wa.warehouseid=w.id AND wa.active=1 AND wa.warehousealiastypeid=" . WarehouseAliasType::ALIASTYPEID_CODENAME . " AND wa.alias='UnreconciledTransitNoteParts'
				WHERE w.active=1 AND w.position LIKE '" . $this->_stockWarehouse->getPosition() . "%'";
		$res = Dao::getResultsNative($sql);
		foreach ($res as $r)
		{
			$whIds[] = $r[0];
		}
		$this->_ignoreStockCountWhIds = $whIds;
		return $whIds;
	}

	/**
	 * searching the requested results.
	 *
	 * @param int    $pageNo          Which page we are fetching.
	 * @param int    $pageSize        The page size of each page
	 * @param int    $totalRows       The total number of rows in total of the results
	 * @param string $orderField      The field we are sorting on
	 * @param string $orderDirection  The order of the sorting: asc | desc
	 * @param array  $overrideWithIds The requestIds for refreshing updates
	 *
	 * @return Ambigous <multitype:]array[ , multitype:>
	 */
	private function _getData($isExcel = false,$pageNo, &$totalRows = 0, $orderField = 'slaend', $orderDirection = 'asc', $overrideWithIds = array(), $sinceSearchDate = "", $ownerWarehouse="")
	{
		list($fieldTaskNo, $clientRefNo, $searchPartTypeId, $searchAttendedById, $attendedStatus, $taskStatus, $hasReserve, $sentToWarehouseId, $sentToTechId, $workTypeIds, $zoneSetIds, $serialNo,$recipientTechId) = $this->_getSearchCriteria();
		$additionalJoins = "";
		$sentToWarehouse = Factory::service("Warehouse")->get($sentToWarehouseId);

		if($sentToWarehouseId !=='' && !$sentToWarehouse instanceof Warehouse){
			throw new Exception("Invalid warehouse for 'sent to' for ID(=$sentToWarehouseId)!");
		}

		if($ownerWarehouse instanceOf Warehouse)
			$this->_ownerWarehouse = $ownerWarehouse;
		else
			$this->_ownerWarehouse = $this->getOwnerWarehouse();

		$this->_stockWarehouse = FacilityRequestLogic::getWarehouseForPartCollection($this->_ownerWarehouse, Factory::service('Warehouse')->getDefaultWarehouse(Core::getUser()));

		//get the warehouse ids not to count under the owner warehouse
		$this->_setIgnoreInStockCountWhIds();

		$sql = "";
		$where = "facireq.active = 1";
		$additionJoin = "";
		$hasReserverSQL = '';
		$fieldtaskStatuses = '';

		$partInstanceIdsArray = array();
		if($serialNo)
		{
			$partInstances = Factory::service("PartInstance")->getPIonPIAType(array($serialNo), array(PartInstanceAliasType::ID_SERIAL_NO,PartInstanceAliasType::ID_BOX_LABEL));
			if(count($partInstances) > 0)
			{
				foreach($partInstances as $partInstance)
				{
					$partInstanceIdsArray[] = $partInstance->getId();
				}
			}
			else
			{
				throw new Exception("No Parts found for serial number: " . $serialNo);
			}
		}
		if(count($overrideWithIds) > 0)
		{
			$fieldtaskStatuses = "";
		}
		else
		{
			//append field task status
			$fieldtaskStatuses = ($taskStatus !== self::OPTION_FOR_ALL && trim($taskStatus) !=='') ? " AND ft.status = '$taskStatus'" : " ";
		}

		$additionalJoins .= " inner join fieldtask ft ON (facireq.fieldTaskId = ft.id and (ft.active = 1 and ft.escalated = 0 $fieldtaskStatuses ))";
		$additionalJoins .= " inner join address a on (a.id = ft.addressId)  ";
		if($isExcel)
		{
			$additionalJoins .= " inner join state st on (st.id = a.stateId)  ";
		}
		$additionalJoins .= " inner join zone z on (z.id = a.zoneId) ";
		$additionalJoins .= " inner join zoneset zs on (zs.id = z.zoneSetId) ";

		if(count($overrideWithIds) > 0) //if this is a refresh request, then just look for the request ids
		{
			$where .= ' AND facireq.id in (' . implode(",", $overrideWithIds) . ')';
		}
		else if($fieldTaskNo !== '') //When user puts in a field task number, it should search any where in the system
		{
			$where .= " AND (facireq.fieldtaskId IN(" . UtilsLogic::convertFieldtaskToInString($fieldTaskNo) . "))";
		}
		else if($clientRefNo !== '') //When user puts in a Client field task number, it should search any where in the system
		{
			$where .= " AND (ft.clientfieldtasknumber IN(" . UtilsLogic::convertToInString($clientRefNo) . "))";
		}
		else if($serialNo)
		{
			if(count($partInstanceIdsArray) > 0)
			{
				$additionalJoins .= "";
				$where .= ' AND pi.id in ('. implode(",", $partInstanceIdsArray) . ')';
			}
		}
		else
		{
			$defaultWarehouseFacility = $this->_ownerWarehouse->getFacility();
			if($recipientTechId !== '')
			{
				$where .= " AND ft.technicianId in ('".$recipientTechId."')"; //returning defaultMobileWarehouseId need uaId
			}
			else
			{
				$searchAll = true;
				if(!$defaultWarehouseFacility instanceOf Facility)
				{
					//append facility id limit
					$where .= " AND (facireq.ownerId = " . $this->_ownerWarehouse->getId() . ") ";
					$searchAll = false;
				}

				if($searchAll)
				{
					//append facility id limit
					$where .= " AND (facireq.ownerId = " . $this->_ownerWarehouse->getId() . " OR (facireq.facilityId =  " . $defaultWarehouseFacility->getId() . " AND (ISNULL(facireq.ownerId) OR facireq.ownerId = " . $this->_ownerWarehouse->getId() . "))) ";
				}

			}
			$where .= $this->getStatusWhere($attendedStatus);

			if($searchPartTypeId !== '')
				$where .= ' AND facireq.partTypeId = '. $searchPartTypeId;

			if($searchAttendedById !== '')
				$where .= ' AND facireq.updatedById = '. $searchAttendedById;

			if($sentToWarehouse instanceof Warehouse)
			{
				$additionalJoins .= " inner join lu_worktype_zoneset xwz on (xwz.active = 1 and xwz.workTypeId = ft.workTypeId and xwz.zoneSetId = z.zoneSetId) inner join lu_partdelivery xup on (xup.active = 1 and xup.lu_WorkType_ZonesetId = xwz.id and xup.remoteWarehouseId = " . $sentToWarehouse->getId() . ")";
			}

			if($hasReserve !== self::OPTION_FOR_ALL)
			{
				if($hasReserve === 'YES')
				{
					$where .= " AND (select sum(pi.quantity) from partinstance pi where pi.active = 1 and pi.facilityRequestId = facireq.id) > 0 ";
				}
				else
				{
					$where .= " AND isnull((select sum(pi.quantity) from partinstance pi where pi.active = 1 and pi.facilityRequestId = facireq.id)) ";
				}
			}

			if(count($workTypeIds) > 0)
				$where .= " AND ft.workTypeId in (" . implode(",", $workTypeIds). ")";
			if(count($zoneSetIds) > 0)
				$where .= " AND z.zoneSetId in (" . implode(",", $zoneSetIds). ")";
		}

		if($sinceSearchDate)
		{
			$hydraDate = new HydraDate($this->timeOfSearch->Value);
			if($hydraDate instanceOf HydraDate)
			{
				$where .= " AND facireq.updated > '" . $hydraDate->__toString() . "'";
			}
		}

		$columns = "
					facireq.id as id,
					facireq.quantity as qty,
					facireq.status as facireqStatus,
					ft.id as fieldTaskId,
					ft.status as taskStatus,
					ft.ETA,ft.serialNumber,
					ft.position as ftPosition,
					ft.isTravellingTo,
					ft.created as ftCreated,
					ncttm.targetEvent as ncttm,
					if(ft.isBillable=1,'YES','NO') as billable,
					ft.siteId as siteId,
					ft.workTypeId as workTypeId,
					z.zoneSetId as zoneSetId,
					facireq.status as status,
					group_concat(pta.alias) as partCode, ";

		$columns .= " '' as availQty , ";
		$columns .= " '' as compatibleAvailQty , ";
		$columns .= "(select sum(pi.quantity) from partinstance pi where pi.active = 1 and pi.facilityRequestId = facireq.id) as reserved, ";
		$columns .= "(
						select
							CONCAT(fttg.targetEndTime, ' ', fttg.targetFor)
						from fieldtasktarget fttg
						where
							fttg.active = 1 and
							fttg.fieldtaskId = facireq.fieldtaskId
						order by
							fttg.targetEndTime desc limit 1
					) as slaEnd, ";


		$columns .= "'P99' as frPriority,a.timezone as siteTimezone,facireq.ownerId as ownerId, pt.id as partTypeId, pt.name as partName, ";
		$columns .= "CONCAT(p.firstName,' ', p.lastName) as updatedFullName, facireq.updated as updated, IFNULL(w.name, '') as owner, ";
		$columns .= "IFNULL(w.position,'') as ownerPos, zs.name as zoneSetName, CONCAT(wt.typeName, ' - ', c.contractName) as workTypeLongName, ";
		$columns .= "CONCAT(s.siteCode, ' - ', s.commonName) as siteLongName,pr.problem,pc.category as problemCategory ";

		if($isExcel)
		{
			$columns .= ",st.name as stateName ";
		}

		$columns .= ", '' as FrCount";

		$additionalJoins .= " LEFT join fieldtasktarget ncttm on (ft.nearestClientTargetToMeetId = ncttm.id and (ncttm.active = 1)) ";
		$additionalJoins .= " inner join parttype pt ON facireq.partTypeId = pt.id ";
		$additionalJoins .= " inner join useraccount ua ON ua.id = facireq.updatedById ";
		$additionalJoins .= " inner join person p ON p.id = ua.personid  ";
		$additionalJoins .= " left join warehouse w ON w.id = facireq.ownerId ";
		$additionalJoins .= " inner join site s ON s.id = ft.siteId ";
// 		$additionalJoins .= " inner join address a ON a.id = s.addressId ";
		$additionalJoins .= " inner join worktype wt ON wt.id = ft.workTypeId ";
		$additionalJoins .= " inner join contract c ON c.id = wt.contractId ";
		$additionalJoins .= " left join parttypealias pta on (pta.partTypeId = pt.id and pta.active = 1 and pta.partTypeId = pt.id and pta.partTypeAliasTypeId = " . PartTypeAliasType::ID_PARTCODE . ") ";
		$additionalJoins .= " left join problem pr on (ft.problemId = pr.id)";
        $additionalJoins .= " left join problemcategory pc on (pr.problemCategoryId = pc.id)";


		if(count($partInstanceIdsArray) > 0)
		{
			$additionalJoins .= " inner join partinstance pi ON pi.facilityRequestId = facireq.id ";
		}

		$where .= " AND concat(pta.alias) != 999 ";
        $groupBy = ' group by facireq.id ';
        if($orderField=='status' || $orderField=='facireqStatus')
        {
	       $orderBy = ' order by ' . $orderField . ' ' . $orderDirection . ', updatedFullName ' . $orderDirection . ',  id desc ';
        }
        else
        {
	       $orderBy = ' order by ' . $orderField . ' ' . $orderDirection . ', id asc ';
        }

        $sql = "SELECT count(facireq.id) FROM facilityrequest facireq " . $additionalJoins . " where " . $where ;
		$resultCount = Dao::getResultsNative($sql);
		if($resultCount)
		{
			$totalRows = $resultCount[0][0];
		}

       	$limit = '';
       	if(!$isExcel)
       	{
			$limitValue = $this->_getLimit();
			$startRecord = ($pageNo - 1) * $limitValue;
			$limit = ' limit ' . max($startRecord, 0) . ', ' . $limitValue;
       	}

		$sql = "SELECT " . $columns . " FROM facilityrequest facireq " . $additionalJoins . " where " . $where . $groupBy . $orderBy . $limit;
		$result = Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC);
		if($isExcel)
		{
			$totalRows = count($result);
		}
		return $this->_getTrunkData($result);
	}

	/**
     * Get pop div
     *
     * @param unknown_type $row
     * @param unknown_type $linkText
     * @return unknown
     */
    private function getPopUpMessage($row)
    {
        $fieldtaskId = $row['fieldTaskId'];
        $message = "Task # : $fieldtaskId{NEWLINE_BR}";
        $message .= "Creation Time : {$row['ftCreated']}{NEWLINE_BR}";
        $message .= "Nearest Target To Meet : " . (strlen($row['ncttm']) == 0 ? "Not Available" : $row['ncttm']) . "{NEWLINE_BR}";
        $message .= "ETA : " . ($row['ETA'] == HydraDate::zeroDateTime() ? "Not Available" : $row['ETA']) . "{NEWLINE_BR}";
        $message .= "Equipment : {$row['partName']}{NEWLINE_BR}";
        $message .= "Serial No : {$row['serialNumber']}{NEWLINE_BR}";
        $message .= "Problem Category : {$row['problemCategory']}{NEWLINE_BR}";
        $message .= "Problem : {$row['problem']}{NEWLINE_BR}";
        $message .= "Position : " . ($row['ftPosition'] == '' ? '?' : $row['ftPosition']) . "{NEWLINE_BR}";

        if ($row['isTravellingTo'] == 1) {
            $sql = "select startTime from fieldtasktravel where fieldTaskId = $fieldtaskId and stopTime = '0001-01-01 00:00:00'";
            $startTime = Dao::getResultsNative($sql);

            if (sizeof($startTime) > 0) {
                if ($startTime[0][0] > "")
                    $message .= "Travel Start time : " . $startTime[0][0] . "";
                else
                    $message .= "Not Available";
            }
            else
                $message .= "Not Available";
        }

        else {
            $message .= "Travel Start time : None";
        }

        $message = str_replace('"', "", $message);
		$message = str_replace("'", "", $message);
        $message = StringUtils::cleanString(htmlentities($message));
		$message = str_replace("{NEWLINE_BR}", "<br />", $message);
        return $message;
    }

	/**
	 * Get Elapsed Time
	 *
	 * @param unknown_type $secs
	 * @return unknown
	 */
	private function _getElapsedTime($secs)
	{
		$vals = array(
	            'w' => (int) ($secs / 86400 / 7),
	            'd' => $secs / 86400 % 7,
	            'h' => $secs / 3600 % 24,
	            'm' => $secs / 60 % 60);

		$ret = array();

		$added = false;
		foreach ($vals as $k => $v) {
			if ($v > 0 || $added) {
				$added = true;
				$ret[] = $v . $k;
			}
		}
		return  join('', $ret);
	}

	/**
	 * Reformat SLA End
	 *
	 * @param unknown_type $slaEnd
	 * @param HydraDate $now
	 * @return unknown
	 */
	private function reformatSLAEnd($slaEnd,HydraDate $now)
	{
		$slaType = "";
		$replaceClient = " CLIENT";
		$replaceByt = " BYT";
		if(strpos($slaEnd,$replaceClient))
		{
			$slaType = $replaceClient;
			$slaEnd = trim(str_replace($replaceClient,"",$slaEnd));
		}
		else if(strpos($slaEnd,$replaceByt))
		{
			$slaType = $replaceByt;
			$slaEnd = trim(str_replace($replaceByt,"",$slaEnd));
		}

		$hydraDate = new HydraDate($slaEnd);
		$diff = $now->getSecondsDifferenceBetweenTwoHydraDates($hydraDate);

		if($diff > 0)
		{
			$slaEnd .= 	$slaType . " (BREACHED)";
		}
		else
		{
			$slaEnd .= 	$slaType;
		}
		return $slaEnd;
	}

	/**
	 * translating some of the facility request into more information.
	 *
	 * @param array $results The sql result
	 *
	 * @return string
	 */
	private function _getTrunkData(array $results)
	{
		$now = new HydraDate();
		$whPosition = $this->_stockWarehouse->getPosition();
		$ignoreWhIdSql = '';

		if (!empty($this->_ignoreStockCountWhIds))
			$ignoreWhIdSql = " AND w.id NOT IN (" . implode(',', $this->_ignoreStockCountWhIds) . ")";

		$return = array();
		foreach ($results as $row)
		{
			$pt=Factory::service('PartType')->get($row['partTypeId']);
			$availQty = Factory::service('FacilityRequest')->getCountsWithinWarehouseForFR(array($row['partTypeId']),$this->_stockWarehouse);
			$compatibleAvailQty = Factory::service('FacilityRequest')->getCountsForCompatiableWithWHForFR($pt,$this->_stockWarehouse);

			$row['availQty'] = $availQty[0]." : ".$availQty[1];
			$row['compatibleAvailQty'] = $compatibleAvailQty[0]." : ".$compatibleAvailQty[1];

			//frCount
			$sql_FrCount = "select
								count(facireq_count.id)
							from
								facilityrequest facireq_count
							where
								facireq_count.fieldtaskid = ".$row['fieldTaskId']."  and
						        facireq_count.status in('".implode("','",FacilityRequestLogic::getOpenStatuses())."') and
						        facireq_count.active=1";
			$result_FrCount = Dao::getResultsNative($sql_FrCount);
			$row['FrCount'] = ($result_FrCount[0][0]>0 ? $result_FrCount[0][0] : 0);

			$row['frPriority'] = $this->_getFRPriorityDetails($row['slaEnd'],$row['siteTimezone']);//FR Priority
			$row['slaEnd'] = $this->reformatSLAEnd($row['slaEnd'], $now);

			$row['cancelable'] = false; //whether that facility request can be cancelled
			$row['reopenable'] = false; //whether that facility request can be reopened from cancelled
			if (($request = Factory::service("FacilityRequest")->get($row["id"])) instanceof FacilityRequest)
			{
				$row['cancelable'] = in_array(FacilityRequest::STATUS_CANCEL, $request->getNextStatuses());
				$row['reopenable'] = ($request->getStatus() === FacilityRequest::STATUS_CANCEL);
			}
			$row['site'] = ($row['siteLongName'] ? $row['siteLongName'] : "");
			$row['worktype'] = ($row['workTypeLongName'] ? $row['workTypeLongName'] : "");
			$row['zoneset'] = ($row['zoneSetName'] ? $row['zoneSetName'] : "");

			$row['updatedElapsedTime'] = $this->_getElapsedTime($now->getSecondsDifferenceBetweenTwoHydraDates(new HydraDate($row['updated'])));

			$row['popupMessage'] = $this->getPopUpMessage($row);

			$row['ptHotMessage'] = '';
			$hotmessagePT = Factory::service("PartType")->searchPartTypeAliaseByPartTypeAliasTypeForPartType($row['partTypeId'], PartTypeAliasType::ID_HOT_MESSAGE);
			if(count($hotmessagePT)>0)
			{
				$row['ptHotMessage'] = PartTypeLogic::showHotMessageDetail($hotmessagePT[0]->getAlias(), $row['id']);
			}

			$return[] = $row;
		}
		return $return;
	}

	/**
	 * Get Tech Agents Position
	 *
	 * @param unknown_type $defaultWarehouseId
	 * @return unknown
	 */
	private function _getTechAgentsPositionWhere($defaultWarehouseId)
	{
		$sql = "SELECT
					w.position
				FROM
					userpreference up
					INNER JOIN warehouse w on w.id = up.value
				WHERE
					up.userAccountId in (SELECT userAccountId FROM userpreference where lu_UserPreferenceId = 55 and value = " . $this->_ownerWarehouse->getId() . " and active = 1)
					and up.lu_UserPreferenceId = 70
					and up.active = 1
					and (w.warehouseCategoryId = " . WarehouseCategory::ID_AGENT . " or w.warehouseCategoryId = " . WarehouseCategory::ID_TECH. ")";

		$result = Dao::getResultsNative($sql);
		$positions = "";
		foreach($result as $rows)
		{
			$positions .= " OR wa.position like '" . $rows[0] . "%'";
		}
		return $positions;
		}

	/**
	 * Get Available Parts
	 *
	 * @param unknown_type $partTypeId
	 * @param unknown_type $onlyUnderOwnerWarehouse
	 * @param unknown_type $goodParts
	 * @return unknown
	 */
	private function _getAvailableParts($partTypeId, $onlyUnderOwnerWarehouse = true, $goodParts = 1, $compatibleParts=false)
	{
		$this->_ownerWarehouse = $this->getOwnerWarehouse();
		$this->_stockWarehouse = FacilityRequestLogic::getWarehouseForPartCollection($this->_ownerWarehouse, Factory::service('Warehouse')->getDefaultWarehouse(Core::getUser()));

		$pt=Factory::service('PartType')->get($partTypeId);
		$compatiblePartTypeArray=Factory::service('Lu_PartCompatibility')->getCompatiblePartTypes($pt);

		$goodParts=($goodParts==1?true:false);
		if($compatibleParts && count($compatiblePartTypeArray)<=0)// check for compatible pt
			$result=array();
		else if($compatibleParts)
			$result = Factory::service('FacilityRequest')->getWHListForCompatiablePart($pt, $this->_ownerWarehouse, $this->_stockWarehouse, $onlyUnderOwnerWarehouse, $goodParts);//compatible parts
		else
			$result = Factory::service('FacilityRequest')->getWHListForParts(array($partTypeId), $this->_ownerWarehouse, $this->_stockWarehouse, $onlyUnderOwnerWarehouse, $goodParts);

		return $result;
	}

	/**
	 * Get availablity / compatible availability  for that part
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 * @throws Exception
	 */
	public function getAvailParts($sender, $param)
	{
		$html = "No parts found!";
		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || (($selectedId = $param->CallbackParameter->selectedIds) == '') )
				throw new Exception("Please select a request first!");

			$request = Factory::service("FacilityRequest")->get($selectedId);
			$partType = $request->getPartType();
			$hotMessage = $partType->getAlias(PartTypeAliasType::ID_HOT_MESSAGE);

			if(!$request instanceof FacilityRequest)
				throw new Exception("Invalid facility request id provided(={$selectedId})!");

			$goodParts = 1;
			if(isset($param->CallbackParameter->goodParts))
			{
				$goodParts = $param->CallbackParameter->goodParts;
			}
			$compatibleParts = false;
			if(isset($param->CallbackParameter->compatibleParts))
			{
				$compatibleParts = $param->CallbackParameter->compatibleParts;
			}

			$result = $this->_getAvailableParts($partType->getId(), true, $goodParts, $compatibleParts);
			$html = "";

			$html .= "<table class='ResultDataList'>";

			if($hotMessage)
			{
				$html .= "<tr><td>".  PartTypeLogic::showHotMessageDetail($hotMessage, '', false, false) . "</td></tr>";
			}

			$html .= "<tr>";
			if($compatibleParts)
				$html .= "<td><a href='javascript: void(0);' onClick='pageJs.viewAvailListOtherStores(\"" .  $selectedId  . "\", \"" .  $this->showExtraAvailListBtn->getUniqueID() . "\",\"" .  $goodParts  . "\",true)'>View Compatible Parts from other Locations</a></td>";
			else
				$html .= "<td><a href='javascript: void(0);' onClick='pageJs.viewAvailListOtherStores(\"" .  $selectedId  . "\", \"" .  $this->showExtraAvailListBtn->getUniqueID() . "\",\"" .  $goodParts  . "\",false)'>View Parts from other Locations</a></td>";

			$html .= "<td style='float:right'><input type='image'  src='/themes/images/mail.gif' onclick='return pageJs.email(\"" . $selectedId . "\", \"" . $this->showEmailPanelBtn->getUniqueID() . "\", this);' title='Send an email'/></td>";

			$html .= "</tr>";
			$html .= "<table>";

			if($compatibleParts)
				$html .= PartTypeLogic::showCompatiblePartTypeDetail($partType);
			else
				$html .= "<div><br><b>Part Details : </b>".$partType->getAlias()." - ".$partType->getName()."</div><br>";

			if(count($result)=== 0)
			{
				$html .= "<br><b style='color:red'>No parts found!</b>";
				$this->responseLabel->Text = $html;
				return;
			}

			$html .= "<table class='ResultDataList'>";
			$html .= "<thead>";
			$html .= "<tr>";
			$html .= "<th>Warehouse</th>";
			if($compatibleParts){$html .= "<th>Partcode</th>";}
			$html .= "<th>Status</th>";
			$html .= "<th>Qty</th>";
			$html .= "<th>Reserved For</th>";
			$html .= "</tr>";
			$html .= "</thead>";
			$html .= "<tbody>";
			$rowNo =0;
			$total = 0;
			foreach($result as $r)
			{
				if(!($warehouse = Factory::service("Warehouse")->get($r['warehouseId'])) instanceof Warehouse)
				continue;

				$html .= "<tr class='" . ($rowNo++ % 2 === 0 ? "ResultDataListItem" : "ResultDataListAlterItem") . "' >";
				$html .= "<td>" . $warehouse->getBreadCrumbs() . "</td>";
				if($compatibleParts)
				{
					$partcode = Factory::service('PartType')->get($r['partTypeId'])->getAlias();
					$html .= "<td>".$partcode."</td>";
				}
				$html .= "<td>{$r['status']}</td>";
				$html .= "<td>{$r['qty']}</td>";
				$html .= "<td>{$r['fieldTaskId']}</td>";
				$html .= "</tr>";
				$total +=$r['qty'];
			}
			$html .= "</tbody>";
			$html .= "<tfoot>";
			$html .= "<tr>";
			$html .= "<td>Total</td>";
			$html .= "<td>&nbsp;</td>";
			if($compatibleParts){$html .= "<td>&nbsp;</td>";}
			$html .= "<td>$total</td>";
			$html .= "<td>&nbsp;</td>";
			$html .= "</tr>";
			$html .= "</tfoot>";
			$html .= "</table>";
		}
		catch(Exception $ex)
		{
			$html = $this->_getErrorMsg($ex->getMessage());
		}
		$this->responseLabel->Text = $html;
	}

	/**
	 * Get availablity parts in other stores
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 * @throws Exception
	 */
	public function getExtraAvailParts($sender, $param)
	{
		$html = "No parts found!";
		try{
			if(!isset($param->CallbackParameter->selectedIds) || (($selectedId = $param->CallbackParameter->selectedIds) == '') )
				throw new Exception("Please select a request first!");

			$request = Factory::service("FacilityRequest")->get($selectedId);
			if(!$request instanceof FacilityRequest)
				throw new Exception("Invalid facility request id provided(={$selectedId})!");


			$partType = $request->getPartType();
			$hotMessage = $partType->getAlias(PartTypeAliasType::ID_HOT_MESSAGE);

			$goodParts = 1;
			if(isset($param->CallbackParameter->goodParts))
			{
				$goodParts = $param->CallbackParameter->goodParts;
			}

			$compatibleParts = false;
			if(isset($param->CallbackParameter->compatibleParts))
			{
				$compatibleParts = $param->CallbackParameter->compatibleParts;
			}
			$result = $this->_getAvailableParts($request->getPartType()->getId(), false, $goodParts, $compatibleParts);

			$html = "<table class='ResultDataList'>";
			if($hotMessage)
			{
				$html .= "<tr><td>".  PartTypeLogic::showHotMessageDetail($hotMessage, '', false, false) . "</td></tr>";
			}

			$html .= "<tr>";
			if($compatibleParts)
				$html .= "<td><a href='javascript: void(0);' onClick='pageJs.viewAvailList(\"" .  $selectedId  . "\", \"" .  $this->showAvailListBtn->getUniqueID() . "\", \"" .  $goodParts  . "\", true)'>View Compatible Parts from " . $this->_ownerWarehouse->getName() . "</a></td>";
			else
				$html .= "<td><a href='javascript: void(0);' onClick='pageJs.viewAvailList(\"" .  $selectedId  . "\", \"" .  $this->showAvailListBtn->getUniqueID() . "\", \"" .  $goodParts  . "\", false)'>View Parts from " . $this->_ownerWarehouse->getName() . "</a></td>";

			$html .= "<td style='float:right'><input type='image'  src='/themes/images/mail.gif' onclick='return pageJs.email(\"" . $selectedId . "\", \"" . $this->showEmailPanelBtn->getUniqueID() . "\", this);' title='Send an email'/></td>";
			$html .= "</tr>";
			$html .= "<table>";

			if($compatibleParts)
				$html .= PartTypeLogic::showCompatiblePartTypeDetail($partType);
			else
				$html .= "<div><br><b>Part Details : </b>".$partType->getAlias()." - ".$partType->getName()."</div><br>";

			if(count($result)=== 0)
			{
				$html .= "<br><b style='color:red'>No parts found!</b>";
				$this->responseLabel->Text = $html;
				return;
			}

			$html .= "<table class='ResultDataList'>";
			$html .= "<thead>";
			$html .= "<tr>";
			$html .= "<th>Warehouse</th>";
			if($compatibleParts){$html .= "<th>PartCode</th>";}
			$html .= "<th>Status</th>";
			$html .= "<th>Qty</th>";
			$html .= "<th>Reserved For</th>";
			$html .= "</tr>";
			$html .= "</thead>";
			$html .= "<tbody>";
			$rowNo =0;
			$total = 0;
			$previousState = "";
			foreach($result as $r)
			{
				if(!($warehouse = Factory::service("Warehouse")->get($r['warehouseId'])) instanceof Warehouse)
					continue;

				if($previousState=="" || $previousState!= $r['stateName'])
				{
					$colspan=($compatibleParts?'5':'4');
					$html .= "<tr><td colspan=".$colspan." style='color:white;background:black'><b>" . $r['stateName'] . "</b></td></tr>";
				}

				$warehouseName = $warehouse;
				$index = strpos ($warehouse, "|");
				if($index > 0)
				{
					$warehouseName = substr($warehouse , 0, $index);
				}

				$html .= "<tr class='" . ($rowNo++ % 2 === 0 ? "ResultDataListItem" : "ResultDataListAlterItem") . "' >";
				$html .= "<td>" . $warehouseName . "</td>";
				if($compatibleParts)
				{
					$partcode = Factory::service('PartType')->get($r['partTypeId'])->getAlias();
					$html .= "<td>".$partcode."</td>";
				}

				$html .= "<td>{$r['status']}</td>";
				$html .= "<td>{$r['qty']}</td>";
				$html .= "<td>{$r['fieldTaskId']}</td>";
				$html .= "</tr>";
				$total +=$r['qty'];
				$previousState = $r['stateName'];
			}
			$html .= "</tbody>";
			$html .= "<tfoot>";
			$html .= "<tr>";
			$html .= "<td>Total</td>";
			$html .= "<td>&nbsp;</td>";
			if($compatibleParts){$html .= "<td>&nbsp;</td>";}
			$html .= "<td>$total</td>";
			$html .= "<td>&nbsp;</td>";
			$html .= "</tr>";
			$html .= "</tfoot>";
			$html .= "</table>";
		}
		catch(Exception $ex)
		{
			$html = $this->_getErrorMsg($ex->getMessage());
		}

		$this->responseLabel->Text = $html;
	}

	/**
	 * Get the reserved Parts (SerialNos)  for the facilityRequest.
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 * @throws Exception
	 */
	public function getRsrvdParts($sender, $param)
	{
		$html = "No parts found!";
		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");

			$requestId = $selectedIds[0];
			$request = Factory::service("FacilityRequest")->get($requestId);
			if(!$request instanceof FacilityRequest)
				throw new Exception("Invalid facility request id provided(=$requestId)!");

			//find all reserved part instances
			$pis = Factory::service("PartInstance")->findByCriteria("pi.facilityRequestId = $requestId");

			$html = "<table class='ResultDataList'>";
			$html .= "<thead>";
			$html .= "<tr>";
			$html .= "<th width='20%'>Barcode</th>";
			$html .= "<th>Comments</th>";
			$html .= "<th width='8%'>Qty</th>";
			$html .= "<th width='5%'>&nbsp</th>";
			$html .= "</tr>";
			$html .= "</thead>";
			$html .= "<tbody>";
			$html .= "<tr class='ResultDataListAlterItem' resrpartpan='reservedTr'>";

			if(!in_array($request->getStatus(), FacilityRequest::getStatusesAllowResvPI()))
				$html .= "<td colspan=4  style='color: red; font-weight: bold;'>The status(`" . $request->getStatus() . "`) of the selected request does not allow reservation of parts!</td>";
			else if(!Factory::service("FacilityRequest")->checkOwnership($request, $this->getOwnerWarehouse()))
				$html .= "<td colspan=4  style='color: red; font-weight: bold;'>You don't have access to this request.You need to own the request to proceed.</td>";
			else
			{
				$html .= "<td><input PlaceHolder='Barcode' resrpartpan='reservedSerialNoSearch' onkeydown=\"pageJs.enterEvent(event, $(this).up('tr[resrpartpan=reservedTr]').down('input[resrpartpan=reservedAddBtn]'));\"/>
				</br>
				</br>BL:
				<input id='BLNo' PlaceHolder='Barcode:BL' resrpartpan='reservedBLNoSearch' onkeydown=\"pageJs.enterEvent(event, $(this).up('tr[resrpartpan=reservedTr]').down('input[resrpartpan=reservedAddBtn]'));\"/>
				</td>";
				$html .= "<td>
				</br>
				(Limit text to 120 Characters)<input PlaceHolder='Comments' resrpartpan='reservedComments' style='width: 98%;'/>
				</br>
				</td>";
				$html .= "<td><input id='reservedQty' resrpartpan='reservedQty' value='1' style='width: 30px;'/></th>";
				$html .= "<td>";
				$html .= "<input resrpartpan='reservedAddBtn' type='button' value='Add' Onclick=\"pageJs.reservePI('$requestId', '" . $this->rsvPartBtn->getUniqueID() . "', '" . $this->unRsvPartBtn->getUniqueID() . "', this,'" . $this->checkPartBtn->getUniqueID() .
				"','" . $this->checkPartType->getClientId() . "','" . $this->checkPartErrors->getClientId() . "','" . $this->errorBL->getClientId() . "');\"/>";
				$html .= "<input resrpartpan='bpregex' type='hidden' value='" . BarcodeService::getBarcodeRegex(BarcodeService::BARCODE_REGEX_CHK_PART_TYPE, null, '^', '$') . "' />";
				$html .= "<input resrpartpan='partregex' type='hidden' value='" . BarcodeService::getBarcodeRegex(BarcodeService::BARCODE_REGEX_CHK_PART, null, '^', '$') . "' />";
				$html .= "</td>";
			}
			$html .= "</tr>";
			$rowNo =0;
			$total = 0;
			foreach($pis as $pi)
			{
				$piQty = ($pi->getPartType()->getSerialised()==1)? 1:$pi->getQuantity();
				$barcode = ($pi->getPartType()->getSerialised()==1)? $pi->getAlias(PartInstanceAliasType::ID_SERIAL_NO):$pi->getPartType()->getAlias(PartTypeAliasType::ID_BP);
				$html .= "<tr rsvdPIId=\"" . $pi->getId() . "\" class='" . ($rowNo++ % 2 === 0 ? "ResultDataListItem" : "ResultDataListAlterItem") . "' >";
				$html .= "<td>" . $barcode . "</td>";
				$html .= "<td>" . $pi->getWarehouse()->getBreadCrumbs() . "</td>";
				$html .= "<td>".intval($piQty)."</td>";
				$html .= "<td>";
				$html .= "<input type='image' src='/themes/images/delete_mini.gif' onClick=\"pageJs.unresrvPI('$requestId', '" . $this->unRsvPartBtn->getUniqueID() . "', this);\"/>";
				$html .= "</td>";
				$html .= "</tr>";
				$total += intval($piQty);
			}
			$html .= "</tbody>";
			$html .= "<tfoot>";
			$html .= "<tr>";
			$html .= "<td>Total</td>";
			$html .= "<td>&nbsp;</td>";
			$html .= "<td><span resrpartpan='reservedTotalQty'>$total</span></td>";
			$html .= "<td>&nbsp;</td>";
			$html .= "</tr>";
			$html .= "</tfoot>";
			$html .= "</table>";

 			if($this->checkFieldTaskHasAllReservationsFulfilled($request->getFieldTask()))
 			{
 				$html .= "<select id='titleActionListDropDown'>";
 				$html .= "<option value='pushToAvail' class='fieldtaskoptons'>Push Field Task Avail</option>";
 				$html .= "</select>";
 				$html .= "<input id='titleActionListBtn' value='Go' onclick=\"return pageJs.pushFTWrapper($('titleActionListDropDown').value, '" . $this->changeFTStatusBtn->getUniqueID() . "'," . $requestId . "); return false;\" type='button'>";
 			}
		}
		catch(Exception $ex)
		{
			$html = $this->_getErrorMsg($ex->getMessage());
		}
		$this->responseLabel->Text = $html;
	}

	/**
	 * Check FieldTask Has All Reservations Fulfilled
	 *
	 * @param unknown_type $fieldTask
	 * @return unknown
	 */
	private function checkFieldTaskHasAllReservationsFulfilled($fieldTask)
	{
		$facilitys = Factory::service("FacilityRequest")->findByCriteria("fieldTaskId=?  and status != ? and status != ? and status != ? ",array($fieldTask->getId(),FacilityRequest::STATUS_CLOSED,FacilityRequest::STATUS_CANCEL,FacilityRequest::STATUS_COMPLETE));
		$foundOpen = false;
		foreach($facilitys as $facility)
		{
			$foundOpen = true;
			$sql = "select id from partinstance where active = 1 and facilityRequestId = " . $facility->getId();
			$result = Dao::getResultsNative($sql);
			if(!$result || count($result)==0)
			{
				return false;
			}
		}

		if($foundOpen)
		{
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Get Mismatch Message
	 *
	 * @param unknown_type $pi
	 * @param unknown_type $request
	 * @return unknown
	 */
	private function getMismatchMessage($pi,$request)
	{
		$partCode = Factory::service("PartType")->getPartTypeAliasByTypeForPartType($pi->getPartType()->getId(),1);
		if(count($partCode)>0)
		{
			$partCode = $partCode[0];
		}
		else
		{
			$partCode = "";
		}

		$requestPartCode = Factory::service("PartType")->getPartTypeAliasByTypeForPartType($request->getPartType()->getId(),1);
		if(count($requestPartCode)>0)
		{
			$requestPartCode = $requestPartCode[0];
		}
		else
		{
			$requestPartCode = "";
		}
		return  "Mismatched Part: Part requested: PC:" . $partCode . " – Name:" .  $pi->getPartType()->getName() . " NOT PC Scanned:" . $requestPartCode . " – Name:" .  $request->getPartType()->getName();
	}

	/**
	 * Check Part
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function checkPart($sender, $param)
	{
		$result = array();
		$error = "";
		$partTypeMessage = "";
		$partInstanceId = "";

		$this->checkPartType->Value = "";
		$this->checkPartErrors->Value = "";
		$this->partInstanceId->Value = "";
		$this->errorBL->Value = "";

		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");

			$request = Factory::service("FacilityRequest")->get($selectedIds[0]);

			if(!$request instanceof FacilityRequest)
				throw new Exception("Invalid facility request id provided(={$selectedIds[0]})!");

			$result['requestId'] = $selectedIds[0];

			if(count($resrvInfo = ($param->CallbackParameter->resrvInfo)) === 0)
				throw new Exception("Please no resrvd part info passed in!");

			$result['barcode'] = isset($resrvInfo->barcode) ? trim($resrvInfo->barcode) : '';
			$result['BL'] = isset($resrvInfo->BL) ? trim($resrvInfo->BL) : '';
			$result['qty'] = isset($resrvInfo->qty) ? trim($resrvInfo->qty) : '';

			if($result['barcode'] === '' )
				throw new Exception("Please Provide Barcode!");

			if(!is_numeric($result['qty']))
				throw new Exception("Invalid qty(={$result['qty']}) provided!");

			$errorBL = "";
			$pi = $this->getPartInstanceFromBarcode($result['barcode'], $result['BL'], $errorBL);

			if(!$errorBL)
			{
				if(!$pi instanceof PartInstance)
					throw new Exception("No part instance found for '{$result['barcode']}'!");

				$partInstanceId = $pi->getId();

				if($pi->getPartType()->getId() != $request->getPartType()->getId())
				{
					$partTypeMessage = $this->getMismatchMessage($pi, $request);
					$partTypeMessage .= "\nAre you sure you wish to proceed?";
				}
			}

		}
		catch(Exception $ex)
		{
			$error = $ex->getMessage();
		}

		$this->checkPartType->Value = $partTypeMessage;
		$this->checkPartErrors->Value = $error;
		$this->partInstanceId->Value = $partInstanceId;
		$this->errorBL->Value = $errorBL;
	}

	/**
	 * gets a part instance from a barcode
	 *
	 * @param String $barcode
	 * @param String $bl
	 * @param String $errorBL //whether to move focus to BL
	 * @param PartInstance
	 */
	private function getPartInstanceFromBarcode($barcode, $bl, &$errorBL = "")
	{
		$pi = null;
		$isNonSerialised = false;
		$isSerialised = false;

		try {
			$isNonSerialised = BarcodeService::validateBarcode($barcode,BarcodeService::BARCODE_REGEX_CHK_PART_TYPE);
		}
		catch(Exception $e)
		{}

		try {
			$isSerialised = BarcodeService::validateBarcode($barcode,BarcodeService::BARCODE_REGEX_CHK_PART_INSTANCE);
		}
		catch(Exception $e)
		{}

		//if BP
		if($isNonSerialised)
		{
			//check for BL
			if($bl==='')
				$errorBL = "Invalid BL.Please Check the BL and try again.";

			if(!$errorBL)
			{
				if($bl>"")
				{
					$warehouse = Factory::service('Warehouse')->getWarehouseByLocationBarcode($bl);
					if(!$warehouse instanceof Warehouse)
						$errorBL = "Invalid BL.Please Check the BL and try again.";
				}

				//get PartInstance based on BL
				$pis = Factory::service("PartInstance")->searchPartInstanceByBarcodeAndPartcode($barcode, $barcode, null, 30, $warehouse, false, true);
				foreach ($pis as $pi)
				{
					//only un-reserved parts
					if (!$pi->getFacilityRequest() instanceof FacilityRequest)
						return $pi;
				}
			}
		}

		if($isSerialised)
		{
			$pi = Factory::service("PartInstance")->searchPartInstanceByBarcodeAndPartcode($barcode, $barcode, null, 30, null, false, true);
			if($pi && count($pi)>0)
			{
				return $pi[0];
			}
		}
		return null;
	}



	/**
	 * reserving a new part instance for a request
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 */
	public function rsvPart($sender, $param)
	{
		$result = array();
		$errors = array();
		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");

			$request = Factory::service("FacilityRequest")->get($selectedIds[0]);

			if(!$request instanceof FacilityRequest)
				throw new Exception("Invalid facility request id provided(={$selectedIds[0]})!");

			$result['requestId'] = $selectedIds[0];

			if(count($resrvInfo = ($param->CallbackParameter->resrvInfo)) === 0)
				throw new Exception("Please no resrvd part info passed in!");

			$result['barcode'] = isset($resrvInfo->barcode) ? trim($resrvInfo->barcode) : '';
			$result['BL'] = isset($resrvInfo->BL) ? trim($resrvInfo->BL) : '';
			$result['qty'] = isset($resrvInfo->qty) ? trim($resrvInfo->qty) : '';

			if($result['barcode'] === '' )
				throw new Exception("Barcode needed!");

			if(!is_numeric($result['qty']))
				throw new Exception("Invalid qty(={$result['qty']}) provided!");


			$pi = Factory::service("PartInstance")->get($this->partInstanceId->Value);

			if(!$pi instanceof PartInstance)
				throw new Exception("No part instance found for '{$result['barcode']}'!!");

			$comments = "";
			if(isset($resrvInfo->comments) && $resrvInfo->comments)
			{
				$comments = $resrvInfo->comments;
			}

			$result['piId'] = $pi->getId();
			$result['warehouse'] = $pi->getWarehouse()->getBreadCrumbs();
			$this->_ownerWarehouse = $this->getOwnerWarehouse();

			if ($this->_reportServerRedirectEnabled)
				Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

			Factory::service("FacilityRequest")->reservePI($request, $pi, $this->_ownerWarehouse, $comments,$result['qty']);
		}
		catch(Exception $ex)
		{
			$errors[] = $this->_getErrorMsg($ex->getMessage());
		}

		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON

		if (empty($errors))
			$result['requests'] = $this->_getData(false,null ,$totalRows, 'slaEnd', 'asc', $selectedIds);

		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));

	}

	/**
	 * unreserving a new part instance for a request
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed $param  The parameters that sent along with this event
	 */
	public function unRsvPart($sender, $param)
	{
		$result = $errors = array();
		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");
			$request = Factory::service("FacilityRequest")->get($selectedIds[0]);
			if(!$request instanceof FacilityRequest)
				throw new Exception("Invalid facility request id provided(={$selectedIds[0]})!");

			if(!isset($param->CallbackParameter->unResrvInfo))
				throw new Exception("No search unResrvInfo passed in!");
			$unResrvInfo =  $param->CallbackParameter->unResrvInfo;
			$result['piId'] = isset($unResrvInfo->piId) ? trim($unResrvInfo->piId) : '';
			$comments = isset($unResrvInfo->comment) ? trim($unResrvInfo->comment) : '';

			$pi = Factory::service("PartInstance")->get($result['piId']);
			if(!$pi instanceof PartInstance)
				throw new Exception("Invalid part instance id provided(={$result['piId']})!");

			$result['qty'] = 0 - $pi->getQuantity();
			$this->_ownerWarehouse = $this->getOwnerWarehouse();

			if ($this->_reportServerRedirectEnabled)
				Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

			Factory::service("FacilityRequest")->unreservePI($request, $pi,$this->_ownerWarehouse, $comments);
		}
		catch(Exception $ex)
		{
			$errors[] = $this->_getErrorMsg($ex->getMessage());
		}

		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON

		if (empty($errors))
			$result["requests"] = $this->_getData(false,null ,$totalRows, 'slaEnd', 'asc', $selectedIds);

		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

	/**
	 * get error message in HTML format
	 *
	 * @param String $msg The message we are trying to display
	 *
	 * @return String
	 */
	private function _getErrorMsg($msg)
	{
		return "<b style='color:red'>$msg</b>";
	}

	/**
	 * Printing the pick list for selected requests
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 */
	public function getReservationLabelInfo($sender, $param)
    {
    	try
    	{
	    	if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
	    			throw new Exception("Please select a request first!");

	    	$results = array();
	    	$this->_ownerWarehouse = $this->getOwnerWarehouse();

	   		$sql = "SELECT fr.id ,
						concat(w.name, '-', w.id, '-', sum(pi.quantity)) as `maxNoLocation`,
						concat(if(wa2.alias is not null,wa2.alias,replace(wa.alias,'|','/')),'/',w.name) as `Breadcrumbs`

					FROM facilityrequest fr
						left join partinstance pi on (pi.facilityrequestId = fr.id)
						left join warehouse w on pi.warehouseid  = w.id and w.active=1
						left join warehousealias wa on w.id = wa.warehouseid and wa.warehousealiastypeid=20
						left join warehousealias wa2 on w.id = wa2.warehouseid and wa2.warehousealiastypeid=21
						left join warehousealias wa3 on (w.id = wa3.warehouseid and wa3.warehousealiastypeid=1 )
					WHERE
						fr.id in (" . implode(", ", $selectedIds) . ")
						and (trim(wa3.alias) !='UnreconciledTransitNoteParts' or wa3.alias is null)
					GROUP BY
						w.id, fr.id";

    		$result =  Dao::getResultsNative($sql, array(), PDO::FETCH_ASSOC);

    		$deliveryInfo = FacilityRequestLogic::getShipToInformation($selectedIds);

    		foreach($result as $rowNo => $res)
	    	{
	    		$request = Factory::service("FacilityRequest")->get($res['id']);
	    		$this->autoTake($request, $this->_ownerWarehouse);
				if($request instanceof FacilityRequest)
				{
					$comments = $request->getCommentArray();
					if(count($comments)>0)
					{
	    				$results[$rowNo]['fr'] = $res['id'];
	    				$results[$rowNo]['comment'] = $comments[0];
					}
				}
	    		$results[$rowNo]['location'] = 'No available parts!';

	    		$shipTo = (array_key_exists($res['id'], $deliveryInfo) ? $deliveryInfo[$res['id']] : '');
	    		if (is_array($shipTo))
	    		{
	    			$shipTo = $deliveryInfo[$res['id']]['wh'] . $deliveryInfo[$res['id']]['address'];
	    		}
	    		$results[$rowNo]['shipTo'] = $shipTo;

	    		if(is_array($locationArray = explode("-", $res['maxNoLocation'])))
	    		{
	    			$warehouse = Factory::service("Warehouse")->getWarehouse(isset($locationArray[1]) ? trim($locationArray[1]) : '');
	    			$availQty = isset($locationArray[2]) ? trim($locationArray[2]) : '';
	    			if(!is_null($availQty)&&$availQty>0)
	    			{
	    				$results[$rowNo]['location'] = $res['Breadcrumbs'];
	    			}
	    			else
	    			{
	    				//get the location  from the picklist
	    				$this->_ownerWarehouse = $this->getOwnerWarehouse();
	    				$stockWarehouse = FacilityRequestLogic::getWarehouseForPartCollection($this->_ownerWarehouse, Factory::service('Warehouse')->getDefaultWarehouse(Core::getUser()));
	    				$resultPickList = Factory::service("FacilityRequest")->getPickList(array($res['id']), $stockWarehouse);
	    				if(count($resultPickList)>0 && sizeof($resultPickList)>"")
	    				{
	    					if(is_array($locationArray_pickList = explode("-", $resultPickList[0]['maxNoLocation'])))
	    					{
	    						$warehouse = Factory::service("Warehouse")->getWarehouse(isset($locationArray_pickList[1]) ? trim($locationArray_pickList[1]) : '');
	    						if($warehouse instanceof Warehouse)
	    							$results[$rowNo]['location'] = $warehouse->getBreadCrumbs();
	    					}
	    				}
	    			}
	    		}
	    	}
    	}
	    catch(Exception $ex)
	    {
	    	$results[0] = $ex->getMessage();
	    }
    	$this->responseLabel->Text = htmlentities(json_encode($results));
    }

	/**
	 * Auto Take
	 *
	 * @param unknown_type $facilityRequest
	 * @param unknown_type $ownerWarehouse
	 * @return unknown
	 */
	private function autoTake($facilityRequest, $ownerWarehouse)
	{
		$taken = false;
		if($this->AutoTake->Checked)
		{
			if($facilityRequest->getOwner()!= $ownerWarehouse || ($facilityRequest->getUpdatedBy()->getId() !=Core::getUser()->getId()))
			{
				if ($this->_reportServerRedirectEnabled)
					Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

				Factory::service("FacilityRequest")->takeFR($facilityRequest, $ownerWarehouse, "(TAKE)");

				if ($this->_reportServerRedirectEnabled)
					Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON

				$taken = true;
				sleep(1);
			}
		}
		return $taken;
	}

	/**
	 * Printing the pick list for selected requests
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 */
	public function printPickList($sender, $param)
    {
    	$html = '';
    	try
    	{
	    	if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
    			throw new Exception("Please select a request first!");
	    	$totalQty = 0;

	    	//get timestamp
	    	$timeZone  = Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
	    	$now = new HydraDate('now', $timeZone);

	    	$this->_ownerWarehouse = $this->getOwnerWarehouse();
	    	$stockWarehouse = FacilityRequestLogic::getWarehouseForPartCollection($this->_ownerWarehouse, Factory::service('Warehouse')->getDefaultWarehouse(Core::getUser()));
	    	$result = Factory::service("FacilityRequest")->getPickList($selectedIds, $stockWarehouse);
	    	$deliveryInfo = FacilityRequestLogic::getShipToInformation($selectedIds);

	    	$html = "<div>";
	    	$html .= "<img src='/themes/images/print.png' onclick='window.print();' title='Print' style='float:right;margin: 0 0 0 10px;'/>";
	    	$html .= "<b>The list of selected parts you are picking under <u>" . $this->_ownerWarehouse->getBreadCrumbs(true) . "</u> :</b>";
	    	$html .= "</div>";
	    	$html .= ($printInfo = "<div style='font-style:italic; font-size: 9px;'>Printed by " . Core::getUser()->getPerson() . " @ $now($timeZone) </div>");
	    	$html .= "<table style='width:100%;' border=1 cellspacing=0>";
	    	$html .= "<thead>";
	    	$html .= "<tr style='background: black; color: white; font-weight: bold;'>";
	    	$html .= "<td>Location</td><td>Avail Qty</td><td>Field task</td><td>Part Type</td><td>Req. Qty</td><td width='30px'>Request ID</td>";
	    	$html .= "</tr>";
	    	$html .= "</thead>";
	    	$html .= "<tbody>";
    		$totalQty = 0;
    		foreach($result as $rowNo => $res)
    		{
				$shipTo = '';

    			$request = Factory::service("FacilityRequest")->get($res['id']);
    			$this->autoTake($request, $this->_ownerWarehouse);
    			$location = 'No available parts!';
    			if(is_array($locationArray = explode("-", $res['maxNoLocation'])))
    			{
    				$warehouse = Factory::service("Warehouse")->getWarehouse(isset($locationArray[1]) ? trim($locationArray[1]) : '');
    				$availQty = isset($locationArray[2]) ? trim($locationArray[2]) : '';
    				if($warehouse instanceof Warehouse)
    				{
    					$location = $warehouse->getBreadCrumbs() . "<br /><img style='margin: 15px 15px 0 15px;' src='/ajax/?method=renderBarcode&text=" . $warehouse->getAlias(WarehouseAliasType::$aliasTypeId_barcode) . "' />";

    					$shipTo = (array_key_exists($res['id'], $deliveryInfo) ? $deliveryInfo[$res['id']] : '');
    					if (is_array($shipTo))
    					{
    						$shipTo = $deliveryInfo[$res['id']]['wh'];
    					}
    					$shipTo = '<br /><br /><span style="font-style:bold;">SHIP TO: <span style="font-style:italic;">' . $shipTo . '</span></span>';
    				}
    			}
    			if(!$request instanceof FacilityRequest)
    				continue;

    			$totalQty += ($qty = $request->getQuantity());
    			$ftaskNumber = $request->getFieldTask();
    			$partType = $request->getPartType();
    			$html .= "<tr valign='bottom' style='" . ($rowNo++ % 2 ===0 ? '': 'background: #eeeeee;') . "'>";
		    	$html .= "<td>" . $location . $shipTo . "</td>";
		    	$html .= "<td>$availQty <i style='font-size:9px;'>GOOD</i></td>";
		    	$html .= "<td>" . $ftaskNumber->getId() ."</td>";
		    	$html .= "<td>" . $partType->getAlias() . "<div style='font-style:italic; font-size: 9px;'>" . $partType->getName() ."</div>";
		    	if(($bp = trim($partType->getAlias(PartTypeAliasType::ID_BP))) !== '')
		    		$html .= "<img style='margin: 15px 15px 0 15px;' src='/ajax/?method=renderBarcode&text=$bp' />";

		    	$html .= "</td>";
		    	$html .= "<td>$qty</td>";
		    	$html .= "<td><img style='margin: 15px 15px 0 15px;' src='/ajax/?method=renderBarcode&text=" . $request->getId() . "' /></td>";
		    	$html .= "</tr>";
    		}
	    	$html .= "<tr style='background: black; color: white; font-weight: bold;'>";
	    	$html .= "<td>&nbsp;</td><td>&nbsp;</td><td>&nbsp;</td><td>Total:</td><td>$totalQty</td><td>&nbsp;</td>";
	    	$html .= "</tr>";
	    	$html .= "</tbody>";
	    	$html .= "<tfoot>";
	    	$html .= "<tr>";
			$html .= "<td colspan=6 style='text-align: right;'>$printInfo</td>";
			$html .= "</tr>";
		   	$html .= "</tfoot>";
	    	$html .= "</table>";
    	}
	    catch(Exception $ex)
	    {
	    	$html = $this->_getErrorMsg($ex->getMessage());
	    }
    	$this->responseLabel->Text = $html;
    }

	/**
	 * Getting the HTML of the request comments panel
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @throws Exception
	 */
	public function getComments($sender, $param)
	{
		$html = '';
		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");
			$requestId = $selectedIds[0];
			$request = Factory::service("FacilityRequest")->get($requestId);

			if(!$request instanceof FacilityRequest)
				throw new Exception("Invalid facility request id provided(=$requestId)!");

			$fieldTask = $request->getFieldTask();
			$address = $fieldTask->getAddress();
			$html = "<div detailspanel='detailspanel'>";
			$html .= "<h3>Details for Facility Request (Fieldtask No.: " . $fieldTask->getId() . " Part Type: " . $request->getPartType()->getAlias() . ")</h3>";
			$html .= "<div detailspanel='deliveryDetailsPanel' class='detailsInputPanel'>";
			$html .= "<span class='detailsInputLabel'>Delivery Look Up Info:</span> ";
			$result = Factory::service("Lu_PartDelivery")->findByCriteria("lu_WorkType_ZonesetId in (select distinct x.id from lu_worktype_zoneset x where x.active = 1 and x.workTypeId = ? and x.zoneSetId = ?)", array($fieldTask->getWorkType()->getId(), $address->getZone()->getZoneSet()->getId()));
			if(count($result) > 0)
			{
				foreach($result as $row)
				{
					$html .= '<div>';
					$html .= 'Part To:' . $row->getRemoteWarehouse()->getBreadCrumbs();
					$html .= '</div>';
					$html .= '<div>';
					$html .= 'Courier:' . $row->getServiceCompany();
					$html .= '</div>';
				}
			}
			else
			$html .= 'None Set!';
			$html .= "</div>";
			$html .= "<div detailspanel='newCommentsPanel' class='detailsInputPanel'>";
			$html .= "<span class='detailsInputLabel'>New FR Comments:</span> <input detailspanel='newComments' style='width: 60%' PlaceHolder='New Comments' onkeydown=\"pageJs.enterEvent(event, $(this).next('input[detailspanel=newCommentsAddBtn]'));\" />";
			$html .= "<input detailspanel='newCommentsAddBtn' type='button' value='add' Onclick=\"return pageJs.sumbmitNewComments('$requestId', '" . $this->addNewCommentsBtn->getUniqueID(). "', this);\"/>";
			$html .= "</div>";
			$html .= "<div detailspanel='commentsPanel'  style='overflow:auto; max-height:300px;'>";
			//add the FR comments first
			$html .= "<br />";
			$html .= "<table class='ResultDataList' detailspanel='commentsList'>";
				$html .= "<thead>";
					$html .= "<tr>";
						$html .= "<th width='10%'>User</th>";
						$html .= "<th width='22%'>Time</th>";
						$html .= "<th style='font-weight:bold;'>Facility Request Comments</th>";
					$html .= "</tr>";
				$html .= "</thead>";
			$html .= "<tbody>";
			$frNotes = array_reverse($request->getCommentArray(true));
			foreach ($frNotes as $i => $comments)
			{
				$user = $comments[0];
				$time = $comments[1];
				$comment = $comments[2];

				$html .= "<tr class='" . ($i % 2 === 0 ? "ResultDataListItem" : "ResultDataListAlterItem") . "'>";
				$html .= "<td>$user</td>";
				$html .= "<td>" . str_replace("(", " (", $time). "</td>";
				$html .= "<td>$comment</td>";
				$html .= "</tr>";
			}
			$html .= "</tbody>";
			$html .= "</table>";
			$html .= "<br /><br />";
			//consolidated task notes
			$html .= "<table class='ResultDataList' detailspanel='commentsList'>";
				$html .= "<thead>";
					$html .= "<tr>";
						$html .= "<th width='10%'>User</th>";
						$html .= "<th width='22%'>Time</th>";
						$html .= "<th style='font-weight:bold;'>Field Task Consolidated Notes</th>";
					$html .= "</tr>";
				$html .= "</thead>";
			$html .= "<tbody>";
			$notesArr = Factory::service("FieldTask")->getConsolidatedNotes($fieldTask);
			$tz = $address->getTimezone();
			foreach ($notesArr as $i => $comments)
			{
				$user = $comments['by'];
				$time = $comments['dt'];
				$comment = $comments['msg'];

				$html .= "<tr class='" . ($i % 2 === 0 ? "ResultDataListItem" : "ResultDataListAlterItem") . "'>";
				$html .= "<td>$user</td>";
				$html .= "<td>" . str_replace("(", " (", $time). " (" . $tz . ")</td>";
				$html .= "<td>$comment</td>";
				$html .= "</tr>";

			}
			$html .= "</tbody>";
			$html .= "</table>";
			$html .= "</div>";
			$html .= "</div>";
		}
		catch(Exception $ex)
		{
			$html = $this->_getErrorMsg($ex->getMessage());
		}
		$this->responseLabel->Text = $html;
	}

	/**
	 * Event to add a new comments to a facility request
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @throws Exception
	 */
	public function addNewComments($sender, $param)
	{
		$result = $errors = array();
		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");

			$requestId = $selectedIds[0];
			$request = Factory::service("FacilityRequest")->get($requestId);

			if(!$request instanceof FacilityRequest)
				throw new Exception("Invalid facility request id provided(=$requestId)!");

			if(!isset($param->CallbackParameter->newComment))
				throw new Exception("No newComment passed in!");

			$newComments = trim($param->CallbackParameter->newComment);
			if($newComments !== '')
			{
				if ($this->_reportServerRedirectEnabled)
					Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

				Factory::service("FacilityRequest")->addComments($request, "Additional Comments : ".$newComments);

				$result["user"] = Core::getUser()->getPerson()."";
				$result["time"] = str_replace("(", " (", DateUtils::getNowFromDWH('UTC')->getStringWithTimeZone());
				$result["comment"] = $newComments;
			}
		}
		catch(Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}

		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to repor5t server, if redirection is ON

		if (empty($errors))
			$result["requests"] = $this->_getData(false,null ,$totalRows, 'slaEnd', 'asc', $selectedIds);

		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

	/**
	 * Hide Transit Panel
	 *
	 */
	public function hideTransitPanel()
	{
		$this->ConfirmTransitNoteWindow->Display = "None";
	}

	/**
	 * Hide Move To Tech Panel
	 *
	 */
	public function hideMoveToTechPanel()
	{
		$this->ConfirmMoveToTechWindow->Display = "None";
	}

	/**
	 * Hide Panel
	 *
	 */
	public function hideEmailPanel()
	{
		$this->SendEmailWindow->Display = "None";
		$this->emailBody->Text = "";
		$this->emailSubject->Text = "";
		$this->emailSendTo->Text = "";
	}

	/**
	 * Function to show the transit note panel
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @throws Exception
	 */
	public function showTransitNotePanel($sender, $param)
	{
		$this->ConfirmTransitNoteWindow->Display = "Dynamic";
		$this->ConfirmTransitNoteWindow->Style = "position: fixed;top: 10px;z-index:99;left: 10%; width: 80%; height: 80%;";
	}

	/**
	 * Function to show the transit note panel
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @throws Exception
	 */
	public function showMoveToTechPanel($sender, $param)
	{
		$result = $errors = array();
		$recipientWarehouse = $this->sendTo->getSelectedValue();
		$recipientTechnician = trim($this->recipientTech->getSelectedValue());

		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");

			if(!isset($recipientTechnician) || $recipientTechnician =="")
				throw new Exception("Please provide Recipient Technician.");

 			$fromWarehouse = $this->getOwnerWarehouse();

			//get the base result
			$partsAddedToMove = FacilityRequestLogic::getBaseDataForMoveToTech($selectedIds);
			$warehouseTo = Factory::service("Warehouse")->getDefaultMobileWarehouse(Factory::service('UserAccount')->getUserAccount($recipientTechnician));
			$toWarehouseId = $warehouseTo->getId();

			$userAccount = Factory::service('UserAccount')->getUserAccount($recipientTechnician);
			$validateTechAgainstTasks = FacilityRequestLogic::getTakePartsMessages($partsAddedToMove,$userAccount);

			//WARNING MSG Constructor
			//covert it to modal box
			$confirmMsg= "";
			if(!empty($validateTechAgainstTasks) && $validateTechAgainstTasks>"")
			{
				if(count($validateTechAgainstTasks['noowner'])>0)
				{
					$confirmMsg .="Take below Task(s) and move Reserved part(s) to `<b><i>".$warehouseTo->getName()."</b></i>`?<br /><br />";
					$ftIds=array();
					foreach($validateTechAgainstTasks['noowner'] as $k =>$ft)
					{
						//Warning Message
						if(!in_array($ft->getId(),$ftIds))
						{
							$ftIds[] = $ft->getId();
							$infoMsg = 'This part will be moved to '.$warehouseTo->getName();
							$warning = FacilityRequestLogic::getFacilityRequestWarningMessage($ft, null, $infoMsg, $selectedIds);
							$confirmMsg .= $warning['html'];
						}
					}
				}

				if(count($validateTechAgainstTasks['owned_by_other'])>0)
				{
					$confirmMsg .="\nMove below part(s) to `".$warehouseTo->getName()."` <br>";
					$confirmMsg .="<br>Warning: <br>";
					foreach($validateTechAgainstTasks['owned_by_other'] as $k1 =>$ft1)
					{
						$confirmMsg .= "- Task No: ".$ft1->getId()."  (Owned by :".$ft1->getPerson()->getFullName().") <br>";
					}
				}

				if(count($validateTechAgainstTasks['noowner'])<=0&&count($validateTechAgainstTasks['owned_by_other'])<=0)
				{
					$confirmMsg = "Are you sure you want to Move parts to ".$warehouseTo->getName().".<br><br>";
				}

				$result[]=$confirmMsg;
			}
		}
		catch(Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}
		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

	/**
	 * Function to show the email panel
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @throws Exception
	 */
	public function showEmailPanel($sender, $param)
	{
		$this->Page->jsLbl->Text = "";
		$this->Message->Text = "";

		if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
			throw new Exception("Please select a request first!");

		$this->selectedIds->Value = $param->CallbackParameter->selectedIds;
		if(isset($param->CallbackParameter->subject) && trim($param->CallbackParameter->subject) != '')
		{
			$this->emailSubject->Text = $param->CallbackParameter->subject;
		}

		if(isset($param->CallbackParameter->body) && trim($param->CallbackParameter->body) != '')
		{
			$this->emailBody->Text = $param->CallbackParameter->body;
		}
		$this->SendEmailWindow->Display = "Dynamic";
		$this->SendEmailWindow->Style = "position: fixed;top: 10px;z-index:99;left: 10%; width: 80%; height: 80%;";
	}

	/**
	 * Event to send an email
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @throws Exception
	 */
	public function sendEmail($sender, $param)
	{
		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

		$result = $errors = array();
		try
		{
			$sendto = $this->emailSendTo->Text;
			$subject = $this->emailSubject->Text;
			$body  = $this->emailBody->Text;
			$selectedIds = $this->selectedIds->Value;

			if(!$sendto)
			{
				$errors[] = "Please enter 'Sent to' email addresses!";
			}
			if(!$subject)
			{
				$errors[] = "Please enter a Subject!";
			}

			if(count($errors)==0)
			{
				$sendTo = array();
				foreach(explode(";", $sendto) as $email)
				{
					$email = trim($email);
					if($email === '')
						continue;

					$sendTo[] = $email;
				}
				Factory::service("Message")->email(explode(";", $sendto), $subject, $body);

				//loging the email
				$comments = Core::getUser()->getPerson() . ' - ' . DateUtils::getNowFromDWH()->getStringWithTimeZone() . " - Sent Email To  :: $sendto";
				foreach(explode(",",$selectedIds) as $id)
				{
					if(($entity = Factory::service("FacilityRequest")->get($id)) instanceof FacilityRequest)
					{
						Logging::LogEmail($entity->getId(), get_class($entity), $body, $comments, DateUtils::getNowFromDWH());
						$comment = $subject;
						if(strpos($subject,"Multiple Facility Requests for Tasks"))
						{
							$partType = $entity->getPartType();
							if($partType instanceOf PartType)
							{
								$partTypeAlias = Factory::service("PartType")->getPartTypeAliasByTypeForPartType($partType->getId(),1);
								if(count($partTypeAlias)>0)
								{
									if($partTypeAlias[0] instanceOf PartTypeAlias)
									{
										$comment = 'Task: ' . $entity->getFieldTask()->getId() . ' Requires Part Code: ' . $partTypeAlias[0]->getAlias()  . ' Qty: ' . $entity->getQuantity();
									}
								}
							}
						}
						$entity->addComment($comment . ', Sent Email To  :: ' . $sendto);
						Factory::service("FacilityRequest")->save($entity);
					}
				}
			}
		}
		catch(Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}
		if(count($errors)>0)
		{
			$this->Message->Text = implode("<br>",$errors);
			$this->Page->jsLbl->Text = "<script type=\"text/javascript\">Modalbox.hide();</script>";
		}
		else
		{
			$this->hideEmailPanel();
			$this->Page->jsLbl->Text = "<script type=\"text/javascript\">Modalbox.hide();alert('Successfully sent Email.');</script>";
		}

		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON
	}

	/**
	 * Handle Selected Email
	 *
	 */
	public function handleSelectedEmail()
	{
		if($this->emailSendTo->Text)
		{
			$this->emailSendTo->Text = $this->emailSendTo->Text . ";" . $this->emailAuto->getSelectedValue();
		}
		else
		{
			$this->emailSendTo->Text = $this->emailAuto->getSelectedValue();
		}
		$this->emailAuto->setSelectedValue(0);
		$this->emailAuto->Text = "";
	}

	/**
	 * Handle Selected Company Email
	 *
	 */
	public function handleSelectedCompanyEmail()
	{
		if($this->emailSendTo->Text)
		{
			$this->emailSendTo->Text = $this->emailSendTo->Text . ";" . $this->emailCompany->getSelectedValue();
		}
		else
		{
			$this->emailSendTo->Text = $this->emailCompany->getSelectedValue();
		}
		$this->emailCompany->setSelectedValue(0);
		$this->emailCompany->Text = "";
	}

	/**
	 * Handle Selected Warehouse Email
	 *
	 */
	public function handleSelectedWarehouseEmail()
	{
		if($this->emailSendTo->Text)
		{
			$this->emailSendTo->Text = $this->emailSendTo->Text . ";" . $this->emailWarehouse->getSelectedValue();
		}
		else
		{
			$this->emailSendTo->Text = $this->emailWarehouse->getSelectedValue();
		}
		$this->emailWarehouse->setSelectedValue(0);
		$this->emailWarehouse->Text = "";
	}

	/**
	 * Suggest Email Search
	 *
	 * @return unknown
	 */
	public function suggestEmailSearch()
	{
		$emails = array();
		$sql = "select distinct p.email from person p
					inner join useraccount ua on ua.personId = p.id and ua.active = 1
					where p.active = 1 and p.email like '%" . $this->emailAuto->Text . "%'";

		$sql .= " UNION DISTINCT ";

		$sql .= " select alias from warehousealias where active = 1 and warehousealiastypeid in (".$this->_frEmailWarehouseAliasTypeIds." )";
		$sql .= " and alias like '%" . $this->emailAuto->Text . "%'";

		$sql .= " UNION DISTINCT ";

		$sql .= " select email from company where active = 1 ";
		$sql .= " and email like '%" . $this->emailAuto->Text . "%'";

		$result = Dao::getResultsNative($sql);
		if($result)
		{
			foreach($result as $rows)
			{
				$emails[] = array('id'=> $rows[0], 'email'=> $rows[0]);
			}
		}
		if(count($emails)==0)
		{
			$emails[] = array('id'=> 0, 'email'=> 'No emails Found!');
		}

		return $emails;
	}

	/**
	 * Suggest Company Email Search
	 *
	 * @return unknown
	 */
	public function suggestCompanyEmailSearch()
	{
		$emails = array();

		$sql = " select email,name from company where active = 1 and email > '' and email is not null";
		$sql .= " and (email like '%" . $this->emailCompany->Text . "%' ";
		$sql .= " or name like '%" . $this->emailCompany->Text . "%' )";

		$result = Dao::getResultsNative($sql);
		if($result)
		{
			foreach($result as $rows)
			{
				$emails[] = array('id'=> $rows[0], 'email'=> $rows[1]." - ".$rows[0]);
			}
		}
		if(count($emails)==0)
		{
			$emails[] = array('id'=> 0, 'email'=> 'No emails Found!');
		}

		return $emails;
	}

	/**
	 * Suggest Warehouse Email Search
	 *
	 * @return unknown
	 */
	public function suggestWarehouseEmailSearch()
	{
		$emails = array();

		$sql = " select wa.alias, w.name, wat.name from warehousealias wa
					left join warehouse w on (w.id = wa.warehouseid and w.active=1)
					left join warehousealiastype wat on (wat.id = wa.warehousealiastypeid and wat.active=1)
					where
						wa.active = 1 and
						wa.warehousealiastypeid in (".$this->_frEmailWarehouseAliasTypeIds." )";

		$sql .= " and (wa.alias like '%" . $this->emailWarehouse->Text . "%'  or w.name like '%" . $this->emailWarehouse->Text . "%')";

		$result = Dao::getResultsNative($sql);
		if($result)
		{
			foreach($result as $rows)
			{
				$emails[] = array('id'=> $rows[0], 'email'=> $rows[1]." - ".$rows[2]." - ".$rows[0]);
			}
		}
		if(count($emails)==0)
		{
			$emails[] = array('id'=> 0, 'email'=> 'No emails Found!');
		}

		return $emails;
	}

	/**
	 * Event to changes Status ONLY for selected requests
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @throws Exception
	 */
	public function changeStatus($sender, $param)
	{
		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

		$result = $errors = array();
		try
		{
			Dao::beginTransaction();
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");

			if(!isset($param->CallbackParameter->info))
				throw new Exception("No Info passed in!");

			$info = $param->CallbackParameter->info;
			if(!isset($info->comment) || ($comments = trim($info->comment)) === '')
				throw new Exception("No comments passed in!");

			if(!isset($info->newStatus) || ($newStatus = trim($info->newStatus)) === '')
				throw new Exception("No new status passed in!");

			$result['requests'] = array();
			foreach($selectedIds as $id)
			{
				if(($fr = Factory::service("FacilityRequest")->get($id)) instanceof FacilityRequest)
				{
					Factory::service("FacilityRequest")->pushStatus($fr, $newStatus, $comments);
				}
			}

			$totalRows = 0;
			Dao::commitTransaction();
		}
		catch(Exception $ex)
		{
			Dao::rollbackTransaction();
			$errors[] = $ex->getMessage();
		}

		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON

		if (empty($errors))
			$result['requests'] = $this->_getData(false,null ,$totalRows, 'slaEnd', 'asc', $selectedIds);

		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

    /**
     * Send To suggestion
     *
     * @return unknown
     */
	public function suggestSendTo($searchString)
	{
		$result =  WarehouseLogic::getPartDeliveryLookupWarehouses($searchString,true);
		if(count($result)>0)
			return $result;
		else
			return array(array("-1", "No Results Found..."));
	}

	/**
	 * Event to push all field tasks to be the wantted status for selected facility request(s)
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 */
	public function pushFTStatus($sender, $param)
	{
		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

		$result = $errors = array();
		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");

			if(!isset($param->CallbackParameter->info))
				throw new Exception("No Info passed in!");

			if(!isset($param->CallbackParameter->info->newstatus) || ($nextStatus = trim($param->CallbackParameter->info->newstatus)) === '')
				throw new Exception("No newstatus passed in!");

			if(!isset($param->CallbackParameter->info->fieldTaskArray) || (count($fieldTaskArray = $param->CallbackParameter->info->fieldTaskArray)) === 0)
				throw new Exception("No fieldTaskArray passed in!");

			$result['requests'] = array();
			$requestIds = array();
			foreach($selectedIds as $id)
			{
				//used the $fieldTaskArray, just in case there are multiple submitted requests linked to the same fieldtask
				if(!($request = Factory::service("FacilityRequest")->get($id)) instanceof FacilityRequest || (!($fieldTask = $request->getFieldTask()) instanceof FieldTask))
					continue;

				$fieldTaskId = $fieldTask->getId();
				try
				{
					$ftIds = $fieldTaskArray->$fieldTaskId;
					if(isset($fieldTaskArray->$fieldTaskId[0]) && trim($id) !== trim($ftIds[0]))
						continue;
				}
				catch(Exception $e){}

				switch($nextStatus)
				{
					case $this->getFieldTaskAvailStatus():
						{
							Factory::service("FacilityRequest")->pushToAvial($request, "Pushed all selected request(s) to : $nextStatus");
							break;
						}
					case $this->getFieldTaskTransitStatus():
						{

							Factory::service("FacilityRequest")->pushToTransit($request, "Pushed all selected request(s) to : $nextStatus");
							break;
						}
					default:
						throw new Exception("Invalid status(=$nextStatus)!");
				}

				foreach(Factory::service("FacilityRequest")->findByCriteria("fieldTaskId = ?", array($fieldTask)) as $req)
				{
					$requestIds[] = $req->getId();
				}
			}
		}
		catch(Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}

		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON

		if (empty($errors) && count($requestIds = array_filter($requestIds)) > 0)
		{
			$totalRows = 0;
			$result['requests'] = $this->_getData(false,null ,$totalRows, 'slaEnd', 'asc', $requestIds);
		}

		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

	/**
	 * This is for CommandParameter for pushToAvailBtn and function pushToAvailOrTransit, avoiding hardcoding!
	 * @return string
	 */
	public function getFieldTaskAvailStatus()
	{
		return Factory::service("WorkFlowDefinition")->getAvailableStatus();
	}

	/**
	 * This is for CommandParameter for createTransitNoteBtn and function pushToAvailOrTransit, avoiding hardcoding!
	 * @return string
	 */
	public function getFieldTaskTransitStatus()
	{
		return Factory::service("WorkFlowDefinition")->getTransitStatus();
	}

	/**
	 * Event to add or delete a view preference
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 */
	public function addOrDelPreference($sender, $param)
	{
		$result = $errors = array();
		try
		{
			if(!isset($param->CallbackParameter->name) || !isset($param->CallbackParameter->action))
			throw new Exception("Insufficient info passed in!");

			$name = trim($param->CallbackParameter->name);
			$action = trim($param->CallbackParameter->action);
			$result['name'] = $name;
			$result['action'] = $action;

			//getting the current preferernces
			$preferences = $this->_getUPGroups();

			switch($action)
			{
				case 'add': //adding a new preference
					{
						//comment out for allowing user to overwrites preferences
						//if(isset($preferences[$name]))
						//throw new Exception("Preference Name(=$name) Exsits Already!");

						//gathering the search criterias
						list($fieldTaskNo, $clientRefNo, $searchPartTypeId, $searchAttendedById, $frStatus, $ftStatus, $hasReserve, $sendToWarehouseId, $sentToTechId, $workTypeIds, $zoneSetIds,$serialNo,$recipientTechId) = $this->_getSearchCriteria();
						$p = array();
						if($fieldTaskNo !== '') $p['taskNumber'] = $fieldTaskNo;
						if($clientRefNo !== '') $p['clientRefNo'] = $clientRefNo;
						if($searchPartTypeId !== '') $p['partType'] = $searchPartTypeId;
						if($searchAttendedById !== '') $p['recievedBy'] = $searchAttendedById;
						if($frStatus !== '') $p['frStatus'] = $frStatus;
						if($ftStatus !== self::OPTION_FOR_ALL) $p['ftStatus'] = $ftStatus;
						if($hasReserve !== self::OPTION_FOR_ALL) $p['hasReserve'] = $hasReserve;
						if($sendToWarehouseId !== '') $p['sendTo'] = $sendToWarehouseId;
						if(count($workTypeIds) > 0) $p['workType'] = $workTypeIds;
						if(count($zoneSetIds) > 0) $p['zoneSet'] = $zoneSetIds;
						if(count($serialNo) > 0) $p['serialNo'] = $serialNo;
						if(count($recipientTechId) > 0) $p['recipientTechId'] = $recipientTechId;

						$preferences[$name] = $p;
						break;
					}
				case 'del': //deleting a preference
					{
						if(!isset($preferences[$name]))
						throw new Exception("Preference Name ($name) Does NOT Exist!");

						unset($preferences[$name]);
						break;
					}
				default:
					throw new Exception("Invalid action ($action)!");
			}

			if ($this->_reportServerRedirectEnabled)
				Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

			//save the new preference
			Factory::service("UserPreference")->setOption(Core::getUser(), self::PREFERENCE_PARAM_NAME, json_encode($preferences));
		}
		catch(Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}

		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON

		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

	/**
	 * Event to list all preference for the current user
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @throws Exception
	 */
	public function viewPreferenceList($sender, $param)
	{
		$html = '';
		try{
			//get the add preference panel
			$html .= "<table class='ResultDataList'>";
			$html .= "<thead>";
			$html .= "<tr>";
			$html .= "<th>Name</th>";
			$html .= "<th>View Preferences</th>";
			$html .= "<th>&nbsp;</th>";
			$html .= "</tr>";
			$html .= "</thead>";
			$html .= "<tbody>";
			$rowNo = 0;
			foreach($this->_getUPGroups() as $key => $items)
			{
				$html .= "<tr class='" . ((($rowNo++) % 2) === 0 ? "ResultDataListAlterItem" : "ResultDataListItem") . "'>";
				$html .= "<td>$key</td>";
				$html .= "<td>";
				foreach($items as $k => $item)
				{
					$html .= "<div><b>$k: </b>";
					if(is_array($item))
					{
						$funcName = (ucfirst($k) === 'WorkType' ? "getLongName" : "getName");
						$html .= "<ul style='margin-left: 20px;'><li>" . implode("</li><li>", $this->_transIdToName($item, ucfirst($k), $funcName)) . "</li></ul>";
					}
					else
						$html .= "$item";

					$html .= "</div>";
				}
				$html .= "</td>";
				$html .= "<td>";
				$html .= "<input type='image' src='/themes/images/delete.png' onclick=\"return pageJs.delPreference('" . $this->addOrDelPreferenceBtn->getUniqueID() . "', '" . $this->preferencesList->getClientId() . "', this);\" value='$key' />";
				$html .= "</td>";
				$html .= "</tr>";
			}
			$html .= "</tbody>";
			$html .= "</table>";
		}
		catch(Exception $ex)
		{
			$html = $this->_getErrorMsg($ex->getMessage());
		}
		$this->responseLabel->Text = $html;
	}

	/**
	 * Check Values in control
	 * (Make sure values are in control before selecting them)
	 *
	 * @param unknown_type $ctrl
	 * @param unknown_type $values
	 * @return unknown
	 */
	private function checkValuesInControl($ctrl, $values)
	{
		$newValues = array();
		$items = $ctrl->getItems();
		foreach($values as $value)
		{
			if($items->findIndexByValue($value)>=0)
			{
				$newValues[] = $value;
			}
		}
		return $newValues;
	}

	/**
	 * Event to change the view preference, update all the search criterias
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @throws Exception
	 */
	public function changePreferenceView($sender, $param)
	{
		if(!isset($param->CallbackParameter->name) || ($name = trim($param->CallbackParameter->name)) === '')
			throw new Exception("Preference Name Needed!");

		//getting the current preferernces
		$preferences = $this->_getUPGroups();
		if(!isset($preferences[$name]))
			throw new Exception("Preference(=$name) is not set!");

		//clear all values
		foreach($this->SearchPanel->getControls() as $control)
		{
			if($control instanceof TActiveTextBox)
			$control->setText('');
			else if($control instanceof TActiveDropDownList)
			$control->setSelectedIndex(0);
			else if($control instanceof TActiveListBox)
			$control->setSelectedValues(array());
			else if($control instanceof TAutoComplete)
			{
				$control->Text = '';
				$control->setViewState('value', '', null);
			}
		}

		//filling in with the wanted values
		foreach($preferences[$name] as $key => $value)
		{
			switch($key)
			{
				case 'taskNumber':
					{
						$this->taskNumber->setText($value);
						break;
					}
				case 'frStatus':
					{
					//do deal with old preferences
					if($value == 'OPEN')
					{
						$value = array('new','attended','reserved');
					}

					if(gettype($value) == 'string')
					{
						$value = array($value);
					}
					//do deal with old preferences
					$this->$key->setSelectedValues($value);
					break;
					}
				case 'ftStatus':
				case 'hasReserve':
					{
						$this->$key->setSelectedValue($value);
						break;
					}
				case 'zoneSet':
				case 'workType':
					{
						$value = $this->checkValuesInControl($this->$key, $value);
						$this->$key->setSelectedValues($value);
						break;
					}
				case 'sendTo':
					{
						if(($warehouse = Factory::service("Warehouse")->get($value)) instanceof Warehouse)
						{
							$this->sendTo->Text = $warehouse->getName();
							$this->sendTo->setViewState('value', $value, null);
						}
						break;
					}
				case 'partType':
					{
						$this->partType->loadPartTypeId($value);
						break;
					}
			}
		}
	}

	/**
	 * Translate the id into the name string
	 *
	 * @param Array  $Ids          The ids that we are trying to translate
	 * @param String $entityName   The entity name. ie.: WorkType
	 * @param String $functionName The funciton name to the get the name
	 *
	 * @return void|multitype:string
	 */
	private function _transIdToName($Ids, $entityName, $functionName)
	{
		if(!is_array($Ids) || count(array_filter($Ids)) === 0)
			return array();

		$return = array();
		$result = array();
		try {
			if($entityName == 'WorkType')
			{
				$result = Factory::service($entityName)->findByCriteria("wt.id in (" . implode(", ", $Ids) . ")");
			}
			else
			{
				$result = Factory::service($entityName)->findByCriteria("id in (" . implode(", ", $Ids) . ")");
			}
		}
		catch(Exception $e)
		{
			$return	= $Ids;
		}

		foreach($result as $entity)
		{
			$return[] = trim($entity->$functionName());
		}
		return $return;
	}

	/**
	 * Create Warning Message
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function createWarning($sender, $param)
	{
		$result=$errors=array();
		if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0){
			throw new Exception("Please select a request first!");
		}

		$infoMsg='';
		$ftIds = array();
		$warningMsg = '';
		foreach($selectedIds as $selectedId)
		{
			$facilityRequest = Factory::service("FacilityRequest")->get($selectedId);
			$fieldTask = $facilityRequest->getFieldTask();
			if(!in_array($fieldTask->getId(),$ftIds))
			{
				$ftIds[] = $fieldTask->getId();
				$warning = FacilityRequestLogic::getFacilityRequestWarningMessage($fieldTask, null, $infoMsg, $selectedIds);
				$warningMsg .= $warning['html'];
			}
		}
		$result[]=$warningMsg;
		$this->confirmWarningLabel->Text = $warningMsg;
		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

	/**
	 * Create TransitNote/DispatchNote
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function createTnDn($sender, $param)
	{
		$result = $errors = array();
		$warehouseIdTo = "";
		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");

			$noteType = $param->CallbackParameter->noteType;
			$checkTransitNote = $param->CallbackParameter->checkTransitNote;
			$selectedTransitNoteNo = $param->CallbackParameter->selectedTransitNoteNo;

			$confirmedDestinationId = null;
			if (isset($param->CallbackParameter->warehouseToId))
				$confirmedDestinationId = $param->CallbackParameter->warehouseToId;

			$fieldTaskErrors = array();
			$frIds = array();
			$partsAddedToTransit = array();
			foreach($selectedIds as $selectedId)
			{
				$facilityRequest = Factory::service("FacilityRequest")->get($selectedId);
				$frIds[] = $facilityRequest->getId();
				if($checkTransitNote)
				{
					$sql = "SELECT
								distinct pi.id,
								pia.alias as serial ,
								pta.alias as partCode,
								pt.name as partName,
								fr.fieldTaskId
							FROM partinstance pi
								left join partinstancealias pia on (pia.partInstanceId = pi.id and pia.partInstanceAliasTypeId = 1 and pia.active = 1)
								left join parttype pt on (pt.id = pi.partTypeId and pt.active = 1)
								left join parttypealias pta on (pta.partTypeId = pt.id and partTypeAliasTypeId = 1 and pta.active = 1)
								inner join facilityrequest fr on (fr.id = pi.facilityRequestId)
							WHERE
								pi.active = 1
								and fr.status in ('".implode("','",FacilityRequestLogic::getOpenStatuses())."')
								and fr.active=1
								and  fr.id = " . $facilityRequest->getId();
				}
				else
				{
					$sql = "SELECT
								pi.id,
								pi.quantity
							FROM partinstance pi
								inner join facilityrequest fr on fr.id = pi.facilityRequestId
							WHERE
								pi.active = 1
								and fr.status in('".implode("','",FacilityRequestLogic::getOpenStatuses())."')
								and fr.active=1
								and  fr.id = " . $facilityRequest->getId();
				}
				$result = Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC);

				if(count($result)>0 && sizeof($result)>"")
				{
					$partsAddedToTransit = array_merge($partsAddedToTransit,$result);
				}
				else
				{
					$ftId = $facilityRequest->getFieldTask()->getId();
					if(isset($fieldTaskErrors[$ftId]))
						$fieldTaskErrors[$ftId] += 1;
					else
						$fieldTaskErrors[$ftId] = 1;
				}

			}

			//if errors
			if(count($fieldTaskErrors) > 0)
			{
				$message = "";
				foreach($fieldTaskErrors as  $fieldTaskId => $count)
				{
					$message .= "$count Facility Request for FieldTask $fieldTaskId without reservations.\n";
				}

				if($message)
				{
					$message = "There are\n" . $message . "Please reserve parts for these before proceeding.\n\n";
					$errors[] = $message;
				}
			}

			$validationInfo = FacilityRequestLogic::validateTnDnBeforeCreation($noteType, $frIds, $this->getOwnerWarehouse(), $errors, $confirmedDestinationId);

			if (count($errors) == 0)
			{
				$warehouseFrom = $validationInfo['fromWh'];
				$warehouseTo = $validationInfo['toWh'];
				$siteTo = $validationInfo['toSite'];

				$this->warehouseToId->Value = $warehouseTo->getId();
				$this->tnDnNoteType->Value = $noteType;

				//we are still in confrimation mode
				if ($checkTransitNote)
				{
					$this->GenerateTransitNoteBtn->Text = 'Generate ' . $noteType;

					$confirmMessage = "<table width='80%' style='padding:0 10px'>
										<tr><td colspan=4 style='font-weight:bold'>Please review the following before creating " . $noteType . ".</td></tr><tr><td colspan=4>&nbsp;</td></tr> <tr><td colspan=4>&nbsp;</td></tr>";

					$facilityName = $warehouseFrom->getAlias(WarehouseAliasType::$aliasTypeId_facilityName);
		  			if (!is_null($facilityName) && $facilityName != '')
		  				$facilityName = '<br /><span style="font-style:italic;">' . $facilityName . '</span><br />';

					$warehouseFromAddressDetails = '<span style="font-weight:bold;">' . $warehouseFrom->getName() . " <br />" . $facilityName . '</span>' . Factory::service("Address")->getAddressInDisplayFormat($warehouseFrom->getNearestFacility()->getAddress());

					$facilityName = $warehouseTo->getAlias(WarehouseAliasType::$aliasTypeId_facilityName);
		  			if (!is_null($facilityName) && $facilityName != '')
		  				$facilityName = '<br /><span style="font-style:italic;">' . $facilityName . '</span><br />';

		  			$existingTnDnSql = "tn.destinationid=" . $warehouseTo->getId() . " AND tn.noteType IN (" . TransitNote::NOTETYPE_TRANSITNOTE . ',' . TransitNote::NOTETYPE_ASSIGNMENTNOTE . ")";
		  			if ($siteTo instanceof Site)
		  			{
			  			$existingTnDnSql = "tn.destinationsiteid=" . $siteTo->getId() . " AND tn.noteType=" . TransitNote::NOTETYPE_DISPATCHNOTE;
		  				$siteAddress = $siteTo->getServiceAddress();
		  				if ($siteAddress instanceof Address)
		  				{
							$warehouseToAddressDetails = '<span style="font-weight:bold;">Sites<br /><br />' . $siteTo->getCommonName() . " <br />" . $facilityName . '</span>' . Factory::service("Address")->getAddressInDisplayFormat($siteAddress);
		  				}
		  				else
		  				{
							$warehouseToAddressDetails = '<span style="font-weight:bold;color:red;">Site is missing address details.</span>';
		  				}
		  			}
		  			else
		  			{
						$warehouseToAddressDetails = '<span style="font-weight:bold;">' . $warehouseTo->getName() . " <br />" . $facilityName . '</span>' . Factory::service("Address")->getAddressInDisplayFormat($warehouseTo->getNearestFacility()->getAddress());
		  			}

					$confirmMessage .= "<tr>
											<td width='10%'>From:</td><td width='40%'>" . $warehouseFromAddressDetails . "</td>
											<td width='10%'>To:</td><td width='40%'>" . $warehouseToAddressDetails . "</td>
										</tr>
										<tr><td colspan='4'>&nbsp;</td></tr></table>";

					$confirmMessage .= "<table class='DataList' border='0'>
											<thead><tr><th scope='col'><span>
												<table height='25' width='100%'>
													<tbody>
														<tr>
															<th width='12%'>Barcode</th>
															<th width='12%'>For Task #</th>
															<th width='45%'>Partcode - Name</th>
														</tr>
													</tbody>
												</table>
											</span></th></tr></thead>
											<tbody>
												<tr><td class='DataListItem'><span>
												<table height='25' width='100%'>
													<tbody>";

					$warningMsg = '';
					$ftIds = array();
					foreach ($partsAddedToTransit as $rows)
					{
						$serialNo = PartInstanceLogic::getBSorBPFromPartInstance(Factory::service('PartInstance')->getPartInstance($rows['id']));
						$confirmMessage .= "
							<tr bgcolor='black' border='1'>
								<td width='12%'>" . $serialNo . "</td>
								<td width='12%'>" . $rows['fieldTaskId'] . "</td>
								<td width='45%'>" . $rows['partCode'] . " - " . $rows['partName'] . "</td>
							</tr>";
					}

					$confirmMessage .= "<tr><td>&nbsp;</td></tr>
										<tr><td>&nbsp;</td></tr>
									</tbody></table>";

					$sql_checkExistingTN = "SELECT
												tn.transitnoteno as transitNoteNo,
												tn.sourceid as source,
												tn.destinationid as destination,
												tn.destinationsiteid as destinationSiteId,
												tn.created as created,
												tn.createdbyid as createdby
											FROM transitnote tn WHERE
												tn.sourceid=" . $warehouseFrom->getId() . "
												AND $existingTnDnSql
												AND tn.transitnotestatus in ('open')
												AND active=1";
					$results_checkExistingTN = Dao::getResultsNative($sql_checkExistingTN,array(),PDO::FETCH_ASSOC);
					if (count($results_checkExistingTN) > 0)
					{
							$confirmMessage .= "
								<b><span>Existing " . $noteType . "(s):</span></b>
								<table Id='MultipleTNs' class='DataList' border='0'>
									<thead><tr><th scope='col'><span>
										<table height='25' width='100%'>
											<tbody>
												<tr>
													<th width='12%'>&nbsp;</th>
													<th width='12%'>TN/DN No.</th>
													<th width='12%'>Source</th>
													<th width='12%'>Destination</th>
													<th width='21%'>Created By/On</th>
												</tr>
											</tbody>
										</table>
									</th></tr></thead>

									<tbody>
										<tr><td class='DataListItem'><span>
										<table height='25' width='100%'>
											<tbody>";

						foreach($results_checkExistingTN as $tn)
						{
							$createdBy = Factory::service('Useraccount')->getFullName($tn['createdby']);

							if ($tn['destinationSiteId'] !== null)
								$dest = Factory::service('Site')->get($tn['destinationSiteId']);
							else
								$dest = Factory::service('Warehouse')->getWarehouse($tn['destination']);

							$confirmMessage .= "
								<tr>
									<td width='12%'><input type='radio' name='tn'  value=". $tn['transitNoteNo']."></td>
									<td width='12%'>" . $tn['transitNoteNo'] . "</td>
									<td width='12%'>" . Factory::service('Warehouse')->getWarehouse($tn['source']) . "</td>
									<td width='12%'>" . $dest . "</td>
									<td width='21%'>" . $tn['created'] . " - " . $createdBy[0][0] . "</td>
								</tr>";
						}
						$confirmMessage .= "
							<tr>
								<td width='12%'><input type='radio' name='tn'  value='createNew' checked='checked'></td>
								<td colspan=3>Create a Brand New " . $noteType . "</td>
							</tr>";
						$confirmMessage .= "<tr><td>&nbsp;</td></tr>";
						$confirmMessage .= "<tr><td>&nbsp;</td></tr>";

						$confirmMessage .= "<tr><td>&nbsp;</td></tr>";
						$confirmMessage .= "</tbody></table>";

					}

// 					$confirmMessage .= "<table><tbody>";
// 					$confirmMessage .= "<tr><td><b>Warning:</b></td></tr>";
// 					$confirmMessage .= "<tr><td>$warningMsg</td></tr>";
// 					$confirmMessage .= "<tr><td>&nbsp;</td></tr>";
// 					$confirmMessage .= "</tbody></table>";
// 					$confirmMessage .= "	</span></td></tr></tbody></table>";

					$this->transitNoteConfirmLabel->Text = $confirmMessage;
					$this->responseLabel->Text = Core::getJSONResponse(array(), $errors);
					return;
				}
				else
				{
					$this->hideTransitPanel();
                    $movingPartInstances = array();

					if (count($partsAddedToTransit)==0)
					{
						throw new Exception('An unexpected error has occured, There are zero part instances added.');
					}

					foreach($partsAddedToTransit as $rows)
					{
						$movingPartInstances[$rows['id']] = $rows['quantity'];
					}

					$noteTypeId = TransitNote::NOTETYPE_DISPATCHNOTE;
					if ($noteType == 'TN')
						$noteTypeId = TransitNote::NOTETYPE_TRANSITNOTE;

					if ($this->_reportServerRedirectEnabled)
						Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

					if (isset($selectedTransitNoteNo) && !empty($selectedTransitNoteNo) &&  $selectedTransitNoteNo != 'createNew')
					{
						//update existing tn
						$tn = Factory::service('TransitNote')->getTransitNoteByTransitNoteNo($selectedTransitNoteNo);
						if (count($tn) > 0 && $tn[0] instanceof TransitNote)
						{
							$tnDn = TransitNoteLogic::movePartInstancesToTransitNote($movingPartInstances, $tn[0]);
						}
						else
						{
							$tnDn = TransitNoteLogic::createTransitNote($movingPartInstances, $warehouseTo, $noteTypeId, array(), $siteTo);
						}
					}
					else
					{
						//create brand new
						$tnDn = TransitNoteLogic::createTransitNote($movingPartInstances, $warehouseTo, $noteTypeId, array(), $siteTo);
					}

					if (!$tnDn instanceOf TransitNote)
					{
						throw new Exception('An unexpected error has occured, Failed to create ' . $noteType . '.');
					}
					$this->Page->jsLbl->Text = "<script type=\"text/javascript\">openTransitNoteWindow(" . $tnDn->getId() . ");</script>";
				}
			}
		}
		catch(Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}

		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON

		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));

	}

	/**
	 * Create Move to Tech
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function createMoveToTech($sender, $param)
	{
		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

		$result = $errors = array();
		$partsAddedToMove=array();
		$toWarehouseId = "";
		$recipientWarehouse = trim($this->sendTo->getSelectedValue());
		$recipientTechnician = trim($this->recipientTech->getSelectedValue());
		$moveToTech = $param->CallbackParameter->checkMoveToTech;

		$this->ConfirmMoveToTechWindow->Display = "Dynamic";
		$this->ConfirmMoveToTechWindow->Style = "position: fixed;top: 10px;z-index:99;left: 10%; width: 80%; height: 80%;";

		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");

			if(!isset($recipientTechnician) || $recipientTechnician =="")
				throw new Exception("Please provide Recipient Technician.");

			$fromWarehouse = $this->getOwnerWarehouse();
			$userAccount = Factory::service('UserAccount')->getUserAccount($recipientTechnician);
			$techName = Factory::service('UserAccount')->getFullName($recipientTechnician);
			$warehouseTo = Factory::service("Warehouse")->getDefaultMobileWarehouse(Factory::service('UserAccount')->getUserAccount($recipientTechnician));
			$toWarehouseId = $warehouseTo->getId();

			$taskComment="";
			foreach($selectedIds as $frId)
			{
				$fr = Factory::service('FacilityRequest')->getFacilityRequest($frId);
				//take the task
				try
				{
					$fieldTask = $fr->getFieldTask();
					if(is_null($fieldTask->getTechnician()))
					{
						if($fieldTask->getStatus() != 'PENDING')
							throw new Exception('You cannot `Take` this task. The task is in status'.$fieldTask->getStatus());

						$ft = FieldTaskLogic::takeTask($fieldTask,$userAccount);

						if(!is_null($ft))
							$taskComment .= "Task no: ".$ft->getId()." taken successfully. <br/>";
					}
				}
       			catch(Exception $e)
				{
					$errors[] = $e->getMessage()."<br/>";
				}
			}

			//get the base result
			$partsAddedToMove = FacilityRequestLogic::getBaseDataForMoveToTech($selectedIds);
 			if($moveToTech)
			{
				$this->moveToTechWarehouseToId->Value = $toWarehouseId;
			}
			else
			{
				$toWarehouseId = $param->CallbackParameter->moveToTechWarehouseToId;
			}

			//check if the task is owned by the tech
			$warehouseTo = Factory::service("Warehouse")->get($toWarehouseId);
			if($moveToTech)
			{
				$fromWarehouse = $this->getOwnerWarehouse();
				if(isset($recipientWarehouse) && $recipientWarehouse>"")
				{
					$warehouseToNearestFacilityWarehouse = $warehouseTo->getNearestFacilityWarehouse();
					if(!$warehouseToNearestFacilityWarehouse instanceOf Warehouse)
					{
						throw new Exception("The Recipient Warehouse doesn't have a Facility!");
					}
				}
				$confirmMessage = '';
				$confirmMessage .= "<table width='80%' style='padding:0 10px'>
				<tr><td colspan=4 style='font-weight:bold;'><span style='color:green;'>".$taskComment."</span><br />\n\n Please review the following to move part.\n </td></tr><tr><td colspan=4>&nbsp;</td></tr> <tr><td colspan=4>&nbsp;</td></tr>";

 				$facilityName = $fromWarehouse->getAlias(WarehouseAliasType::$aliasTypeId_facilityName);
	  			if (!is_null($facilityName) && $facilityName != '')
	  				$facilityName = '<br /><span style="font-style:italic;">' . $facilityName . '</span><br />';

				$fromWarehouseAddressDetails = '<span style="font-weight:bold;">' . $fromWarehouse->getName(). '</span> <br />' . Factory::service("Address")->getAddressInDisplayFormat($fromWarehouse->getNearestFacility()->getAddress());
				$warehouseToAddressDetails="";
  				$facilityName = $warehouseTo->getAlias(WarehouseAliasType::$aliasTypeId_facilityName);

				if(!is_null($facilityName))
					$warehouseToAddressDetails = '<span style="font-weight:bold;">' . $warehouseTo->getName() . " <br />" . $facilityName . '</span>' . Factory::service("Address")->getAddressInDisplayFormat($warehouseTo->getNearestFacility()->getAddress());
				else
					$warehouseToAddressDetails = '<span style="font-weight:bold;">' . $warehouseTo->getName() .'</span>' ;

				$confirmMessage .= "<tr><td width='10%'>From:</td><td width='40%'>" . $fromWarehouseAddressDetails . "</td>
									<td width='10%'>To:</td><td width='40%'>" . $warehouseToAddressDetails . "</td></tr>
									<tr><td colspan='4'>&nbsp;</td></tr>";

				$confirmMessage .= "</table>";
				$confirmMessage .= "<table class='DataList' border='0'>
					<thead><tr><th scope='col'><span>
						<table height='25' width='100%'>
							<tbody>
								<tr>
									<th width='12%'>For Task #</th>
									<th width='12%'>Barcode</th>
									<th width='45%'>Partcode - Name</th>
								</tr>
							</tbody>
						</table>
					</span></th></tr></thead>
					<tbody>
						<tr><td class='DataListItem'><span>
						<table height='25' width='100%'>
							<tbody>";

				foreach($partsAddedToMove as $rows)
				{
					$confirmMessage .= "
						<tr>
							<td width='12%'>" . $rows['fieldTaskId'] . "</td>
							<td width='12%'>" . $rows['serial'] . "</td>
							<td width='45%'>" . $rows['partCode'] . " - " . $rows['partName'] . "</td>
						</tr>";
				}

				$confirmMessage .= "</tbody></table>
					</span></td>
				</tr></tbody></table>";
				$this->moveToTechConfirmLabel->Text = $confirmMessage;
				$this->responseLabel->Text = Core::getJSONResponse(array(), $errors);
				return;
			}
  			else
			{
				$this->hideMoveToTechPanel();
				$movingPartInstances = array();

				$destinationLocation = Factory::service("Warehouse")->get($toWarehouseId);
				if(!$destinationLocation instanceOf Warehouse){
					throw new Exception('An unexpected error has occured, The destination is invalid.');
				}

				$infoMsg='';
				foreach ($partsAddedToMove as $row)
				{
					$comments = "Moved Via Reserved Parts(Move To Tech) for FR:".$row["FRId"];
					try{
						$partInstance = Factory::service('PartInstance')->get($row["piId"]);
						$moveToTechBoot = Factory::service("PartInstance")->movePartInstanceToWarehouse($partInstance, $partInstance->getQuantity(), $destinationLocation, false, null, $comments, false, null, false);
						$infoMsg .= "Part::: ".$row['serial']." ::: " . $row['partCode'] . " ::: " . $row['partName'] . "\n";

						//update the comments
						$fr = Factory::service('FacilityRequest')->getFacilityRequest($row["FRId"]);
						Factory::service("FacilityRequest")->addComments($fr, "Parts picked up by ".$techName[0][0]." via Reserve Parts");
						//$this->hideMoveToTechPanel();
					}
	       			catch(Exception $e){
						$errors[] = $ex->getMessage()."<br/>";
					}

				}
				$this->setInfoMessage($infoMsg);
				$errors[] = "Below part(s) moved sucessfully to ".$destinationLocation->getName().":\n\n".$infoMsg;
			}
		}
		catch(Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}

		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON

		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

	/**
	 * refereshing the result table
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @return void|multitype:string
	 */
	public function refreshResult($sender, $param)
	{
		$result = $errors = array();
		try
		{
			$orderField = 'slaEnd';
			$orderDirection = 'asc';
			if(!isset($param->CallbackParameter->searchRequest))
			{
				$orderField = (isset($param->CallbackParameter->searchRequest->sortingField) && ($newOrderF = trim($param->CallbackParameter->searchRequest->sortingField) !== '')) ? $newOrderF : $orderField;
				$orderDirection = (isset($param->CallbackParameter->searchRequest->sortingDirection) && ($newOrderD = trim($param->CallbackParameter->searchRequest->sortingDirection) !== '')) ? $newOrderD : $orderDirection;
			}

			$totalRows = 0;
			if(isset($ids)&&count($ids)>0)
				$result['requests'] = $this->_getData(false,null, $totalRows, $orderField, $orderDirection, $ids);
			else
				$result['requests'] = $this->_getData(false,null, $totalRows, $orderField, $orderDirection, null,$this->timeOfSearch->Value);

			if(!$this->showingReloadMessage->Value)
			{
				$checkNewResult = $this->_getData(false,1, $totalRows, $orderField, $orderDirection,null,$this->timeOfSearch->Value);
				if(count($checkNewResult)>0)
				{
					$result['hasnew'] = true;
					$newFrIds = array();
					foreach($checkNewResult as $row)
						$newFrIds[] = $row['id'];

					$result['hasnew_frId'] = implode(",",$newFrIds);
					$this->showingReloadMessage->Value = '1';
				}
			}
		}
		catch(Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}
		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

	/**
	 * take FR into current user's account
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @return void|multitype:string
	 */
	public function takeFR($sender, $param)
	{
		$result = $errors = array();
		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0){
				throw new Exception("Please select a request first!");
			}

			if ($this->_reportServerRedirectEnabled)
				Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

			$warehouse = $this->getOwnerWarehouse();
			foreach($selectedIds as $selectedId)
			{
				if(!($fr = Factory::service("FacilityRequest")->get($selectedId)) instanceof FacilityRequest)
				{
					continue;
				}
				if($warehouse instanceOf Warehouse)
				{
					Factory::service("FacilityRequest")->takeFR($fr, $warehouse, "(TAKE)");
				}
				else
				{
					throw new Exception("Invalid Owner Warehouse!");
				}

			}
			$totalRows = 0;
		}
		catch(Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}

		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON

		if (empty($errors))
			$result['requests'] = $this->_getData(false, null ,$totalRows, 'slaEnd', 'asc', $selectedIds,'',$warehouse);

		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

	/**
	 * push FR into somewhere else
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @return void|multitype:string
	 */
	public function pushFR($sender, $param)
	{
		$result = $errors = array();
		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0)
				throw new Exception("Please select a request first!");

			if(isset($param->CallbackParameter->info->newOwnerId) && $param->CallbackParameter->info->newOwnerId==-1)
				throw new Exception("Please select a Location!");

			if(!isset($param->CallbackParameter->info) || !isset($param->CallbackParameter->info->newOwnerId) || ($newWarehouseId = trim($param->CallbackParameter->info->newOwnerId)) === '')
				throw new Exception("Please select a new warehouse to push to first!");

			if(!($newWarehouse = Factory::service("Warehouse")->getWarehouse($newWarehouseId)) instanceof Warehouse)
				throw new Exception("Invalid warehouse selected as a new warehouse (ID=$newWarehouseId)!");

			$comments = '(PUSH): ';
			if(isset($param->CallbackParameter->info->comments) && $param->CallbackParameter->info->comments)
			{
				$comments .= $param->CallbackParameter->info->comments;
			}

			if ($this->_reportServerRedirectEnabled)
				Dao::prepareNewConnection(Dao::DB_MAIN_SERVER); //reconnect to main server, if redirection is ON

			$warehouseOwner = $this->getOwnerWarehouse();
			foreach($selectedIds as $selectedId)
			{
				if(!($fr = Factory::service("FacilityRequest")->get($selectedId)) instanceof FacilityRequest)
					continue;

				if($fr->getOwner()!= $warehouseOwner)
				{
					Factory::service("FacilityRequest")->takeFR($fr, $warehouseOwner, "(TAKE)");
					sleep(1);
				}
				//send Email
				$checkSendEmail=Factory::service('WarehouseRelationship')->retrieveSendEmail($warehouseOwner, $newWarehouse, WarehouseRelationship::TYPE_FRLIST);
				if($checkSendEmail)
					$sendEmail = (intval($checkSendEmail[0]->getSendEmail()) == 1?true:false);
				else
					$sendEmail = false;

				//push FR
				Factory::service("FacilityRequest")->pushFR($fr, $warehouseOwner, $newWarehouse,$comments,$sendEmail);
			}
			$totalRows = 0;
		}
		catch(Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}

		if ($this->_reportServerRedirectEnabled)
			Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON

		if (empty($errors))
			$result['requests'] = $this->_getData(false,null ,$totalRows, 'slaEnd', 'asc', $selectedIds);

		$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
	}

	/**
	 * showing pushing FR panel div
	 *
	 * @param TActiveButton $sender The clicked button
	 * @param Mixed         $param  The parameters that sent along with this event
	 *
	 * @return void|multitype:string
	 */
	public function showPushFR($sender, $param)
	{
		$html = '';
		try
		{
			if(!isset($param->CallbackParameter->selectedIds) || count($selectedIds = ($param->CallbackParameter->selectedIds)) === 0){
				throw new Exception("Please select a request first!");
			}

			$this->_ownerWarehouse = $this->getOwnerWarehouse();
			$lastPushLocation = '';
			if(isset($param->CallbackParameter->lastPushLocation) && $param->CallbackParameter->lastPushLocation!=-1){
				$lastPushLocation = $param->CallbackParameter->lastPushLocation;
			}

			//checking validation for each requests
			$errors = array();
			foreach($selectedIds as $id)
			{
				try
				{
					if(!($request = Factory::service("FacilityRequest")->get($id)) instanceof FacilityRequest){
						throw new Exception("Invalid request(ID=$id)!");
					}
				}
				catch(Exception $e)
				{
					$emsg = '<div>';
					$emsg .= "<a href='javascript: void(0);' onclick=\"$$('tr[requestid=$id]').first().scrollTo(); return false;\" title='Click here to scroll to that request'>Request</a>: ";
					$emsg .= $e->getMessage();
					$emsg .= '</div>';

					$errors[] = $emsg;
				}
			}
			if(count($errors) > 0)
				throw new Exception(implode("", $errors));

			$html = '<div pushfrdiv="pushfrdiv">';
			$html .= '<div>';
			$html .= 'Push To:';
			$html .= '<select pushfrdiv="newOwnerId">';
			$html .= '<option value="-1">Select Location</option>';

			if(is_array($this->getPushList()))
			{
				foreach($this->getPushList() as $newOwner)
				{
					if($lastPushLocation == $newOwner->getId()){
						$html .= '<option value="' . $newOwner->getId() . '" selected>' . $newOwner->getName() . '</option>';
					}
					else{
						$html .= '<option value="' . $newOwner->getId() . '">' . $newOwner->getName() . '</option>';
					}
				}
			}
			else
			{
				if($this->getPushList())
					$html .= '<option value="' . $this->getPushList()->getId() . '" selected>' . $this->getPushList()->getName() . '</option>';
				else
					$html .= '<option value="-2">No Location Found.</option>';
			}

			$html .= '</select>';
			$html .= '</div>';
			$html .= '<div>';
			$html .= 'Reason / Comments:<br />';
			$html .= '<textarea pushfrdiv="comments" style="width: 95%">';
			$html .= '</textarea>';
			$html .= '</div>';
			$html .= '<div>';
			$html .= "<input type='button' onclick=\"pageJs.pushFR('" . $this->pushFRBtn->getUniqueID() . "', '" . implode(",", $selectedIds). "', this); return false;\" value='Push Selected Request(s)'>";
			$html .= "<input type='button' onclick=\"Modalbox.hide(); return false;\" value='Cancel'>";
			$html .= '</div>';
			$html .= '</div>';
		}
		catch(Exception $ex)
		{
			$html = "<b style='color: red;'>" . $ex->getMessage() . "</b>";
		}
		$this->responseLabel->Text = $html;
	}

	/**
	 * Get the Push list
	 *
	 * @return unknown
	 */
	private function getPushList()
	{
		$pushList = array();
		$this->_ownerWarehouse = $this->getOwnerWarehouse();
		foreach(Factory::service('WarehouseRelationship')->retrieveToWarehouseDetails($this->_ownerWarehouse) as $w)
		{
			$pushList[] = $w->getId();
		}

		$warehouses = array();
		if(count($pushList) > 0)
		{
			$warehouses = Factory::service("Warehouse")->findByCriteria(" id in (" . implode(",",$pushList) . ")");
		}
		else
		{
			//if it's workshop
			$workshopWarehouse = $this->_ownerWarehouse->getWarehouseCategory()->getId(WarehouseCategoryService::$categoryId_Workshop);
			if($workshopWarehouse)
			{
				$warehouses = $this->_ownerWarehouse->getNearestFacilityWarehouse(true);
			}
		}
		return $warehouses;
	}

	/**
	 * Print Excel
	 *
	 * @param unknown_type $results
	 * @return unknown
	 */
	private function printExcel($results)
	{
		$output = "<table class=\"DataList\" border=\"1\">";
		$output .=  "<tr><th><b>Part Code</b></th>";
		$output .=  "<th><b>Part Required (Qty)</b></th>";
		$output .=  "<th><b>Part Name</b></th>";
		$output .=  "<th><b>Field Task ID</b></th>";
		$output .=  "<th><b>Field Task Status</b></th>";
		$output .=  "<th><b>Site</b></th>";
		$output .=  "<th><b>WorkType</b></th>";
		$output .=  "<th><b>ZoneSet</b></th>";
		$output .=  "<th><b>SLA End</b></th>";
		$output .=  "<th><b>Priority</b></th>";
		$output .=  "<th><b>Avail (Good)</b></th>";
		$output .=  "<th><b>Avail (Other)</b></th>";
		$output .=  "<th><b>Rsvd</b></th>";
		$output .=  "<th><b>Status</b></th>";
		$output .=  "<th><b>Updated Elapsed Time</b></th>";
		$output .=  "<th><b>Owner</b></th>";
		$output .=  "<th><b>State</b></th></tr>";

		foreach($results as $rows)
		{
			$output .=  "<tr><td>" . $rows['partCode'] . "</td>";
			$output .=  "<td>" . $rows['qty'] . "</td>";
			$output .=  "<td>" . $rows['partName'] . "</td>";
			$output .=  "<td>" . $rows['fieldTaskId'] . "</td>";
			$output .=  "<td>" . $rows['taskStatus'] . "</td>";
			$output .=  "<td>" . $rows['site'] . "</td>";
			$output .=  "<td>" . $rows['worktype'] . "</td>";
			$output .=  "<td>" . $rows['zoneset'] . "</td>";
			$output .=  "<td>" . $rows['slaEnd'] . "</td>";
			$priority  = $this->_getFRPriorityDetails($rows['slaEnd']);
			$output .=  "<td>" . $priority['priority'] . "</td>";

			$availQty = split(":",$rows['availQty']);
			$availQtyOther = "";
			$availQtyGood = "";
			if(count($availQty)==2)
			{
				$availQtyGood = $availQty[0];
        		$availQtyOther = $availQty[1];
			}
			$output .=  "<td>" . $availQtyGood  . "</td>";
			$output .=  "<td>" . $availQtyOther . "</td>";

			$output .=  "<td>" . $rows['reserved'] . "</td>";
			$output .=  "<td>" . $rows['status'] . "</td>";
			$output .=  "<td>" . $rows['updatedElapsedTime'] . "</td>";
			$output .=  "<td>" . $rows['owner'] . "</td>";
			$output .=  "<td>" . $rows['stateName'] . "</td></tr>";
		}

		$output .= "</table>";
		return $output;
	}

	/**
	 * Output To Excel
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function outputToExcel($sender, $param)
	{
		$results = $this->_getData(true, null);

		header("Pragma: public");
		header("Expires: 0");
		header("Cache-Control: must-revalidate, post-check=0, pre-check=0");
		header('Content-Type: application/vnd.ms-excel;charset=UTF-8');
		header("Content-Disposition: filename=ReserveParts.xls");
		header("Content-Transfer-Encoding: binary");
		echo $this->printExcel($results);
		die;
	}

	/**
	 *	Suggest Recipient Technician
	 */
	public function suggestRecipientTechnician($searchString)
	{
		$result =  WarehouseLogic::getRecipientTechnician($searchString);
		if(count($result)>0)
			return $result;
		else
			return array(array("-1", "No Results Found..."));
	}

	/**
	 * Get Factility Request Priority Details.
	 *
	 * @param String timestamp $slaEnd
	 * @param String timezone $timeZone
	 * @return Array
	 */
	private function  _getFRPriorityDetails($slaEnd,$timeZone='Australia/Melbourne')
	{
		$slaEnd=substr($slaEnd,0,19);//get the timestamp only
		$priorityInfoArray = FacilityRequestLogic::getFacilityRequestPrioritiesFromSLA($slaEnd,$timeZone,null, $this->_frPriorities);
		/*
		 Sample $priorityInfoArray format
		array(1) {
		["P1"]=>
		array(3) {
		["colour"]=>
		string(7) "#FF6600"
		["weekdays"]=>
		string(1) "1"
		["title"]=>
		string(62) "SLA expires before '2015-02-23 12:00:00' (Australia/Melbourne)"
		}
		}
		*/
		$priorityInfo=array();
		if(count($priorityInfoArray)>0 && sizeof($priorityInfoArray)>'')
		{
			foreach($priorityInfoArray as $key => $priority)
			{
				$priorityInfo['priority'] = $key;
				$priorityInfo['colour'] = (isset($priority['colour'])&&$priority['colour']>'' ? $priority['colour'] : 'blue');
				$priorityInfo['title'] = (isset($priority['title'])&&$priority['title']>'' ? strip_tags($priority['title']) : 'No Title Available');
			}
		}
		return $priorityInfo;
	}
}

?>
