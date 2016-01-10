<?php
//ini_set("max_execution_time", 60);
/**
 * Tranist Note Search Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class ConsignmentController extends CRUDPage
{
	/**
	 * @var totalNoOfTNs
	 */
	public $totalNoOfTNs;

	/**
	 * @var $dropDownFilters
	 */
	private $dropDownFilters;

	/**
	 * @var $nabId
	 */
	private $nabId = 48538; //hacked for NAB again.........

	/**
	 * @var $workBook
	 */
	private $workBook;

	/**
	 * @var $querySize
	 */
	protected $querySize;

	/**
	 * @var totalNoOfTNs
	 */
	protected $excelStyles;

	/**
	 * @var totalNoOfTNs
	 */
	private $sortLabels = array("issueDate" => "Dispatched",
								"sourceId" => "Source",
								"destinationId" => "Destination",
								"courier" => "Courier",
								"status" => "Status");

	/**
	 * On Pre Initialization
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
		$str = explode('/',$_SERVER['REQUEST_URI']);
		if ($str[1] == 'staging')
		{
			$this->getPage()->setMasterClass("Application.layouts.StagingLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_consignment,menu_staging";
			$this->menuContext = 'staging/consignment';
		}
		else
		{
			$this->getPage()->setMasterClass("Application.layouts.LogisticsLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_consignment";
			$this->menuContext = 'consignment';
		}

	}

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_consignment,menu_staging";
		$this->workBook = new HYExcelWorkBook();
		$this->initStyles();
	}

	/**
	 * Reset Report Values
	 *
	 */
	private function resetReportValues(){
		$this->predictResultBtn->Visible = true;
		$this->toExcelBtn->Visible = false;
    	$this->backPredictResultBtn->Visible = false;
        $this->searching->setStyle("display:none;");
        $this->masker->Visible = false;
        $this->jsLbl->Text = "";
    	$this->predictResultBox->setText("");
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
        	$this->resetReportValues();
        	$this->sortBy->Value="id";
        	$this->sortOrd->Value="desc";

         	$defaultWarehouse = Factory::service("Warehouse")->getDefaultWarehouse(Core::getUser());
			if(!$defaultWarehouse instanceof Warehouse)
			{
				$this->setErrorMessage("No Default Warehouse is set up, Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");
				$this->MainContent->Enabled=false;
				return;
			}
			$viewWarehouseFilterArray =  Factory::service("Warehouse")->getViewWarehouseFilters();
			if(count($viewWarehouseFilterArray)>0){
				$this->viewWhFilter->Value = implode(",",$viewWarehouseFilterArray);
			}

// 			removing this as we only have to look under the view warehouse filter
// 			$filterArray = explode(',', $this->viewWhFilter->Value);
// 			if (in_array($this->nabId, $filterArray)) //HACKED for NAB EXTERNAL
//     		{
// 				$this->nabFrom->Value = $this->getNabExternalDropDowns('from');
// 	    		$this->nabTo->Value = $this->getNabExternalDropDowns('to');
//     		}

	       	$this->bindStatusList();
			$this->TransitNoteNo->focus();
        }
    }

    /**
     * Get Data Parts Movement
     *
     * @param unknown_type $countOnly
     * @param unknown_type $userArgs
     * @return unknown
     */
	protected function getData_partsMovement($countOnly, $userArgs)
    {
    	$transitNoteType = '';
    	if ($this->dNoteRadio->Checked)
    	{
    		$transitNoteType = TransitNote::NOTETYPE_DISPATCHNOTE;
    	}
    	else if ($this->tNoteRadio->Checked)
    	{
    		$transitNoteType = TransitNote::NOTETYPE_TRANSITNOTE;
    	}
    	else if ($this->aNoteRadio->Checked)
    	{
    		$transitNoteType = TransitNote::NOTETYPE_ASSIGNMENTNOTE;
    	}

    	$results = TransitNoteLogic::getPartsMovementResults($countOnly, $userArgs, $this->querySize, $this->getLocalTimeZone(),$transitNoteType,$this->viewWhFilter->Value);
    	return $results;
    }


    /**
     * Get NAB External Dropdowns
     *
     * @param unknown_type $dir
     * @return unknown
     */
    private function getNabExternalDropDowns($dir) //get all wh's bytecraft dandenong + under nab ext who is siteWH or buffer
    {
    	$fil = array();
    	$cats = ($dir == 'from' ? '14' : '11,14,20,19');
    	$sql = "SELECT id, name
    	FROM warehouse
    	WHERE id=39 OR
    	(position like '" . Factory::service("Warehouse")->getWarehouse($this->nabId)->getPosition() . "%' and
    	active=1
    	and warehousecategoryid IN ($cats)
    	and (facilityid is not null or parts_allow=1)) order by name;";
    	$results = Dao::getResultsNative($sql, array(), PDO::FETCH_ASSOC);
    	$ret = array();
    	foreach ($results as $r)
    	{
			$ret[] = $r['id'];
    	}
    	return implode(',',$ret);
    }

    /**
     * Bind Status List
     *
     */
    private function bindStatusList()
    {
    	$status=array();
       	$status[]=array("id"=>TransitNote::STATUS_OPEN,"name"=>TransitNote::STATUS_OPEN);
       	$status[]=array("id"=>TransitNote::STATUS_TRANSIT,"name"=>TransitNote::STATUS_TRANSIT);
       	$status[]=array("id"=>TransitNote::STATUS_CLOSE,"name"=>TransitNote::STATUS_CLOSE);

       	$this->StatusList->DataSource =  $status;
       	$this->StatusList->DataBind();
    }

    /**
     * To Perform Search
     *
     * @return unknown
     */
	protected function toPerformSearch()
    {
    	if(trim($this->TransitNoteNo->Text)!="")
    		return false;
    	if(trim($this->TransitNoteFromList->getSelectedValue())!="")
    		return false;
    	if(trim($this->TransitNoteToList->getSelectedValue())!="")
    		return false;
    	if(trim($this->StatusList->getSelectedValue())!="")
    		return false;

    	if(trim($this->courierRef->Text)!="")
    		return false;
    	if(trim($this->fromDate->Text)!="")
    		return false;
    	if(trim($this->toDate->Text)!="")
    		return false;
    	if(trim($this->dispatchedPerson->getSelectedValue())!="")
    		return false;

    	return true;
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
    	$this->resetReportValues();
    	//---
    	$sortBy =  trim($this->sortBy->Value);
    	$sortOrd =  trim($this->sortOrd->Value);
    	$sortStr = " Order by tn.".$sortBy ." ". $sortOrd . " ";
    	//--
    	$transitNote=trim($this->TransitNoteNo->Text);
    	$source=trim($this->TransitNoteFromList->getSelectedValue());
    	$destination=trim($this->TransitNoteToList->getSelectedValue());
    	$status=trim($this->StatusList->getSelectedValue());
    	$courierRef = trim($this->courierRef->Text);
    	$toSiteId = trim($this->siteListId->getSelectedValue());

    	$searchTechId = "";
		if(trim($this->searchTech->getSelectedValue()))
		{
    		$warehouseIds = explode('/', trim($this->searchTech->getSelectedValue()));
			$searchTechId = end($warehouseIds);
		}

    	$defaultWarehouseTimeZone = Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
    	$fromDate = trim($this->fromDate->Text);
    	if($fromDate!="")
    	{
	    	$fromDate= new HydraDate($fromDate,$defaultWarehouseTimeZone);
	    	$fromDate->setTimeZone();
	    	$fromDate = $fromDate->getDateTime()->format("Y-m-d H:i:s");
    	}

    	$toDate = trim($this->toDate->Text);
    	if($toDate!="")
    	{
	    	$toDate= new HydraDate($toDate,$defaultWarehouseTimeZone);
	    	$toDate->setTimeZone();
	    	$toDate = $toDate->getDateTime()->format("Y-m-d H:i:s");
    	}

    	$dispatchedUserAccountId = trim($this->dispatchedPerson->getSelectedValue());

    	$where = "tn.active = 1";
    	if($transitNote!="")
    	{
    		$where .=" AND ucase(tn.transitNoteNo) = ucase('$transitNote')";
    	}

    	if ($source!="")
    	{
    		$where .=" AND tn.sourceId = $source";
    	}
    	if ($destination!="")
    	{
    		$where .=" AND tn.destinationId = $destination";
    	}

    	//we have removed the nab hack as a all we need to do is check that the source or destination are within the view filters
    	if($this->viewWhFilter->Value)
    	{
	    	$where .=" AND (tn.sourceId IN (" . $this->viewWhFilter->Value . ") OR tn.destinationId IN (" . $this->viewWhFilter->Value . "))";
    	}

    	if($status!="")	$where .=" AND ucase(tn.transitNoteStatus) = ucase('$status')";
    	if($courierRef!="")	$where .=" AND ucase(tn.CourierJobNo) = ucase('$courierRef')";
    	if($fromDate!="")	$where .=" AND tn.issueDate >= '$fromDate'";
    	if($toDate!="")	$where .=" AND tn.issueDate <= '$toDate'";
    	if($dispatchedUserAccountId!="")	$where .=" AND tn.updatedById = $dispatchedUserAccountId";

    	if($toSiteId!="")	$where .=" AND tn.destinationsiteid = $toSiteId";

    	if($searchTechId!="")	$where .=" AND (tn.destinationid = $searchTechId OR tn.sourceid = $searchTechId )";

    	if ($this->dNoteRadio->Checked)
    	{
    		$where .= " AND tn.noteType = " . TransitNote::NOTETYPE_DISPATCHNOTE;
    	}
    	else if ($this->tNoteRadio->Checked)
    	{
    		$where .= " AND tn.noteType = " . TransitNote::NOTETYPE_TRANSITNOTE;
    	}
    	else if ($this->aNoteRadio->Checked)
    	{
    		$where .= " AND tn.noteType = " . TransitNote::NOTETYPE_ASSIGNMENTNOTE;
    	}

    	if (!Session::checkRoleFeatures(array('pages_all','pages_logistics','feature_displayAssignmentNotes')))
    	{
    		$where .= " AND tn.noteType != " . TransitNote::NOTETYPE_ASSIGNMENTNOTE;
    	}

    	$sql= $this->getQuery(" tn.id,
				    			tn.transitNoteNo,
				    			tnw_d.name `destination`,
				    			tnf_d.addressId `destinationAddrId`,
				         		tnw_s.name `source`,
				    			tnf_s.addressId `sourceAddrId`,
				    			tn.courier,
				    			tn.CourierJobNo,
				    			tn.destinationId,
				    			tn.destinationSiteId,
				    			tn.transitNoteStatus,
				    			wa12d.alias `facilityName_d`,
				    			wa12s.alias `facilityName_s`",$where,(($pageNumber-1) * $pageSize).", $pageSize",$sortStr,false);

    	$data =  Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC);

//     	Debug::inspect($sql);

    	//count all matched transit notes
    	$sql= $this->getQuery(" count(distinct(tn.id))",$where,'','',true);
    	$count =  Dao::getResultsNative($sql);
    	$this->totalNoOfTNs = $count[0][0];

    	return $data;
    }

    /**
     * Init Styles
     *
     */
	protected function initStyles()
    {
    	$style = new HYExcelStyle('sheetTitle');
    	$style->setFont("Swiss", "#000000", true, false, 14);
    	$style->setAlignment("Left", "Bottom", false);
    	$this->excelStyles['sheetTitle'] = $style;

    	$style = new HYExcelStyle('normal');
    	$style->setFont("Swiss", "#000000", false, false, 8);
    	$style->setAlignment("Left", "Bottom", false);
    	$this->excelStyles['normal'] = $style;

    	$style = new HYExcelStyle('tabHead');
    	$style->setBorder("all", '#666666');
    	$style->setFont("Swiss", "#FFFFFF", true, false, 8);
    	$style->setInterior("#333333");
    	$this->excelStyles['tabHead'] = $style;

    	$style = new HYExcelStyle('tabCell');
    	$style->setBorder("all", '#666666');
    	$style->setFont("Swiss", "#000000", false, false, 8);
    	$style->setInterior("#FFFFFF");
    	$style->setAlignment("Left", "Bottom", false);
    	$this->excelStyles['tabCell'] = $style;

    	$style = new HYExcelStyle('tabCellInt');
    	$style->setBorder("all", '#666666');
    	$style->setFont("Swiss", "#000000", false, false, 8);
    	$style->setAlignment("Right");
    	$style->setInterior("#FFFFFF");
    	$this->excelStyles['tabCellInt'] = $style;


    }

    /**
     * Get Report Data
     *
     * @param unknown_type $countOnly
     * @return unknown
     */
    public function getReportData($countOnly){
    	$userArgs = $this->getArguments();
		return  $this->getData_partsMovement($countOnly, $userArgs);
    }

    /**
     * Get Arguments
     *
     * @return unknown
     */
    private function getArguments(){
    	$userArgs = array();

		$userArgs['sourceWarehouseName'] = "";
		$userArgs['destinationWarehouseName'] = "";
		$userArgs['destination'] = "";
		$userArgs['source'] = "";
    	$userArgs['toDateStr'] = "";
    	$userArgs['fromDateStr'] = "";
    	$userArgs['toSiteId'] = "";
    	$userArgs['searchTechId'] = "";

   	 	$searchTechId = "";
		if(trim($this->searchTech->getSelectedValue()))
		{
    		$warehouseIds = explode('/', trim($this->searchTech->getSelectedValue()));
			$searchTechId = end($warehouseIds);
			$userArgs['searchTechId'] = $searchTechId;
		}

    	$toSiteId = trim($this->siteListId->getSelectedValue());
    	$transitNote=trim($this->TransitNoteNo->Text);
	    $source=trim($this->TransitNoteFromList->getSelectedValue());
	    $destination=trim($this->TransitNoteToList->getSelectedValue());
	    $status=trim($this->StatusList->getSelectedValue());
	    $courierRef = trim($this->courierRef->Text);
	    $defaultWarehouseTimeZone = $this->getLocalTimeZone();
		$fromDate = trim($this->fromDate->Text);

	    if($fromDate!="")
	    {
	    	$userArgs['fromDateStr'] = $fromDate;
		   	$fromDate= new HydraDate($fromDate,$defaultWarehouseTimeZone);
		   	$fromDate->setTimeZone();
		   	$fromDate = $fromDate->getDateTime()->format("Y-m-d H:i:s");
		   	$fromDate = preg_replace(array('/-/', '/:/', '/\s/'), array('', '', ''), $fromDate);
	    }

	    $toDate = trim($this->toDate->Text);
	    if($toDate!="")
	    {
	    	$userArgs['toDateStr'] = $toDate;
		   	$toDate= new HydraDate($toDate,$defaultWarehouseTimeZone);
		   	$toDate->setTimeZone();
		   	$toDate = $toDate->getDateTime()->format("Y-m-d H:i:s");
		   	$toDate = preg_replace(array('/-/', '/:/', '/\s/'), array('', '', ''), $toDate);
	   	}
	   	$dispatchedUserAccountId = trim($this->dispatchedPerson->getSelectedValue());

	   	if(trim($destination)!="")
	   		$userArgs['destination'] = $destination;

	   	if(trim($source)!="")
	   		$userArgs['source'] = $source;

    	if($status!="")$userArgs['status'] = $status;
    	if($courierRef!="")$userArgs['courierRef'] = $courierRef;
    	if($fromDate!="")$userArgs['fromDate'] = $fromDate;
    	if($toDate!="")$userArgs['toDate'] = $toDate;
    	if($transitNote!="")$userArgs['transitNoteNo'] = $transitNote;
    	if($dispatchedUserAccountId!="")$userArgs['dispatchedUserAccountId'] = $dispatchedUserAccountId;

    	$userArgs['toSiteId'] = $toSiteId;

    	return $userArgs;
    }

    /**
     * Add Blank Row
     *
     * @param unknown_type $height
     * @return unknown
     */
	protected function addBlankRow($height="")
    {
    	$blankRow = new HYExcelRow($height);
		$blankRow->addCell(new HYExcelCell("","String"));
		return $blankRow;
    }

    /**
     * Add Worksheet Parts Movement
     *
     * @param unknown_type $results
     * @param unknown_type $userArgs
     */
    protected function addWorksheet_partsMovement($results, $userArgs)
    {
		$workSheet = new HYExcelWorkSheet("Parts Movement");
		$headers = array(
				'Movement Time ('.$this->getLocalTimeZone().')' => 94,
				'Make' => 60,
				'Model' => 60,
				'Serial Number' => 70,
				'Partcode' => 50,
				'Part Name' => 170,
				'Previous Part Status' => 70,
				'New Part Status' => 70,
				'Quantity' => 50);
		/*
		if ($this->ShowKitOptions->getSelectedValue() == self::SK_PARTS_IN_KIT)
		{
			$headers = array_merge($headers, array(
					'SubPart Serial Number' => 70,
					'SubPart Partcode' => 50,
					'SubPart Part Name' => 170));
		}
		*/
		$headers = array_merge($headers, array(
				'From' => 180,
				'To' => 180,
				'To Site' => 120,
				'PO Number' => 60,
				'Client Asset No' => 60,
				'Manufacturer Serial No' => 90,
				'Manufacturer' => 100,
				'Warranty Details' => 60,
				'Ownership' => 80,
				'Moved By' => 100,
				'Comments' => 200,
				'Transit Note No' => 80,
				'Action' => 80,
				'Field Task Number' => 60,
				'Client Ref No' => 60,
				'Courier Con note Number' => 60,
				'Courier' => 60,
				'comments' => 60
			));

		$headers['Supplier Asset No'] = 60;
		$headers['UUID'] = 60;
		$headers['Govt ID'] = 60;
		$headers['IMEI'] = 60;

		$rowCount = 1;

		$row = new HYExcelRow();
		$cell = new HYExcelCell("Parts Movements", "String", $this->excelStyles['sheetTitle']);
		$row->addCell($cell);
		$workSheet->addRow($row);
		$rowCount++;


		$warehouseFrom = null;
		$warehouseTo = null;


		if (!empty($userArgs['source']))
			$warehouseFrom = Factory::service("Warehouse")->getWarehouse($userArgs['source']);

		if($warehouseFrom instanceof warehouse){
			$userArgs['sourceWarehouseName']	= $warehouseFrom->getName();
		}

		if (!empty($userArgs['destination']))
			$warehouseTo = Factory::service("Warehouse")->getWarehouse($userArgs['destination']);

		if($warehouseTo instanceof warehouse){
			$userArgs['destinationWarehouseName']	= $warehouseTo->getName();
		}

		$userArgs['dispatchedByName'] = "";
		if (!empty($userArgs['dispatchedUserAccountId'])){

			$qry = Factory::service("UserAccount")->getFullName($userArgs['dispatchedUserAccountId']);
			foreach($qry as $user)
	    	{
	    		$userArgs['dispatchedByName'] = $user[0];
	    	}

		}

		$fromDateStr = $userArgs['fromDateStr'];
		$toDateStr = $userArgs['toDateStr'];

		$transitNoteNo = "all";
		if (!empty($userArgs['transitNoteNo']))
			$transitNoteNo = $userArgs['transitNoteNo'];
		$row = new HYExcelRow();
		$row->addCell(new HYExcelCell("Transit Note No", "String", $this->excelStyles['normal']));
		$row->addCell(new HYExcelCell($transitNoteNo, "String", $this->excelStyles['normal']));
		$workSheet->addRow($row);
		$rowCount++;


		$courierRef = "any";
		if (!empty($userArgs['courierRef']))
			$courierRef = $userArgs['courierRef'];
		$row = new HYExcelRow();
		$row->addCell(new HYExcelCell("Courier Ref", "String", $this->excelStyles['normal']));
		$row->addCell(new HYExcelCell($courierRef, "String", $this->excelStyles['normal']));
		$workSheet->addRow($row);
		$rowCount++;


		$status = "";
		if (!empty($userArgs['status']))
			$status = $userArgs['status'];
		$row = new HYExcelRow();
		$row->addCell(new HYExcelCell("Status", "String", $this->excelStyles['normal']));
		$row->addCell(new HYExcelCell($status, "String", $this->excelStyles['normal']));
		$workSheet->addRow($row);
		$rowCount++;


		$dispatchedByName = "";
		if (!empty($userArgs['dispatchedByName']))
			$dispatchedByName = $userArgs['dispatchedByName'];
		$row = new HYExcelRow();
		$row->addCell(new HYExcelCell("Dispatched By", "String", $this->excelStyles['normal']));
		$row->addCell(new HYExcelCell($dispatchedByName, "String", $this->excelStyles['normal']));
		$workSheet->addRow($row);
		$rowCount++;


		$row = new HYExcelRow();
		$row->addCell(new HYExcelCell("Period", "String", $this->excelStyles['normal']));
		$row->addCell(new HYExcelCell("Between $fromDateStr and $toDateStr (inclusive)", "String", $this->excelStyles['normal']));
		$workSheet->addRow($row);
		$rowCount++;



		$fromWarehouseName = "anywhere";
		if (!empty($userArgs['sourceWarehouseName']))
			$fromWarehouseName = $userArgs['sourceWarehouseName'];
		$row = new HYExcelRow();
		$row->addCell(new HYExcelCell("From", "String", $this->excelStyles['normal']));
		$row->addCell(new HYExcelCell($fromWarehouseName, "String", $this->excelStyles['normal']));
		$workSheet->addRow($row);
		$rowCount++;

		$toWarehouseName = "anywhere";
		if (!empty($userArgs['destinationWarehouseName']))
			$toWarehouseName = $userArgs['destinationWarehouseName'];
		$row = new HYExcelRow();
		$row->addCell(new HYExcelCell("To", "String", $this->excelStyles['normal']));
		$row->addCell(new HYExcelCell($toWarehouseName, "String", $this->excelStyles['normal']));
		$workSheet->addRow($row);
		$rowCount++;



		$workSheet->addRow($this->addBlankRow());
		$rowCount++;

		$row = new HYExcelRow();
		$ctr = 0;
		foreach ($headers as $th => $colw)
		{
			$cell = new HYExcelCell($th, "String", $this->excelStyles['tabHead']);
			$row->addCell($cell);
			$workSheet->setColumnWidth($ctr++, $colw);
		}
		$workSheet->addRow($row);

		foreach ($results as $dataRow)
		{
			$row = new HYExcelRow();
			foreach ($dataRow as $td)
			{
				$cell = new HYExcelCell(trim($td), "String", $this->excelStyles['tabCell']);
				$row->addCell($cell);
			}
			$workSheet->addRow($row);
		}

		$excelFormatOptions = array('orientation' => 'Landscape', 'freezePanes' => true, 'headerRow' => $rowCount);
		$workSheet->applyFormattingOptions($excelFormatOptions);
    	$this->workBook->addWorkSheet($workSheet);
    }

    /**
     * Back Predict Result
     *
     * @param unknown_type $sender
     * @param unknown_type $params
     */
	public function backPredictResult($sender, $params)
	{
		$this->dataLoad();
		$this->resetReportValues();
	}

	/**
	 * Send Result To Excel
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $params
	 */
    public function sendResultToExcel($sender, $params)
	{
		$this->dataLoad();
		try
		{
			$userArgs = $this->getArguments();
			$result = $this->getData_partsMovement(false, $userArgs);
			$this->addWorksheet_partsMovement($result, $userArgs);
			$contentServer = new ContentServer();
			$assetId = $contentServer->registerAsset(ContentServer::TYPE_REPORT, "Parts_Movement_History_Report_Custom.xls", $this->workBook->__toString());
			$this->jsLbl->Text = "<script type=\"text/javascript\">window.open('/report/download/$assetId');</script>";
		}
		catch(Exception $e)
		{
			$this->setErrorMessage($e->getMessage());
		}
	}

	/**
	 * Predict Result
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $params
	 */
	public function predictResult($sender, $params)
	{
		$this->dataLoad();
		$this->resetReportValues();
		try
		{
	    	$countOnly = true;
	    	$maxNoOfMove = 5000;

	    	$userArgs = $this->getArguments();
			$noOfMove =  $this->getData_partsMovement(true, $userArgs);

	    	$mesg = "<br/>There are $noOfMove movement entries recorded.";

	    	if(strval($noOfMove) == "to many"){
	    		$noOfMove = 10000;
	    	}
    		$canContinue = true;
	    	if ($noOfMove > $maxNoOfMove)
	    	{
	    		$canContinue = false;
	    		$mesg .= "<br/>Results are too big. Please narrow down search by filling in more criteria.";
	    	}
	    	else if ($noOfMove == 0)
	    	{
	    		$canContinue = false;
	    		$mesg .= "<br/>Nothing to be displayed.";
	    	}

	    	if ($canContinue)
	    	{
				$this->predictResultBtn->Visible = false;
				$this->backPredictResultBtn->Visible = true;
				$this->toExcelBtn->Visible = true;
				$this->masker->Visible = true;
	    	}
    		$this->predictResultBox->setText($mesg);
		}
		catch(Exception $e)
		{
			$this->setErrorMessage($e->getMessage());
		}
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
    	if($this->Page->IsPostBack)
    	{
    		return $this->searchEntity('', $focusObject, $pageNumber, $pageSize);
    	}
    }

    /**
     * Return Note Type
     *
     * @return unknown
     */
    public function returnNoteType()
    {
    	if ($this->dNoteRadio->Checked) return 'Dispatch';
    	else if ($this->tNoteRadio->Checked) return 'Transit';
    	else return 'Transit/Dispatch';
    }

    /**
     * Get Query
     *
     * @param unknown_type $selection
     * @param unknown_type $where
     * @param unknown_type $limit
     * @param unknown_type $orderBy
     * @return unknown
     */
    private function getQuery($selection,$where,$limit="", $orderBy="",$rowCountOnly=false)
    {
    	//-- check if there is a limit
    	($limit != "") ? $limit = " limit " . $limit : $limit = $limit;
    	$sql="
    			select
    			$selection
    			from transitnote tn ";
    	if(!$rowCountOnly)
    	{
	    	$sql .="
	    			inner join warehouse tnw_d on (tnw_d.id = tn.destinationId)
	    			left join facility tnf_d on (tnf_d.id = tnw_d.facilityId)
	    			left join warehousealias wa12d on (wa12d.warehouseId = tnw_d.id and wa12d.active = 1 and wa12d.warehouseAliasTypeId=12)

	    			inner join warehouse tnw_s on (tnw_s.id = tn.sourceId)
	    			left join facility tnf_s on (tnf_s.id = tnw_s.facilityId)
	    			left join warehousealias wa12s on (wa12s.warehouseId = tnw_s.id and wa12s.active = 1 and wa12s.warehouseAliasTypeId=12)
	    			";
    	}

	    	$sql .="
    			where $where $orderBy
    			$limit
    	";
    	//echo $sql;die();
    	return $sql;
    }

    /**
     * How Big was the Query
     *
     * @return unknown
     */
 	protected function howBigWasThatQuery()
    {
    	return $this->totalNoOfTNs;
    }

    /**
     * Get TransitNote URL
     *
     * @param unknown_type $transitnoteId
     * @param unknown_type $transitnoteNo
     * @return unknown
     */
	protected function getTransitNoteUrl($transitnoteId, $transitnoteNo)
	{
		$url="";
		$str = explode('/',$_SERVER['REQUEST_URI']);
		if ($str[1] == 'staging')
		{
			$url='/staging';
		}
		if (substr($transitnoteNo, 0, 2) == 'DN')
			$url.="/dispatchnote/".$transitnoteId."/";
		else
			$url.="/transitnote/".$transitnoteId."/";
		return $url;
	}

	/**
	 * Get TransitNote Details
	 *
	 * @param unknown_type $transitNoteId
	 * @return unknown
	 */
	public function getTransitNotesDetails($transitNoteId)
	{
		$displayStr = "";

		$transitNote = Factory::service("TransitNote")->get($transitNoteId);
		if(!$transitNote instanceof TransitNote)
			return;

    	// As per discussion with Noel / Trevor.... decided to show melbourne timezone instead of UTC timezone....
		$udpatedDateMelTimeZone = new HydraDate($transitNote->getUpdated());
		$udpatedDateMelTimeZone->setTimeZone("Australia/Melbourne");

		$createdDateMelTimeZone = new HydraDate($transitNote->getCreated());
		$createdDateMelTimeZone->setTimeZone("Australia/Melbourne");

		if ($transitNote->getTransitNoteLocation() != '')
		$displayStr .= "<b>TransitNoteLocation: </b>". $transitNote->getTransitNoteLocation() ."<br />";
		$displayStr .= "<b>Last Updated On: </b>". $udpatedDateMelTimeZone ." (Mel Time) <br />";
		$displayStr .= "<b>Last Updated By: </b>". $transitNote->getUpdatedBy() ."<br />";
		$displayStr .= "<b>Created On: </b>". $createdDateMelTimeZone ." (Mel Time) <br />";
		$displayStr .= "<b>Created By: </b>". $transitNote->getCreatedBy() ."<br />";

		return $displayStr;
	}

	/**
	 * Show Recieve Part Button
	 *
	 * @param unknown_type $transitNoteStatus
	 * @return unknown
	 */
	public function showRecievePartButton($transitNoteStatus)
	{
		return strtoupper($transitNoteStatus)==strtoupper(TransitNote::STATUS_TRANSIT);
	}

	/**
	 * Get Package Info
	 *
	 * @param unknown_type $transitNoteId
	 * @return unknown
	 */
	public function getPackageInfo($transitNoteId)
	{
		$totalItems = 0;
		$noOfPackages = 0;

		$transitNote = Factory::service("TransitNote")->get($transitNoteId);
		$totalItems = $transitNote->getTotalItems();
		$noOfPackages = $transitNote->getNoOfPackages();

		$actualItems = 0;
		try
		{
			if($transitNote->getTransitNoteLocation() instanceof Warehouse)
			{
				$warehouseId = $transitNote->getTransitNoteLocation()->getId();
				$sql = "select sum(pi.quantity) from partinstance pi where pi.active = 1 and pi.warehouseId = $warehouseId";
				$result = Dao::getResultsNative($sql);
				if(count($result)>0 && $result[0][0]>"")
					$actualItems=$result[0][0];
			}
		}
		catch (Exception $ex)
		{
			$actualItems = 0;
		}

		if($totalItems==0)
			$noOfPackages = 0;
		else
			$noOfPackages = $transitNote->getNoOfPackages();

		$return = "<b>$actualItems / $totalItems</b> <span style='font-size:9px'>Item(s)</span>";
		$return .= "<br />";
		$return .= "<b>$noOfPackages</b> <span style='font-size:9px'>Package(s)</span>";
		return $return;
	}

	/**
	 * Get Local TimeZone
	 *
	 * @return unknown
	 */
	public function getLocalTimeZone()
    {
    	$default_warehouse_timezone=Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
    	return $default_warehouse_timezone;
    }

	/**
	 * Show Issue Date
	 *
	 * @param unknown_type $transitNoteId
	 * @param unknown_type $dateOnly
	 * @return unknown
	 */
	public function showIssueDate($transitNoteId, $dateOnly = FALSE)
	{
		$transitNote = Factory::service("TransitNote")->get($transitNoteId);
		//if($transitNote->getTransitNoteStatus() == TransitNote::STATUS_OPEN)
		//	return "";

		$date = $transitNote->getIssueDate();
		$timeZone = Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
		$date->setTimeZone($timeZone);
		if($dateOnly)
		{
			//die("date ". $transitNote->getIssueDate());
			if ($transitNote->getIssueDate() < "1970-0-0")
			{
				 $return = "";
			}
			else{
				$return = "<b>". date("Y-m-d",strtotime($transitNote->getIssueDate()))."</b>";
			}
		}
		else{
      		$return = "<b>".$transitNote->getIssueDate()."</b>";


	      	$fromWarehouse = $transitNote->getSource();
	      	if(!$fromWarehouse instanceof Warehouse)
	      		return $return;

	      	$fromFacility = $fromWarehouse->getFacility();
	      	if(!$fromFacility instanceof Facility)
	      		return $return;

	      	$return .= "<br /><i style='font-size:10px;'>From ".$fromWarehouse->getName(). " in ".$fromFacility->getAddress()->getState()."</i>";
		}
      	return $return;
	}

	/**
	 * Get Address
	 *
	 * @param unknown_type $addressId
	 * @return unknown
	 */
	public function getAddress($addressId)
	{
		return Factory::service("Address")->get($addressId);
	}

	/**
	 * Get Facility Name
	 *
	 * @param unknown_type $fName
	 * @return unknown
	 */
	public function getFacilityName($fName)
	{
		if (!is_null($fName) && $fName != '')
			return $fName . '<br />';
		else return '';
	}

	/**
	 * Check Site Address
	 *
	 * @param unknown_type $destinationAddrId
	 * @param unknown_type $destinationWarehouseId
	 * @param unknown_type $destinationSiteId
	 * @return unknown
	 */
	public function checkSiteAddress($destinationAddrId, $destinationWarehouseId, $destinationSiteId)
	{
		if ($destinationWarehouseId == Factory::service("Warehouse")->getSiteWarehouse()->getId()) //going to Sites Bucket
		{
			$destinationSite = Factory::service("Site")->getSite($destinationSiteId);
			if($destinationSite instanceof Site && $destinationSite->getServiceAddress() != null) return $destinationSite->getServiceAddress();
		}
		return $this->getAddress($destinationAddrId);
	}

	/**
	 * Get Default Warehouse TimeZone
	 *
	 * @return unknown
	 */
	public function getDefaultWarehouseTimeZone()
	{
		return Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
	}

	/**
	 * Sort
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function sort($sender, $param)
	{
		$sortBy = $param->CommandParameter;
		$sortOrd = $this->sortOrd->Value;

		($sortOrd=="DESC") ? $sortOrd = "ASC" : $sortOrd = "DESC";

		$this->sortBy->Value = $sortBy;
		$this->sortOrd->Value = $sortOrd;

		$this->search($sender, $param);
	}

	/**
	 * Get Button Text
	 *
	 * @param unknown_type $defaultText
	 * @param unknown_type $sortField
	 * @return unknown
	 */
	public function getButtonText($defaultText,$sortField)
	{
		$return = $defaultText;
		if($this->sortBy->Value==$sortField)
			$return.=($this->sortOrd->Value == "ASC" ? " ^ " : " v ");
		return $return;
	}

	/**
	 * Check Stie Name
	 *
	 * @param unknown_type $destination
	 * @param unknown_type $destinationWarehouseId
	 * @param unknown_type $destinationSiteId
	 * @return unknown
	 */
	public function checkSiteName($destination, $destinationWarehouseId, $destinationSiteId)
	{
		if ($destinationWarehouseId == Factory::service("Warehouse")->getSiteWarehouse()->getId()) //going to Sites Bucket
		{
			$destinationSite = Factory::service("Site")->getSite($destinationSiteId);
			if($destinationSite instanceof Site && $destinationSite->getCommonName() != null) return 'Sites: ' . $destinationSite->getCommonName();
		}
		return $destination;
	}

	/**
	 * Get Recieve Part URL
	 *
	 * @param unknown_type $transitnoteid
	 * @return unknown
	 */
	public function getReceivePartURL($transitnoteid)
	{
		$str = explode('/',$_SERVER['REQUEST_URI']);
		if ($str[1] == 'staging')
		{
			$url='/staging/transferpartinward/transitnote/'.$transitnoteid;
		}
		else
		{
			$url='/transferpartinward/transitnote/'.$transitnoteid;
		}

		return $url;
	}

	/**
	 * Get Reconcile RA URL
	 *
	 * @param unknown_type $item
	 * @param unknown_type $transitnoteid
	 * @param unknown_type $tnNo
	 * @return unknown
	 */
	public function getReconcileRaURL($item, $transitnoteid, $tnNo)
	{
		$url = '/reconcileRA/';
		if (strpos(strtoupper($tnNo), 'DN') !== false)
		{
			$ra = Factory::service("ReturnAuthority")->getRaFromTnId($transitnoteid);
			if ($ra instanceof ReturnAuthority)
			{
				$status = $ra->getStatus();
				if ($status != ReturnAuthority::STATUS_FULLY_RETURNED)
				{
					$item->reconcileLink->Visible = true;
					$item->reconcileLink->Tooltip = 'Reconcile Parts for ' . $ra->getRaNo();
					$url = '/reconcileRA/'.$ra->getId();
				}
			}
		}
		return $url;
	}
}
?>