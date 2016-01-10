<?php
/**
 * TransitNote Controller page
 *
 * @package	Hydra-Web
 * @subpackage Controller
 * @version	1.0
 */
class TransitNoteController extends HydraPage
{
	/**
	 * @var unknown_type
	 */
	protected $transitNote;

	/**
	 * @var unknown_type
	 */
	public $menuContext;

	/**
	 * @var unknown_type
	 */
	public $canEditTransitNote;

	/**
	 * @var unknown_type
	 */
	public $theDataList;

	/**
	 * @var PaginationPanel
	 */
	public $PaginationPanel;

	/**
	 * @var itemCount
	 */
	public $itemCount;

	/**
	 * @var unknown_type
	 */
	private $isAgent;

	/**
	 * @var unknown_type
	 */
	private $isDispatchNote;

	/**
	 * @var unknown_type
	 */
	private $isAssignmentNote;

	/**
	 * @var unknown_type
	 */
	private $isTo3rdPartyRepairer;

	/**
	 * @var unknown_type
	 */
	protected $excelStyles;

	/**
	 * @var unknown_type
	 */
	public $defaultWarehouse;

	/**
	 * @var unknown_type
	 */
	protected $querySize;

	/**
	 * @var unknown_type
	 */
	private $workBook;

	/**
	 * @var totalRows
	 */
	public $totalRows;


	/**
	 * @var qty
	 */

	public $qty;

	/**
	 * On Pre Initialization
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
		$str = explode('/',$_SERVER['REQUEST_URI']);
		if ($str[1] == 'agent')
		{
			$this->isAgent = true;
			$this->getPage()->setMasterClass("Application.layouts.AgentLogisticsLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_agent_logistics_listTransitNote";
			$this->menuContext = 'consignment';
		}
		else if ($str[1] == 'staging')
		{
			//$this->isAgent = true;
			$this->getPage()->setMasterClass("Application.layouts.StagingLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_agent_logistics_listTransitNote,menu_staging";
			$this->menuContext = 'staging/consignment';
		}
		else
		{
			$this->isAgent = false;
			$this->getPage()->setMasterClass("Application.layouts.LogisticsLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_transitNote";
			$this->menuContext = 'consignment';
		}

	}

	/**
	 * Get Local Timezone
	 *
	 * @return unknown
	 */
	public function getLocalTimeZone()
    {
    	$default_warehouse_timezone = Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
    	return $default_warehouse_timezone;
    }

	/**
	 * Shows Part Instance Details hover
	 *
	 * @param Integer $partInstanceId
	 * @param String $cssclass
	 * @param String $divPrefix
	 *
	 * @return string $div
	 */
	public function showPartInstanceDetail($partInstanceId, $cssclass="toolTipWindow", $divPrefix="PartInstanceDetails_")
	{

		if ($this->transitNote->getTransitNoteStatus() == TransitNote::STATUS_OPEN)
		{
			$partInstance = Factory::service("PartInstance")->get($partInstanceId);
			if($partInstance instanceOf PartInstance)
			{
				return PartInstanceLogic::showPartInstanceDetail($partInstance, $cssclass, $divPrefix);
			}
			else
			{
				return "";
			}
		}
		return '';
	}

    /**
     * Constructor
     *
     */
	public function __construct()
	{
		parent::__construct();
		$this->workBook = new HYExcelWorkBook();
		$this->initStyles();
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 * @return unknown
	 */
	public function onLoad($param)
    {
       	parent::onLoad($param);
       	$this->setErrorMessage('');
       	$this->setInfoMessage('');
       	$this->page->formPanel->Text='';
       	$this->jsLbl->Text = '';

       	if(!$this->IsPostBack && !$this->IsCallBack)
		{
			//hide the info and error messages from printing
			$this->getMaster()->MessagePanel->setCssClass($this->getMaster()->MessagePanel->getCssClass() . ' printhide');
		}

       	$this->transitNote = Factory::service("TransitNote")->get($this->Request['id']);
		$tnType = $this->transitNote->getNoteType();
		if ($tnType == TransitNote::NOTETYPE_DISPATCHNOTE)
		{
			$this->getPage()->setTitle($this->transitNote->getTransitNoteNo()." - DispatchNote");
			$this->isDispatchNote = true;
			$this->theDataList = &$this->DispatchNoteDataList;
			$this->PaginationPanel = &$this->PaginationPanelDispatchNote;
			$this->PaginationPanelTransitNote->Visible=false;
			$this->PaginationPanelDispatchNote->Visible=true;
			$this->TransitNote_PagerList_label->Visible=false;
			$this->TransitNote_PagerGoTo_label->Visible=false;
			$this->Page->signatureRow->Visible = false;
			$this->alertMessageType->Value = "Dispatch";

			$destWhCategory = $this->transitNote->getDestination()->getWarehouseCategory()->getId();
			$this->isTo3rdPartyRepairer = ($destWhCategory == WarehouseCategory::ID_3RD_PARTY_REPAIRER ? true : false);
		}
		else
		{
			$this->getPage()->setTitle($this->transitNote->getTransitNoteNo()." - TransitNote");
			$this->isAssignmentNote = false;
			$this->isDispatchNote = false;
			$this->theDataList = &$this->DataList;
			$this->PaginationPanel = &$this->PaginationPanelTransitNote;
			$this->PaginationPanelTransitNote->Visible=true;
			$this->PaginationPanelDispatchNote->Visible=false;
			$this->DispatchNote_PagerList_label->Visible=false;
			$this->DispatchNote_PagerList_label->Visible=false;
			$this->alertMessageType->Value = "Transit";
			$this->isTo3rdPartyRepairer = false;

			if ($tnType == TransitNote::NOTETYPE_ASSIGNMENTNOTE)
			{
				$this->getPage()->setTitle($this->transitNote->getTransitNoteNo()." - AssignmentNote");
				$this->isAssignmentNote = true;
			}
		}

		$this->isEditable($this->transitNote);
		$this->addPartPanel->Visible = $this->canEditTransitNote;
		$this->searchResult_multipleFound_Panel->Visible = $this->canEditTransitNote;

		if(!$this->IsPostBack || $param=="reload" || $param=="print")
		{
			$this->searchResult_multipleFound_Panel->Visible=false;
			$this->loadTransitNoteDetails($this->transitNote);

			if($param=="print")
			{
				$this->printPanel->Visible=true;
			}
		}

		if ($this->isAgent)
		{
			$this->MainContent->Enabled=false;

			if(!$this->transitNote instanceof TransitNote || $tnType == 'DN')
			{
				$this->onError("Cannot edit Dispatch Notes!");
				return;
			}

			$defaultWarehouse = Factory::service("Warehouse")->getWarehouse(Factory::service("UserPreference")->getOption(Core::getUser(),'defaultWarehouse'));

			if (!$defaultWarehouse instanceof Warehouse)
				$this->setErrorMessage("No Default Warehouse Set, Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");
			else if ($defaultWarehouse->getId() == $this->transitNote->getSource()->getId())
				$this->MainContent->Enabled=true;
			else if ($defaultWarehouse->getNearestFacility()->getWarehouse()->getId() == $this->transitNote->getSource()->getId())
				$this->MainContent->Enabled=true;
			else if ($defaultWarehouse->getId() == $this->transitNote->getDestination()->getId())
				$this->MainContent->Enabled=true;
			else
			{
				$tnSource = $this->transitNote->getSource();
				$subWhs = Factory::service('Warehouse')->getWarehouseChildrenIds($defaultWarehouse);
				foreach ($subWhs as $whId)
				{
					if ($whId == $tnSource->getId())
					{
						$this->MainContent->Enabled=true;
						break;
					}
				}
			}

			if($this->MainContent->Enabled === false)
			{
				$this->onError("<br />You cannot edit this Transit Note, as it is not From/To your default warehouse: '$defaultWarehouse'");
			}
		}

		// this bit is to determine whether to display "fromWarehouse" tree
		//if we suspect this is nonserialised part, show the from tree
		$currFromParts = $this->searchPart->getText();
       	$maybeNonSerialised = false;
       	if (!empty($currFromParts) && $this->maybeNonSerialisedCode($currFromParts))
		{
       		$maybeNonSerialised = true;
       	}
		$this->fromWarehousePanel->setAttribute("style", "display:".($maybeNonSerialised ? "" : "none"));
		$this->fromWarehouseCaption->setAttribute("style", "display:".($maybeNonSerialised ? "" : "none"));

		$currSelectedWarehouse = $this->fromWarehouseid->getValue();
		$userDefaultWarehouse = null;
		if ($this->isAgent)
		{
			if (empty($currSelectedWarehouse))
			{
				$userDefaultWarehouseId = Factory::service("UserPreference")->getOption(Core::getUser(), "defaultWarehouse");
				if (!empty($userDefaultWarehouseId))
				{
					$userDefaultWarehouse = Factory::service("Warehouse")->getWarehouse($userDefaultWarehouseId);
				}
			}
		}
		else
		{
			if (empty($currSelectedWarehouse))
			{
				$userDefaultWarehouse = Factory::service("Warehouse")->getDefaultMobileWarehouse(Core::getUser());
			}
		}

		if (!empty($userDefaultWarehouse))
		{
			$this->fromWarehouseid->setValue(Factory::service("Warehouse")->getWarehouseIdBreadCrumbs($userDefaultWarehouse));
		}

		////////////////////////////////////////////////////////////////////////////
		/// pre-checking whether we have access to courier warehouse////////////////
		////////////////////////////////////////////////////////////////////////////
	  	$courierWarehouse = Factory::service("Warehouse")->getRootCourierWarehouse();
	    if(!$courierWarehouse instanceof Warehouse)
	    {
	    	return $this->onError("Invalid courier. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");
	    }

	    $this->SaveButton->Enabled=true;
		try
		{
			Factory::service("Warehouse")->checkAccessToWarehouse($courierWarehouse);
    	}
    	catch(Exception $ex)
		{
			$this->SaveButton->Enabled=false;
			if ($this->canEditTransitNote==true)
			{
				return $this->onError("Access to couriers not permitted. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");
			}
		}

		/* Hiding the Print View button */
   		 if ($this->transitNote->getTransitNoteStatus() != "transit")
       		{
       			$this->printAllParts1->Visible=false;
       			$this->printAllParts2->Visible=false;
       		}
    }

    /**
     * Get Default WarehouseId
     *
     * @return unknown
     */
    public function getDefaultWarehouseId()
    {
    	return Factory::service("UserPreference")->getOption(Core::getUser(),'defaultWarehouse');
    }

    /**
     * Is Editable
     *
     * @param TransitNote $transitNote
     */
    protected function isEditable(TransitNote $transitNote)
    {
    	$this->canEditTransitNote=true;
		if($transitNote->getTransitNoteStatus()!=TransitNote::STATUS_OPEN)
		{
			$this->canEditTransitNote=false;
		}
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
     * Add Worksheet
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

		$transitNoteNo = "all";
		if (!empty($userArgs['transitNoteNo']))
			$transitNoteNo = $userArgs['transitNoteNo'];
		$row = new HYExcelRow();
		$row->addCell(new HYExcelCell("Transit Note No", "String", $this->excelStyles['normal']));
		$row->addCell(new HYExcelCell($transitNoteNo, "String", $this->excelStyles['normal']));
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
		//$workSheet->applyFormattingOptions($excelFormatOptions);
    	$this->workBook->addWorkSheet($workSheet);
    }

    /**
     * Send Result to Excel
     *
     * @param unknown_type $sender
     * @param unknown_type $params
     */
 	public function sendResultToExcel($sender, $params)
	{
		$this->jsLbl->Text = '';
		$this->printPanel->Visible=false;
		try
		{
			$transitNoteId = $this->Request['id'];
			$transitNote = Factory::service("TransitNote")->get($transitNoteId);
			$this->loadTransitNoteDetails($transitNote);
			$result = $this->getData_partsMovement($transitNoteId);

			$userArgs = array();

			$userArgs['source'] = $transitNote->getSource()->getId();
			$userArgs['destination']= $transitNote->getDestination()->getId();
			$userArgs['transitNoteNo']= $transitNote->getTransitNoteNo();

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
	 * Get Data parts movement
	 *
	 * @param unknown_type $transitNoteId
	 * @return unknown
	 */
	protected function getData_partsMovement($transitNoteId)
    {
    	$userArgs = array();
    	$userArgs['transitNoteId'] = $transitNoteId;
    	$results = TransitNoteLogic::getPartsMovementResults(false, $userArgs, $this->querySize, $this->getLocalTimeZone());
    	return $results;
    }

    /**
     * This function updates any info in transit notes, except the Status!
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function saveDraftTransitNote($sender, $param)
    {
    	$transitNote = $this->transitNote;
    	if (!$transitNote instanceof TransitNote)
    	{
    		return $this->onError("Invalid Transit Note.");
    	}

    	$transitNote->setSpecialDeliveryInstructions(trim($this->SpecialDeliveryInstructions->Text));
      	$transitNote->setCourier(trim($this->Courier->getSelectedValue()));
      	$transitNote->setClientJobNos(trim($this->ClientJobNos->Text));
      	$transitNote->setDeliveryMethod(trim($this->deliveryMethod->getSelectedValue()));
      	$transitNote->setCourierJobNo(trim($this->CourierJobNo->Text));
     	$transitNote->setNoOfPackages(trim($this->TotalPackages->Text));

      	$timeZone = Factory::service("Warehouse")->getWarehouseTimeZone($transitNote->getSource());
        $eta = trim($this->etaDate->Text)." ".trim($this->etaTime->getSelectedValue());

        if ($eta !== '')
        {
	         $eta = new HydraDate($eta, $timeZone);
	         $eta->setTimeZone('UTC');
	         $transitNote->setEta($eta);
        }

    	$comments = trim($this->Comments->Text);
    	if($comments!="")
    	{
	    	$now = new HydraDate("now",$timeZone);
	    	$transitNote->setComments(Factory::service("TransitNote")->appendComments($transitNote,Core::getUser()->getPerson()." - $now($timeZone) - $comments"));
    	}

		Factory::service("TransitNote")->saveTransitNote($transitNote);
		$this->Comments->Text="";
		$this->onLoad("reload");
    }

    /*
    * Sends an email to the technician the assignment note is destined to
    * @param TransitNote $transitNote
    */
    private function sendEmailForAssignmentNote($transitNote)
    {
    	if ($transitNote->getNoteType() == TransitNote::NOTETYPE_ASSIGNMENTNOTE)
		{
			$message = "";
			$person = Factory::service("Warehouse")->getAllUsersWithDefaultOption($transitNote->getDestination()->getId(), "defaultMobileWarehouse");
			if(count($person)>0)
			{
				$userAccount = Factory::service("UserAccount")->getAllUserAccounts($person[0][0]);
				if(count($userAccount)>0)
				{
					if($userAccount[0] && $userAccount[0] instanceOf UserAccount)
					{
						$transitNoteId = $transitNote->getId();
						$logsArr = Factory::service("LogPartsInTransitNote")->findByCriteria(" transitNoteId = ?",array($transitNoteId));

						$message .="This email is to advise you that there are parts awaiting your immediate collection: \r\n ";
						$message .="Location: ". $transitNote->getSource()->getName() . "\r\n";
						$facility = $transitNote->getSource()->getFacility();
						$message .= "Address: ".$facility->getAddress()."\r\n";
						$message .= "Part Details: \r\n";
						foreach ($logsArr as $logs)
						{
							$message .= " Part: ". $logs->getPartInstance()." for Field Task#: ".$logs->getFieldTaskNumber()." Quantity:".$logs->getQty()." \r\n";
						}
						$subject = "Notification of parts pickup on Assignment Note: ".$transitNote->getTransitNoteNo() ;
						Factory::service("Message")->email($userAccount[0]->getPerson()->getEmail(),$subject,$message);
					}
				}
			}
		}
    }

    /**
	 * Calling this function to print if pagination panel is visible
     *
     */
     public function  finishProcessingNotePagination()
     {
		$this->jsLbl->Text = "<script type=\"text/javascript\">startPrint();</script>";
     }

    /**
	 * Finish ajax processing, show messages
     *
     */
 	public function finishProcessingNote()
    {
    	$this->listParts();
    	if ($this->transitNote->getTransitNoteStatus() != TransitNote::STATUS_OPEN)
    	{
	    	$this->onLoad("print");
	    	$this->successMessage->Value .= "<br>Successfully dispatched.";
    	}
    	else
    	{
    		$this->onLoad("reload");
    	}

    	if ($this->exceptionMessage->Value)
    	{
    		$this->setErrorMessage($this->exceptionMessage->Value);
    	}

    	if ($this->errorMessage->Value)
    	{
    		$this->setErrorMessage($this->getErrorMessage() . "<br />" . $this->errorMessage->Value);
    	}

    	$this->onSuccess("<br />" . $this->successMessage->Value);
    }

    /**
     * call javascript function to hide modal box and call finishProcessingNote()
     *
     */
	public function finishProcessDispatchNote()
	{
		if($this->isPaginated->Value == 0)
		{
			$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessingNote();</script>";
		}

		else
		{
			$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessingNotePagination();</script>";
		}
    }

    /**
     * cycle through via ajax until all the parts have moved
     *
     * @return unknown
     */
	public function processDispatchNote()
    {
   	 	$numberOfPartsProcessedPerAjaxCallForDispatchNote = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'numberOfPartsProcessedPerAjaxCallForDispatchNote',true);
    	if (!is_numeric($numberOfPartsProcessedPerAjaxCallForDispatchNote))
    	{
    		$numberOfPartsProcessedPerAjaxCallForDispatchNote = 1;
    	}

    	try
    	{
	    	$ra = null;
		 	if($this->raId->Value)
		 	{
		 		$ra = Factory::service("ReturnAuthority")->get($this->raId->Value);
		 	}

	    	Factory::service("TransitNote")->closeDispatchNote($this->transitNote, '', $ra, null, $numberOfPartsProcessedPerAjaxCallForDispatchNote);
	    	$partInstances = Factory::service("PartInstance")->findAllPartsForWarehouseAndPartType($this->transitNote->getTransitNoteLocation(), null, false, 1);
	    	if (count($partInstances) == 0)
	    	{
	    		return array('stop' => true);
	    	}
    	}
    	catch (Exception $e)
    	{
    		$this->exceptionMessage->Value = $e->getMessage();
    		$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessingNote();</script>";
    		return array('stop' => true);
    	}
    	return array('stop' => false);
    }

    /**
     * Push Note to Transit
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
    public function pushNoteToTransit($sender,$param)
    {
    	if (!$this->transitNote instanceof TransitNote)
    	{
    		return $this->onError("Invalid Transit Note!");
    	}

    	$ra = null;
    	$this->successMessage->Value = $this->errorMessage->Value = $this->exceptionMessage->Value = '';

    	//here we check to see if we need to progress the task, if it is linked
    	try																															//see if we can progress the task (if required)
    	{
	    	if ($this->isDispatchNote && $this->isTo3rdPartyRepairer)	//we're going to a 3rd party on a DN
	    	{
	    		$ra = Factory::service("ReturnAuthority")->getGenerateRaEntity($this->transitNote);													//get the RA from the TN

	    		if ($ra instanceOf ReturnAuthority)
	    		{
	    			$sessionArray = Session::getReturnAuthorityReviewedStatus($this->transitNote->getId());							//the session info from reviewing the RA
	    			if ($sessionArray !== false)
	    			{
	    				$sessionArray = array_pop($sessionArray);																	//get the last element
	    				if (array_key_exists('ftIdToProgress', $sessionArray))														//check if we have field task information to progress the task
	    				{
	    					$result = Factory::service("ReturnAuthority")->checkAndProgressFieldTaskOnDispatchOrReconcile($sessionArray['ftIdToProgress']);		//progress the task, and update the notes
	    					if (!$result instanceof FieldTask && $result !== false)													//we've had an error along the way
	    					{
	    						throw new Exception("Unable to continue, error progressing Field Task (" . key($sessionArray['ftIdToProgress']) . ") to (" . array_pop($sessionArray['ftIdToProgress']) . "), please contact your supervisor:<br /><br />" . $result);
	    					}
	    					if ($result instanceof FieldTask)
	    					{
	    						$this->successMessage->Value = "Linked field Task (" . key($sessionArray['ftIdToProgress']) . ") progressed to (" . array_pop($sessionArray['ftIdToProgress']) . ")";
	    					}
	    				}
	    			}
	    		}
    		}
    	}
    	catch (Exception $e)
    	{
    		$this->setErrorMessage($e->getMessage());
    		Factory::service("Message")->email(Config::get("SupportHandling","Email"), "Error progressing linked task from RA (" . $this->transitNote->getTransitNoteNo() . ")", $e->getMessage());

    		$this->jsLbl->Text = "<script type=\"text/javascript\">Modalbox.hide();</script>";
    		return;
    	}

    	//now dispatch the note
    	try
    	{
		    $courierWarehouse = Factory::service("TransitNote")->getSelectedCourierWarehouseByName(trim($this->Courier->getSelectedValue()));
		    Factory::service("TransitNote")->pushTransitNoteToTransit(	$this->transitNote,
					    											$courierWarehouse,
					    											trim($this->ClientJobNos->Text),
					    											trim($this->CourierJobNo->Text),
					    											trim($this->Comments->Text),
					    											trim($this->TotalPackages->Text),
					    											trim($this->SpecialDeliveryInstructions->Text),
		    														trim($this->formattedEta->Value),
		    														$this->deliveryMethod->getSelectedValue());


		  	$fieldTasksToPushToTransitArray = array();
		    $fieldTasksToPushToTransit = $this->pushFieldTasksToTransit->Value;
		    if($fieldTasksToPushToTransit)
		    {
		    	$fieldTasksToPushToTransitArray = explode(",", $fieldTasksToPushToTransit);
		    }

		 	//check here for parts on Facility Requests
		    $results = Factory::service("TransitNote")->progressTaskAndAddAdditionalNotesOnDispatchForReservedParts($this->transitNote, $fieldTasksToPushToTransitArray);
    		if (!empty($results))
    		{
    			if (!empty($results['errors']))
    			{
    				$this->errorMessage->Value .= implode('<br />', $results['errors']);
    			}

    			if (!empty($results['success']))
    			{
    				$this->successMessage->Value .= implode('<br />', $results['success']);
    			}
    		}

    		//send an email to the recipient of the Assignment Note if applicable
			$this->sendEmailForAssignmentNote($this->transitNote);

			$emailSuccess = '';
			if ($this->isDispatchNote)
			{
				try
				{
					if ($this->isTo3rdPartyRepairer && $ra instanceOf ReturnAuthority) //is going to 3rd party repairer on a DN, and we have an RA from above
					{
						if ($ra instanceOf ReturnAuthority)
						{
							$this->raId->Value = $ra->getId();
							$emailSuccess = Factory::service("ReturnAuthority")->emailRaToRepairer($ra, $this->transitNote);

							if ($emailSuccess === true)
							{
								$this->successMessage->Value .= "<br />RA successfully emailed to Repairer...";
							}
							else if ($emailSuccess == 'email')
							{
								$this->errorMessage->Value .= "<br />Unable to email RA to Repairer... Could not find an email address";
							}
							else if ($emailSuccess == 'content')
							{
								$this->errorMessage->Value .= "<br />Unable to email RA to Repairer... Could not fetch the RA";
							}
							else if ($emailSuccess == 'asset')
							{
								$this->errorMessage->Value .= "<br />Unable to email RA to Repairer... Could not find a valid assetID";
							}
						}
					}
				}
				catch (Exception $e)
				{
					Factory::service("Message")->email(Config::get("SupportHandling","Email"), "Error emailing RA to repairer from " . $this->transitNote->getTransitNoteNo(), $e->getMessage());
				}
				$this->jsLbl->Text = "<script type=\"text/javascript\">closeDispatchNote();</script>";
				return;
			}
    	}
    	catch (Exception $e)
    	{
    		$this->exceptionMessage->Value = $e->getMessage();
    		$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessingNote();</script>";
    		return;
    	}
    	$this->finishProcessDispatchNote();
    }

    /**
     * checkBeforePushNoteToTransit
     * Checks facility requests against parts on a transit note
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
	public function checkBeforePushNoteToTransit($sender,$param)
    {
		$warningMessages = array();

    	if (!$this->transitNote instanceof TransitNote)
    	{
    		return $this->onError("Invalid Transit Note!");
    	}

    	$progressToStatus = TransitNoteService::DISPATCHED_TASK_STATUS_TN;
    	if ($this->transitNote->getNoteType() == TransitNote::NOTETYPE_DISPATCHNOTE)
    		$progressToStatus = TransitNoteService::DISPATCHED_TASK_STATUS_DN;
    	else if ($this->transitNote->getNoteType() == TransitNote::NOTETYPE_ASSIGNMENTNOTE)
    		$progressToStatus = TransitNoteService::DISPATCHED_TASK_STATUS_AN;

	    $fieldTasks = FacilityRequestLogic::getFieldTasksForPartsWithFacilityRequestsInWarehouseNode($this->transitNote->getTransitNoteLocation());

	    $tnSource = $this->transitNote->getSource();

		foreach ($fieldTasks as $fieldTask)
		{
			$warning = FacilityRequestLogic::getFacilityRequestWarningMessage($fieldTask, $tnSource);
			if ($warning['html'] === '')
				continue;

			$warningMessage = "<div class='task'>" . $warning['html'] . "</div>";

			$chkChecked = 'checked';
			$chkStyle = $chkExtraLbl = '';
			if ($warning['canPush'] !== true)
			{
				$chkChecked = '';
				$chkStyle = "style='text-decoration:line-through;'";
				$chkExtraLbl = "&nbsp;&nbsp&nbsp;<span style='color:red;'>You are unable to progress the task because " . $warning['canPush'] . "</span>";

			}
			$warningMessage .= "<div class='selecttask'>";
			$warningMessage .= "<input $chkChecked disabled type='checkbox' id='" . $fieldTask->getId() . "' name='selectedFieldTasks' value='" . $fieldTask->getId() . "'>";
			$warningMessage .= "<label for='" . $fieldTask->getId() . "' $chkStyle>Push Task to $progressToStatus</label> " . $chkExtraLbl;
			$warningMessage .= "</div>";

			$warningMessages[] = $warningMessage;
		}

    	if(count($warningMessages) > 0)
    	{
    		$this->progressToStatusLbl->Text = $progressToStatus;
    		$this->transitNoteConfirmLabel->Text = implode("<br />", $warningMessages);
    		$this->jsLbl->Text = "<script type=\"text/javascript\">showConfirmPushToTransitPanel();</script>";
    	}
    	else
    	{
    		$this->jsLbl->Text = "<script type=\"text/javascript\">pushNoteToTransit();</script>";
    	}
    }


    /**
     * Generate RA
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
 	public function generateRA($sender,$param)
    {
    	if (!$this->transitNote instanceof TransitNote)
    	{
    		return $this->onError("Invalid Dispatch Note!");
    	}

    	try
    	{
			if ($this->isDispatchNote)
			{
				$ra = null;
				if ($this->isTo3rdPartyRepairer) //is going to 3rd party repairer
				{
					$ra = Factory::service("ReturnAuthority")->getGenerateRaEntity($this->transitNote);
		    		if ($ra instanceof ReturnAuthority)
		    		{
	    				$alertMsg = '';
		    			$sessionArray = Session::getReturnAuthorityReviewedStatus($this->transitNote->getId());
		    			if ($sessionArray !== false)
		    			{
		    				if (array_key_exists('errMsg', $sessionArray[$this->transitNote->getId()]))
		    				{
		    					$alertMsg = 'alert("' . $sessionArray[$this->transitNote->getId()]['errMsg'] . '");';
		    				}
		    			}
						$raAssetId = Factory::service("ReturnAuthority")->getRaAssetId($this->transitNote);
		    			$this->jsLbl->Text = '<script type="text/javascript">childwindow = window.open("/report/download/' . $raAssetId . '");</script>';
			    		$this->reloadLbl->Text = '<script type="text/javascript">' . $alertMsg . 'window.location.reload();</script>';
		    		}
		    		else
		    		{
		    			return $this->onError("Unable to Generate the Return Authority (RA)...");
		    		}
				}
			}
    	}
    	catch (Exception $e)
    	{
    		return $this->onError($e->getMessage());
    	}
    }

    /**
     * Review RA
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
    public function reviewRA($sender,$param)
    {
    	if (!$this->transitNote instanceof TransitNote)
    		return $this->onError("Invalid Transit Note!");

    	try
    	{
		    $this->jsLbl->Text = '<script type="text/javascript">childwindow = window.open("/reviewRA/' . $this->transitNote->getId() . '");</script>';
    	}
    	catch (Exception $e)
    	{
    		return $this->onError($e->getMessage());
    	}
    }

    /**
     * View RA
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
    public function viewRA($sender,$param)
    {
    	if (!$this->transitNote instanceof TransitNote)
    		return $this->onError("Invalid Transit Note!");

    	try
    	{
    		$raAssetId = Factory::service("ReturnAuthority")->getRaAssetId($this->transitNote);
    		if ($raAssetId != null)
    			$this->jsLbl->Text = '<script type="text/javascript">childwindow = window.open("/report/download/' . $raAssetId . '");Modalbox.hide();</script>';
    		else
    			$this->onError("Error retrieving RA...");
    	}
    	catch (Exception $e)
    	{
    		return $this->onError($e->getMessage());
    	}
    }

    /**
     * Close transit note, when there is no part on it.
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function closeTransitNote($sender,$param)
    {
    	if (!$this->transitNote instanceof TransitNote)
    		return $this->onError("Invalid Transit Note!");

    	Factory::service("TransitNote")->closeTransitNote($this->transitNote);

    	$errMsg = $this->transitNote->getErrorMsg();
    	if ($errMsg != '')
    	{
    		$this->errorLabel->Text = $this->Page->alertMessageType->Value . $errMsg;
    		$this->transitNote->setErrorMsg('');
    	}

    	$this->onLoad("reload");

    }

   /**
     * if the code is either 189xxx numbers or BCPxxxxxxxx or BPxxxxxxxx, then return true
     *
     * @param String $code
     * @return boolean
     */
    protected function maybeNonSerialisedCode($code)
    {
    	if (preg_match("/^\s*\d{7}\s*$/", $code) == 1 ||
       		preg_match("/^\s*BCP\d{8}\s*$/i", $code) == 1 ||
       		preg_match("/^\s*BP\d{8}\w\s*$/i", $code) == 1)
			return true;
		return false;
    }

    /**
     * Check Parts
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function checkParts($sender, $param)
    {
   		$fromStr = $this->activePart->Value;
		$exception = false;
		try{
   			$candidates = Factory::service("PartInstance")->searchPartInstanceByBarcodeAndPartcode($fromStr, $fromStr, null, 30, null, true);
			if (!empty($candidates)){
				if (count($candidates) == 1 && $candidates[0]->getQuantity()==1)
				{
					$partInstance = $candidates[0];
					$partWarehouse = $partInstance->getWarehouse();
					if($partWarehouse instanceof Warehouse)
					{
						//checking whether this part is within another part
						if($partWarehouse->getId()==29){
							$exception = true;
							$this->jsLbl->Text = "<script type=\"text/javascript\">confirmPart('Part (" . StringUtils::addOrRemoveSlashes($partInstance). ") is within another part. Are you sure you wish to move?');</script>";
						}

						$kitWithoutChildren = Factory::service("PartInstance")->checkIfPartInstanceIsEmptyKit($partInstance);

						if($kitWithoutChildren){
							$exception = true;
							$this->jsLbl->Text = "<script type=\"text/javascript\">alert('Part (" . StringUtils::addOrRemoveSlashes($partInstance). ") is an empty kit and cannot be moved!');</script>";
						}
					}

					$partType = $partInstance->getPartType();
					if ($partType->getActive() == 0 && !$this->isAgent)  //block if NOT an agent and part type is inactive
					{
						$exception = true;
						$this->jsLbl->Text = "<script type=\"text/javascript\">alert('Part Type (" . StringUtils::addOrRemoveSlashes($partType). ") for Part Instance (" . StringUtils::addOrRemoveSlashes($partInstance). ") has been deactivated and cannot be moved!');hideModalbox();</script>";
					}
				}
			}

		}
		catch(Exception $ex)
		{
			$this->onError("Error:".$ex->getMessage());
		}

		if(!$exception)
		{
			$this->jsLbl->Text = "<script type=\"text/javascript\">movePart();</script>";
		}
	}

    /**
     * Search Parts
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
    public function searchParts($sender, $param)
    {
    	if(!$this->transitNote instanceof TransitNote)
    		return $this->onError("Invalid transit note!");

    	$fromStr = $this->searchPart->Text;
        $this->searchResult_multipleFound_Panel->Visible=false;

    	$fromWarehouse = null;
		if ($this->maybeNonSerialisedCode($fromStr))
		{
			$fromWarehouseId = $this->fromWarehouseid->getValue();
			$fromWarehouseId = explode('/', $fromWarehouseId);
			$fromWarehouseId = end($fromWarehouseId);
			$fromWarehouse = Factory::service("Warehouse")->getWarehouse($fromWarehouseId);
		}
    	$candidates = Factory::service("PartInstance")->searchPartInstanceByBarcodeAndPartcode($fromStr, $fromStr, null, 30, $fromWarehouse, true);
		if (empty($candidates))
			return $this->onError("No parts found for '$fromStr'!");

		 /**
	     * ********************************************
	     * This is for TOM ROSE!!!!!!
	     * This is preventing moving serialised part by just type in the part code!
	     * ********************************************
	     */
		$fromStr = strtoupper($fromStr);
		if(strstr($fromStr,"BCS")===false && strstr($fromStr,"BS")===false && strstr($fromStr,"BT")===false && strstr($fromStr,"BOX")===false && strstr($fromStr,"BX")===false)
		{
			/*
		     * ********************************************
		     * This is for Noel Burridge, wanting to move serialised part just by typing the manuf serial no
		     * But we make sure the quantity is exactly one.
		     * ********************************************
		     */
			if (count($candidates) == 1 && $candidates[0]->getQuantity()==1)
			{
				; // ok, pass through check
			}
			else if(!Factory::service("PartInstance")->checkContainingBCP($candidates))
			{
		    	return $this->onError("This is a serialised part, or the quantity available is more than one. Please use the serial number to move the part.");
			}
		}
		if (count($candidates) == 1 && $candidates[0]->getQuantity()==1)
		{
			try
			{
				$error = false;
				$partInstance = $candidates[0];

				try
				{
					$restricted = Factory::service("PartInstance")->checkIfPartIsRestricted($partInstance);
				}
				catch (Exception $e)
				{
					$error = true;
					return $this->onError(str_replace(":::", "<br /><br />", $e->getMessage()));
				}

				//check for open tasks
				$openTasks = Factory::service('FieldTask')->getOpenTasksForPI($partInstance);
				if (count($openTasks) > 0)
				{
					$ftIds = array_map(create_function('$a', 'return $a->getId();'), $openTasks);
					return $this->onError(PartInstanceLogic::getOpenTasksMessageForPartInstance($partInstance, $ftIds));
				}

				if (!$error)
				{
					$msg = Factory::service("TransitNote")->checkPartInstanceCanBeMovedOnTnDn($partInstance, $fromStr, $this->isAgent);
					if ($msg !== true)
					{
						$foundFeature = Session::checkRoleFeatures(array('pages_all','pages_logistics','page_logistics_partInstanceReRegister'));
						$this->onError($msg.=($foundFeature==true ? " <input type='Button' Value='Edit this Part' onclick=\"window.open('/reregisterparts/".$partInstance->getId()."/".htmlentities($msg)."');return false;\" />" : " Please contact Logistics to edit this part then move it!" ));
					}
					else
					{
						Factory::service("TransitNote")->putPartOntoTransitNote($this->transitNote,$candidates[0]);
						$this->onSuccess("Part added");
					}
				}
			}
			catch(Exception $ex)
			{
				$this->onError('Error adding Part : '.$ex->getMessage());
			}
		}
		else
		{
			$this->showSearchResultPanel($candidates);
		}

		$this->loadTransitNoteDetails($this->transitNote);
	    $this->searchPart->Text="";
        $this->searchPart->focus();
    }

    /**
     * Show Remove Part Panel
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
	public function showRemovePartPanel($sender,$param)
    {
    	$partInstanceId = $this->theDataList->DataKeys[$sender->Parent->ItemIndex];

    	if ($this->isAgent)
    	{
			if(!Factory::service("UserAccountFilter")->hasFilter(Core::getUser(),Core::getRole(),"ViewWarehouse"))
				return $this->onError("No UserAccountFilter 'ViewWarehouse' set for you, Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");
    	}

    	$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
    	if(!$partInstance instanceof PartInstance)
    		return $this->onError("Invalid part to remove! Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."! ");

    	if($param != null)
			$itemIndex = $sender->Parent->ItemIndex;
		else
			$itemIndex = 0;

    	$this->theDataList->SelectedItemIndex = -1;
		$this->theDataList->EditItemIndex = $itemIndex;
    	$this->loadTransitNoteDetails($this->transitNote);

    	$this->theDataList->getEditItem()->removingPartInstance_SerialNo->Text=$partInstance;
    	$this->theDataList->getEditItem()->removingPartInstance_Id->Value=$partInstanceId;
    	$this->theDataList->getEditItem()->targetWarehouseId->Value="";
    }

    /**
     * Cancel Remove Part
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function cancelRemovePart($sender, $param)
    {
    	$this->theDataList->EditItemIndex=-1;
    	$this->loadTransitNoteDetails($this->transitNote);
    }

    /**
     * Add From Part Multiple Found
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
	public function addFromPart_MultileFound($sender, $param)
    {
    	$qty = trim($this->partResultList_qty->Text);
   		if(!is_numeric($qty))
   			return $this->onError("Quantity must be numeric.");
   		if($qty<1)
   			return $this->onError("Quantity must be greater than 0.");

   		$partInstanceId = $this->partResultList->getSelectedValue();
   		$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
   		if($partInstance instanceof PartInstance)
   		{
   			if($qty>$partInstance->getQuantity())
   				return $this->onError("Not enough parts. $qty requrested, but there only ".$partInstance->getQuantity()." available.");

			try
			{
				Factory::service("TransitNote")->putPartOntoTransitNote($this->transitNote,$partInstance,$qty);
				$this->onSuccess("$qty part(s) added.");
			}
			catch(Exception $ex)
			{}
   		}
   		$this->loadTransitNoteDetails($this->transitNote);
    }

    /**
     * Remove Part
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
	public function removePart($sender, $param)
    {
    	$targetWarehouseIds = explode("/",$this->theDataList->getEditItem()->targetWarehouseId->Value);
    	$targetWarehouseId = end($targetWarehouseIds);
    	$targetWarehouse = Factory::service("Warehouse")->getWarehouse($targetWarehouseId);
    	if(!$targetWarehouse instanceof Warehouse)
    		return $this->onError("Invalid warehouse. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

    	$movingPartInstanceId= $this->theDataList->getEditItem()->removingPartInstance_Id->Value;
    	$movingPartInstance = Factory::service("PartInstance")->getPartInstance($movingPartInstanceId);
    	if(!$movingPartInstance instanceof PartInstance)
    		return $this->onError("Invalid part. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

    	try
    	{
    		Factory::service("TransitNote")->removePartFromTransitNote($this->transitNote,$movingPartInstance,$targetWarehouse);
	    	$this->onSuccess("Part removed.");
	    	$this->onLoad("reload");
	    	$this->theDataList->EditItemIndex = -1;
	    	//$this->loadTransitNoteDetails($this->transitNote);
    	}
    	catch(Exception $e)
    	{
    		$this->theDataList->EditItemIndex=$sender->Parent->ItemIndex;
    		$this->theDataList->getEditItem()->removingPartInstance_Id->Value = $movingPartInstanceId;
    		return $this->onError($e->getMessage());
    	}

    }

    /**
     * Check Recieved
     *
     * @param unknown_type $partInstanceId
     * @return unknown
     */
    public function checkReceived($partInstanceId)
    {
    	if(!$this->transitNote instanceof TransitNote)
    		return false;
    	$recievedIds = $this->transitNote->getReconciledInstances();
    	return in_array($partInstanceId,explode(",",$recievedIds));
    }

    /**
     * Redirect to Recieve Parts
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function redirectToRecieveParts($sender, $param)
    {
    	if ($this->isAgent)
    		$this->Response->redirect("/agent/receivepartin/transitnote/".$this->transitNote->getId());
    	else
    		$this->Response->redirect("/transferpartinward/transitnote/".$this->transitNote->getId());
    }

	/**
	 * Trying to display the barcode (in Code39 format) along with Text
	 *
	 * @param string $barcodeText
	 * @return string
	 */
    public function showBarcode($barcodeText,$id="")
    {
    	$barcode = $barcodeText;
    	try
    	{
	    	$partInstance = Factory::service("PartInstance")->get($id);
	    	$transiteNoteWarehouse = $this->transitNote->getTransitNoteLocation();
	    	$partInstanceWarehouse = $partInstance->getWarehouse();
	    	if(!$partInstanceWarehouse instanceof Warehouse)
	    		throw new Exception();


	    	$sql ="select * from transitnote where active = 1 and transitNoteLocationId = ".$partInstanceWarehouse->getId()." and id=".$this->transitNote->getId();
	    	if(count(Dao::getResultsNative($sql))==0)
	    	{
	    		$barcode .=" <img src='../../../themes/images/warning.png' title='Part no longer on this Transit Note'/>";
	    	}
    	}
    	catch(Exception $ex)
    	{}
    	//TODO: should translate the string into barcode (code39 font)
    	return $barcode;
    }

    /**
     * To Excel
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function toExcel($sender, $param)
    {
    	$transitNoteId = $this->transitNote->getId();
    	$transitNoteNo = $this->transitNote->getTransitNoteNo();
    	$status =  $this->transitNote->getTransitNoteStatus();
    	$source =  $this->transitNote->getSource();
    	$destination =  $this->transitNote->getDestination();

    	if ($source->getFacility()!=null && $source->getFacility()->getAddress() != Null) {
    		$sourceAddress = $source->getFacility()->getAddress();
    	}
    	else {
    		$sourceAddress="";
    	}

    	if ($destination->getFacility()!=null && $destination->getFacility()->getAddress() != Null) {
    		$destinationAddress = $destination->getFacility()->getAddress();
    	}
    	else {
    		$destinationAddress="";
    	}

    	$issuedDate =  $this->transitNote->getIssueDate()->getDate();

    	if ($issuedDate == '0001-01-01') $issuedDate = 'Not yet dispatched...';

    	$courierRefNo =  $this->transitNote->getCourierJobNo();
    	$courier =  $this->transitNote->getCourier();
    	// RobMcD
		$courierSpecialDelivery = $this->transitNote->getSpecialDeliveryInstructions();
    	$comments=  str_replace("!]","<br />",str_replace("[!","",$this->transitNote->getComments()));

    	$str = "<table border='1' style='font-weight:bold;'>
    				<tr valign='top'><td>Consignment Note:</td><td>$transitNoteNo</td></tr>
    				<tr valign='top'><td>Consignment Note Status:</td><td>$status</td></tr>
    				<tr valign='top'><td>From :</td><td>$source <br />$sourceAddress</td></tr>
    				<tr valign='top'><td>To :</td><td>$destination <br />$destinationAddress</td></tr>
    				<tr valign='top'><td>Date Issued :</td><td>$issuedDate</td></tr>
    				<tr valign='top'><td>Courier:</td><td>$courier</td></tr>
    				<tr valign='top'><td>Courier Ref No.:</td><td>$courierRefNo</td></tr>
    				<tr valign='top'><td>Special Delivery Instructions:</td><td>$courierSpecialDelivery</td></tr>
    				<tr valign='top'><td>Comments :</td><td>$comments</td></tr>
    			</table>
    			<br /><br />";


		//if the transitNote is not open any more, then we show the history
		if($this->canEditTransitNote==false)
		{
    		$results = TransitNoteLogic::getTransitNoteExtendedPartInfo($transitNoteId,false);
		}
		else
		{
			$results = TransitNoteLogic::getTransitNoteExtendedPartInfo($transitNoteId,true);
		}
		if ($this->isDispatchNote)
    	{
	   	 	$str .= "<table border='1'>
		    				<tr style='font-weight:bold;'>
		    					<td>Movement Time</td>
		    					<td>Make</td>
		    					<td>Model</td>
		    					<td>Serial Number</td>
		    					<td>Partcode</td>
		    					<td>Part Name</td>
		    					<td>Part Count</td>
		    					<td>Client Asset No</td>
		    					<td>Man Serial No</td>
		    					<td>Courier Con Note Num</td>
		    					<td>Courier</td>
		    					<td>Supplier Asset No</td>
		    					<td>IMEI</td>
		    				</tr>";


	    			foreach($results as $part)
	    			{
		    			$str .= "<tr>
			    					<td>&nbsp;{$part["created"]}</td>
			    					<td>&nbsp;{$part["make"]}</td>
			    					<td>&nbsp;{$part["model"]}</td>
			    					<td>{$part["serialno"]}</td>
			    					<td>&nbsp;{$part["partcode"]}</td>
			    					<td>&nbsp;{$part["partDescription"]}</td>
			    					<td>{$part["quantity"]}</td>
			    					<td>&nbsp;{$part["cliassno"]}</td>
			    					<td>&nbsp;{$part["manufno"]}</td>
			    					<td>&nbsp;{$part["courierConNoteNo"]}</td>
			    					<td>&nbsp;{$part["courier"]}</td>
			    					<td>&nbsp;{$part["piasan"]}</td>
			    					<td>&nbsp;{$part["piamei"]}</td>
		    					</tr>";

	    			}
    		}
    		else
    		{
    			$str .= "<table border='1'>
		    				<tr style='font-weight:bold;'>
		    					<td>Time added (UTC)</td>
		    					<td>Make</td>
		    					<td>Model</td>
		    					<td>Status</td>
		    					<td>Field Task</td>
		    					<td>Serial Number</td>
		    					<td>Partcode</td>
		    					<td>Part Name</td>
		    					<td>Part Count</td>
		    					<td>Client Asset No</td>
		    					<td>Man Serial No</td>
		    					<td>Courier Con Note Num</td>
		    					<td>Courier</td>
		    					<td>Supplier Asset No</td>
		    					<td>IMEI</td>
		    					<td>Received</td>
		    				</tr>";


	    			foreach($results as $part)
	    			{
		    			$str .= "<tr>
			    					<td>&nbsp;{$part["created"]}</td>
			    					<td>&nbsp;{$part["make"]}</td>
			    					<td>&nbsp;{$part["model"]}</td>
			    					<td>&nbsp;{$part["status"]}</td>
			    					<td>&nbsp;{$part["fieldTaskId"]}</td>
			    					<td>{$part["serialno"]}</td>
			    					<td>&nbsp;{$part["partcode"]}</td>
			    					<td>&nbsp;{$part["partDescription"]}</td>
			    					<td>{$part["quantity"]}</td>
			    					<td>&nbsp;{$part["cliassno"]}</td>
			    					<td>&nbsp;{$part["manufno"]}</td>
			    					<td>&nbsp;{$part["courierConNoteNo"]}</td>
			    					<td>&nbsp;{$part["courier"]}</td>
			    					<td>&nbsp;{$part["piasan"]}</td>
			    					<td>&nbsp;{$part["piamei"]}</td>
			    					<td>".($this->checkReceived($part["partInstanceId"])==true ? "YES" : "NO")."</td>
		    					</tr>";

	    			}
    		}

    	$str .= "</table>";
    	if($str!='')
    	{
    		$contentServer = new ContentServer();
    		if ($this->isDispatchNote)
    		{
    			$prefix = 'dispatch_note_';
    		}
    		else if ($this->isAssignmentNote)
    		{
    			$prefix = 'assignment_note_';
    		}
    		else
    		{
    			$prefix = 'transit_note_';
    		}

			$assetId = $contentServer->registerAsset(ContentServer::TYPE_REPORT, $prefix . $transitNoteNo .".xls", $str);
			$this->assetId->Value= $assetId;
    	}
    	$this->jsLbl->Text = '<script type="text/javascript">Modalbox.hide();</script>';
    }

	/**
     * get correct TransitNote based on URL
     *
     * @return TransitNote
     */
    private function getTransitNote($id,$loadDetails=true)
    {
    	$transitNote = Factory::service("TransitNote")->getTransitNote($id);
    	if($transitNote!=null && $transitNote instanceof TransitNote)
    	{
    		if($loadDetails==true)
    			$this->loadTransitNoteDetails($transitNote);
    	}
    	return $transitNote;
    }

    /**
     * Load TransitNote Details
     *
     * @param TransitNote $transitNote
     */
    protected function loadTransitNoteDetails(TransitNote $transitNote)
    {
    	$noteType = $transitNote->getNoteType();
    	$transitNoteNo = $transitNote->getTransitNoteNo();
    	$this->TransitNoteNo->Text = $transitNoteNo . "<br /><img src='/ajax/?method=renderBarcode&text=$transitNoteNo' />";

    	if (!$this->isAgent)
    	{
	    	if ($this->isDispatchNote)
	    	{
	    		$this->TransitNoteNoLabel->Text = "Dispatch Note #:";
	    		$this->TransitNoteStatusLabel->Text = "Dispatch Note Status:";
	    		$this->AddingPartsLabel->Text = "Adding Parts To Dispatch Note";
	    		$this->ReprintButton->Text = "Reprint Dispatch Note";
	    		$this->CloseButton->Text = "Close Dispatch Note";
	    		$this->SaveButton->Text = "Save, Print and Dispatch (DN)";

	    	}
	    	else if($this->isAssignmentNote)
	    	{
	    		$this->TransitNoteNoLabel->Text = "Assignment Note #:";
	    		$this->TransitNoteStatusLabel->Text = "Assignment Note Status:";
	    		$this->AddingPartsLabel->Text = "Adding Parts To Assignment Note";
	    		$this->ReprintButton->Text = "Reprint Assignment Note";
	    		$this->CloseButton->Text = "Close Assignment Note";
	    		$this->SaveButton->Text = "Save, Print and Dispatch (AN)";
	    	}
	    	else
	    	{
	    		$this->TransitNoteNoLabel->Text = "Transit Note #:";
	    		$this->TransitNoteStatusLabel->Text = "Transit Note Status:";
	    		$this->AddingPartsLabel->Text = "Adding Parts To Transit Note";
	    		$this->ReprintButton->Text = "Reprint Transit Note";
	    		$this->CloseButton->Text = "Close Transit Note";
	    		$this->SaveButton->Text = "Save, Print and Dispatch (TN)";

    		}
    	}

    	//get From and To location address!
  		$sourceLocation = $transitNote->getSource();
  		$destinationLocation = $transitNote->getDestination();
  		$destinationSite = $transitNote->getDestinationSite();
    	//Courier Barcode.
    	$tntData = Factory::service('Lu_CourierDestinationTrackingBarcode')->findByCriteria("courierWarehouseId=11168 and entityName = 'Warehouse' and entityId=?",array($destinationLocation->getId()));
    	if(count($tntData)>0 && sizeof($tntData)>'')
     		$this->CourierBarcode->Text = "<br /><img src='/ajax/?method=renderBarcode&text={$tntData[0]->getTrackingBarcode()}' />";

  		if($sourceLocation->getFacility()!=null && $sourceLocation->getFacility()->getAddress() != Null)
  		{
  			$facilityName = $sourceLocation->getAlias(WarehouseAliasType::$aliasTypeId_facilityName);
  			if (!is_null($facilityName) && $facilityName != '')
  				$facilityName = '<br /><span style="font-style:italic;">' . $facilityName . '</span><br />';

  			$this->TransitNoteFrom->Text = '<span style="font-weight:bold;">' . $sourceLocation->getName() . " <br />" . $facilityName . '</span>' . Factory::service("Address")->getAddressInDisplayFormat($sourceLocation->getFacility()->getAddress());
  		}
  		else if($sourceLocation->getWarehouseCategory()->getId() == WarehouseCategory::ID_TECH && $this->isAssignmentNote)
  		{
			$this->TransitNoteFrom->Text = "<b style='font-weight:bold;'>" . Factory::service("Warehouse")->getWarehouseBreadCrumbs($sourceLocation,true) . ".</b>";
  		}
  		else
  		{
  			$this->TransitNoteFrom->Text = "<b style='color:#ff0000'>Warehouse is missing address details.</b>";
  		}

  		$specialDeliveryInstructions = '';
  		//to address
  		$courierBarcode=array();
  		if ($destinationSite instanceof Site)
  		{
  			$courierBarcode = Factory::service('Lu_CourierDestinationTrackingBarcode')->findByCriteria('courierWarehouseId = ? and entityName = ? and entityId = ?',array($transitNote->getCourierJobNo(),'Site',$destinationSite->getId()));
  			if ($destinationSite->getServiceAddress() != null)
  			{
  				$this->TransitNoteTo->Text = '<b>Sites</b><br />' . $destinationSite->getCommonName() . " <br />" . Factory::service("Address")->getAddressInDisplayFormat($destinationSite->getServiceAddress());
  			}
	  		else
	  		{
	  			$this->TransitNoteTo->Text = "<b style='color:#ff0000'>Site is missing address details.</b>";
	  		}
  		}
  		else
  		{
  			$specialDeliveryInstructions = $destinationLocation->getAlias(WarehouseAliasType::ALIASTYPEID_SPEC_DEL_INST_ID);
  			$this->TransitNoteTo->Text = '<span style="font-weight:bold;">' . $destinationLocation->getName() . "</span>";
  			$courierBarcode = Factory::service('Lu_CourierDestinationTrackingBarcode')->findByCriteria('courierWarehouseId = ? and entityName = ? and entityId = ?',array($transitNote->getCourierJobNo(),'Warehouse',$destinationLocation->getId()));

  			$facility = $destinationLocation->getFacility();
			if ($facility instanceof Facility && $address = $facility->getAddress())
			{
	  			$company = $destinationLocation->getCompany();
	  			if ($company instanceof Company)
	  			{
	  				$this->TransitNoteTo->Text .= "<br />" . $company->getName() . "<br />";
	  			}

				$facilityName = $destinationLocation->getAlias(WarehouseAliasType::$aliasTypeId_facilityName);
	  			if ($facilityName != null && $facilityName != '')
	  			{
	  				$this->TransitNoteTo->Text .= '<br /><span style="font-style:italic; font-weight:bold;">' . $facilityName . '</span>';
	  			}

	  			$addressName = $address->getAddressName();
	  			if ($addressName != '')
	  			{
	  				$this->TransitNoteTo->Text .= '<br /><span style="font-style:italic; font-weight:bold;">' . $addressName . '</span>';
	  			}

	  			$this->TransitNoteTo->Text .= "<br />" . Factory::service("Address")->getAddressInDisplayFormat($address) . "<br/>";

                // To display phone mobile and Fax if company if exists.
                if ($company instanceof Company)
                {
                	$this->TransitNoteTo->Text .=  	"<br />" .
                  									$company->getPhoneNumber() . "<br/>" .
                  									$company->getEmail() . "<br/>" .
                                         			$company->getFax();
               	}
			}
			else
			{
				if ($noteType == TransitNote::NOTETYPE_ASSIGNMENTNOTE)
				{
					$this->TransitNoteTo->Text = '<span style="font-weight:bold;">' . Factory::service("Warehouse")->getWarehouseBreadCrumbs($destinationLocation, true) . '</span>';
				}
				else
				{
	  				$this->TransitNoteTo->Text .= "<b style='color:#ff0000'>Warehouse is missing address details.</b>";
				}
			}
  		}
  		//Courier Barcode.
  		if(count($courierBarcode)>0 && sizeof($courierBarcode)>'')
  			$this->CourierBarcode->Text = "<br /><img src='/ajax/?method=renderBarcode&text=".$courierBarcode[0]->getTrackingBarcode()."' />";

  		$tnSpecDelInst = $transitNote->getSpecialDeliveryInstructions();
  		if ($tnSpecDelInst == '')												//only if there are none set for the TN itself
  		{
  			$tnSpecDelInst = $specialDeliveryInstructions;		//then we use the warehouse alias
  		}
      	$this->SpecialDeliveryInstructions->Text = $tnSpecDelInst;

  		$this->TransitNoteStatus->Text = $transitNote->getTransitNoteStatus();
       	$this->IssueDate->Text = "";

      	//listing all parts on that transitNote
       	$parts = $this->listParts();

       	if ($transitNote->getNoOfPackages() == 0) $this->TotalPackages->Text = '';
       	else $this->TotalPackages->Text = (string)$transitNote->getNoOfPackages();

       	$this->ExistingCommentsLabel->Visible=true;

       	$htmlComments = Factory::service("TransitNote")->formatCommentsForHTML($transitNote);
       	$count = 0;
       	foreach (explode("<br />", $htmlComments) as $com)
       	{
       		if ((strlen($com)) > 144) $count++; //line wrap
       		$count++;
       	}
       	$commentsHeight = 38 * $count;

       	$this->NotesWindow->SetStyle("height:{$commentsHeight}px;");
       	$this->NotesWindow->Visible=true;

  		$this->ExistingComments->Text=$htmlComments;

       	$this->ClientJobNos->Text=$transitNote->getClientJobNos();
       	$this->CourierJobNo->Text=$transitNote->getCourierJobNo();

  		$this->Courier->DataSource =Factory::service("Warehouse")->getCourierWarehouses();
        $this->Courier->DataBind();
        $courier = $transitNote->getCourier();
        if(trim($courier)!="")
        {
        	$this->Courier->setSelectedValue(Factory::service("TransitNote")->getSelectedCourierWarehouseByName($courier));
        }

        $this->deliveryMethod->dataSource = DropDownLogic::getDeliveryMethod();
        $this->deliveryMethod->dataBind();

        $deliveryMethod = $transitNote->getDeliveryMethod();
        if ($deliveryMethod != '' && $deliveryMethod != 0)
        {
        	$this->deliveryMethod->setSelectedValue($deliveryMethod);
        	$selected = $this->deliveryMethod->getSelectedValuesText();
        	$this->deliveryMethodLabel->Text = array_pop($selected);
        }

        $timeData = DropDownLogic::getHoursMinutesSeconds();
        $this->etaTime->dataSource = $timeData['HH:00'];
        $this->etaTime->dataBind();

        $timeZone = Factory::service("Warehouse")->getWarehouseTimeZone($sourceLocation);
        $this->etaTimezoneLabel->Text = '(' . $timeZone . ')';

        $eta = $transitNote->getEta();
        if ($eta instanceof HydraDate && $eta != HydraDate::zeroDateTime())
        {
        	$eta->setTimeZone($timeZone);
        	$this->etaDate->Text = str_pad($eta->getDayOfTheMonth(), 2, "0", STR_PAD_LEFT) . '-' . str_pad($eta->getMonth(), 2, "0", STR_PAD_LEFT) . '-' . $eta->getYear();
        	$this->etaTime->setSelectedValue(str_pad($eta->getHours(), 2, "0", STR_PAD_LEFT) . ':' . str_pad($eta->getMinutes(), 2, "0", STR_PAD_LEFT));
        	$this->etaLabel->Text = $this->etaDate->Text . ' ' . $this->etaTime->getSelectedValue();
        }

  		//disable fields according to the current status!
       	if($transitNote->getTransitNoteStatus() != TransitNote::STATUS_OPEN)
       	{
	       	$this->SpecialDeliveryInstructionsLabel->Text = $this->SpecialDeliveryInstructions->Text;
	       	$this->SpecialDeliveryInstructions->Visible=false;

			$timezone = 'UTC';
			$issueDate = $transitNote->getIssueDate();

			$company = Factory::service("Company")->getCompanyFromUserAccount(Core::getUser());
			if ($company instanceof Company)
			{
				$address = $company->getAddress();
				if ($address instanceof Address)
				{
					$timezone = $address->getTimezone();
			       	$issueDate->setTimeZone($timezone);
				}
			}

	       	$this->IssueDateLabel->Text = "$issueDate ($timezone)";
	       	$this->IssueDate->Visible=false;

	       	$this->TotalItemsLabel->Text = $this->TotalItems->Text;
	       	$this->TotalItems->Visible=false;

	       	$this->TotalPackagesLabel->Text = $this->TotalPackages->Text;
	       	$this->TotalPackages->Visible=false;

//        	$this->ClientJobNosLabel->Text = $this->ClientJobNos->Text;
	       	$this->ClientJobNos->Text = $this->ClientJobNos->Text;

	       	try{$this->CourierLabel->Text = $this->Courier->SelectedItem->Text;}
	       	catch(Exception $e)
	       	{
	       		$this->CourierLabel->Text=$transitNote->getCourier();
	       	}
	       	$this->Courier->Visible = false;
	       	$this->CourierJobNo->Text = $this->CourierJobNo->Text;

	       	$this->SaveButton->Visible = false;
       		$this->RecievePartsButton->Visible = false;

       		if ($transitNote->getTransitNoteStatus() == TransitNote::STATUS_TRANSIT)
       		{
       			$this->RecievePartsButton->Visible = true;
       		}
       		else
       		{
       			if (!$this->isAgent) $this->ReprintButton->Visible = true;
       		}

       		if ($this->isTo3rdPartyRepairer && $this->isDispatchNote) //is going to 3rd party repairer
    		{
    			if (Factory::service("ReturnAuthority")->getRaAssetId($this->transitNote) != null)
    				$this->viewRaButton->Style = '';

	    		$this->generateRaButton->Style = 'display:none;';
	    		$this->reviewRaButton->Style = 'display:none;';
    		}

    		$this->deliveryMethod->Style = 'display:none;';
    		$this->etaDate->Style = 'display:none;';
    		$this->etaTime->Style = 'display:none;';
    		$this->etaLabel->Style = '';
    		$this->deliveryMethodLabel->Style = '';
       	}
       	else
       	{
       		if ($this->isTo3rdPartyRepairer && $this->isDispatchNote) //is going to 3rd party repairer
    		{
    			$parts = Factory::service("PartInstance")->findByCriteria("warehouseId=?",array($this->transitNote->getTransitNoteLocation()->getId()),false);
    			$piIds = array();
    			foreach ($parts as $part)
    			{
    				$piIds[] = $part->getId();
    			}

    			try
    			{
    				//check here if any of the parts are on unreconciled RAs
    				$raps = Factory::service("ReturnAuthorityPart")->getOpenRAPsForPIIds($piIds);
    				if (count($raps) > 0)
    				{
    					$errMsg = array();
    					foreach ($raps as $repId => $piIds)
    					{
    						$repairer = Factory::service("Warehouse")->get($repId);

    						$serialNos = array();
    						foreach ($piIds as $piId)
    							$serialNos[] = Factory::service("PartInstance")->get($piId)->getAlias();

    						$href = '<a href="#" onclick="window.open(\'/reconcileRA/' . $repairer->getId() . '\'); return false;">reconcile</a>';
    						$errMsg[] = "The following RA part(s) are unreconciled at '" . $repairer->getName() . "', please $href them first [" . implode(', ', $serialNos) . ']';
    					}
    					$errMsg[] = '<br /><a href="#" onclick="window.location.reload();return false;">Click here to reload the page to re-validate the data.</a>';
    					throw new Exception (implode('<br />', $errMsg));
    				}

	    			//check here if we can allow more than one part on the DN, looking at OPEN tasks with the FTP set matching the part instances
	    			$taskInfo = Factory::service("ReturnAuthority")->getFieldTaskInfoForReceivedPartInstancesFromFieldTaskProperty($piIds, null, true);
	    			if (count($taskInfo) > 1)
	    			{
	    				$piIds = array();
	    				foreach ($taskInfo as $ti)
	    				{
	    					$piIds[] = $ti['piId'];
	    				}
	    				if (count(array_unique($piIds)) == 1)
	    				{
			    			throw new Exception('This Dispatch Note has a part instance linked to multiple tasks (' . implode(', ', array_keys($taskInfo)) . ') unable to continue, please contact your supervisor.');
	    				}
	    				throw new Exception('This Dispatch Note has part instances linked to multiple tasks (' . implode(', ', array_keys($taskInfo)) . ') that only allow one part instance to be sent on one Return Authority, please remove parts as need be.');
	    			}
    			}
    			catch (Exception $ex)
    			{
    				$this->searchPart->Enabled = false;
    				$this->searchPart_SearchButton->Enabled = false;

    				$this->viewRaButton->Style = 'display:none;';
    				$this->generateRaButton->Style = 'display:none;';
    				$this->SaveButton->Style = 'display:none;';
    				$this->reviewRaButton->Style = 'display:none;';

    				$this->setErrorMessage($ex->getMessage());
    				return;
    			}

    			//we've generated the RA already
    			if (Factory::service("ReturnAuthority")->getRaAssetId($this->transitNote) != null)
    			{
    				$this->searchPart->Enabled = false;
    				$this->searchPart_SearchButton->Enabled = false;

		    		$this->SaveButton->Text = "Save, Print and Dispatch (DN) & Email RA to Repairer";
	    			$this->SaveButton->Style = '';

	    			$this->viewRaButton->Style = '';
	    			$this->generateRaButton->Style = 'display:none;';
    			}
    			else
    			{
	    			$this->viewRaButton->Style = 'display:none;';
	    			$this->SaveButton->Style = 'display:none;';

	    			$sessionArray = Session::getReturnAuthorityReviewedStatus($transitNote->getId());
		    		if ($sessionArray !== false)
		    		{
		    			$this->searchPart->Enabled = false;
		    			$this->searchPart_SearchButton->Enabled = false;

		    			$this->reviewRaButton->Style = '';
		    			$this->generateRaButton->Style = '';
		    		}
		    		else
		    		{
		    			$this->reviewRaButton->Style = '';
		    		}
    			}
    		}

    		$this->etaLabel->Style = 'display:none;';
    		$this->deliveryMethodLabel->Style = 'display:none;';

       	}

       	//if assignment note hide eta and delivery method
       	if ($noteType == TransitNote::NOTETYPE_ASSIGNMENTNOTE)
       	{
       		$this->deliveryMethod->setSelectedValue(3);
       		$this->deliveryMethod->Style = 'display:none;';
       		$this->deliveryMethodLabel->Text = 'N/A';
       		$this->deliveryMethodLabel->Style = '';

       		$now = new HydraDate('now', $timeZone);
       		$now->modify('+1 day');
       		$this->etaDate->Style = 'display:none;';
       		$this->etaDate->Text = str_pad($now->getDayOfTheMonth(), 2, "0", STR_PAD_LEFT) . '-' . str_pad($now->getMonth(), 2, "0", STR_PAD_LEFT) . '-' . $now->getYear();
       		$this->etaTime->setSelectedValue(str_pad($now->getHours(), 2, "0", STR_PAD_LEFT) . ':00');

       		$this->etaTime->Style = 'display:none;';

       		$this->etaLabel->Text = 'N/A';
       		$this->etaLabel->Style = '';
       		$this->etaTimezoneLabel->Text = '';
       	}
    }

    /**
     * Show Search Result Panel
     *
     * @param unknown_type $results
     */
	private function showSearchResultPanel($results)
    {
    	$this->searchResult_multipleFound_Panel->Visible = true;
    	$this->Page->setFocus($this->partResultList_qty->getClientID());
    	$this->bindPartInLocationList($results);
    	$this->loadTransitNoteDetails($this->transitNote);
    }

    /**
     * Bind Part in Location List
     *
     * @param unknown_type $results
     */
	private function bindPartInLocationList($results)
	{
    	$array = array();
    	foreach($results as $partInstance)
    	{
    		$warehouse = $partInstance->getWarehouse();
    		if(!$warehouse instanceof  Warehouse)
    			continue;

    		$site = $partInstance->getSite();
    		if($site instanceof Site && $warehouse->getId()==27)
    			$warehouse = $site."($warehouse)";

    		$partInstanceDescription = $partInstance->getPartType()." : ";
    		foreach($partInstance->getPartInstanceAlias() as $alias)
    		{
    			if(in_array($alias->getPartInstanceAliasType()->getId(),array(1,2,3,4,6)))
    				$partInstanceDescription .= $alias;
    		}
    		$ftId = PartInstanceLogic::getFieldtaskIdByPartInstance($partInstance);//only  for pre-allocated
    		$array[] = array("id"=>$partInstance->getId(),"name" => $partInstanceDescription." | ".$warehouse." | ".$partInstance->getPartInstanceStatus().$ftId." | ".$partInstance->getQuantity());

    	}
		usort($array, create_function('$a, $b', 'return strcmp($a["name"], $b["name"]);'));
    	if(count($array)==0)
    		$array[] = array("id"=>"","name"=>"No parts found.");
    	$this->partResultList->DataSource = $array;
    	$this->partResultList->DataBind();
	}

 	/**
	 * onError
	 *
	 * @param unknown_type $errorMessage
	 * @param unknown_type $sound
	 * @return unknown
	 */
	protected function onError($errorMessage)
	{
		$this->setErrorMessage('');
		$this->page->formPanel->Visible=true;
		$this->page->formPanel->Text = "<font color='red'><b>".$errorMessage."</b></font>";

		$this->setErrorMessageSound($errorMessage);
		$this->loadTransitNoteDetails($this->transitNote);
		return false;
	}

	/**
	 * onSuccess
	 *
	 * @param unknown_type $infoMessage
	 * @param unknown_type $sound
	 * @return unknown
	 */
	protected function onSuccess($infoMessage)
	{
		$this->setInfoMessage('');
		$this->page->formPanel->Visible=true;
		$this->page->formPanel->Text = "<font color='green'><b>".$infoMessage."</b></font>";

		$this->setInfoMessageSound($infoMessage);
		$this->loadTransitNoteDetails($this->transitNote);
		return false;
	}


	/**
	 * Reprint TransitNote
	 *
	 */
	public function reprintTransitNote()
	{
		$this->onLoad("print");
	}

	/**
	 * List Parts
	 *
	 * @return unknown
	 */
	private function listParts()
	{
		if(!$this->transitNote instanceof TransitNote)
			return;

		$array = array();
		$totalQuantity = 0;

		$this->theDataList->DataSource =array();
    	$this->theDataList->DataBind();
    	$this->PaginationPanel->Visible=false;

    	$pageNumber = $this->theDataList->CurrentPageIndex+1;
    	$pageSize = $this->theDataList->pageSize;

		$this->theDataList->DataSource = $this->getParts($pageNumber,$pageSize);
       	$this->theDataList->DataBind();


		//show close button if there are no parts left and the status is 'open'
		if ($this->qty == 0 && $this->transitNote->getTransitNoteStatus() == 'open')
		{
			$this->Page->CloseButton->Visible = true;
		}

		$this->totalRows = $this->itemCount;
    	$this->theDataList->VirtualItemCount = $this->totalRows;
    	$this->theDataList->DataBind();

		if($this->totalRows > $this->theDataList->PageSize)
		{
			$this->PaginationPanel->Visible=true;
			$this->isPaginated->Value = 1;
		}
		else
		{
			$this->PaginationPanel->Visible=false;
			$this->isPaginated->Value = 0;
		}
	}

	/**
     * This method revoked for pagination
     */
 	public function pageChanged($sender, $param)
    {
      	$this->theDataList->CurrentPageIndex = $param->NewPageIndex;
      	$this->listParts();
    }

	/**
	 * Get Parts
	 *
	 * @param int $pageNumber
	 * @param int $pageSize
	 * @return multitype:multitype:NULL  multitype:NULL string Ambigous <string, number> number
	 */
    public function getParts($pageNumber,$pageSize)
    {
    	$array = array();
    	//if the transitNote is not open any more, then we show the history
		if($this->canEditTransitNote==false)
		{
			$totalItems = Factory::service("LogPartsInTransitNote")->countByCriteria("transitNoteId=?",array($this->transitNote->getId()),false);
			$partLogs = Factory::service("LogPartsInTransitNote")->findByCriteria("transitNoteId=?",array($this->transitNote->getId()),false,$pageNumber,$pageSize);
			foreach($partLogs as $partLog)
	  		{
	  			$array[] = array("id"=>$partLog->getPartInstance()->getId(),
	  								"serialNo"=>$partLog->getSerialNumber(),
	  								"manfSerialNo"=>$partLog->getManuNumber(),
	  								"taskNo"=>$partLog->getFieldTaskNumber(),
	  								"partinstancestatus"=>$partLog->getPartStatus(),
	  								"partcode"=>$partLog->getPartcode(),
	  								"partDescription"=>$partLog->getPartDescription(),
	  								"qty"=>$partLog->getQty());
	  			//$totalQuantity +=$partLog->getQty();
	  		}

	  		$parts =array();

	  		$qry = "SELECT sum(qty) FROM logpartsintransitnote l where transitNoteId = ".$this->transitNote->getId();
	  		$results = Dao::getResultsNative($qry);
			if(count($results)==0 && sizeof($results)=="")
			$results[0][0] = 0;

			$this->qty = $results[0][0];
	  		$this->TotalItems->Text = $results[0][0];
			$this->itemCount=$totalItems;
		}
		else
		{
			$warehouseLocation = $this->transitNote->getTransitNoteLocation();
			if(!$warehouseLocation instanceof Warehouse)
			 	$parts = array();
			else
			{
		      	$totalItems = Factory::service("PartInstance")->countByCriteria("warehouseId=?",array($warehouseLocation->getId()),false);
				$parts = Factory::service("PartInstance")->findByCriteria("warehouseId=?",array($warehouseLocation->getId()),false,$pageNumber,$pageSize);

				foreach($parts as $part)
		  		{
		  			$partType = $part->getPartType();

		  			$serialNos = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($part->getId(),1);
		  			if (count($serialNos) > 0 && $partType->getSerialised()==1) $serialNo =  $serialNos[0]->getAlias();
		  			else
		  			{
		  				$ptAlias = Factory::service("PartType")->getPartTypeAliasByTypeForPartType($part->getPartType()->getId(), 2); //barcode
		  				if (count($ptAlias) > 0)
		  					$serialNo = $ptAlias[0]->getAlias();
		  				else
		  					$serialNo = '';
		  			}

		  			$manfSerialNos = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($part->getId(),6);
		  			$manfSerialNo = count($manfSerialNos)>0 ? $manfSerialNos[0]->getAlias() : "";

		  			$partCodes = Factory::service("PartType")->searchPartTypeAliaseByPartTypeAliasTypeForPartType($partType->getId(),1);
		  			$partCode = count($partCodes)>0 ? $partCodes[0]->getAlias() : "";

		  			$taskNo = "";
		  			$facilityRequest = $part->getFacilityRequest();
		        	if($facilityRequest instanceof FacilityRequest)
		        	{
		        		$fieldTask = $facilityRequest->getFieldTask();
		        		$taskNo = $fieldTask->getId();
		        	}
		  			$array[] = array("id"=>$part->getId(),
		  								"serialNo"=>$serialNo,
		  								"manfSerialNo"=>$manfSerialNo,
		  								"taskNo"=>$taskNo,
		  								"partinstancestatus"=>$part->getPartInstanceStatus()->getName(),
		  								"partcode"=>$partCode,
		  								"partDescription"=>$partType->getName(),
		  								"qty"=>$part->getQuantity());

		  			//$totalQuantity +=$part->getQuantity();

					$this->itemCount=$totalItems;
		  		}
	  		}


	  		$qry = "SELECT SUM(pi.quantity)
				FROM partinstance pi
				INNER JOIN warehouse w ON (w.id=pi.warehouseid)
				WHERE pi.active=1
				AND w.id = ".$warehouseLocation->getId();

	  		$results = Dao::getResultsNative($qry);

			if(count($results)==0 && sizeof($results)=="")
			$results[0][0] = 0;

			$this->qty = $results[0][0];
			$this->TotalItems->Text = $results[0][0];
		}

			return $array;
    }


 	/**
 	 * cycle through via ajax until all the parts list is generated for Printing
 	 *
 	 * @return unknown
 	 */

    public function processPrint()
    {
    	$pageSize = $this->theDataList->pageSize;
    	$tempArray =array();
    	$partsArray = array();

    	try
    	{
    		if($this->partsArray->Value)
    		{
    			$partsArray = unserialize($this->partsArray->Value);
    		}

    		$pageNumber = $this->pageNumber->Value;
    		if(!$pageNumber)
    		{
				$pageNumber = 1;
    		}
    		else
    		{
    			$pageNumber++;
    		}


    		$tempArray = $this->getParts($pageNumber,$pageSize);

    		$partsArray = array_merge($partsArray, $tempArray);

    		$this->pageNumber->Value = $pageNumber;


    		$this->partsArray->Value = serialize($partsArray);

    		if(empty($tempArray))
    		{

    			return array('stop' => true);
    		}

    	}
    	catch (Exception $e)
    	{
    		$this->exceptionMessage->Value = $e->getMessage();

    		return array('stop' => true);
       }
       return array('stop' => false);

    }


 	/**
     * call javascript function to hide modal box and call finishProcessingPrint()
     *
     */
	public function finishProcessingPrint()
	{
    	$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessingPrint();</script>";
    }


    /**
	 * Finish ajax processing, show messages
     *
     */
    public function finishPrinting()
    {
    	$array = array();
    	$array = unserialize($this->partsArray->Value);

    	$this->theDataList->DataSource = $array;
   		$this->theDataList->PageSize = count($array);
    	$this->theDataList->DataBind();
    	$this->PaginationPanel->visible = false;

    	if ($this->processNote->Value == 1)
    	{
    		$this->onLoad("print");
    	}
    	else
    	{
    		$this->finishProcessingNote();
    	}
    }
}

?>
