<?php
ini_set("display_errors", 1);
error_reporting(E_ALL);
/**
 * This is the "Transfer Parts" Page
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @filesource
 * @version	1.0
 * @author  Lin He <lhe@bytecraft.com.au>
 */
class MovePartInstancesWithTransitNoteController extends HydraPage
{
	/**
	 * @var totalItems
	 */
	public $totalItems;

	/**
	 * @var menuContext
	 */
	public $menuContext;

	/**
	 * @var Warehouse - Boolean use default warehouse for the user!
	 */
	protected $defaultWarehouse;

	/**
	 * @var Warehouse - Default warehouse for the user!
	 */
	protected $thisWarehouseService;

	/**
	 * @var isAgen
	 */
	protected $isAgent;

	/**
	 * @var inOrOut
	 */
	protected $inOrOut;

	/**
	 * @var ismainstore=
	 */
	protected $ismainstore='';

	/**
	 * @var unknown_type
	 */
	public $itemCount;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->inOrOut = 'out';
	}

	/**
	 * On Pre initialization
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
		$str = explode('/',$_SERVER['REQUEST_URI']);
		$this->menuContext = "transferpartoutwards";
		if ($str[1] == 'agent')
		{
			$this->isAgent = true;
			$this->getPage()->setMasterClass("Application.layouts.NoExtJs.AgentLogisticsLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_agent_logistics_transferPartOutwards";
		}
		else if ($str[1] == 'staging')
		{
			$this->isAgent = false;
			$this->getPage()->setMasterClass("Application.layouts.NoExtJs.StagingLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_agent_logistics_transferPartOutwards,menu_staging";
		}
		else
		{
			$this->isAgent = false;
			$this->getPage()->setMasterClass("Application.layouts.NoExtJs.LogisticsLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_transferPartOutward";
		}

	}

	/**
	 * On Initialization
	 *
	 * @param unknown_type $param
	 */
	public function onInit($param)
	{
		parent::onInit($param);
		if ($this->inOrOut == "out")
		{
			$tnExclude = implode(",", Factory::service("TransitNote")->getTransitNoteExcludeCategoryIds());
			$this->TransitNoteToList->setExcludeWarehouseCategoryIds($tnExclude);
			$this->OtherTransitNoteToList->setIncludeWarehouseCategoryIds(WarehouseCategoryService::$categoryId_SiteWarehouse_TN);
			$this->clientWarehouseList->setIncludeWarehouseCategoryIds(WarehouseCategoryService::$categoryId_ClientWarehouse);
			$this->SiteList->setAppendContractInfo(1); //append contract info
			$this->SiteList->getExcludeContractIds(1); //alpha
		}
	}

	/**
	 * Returns true if there are technicians in the current users default warehouse
	 *
	 * @return boolean $technicians
	 */
	private function checkIfTechniciansInDefaultWarehouse()
	{
		$filters = trim(Factory::service("UserAccountFilter")->getFilterValue(Core::getUser(),"ViewWarehouse",Core::getRole()));
		$technicians = false;

    	if(!$filters){
    		$filters = "1";
    	}

    	foreach(explode(",",$filters) as $warehouseid){
	    	$warehouseInstance = Factory::service("Warehouse")->getWarehouse($warehouseid);
	    	if($warehouseInstance instanceOf Warehouse){
	    		$sql = "select warehouseCategoryId from warehouse where  position like '" . $warehouseInstance->getPosition() . "%'
	    		and warehouseCategoryId = " . WarehouseCategory::ID_TECH . " and active = 1 ";
	    		$results = Dao::getResultsNative($sql);
	    		if(count($results)>0){
	    			$technicians = true;
	    		}
	    	}
    	}
    	return $technicians;
	}

	/**
     * Called after to technician auto-complete suggestion is selected
	 *
	 * @param unknown_type $val
	 */
    public function addToTechnicianSuggestionSelected($val)
    {
    	$this->Page->jsLbl->Text = "<script type=\"text/javascript\">setWarehouseId('" . $val . "');</script>";
    }

    /**
     * Add to Technician Suggestion
     *
     */
	public function addToTechnicianSuggestionSuggest()
    {
    	$this->Page->jsLbl->Text = "<script type=\"text/javascript\">setWarehouseId('');</script>";
    }

    /**
     * Get Default warhouseid
     *
     * @return unknown
     */
	public function getDefaultWarehouseId()
    {
    	if ($this->defaultWarehouse === false)
    		return null;
    	else
    		return $this->defaultWarehouse->getId();
    }

    /**
     * Is DWH Mainstore
     *
     * @return unknown
     */
    public function IsDWHMainStore()
    {
    	if ($this->defaultWarehouse === false)
    		return false;
    	else
    		return $this->defaultWarehouse->getAlias(13) ? "true" : "false";
    }

    /**
     * OnLoad
     *
     * @param unknown_type $param
     */
	public function onLoad($param)
	{
		parent::onLoad($param);
		$this->jsLbl->setText("");
		$this->setErrorMessage("");
		$this->setInfoMessage("");
		$this->page->formPanel->Visible=false;
		$this->MainContent->Enabled = true;
		$this->fieldTaskLbl->setText("");
		$this->MovePartTechnician->Checked = "false";
		$this->MovePartLocation->Checked = "true";

		if(!$this->checkIfTechniciansInDefaultWarehouse())
		{
			$this->MovePartTechnicianWrapper->Visible = false;
		}

		if(!Session::checkRoleFeatures(array('pages_all','pages_logistics','feature_displayPending')))
        {
			$this->hasfieldTaskLbl->Checked = false;
        }

		if(!$this->IsPostBack && !$this->IsCallBack)
		{
			$this->dontCheckIfReservedForFieldTask->Value = "";
			$this->checkBOMIsCorrectForNonAgent->Value = "";
		}

		$this->defaultWarehouse = $this->getDefaultWarehouse(Core::getUser());
		if (!$this->defaultWarehouse instanceof Warehouse)
			return;

		if (!$this->IsPostBack || $param == "reload")
		{
			try
			{
				WarehouseLogic::checkDefaultWarehouseWithinViewWarehouseFilter($this->defaultWarehouse);
			}
			catch (Exception $e)
			{
				$this->setErrorMessage($e->getMessage());
				$this->Page->MainContent->Enabled=false;
				$this->whTree->Visible=false;
				return;
			}

			if ($this->inOrOut == "out")
			{
				$userTransOrDispNoteOrAssignNote = Factory::service("UserPreference")->getOption(Core::getUser(),'transferPartOutwardMakeTransitNote');

				//they don't have the preference set, so check features
				if ($userTransOrDispNoteOrAssignNote == null || $userTransOrDispNoteOrAssignNote == "")
				{
					//default to Move Parts
					$userTransOrDispNoteOrAssignNote = 0;

					if (UserAccountService::isSystemAdmin())
					{
						$userTransOrDispNoteOrAssignNote = 1;
					}
					else
					{
						$features = Core::getRole()->getFeatures();
						foreach ($features as $feature)
						{
							if (in_array($feature->getName(), array('page_agent_logistics_listTransitNote','feature_displayTransitNotes')))
							{
								$userTransOrDispNoteOrAssignNote = 1;
								$tnFeatureFound = true;
								break;
							}
						}
					}
					Factory::service("UserPreference")->setOption(Core::getUser(),'transferPartOutwardMakeTransitNote', $userTransOrDispNoteOrAssignNote);
				}

				if ($userTransOrDispNoteOrAssignNote == 0)
				{
					$this->MovePartRadio->Checked = true;
				}
				else if ($userTransOrDispNoteOrAssignNote == 1)
				{
					$this->noteType->Value = "transit";
					$this->MakeTransitNote->Checked = true;
				}
				else if ($userTransOrDispNoteOrAssignNote == 2)
				{
					$this->noteType->Value = "dispatch";
					$this->MakeDispatchNote->Checked = true;
				}
				else if ($userTransOrDispNoteOrAssignNote == 3)
				{
					$this->noteType->Value = "assignment";
					$this->MakeAssignmentNote->Checked = true;
				}
			}
			$this->loadMovingPartsList();
		}
		$currPartcode = $this->partcode->getText();
		$currBarcode = $this->barcode->getText();
		$currBarcodeIsBcp = $this->checkStringContainingNonSerialisedBarcode($currBarcode);
		$this->Page->setFocus($this->barcode->getClientID());
	}

	/**
	 * Shows Part Instance Details hover
	 *
	 * @param PartInstance $partInstance
	 * @param String $cssclass
	 * @param String $divPrefix
	 *
	 * @return string $div
	 */
	public function showPartInstanceDetail($partInstance, $cssclass="toolTipWindow",$divPrefix="PartInstanceDetails_")
	{
		return PartInstanceLogic::showPartInstanceDetail($partInstance, $cssclass, $divPrefix);
	}

	/**
	 * Check String Containing Non serialized Barcode
	 *
	 * @param unknown_type $currBarcode
	 * @return unknown
	 */
	private function checkStringContainingNonSerialisedBarcode($currBarcode)
	{
		$currBarcodeIsBcp = (preg_match("/^\s*BCP\d{8}\s*$/i", $currBarcode) == 1);
		if (!$currBarcodeIsBcp)
		$currBarcodeIsBcp = (preg_match("/^\s*BP\d{8}\w\s*$/i", $currBarcode) == 1);
		return $currBarcodeIsBcp;
	}

	/**
	 * Search partInstance
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 * @return unknown
	 */
	public function searchPartInstance($sender,$param)
	{
		$this->setInfoMessage("");
		$this->setErrorMessage("");
		$this->ajaxLabel->Text="";

		$barcode = trim($this->barcode->Text);
		$partcode = trim($this->partcode->Text);

		//we're coming from a javascript confirm of reserved part
		if ($this->dontCheckIfReservedForFieldTask->Value != "")
		{
			//we've come from the non-serialised drop-down, so we don't want to display that again
			if ($this->partResultList_qtyAuto->Value != '' && $this->partResultList_qtyAuto->Value != '')
			{
				$this->addPartFromMultileFound(null, null);
				$this->partResultList_qtyAuto->Value = '';
				$this->partResultList_valueAuto->Value = '';
				return;
			}
			//we need this information for below
			$barcode = trim($this->barcodeAuto->Value);
			$partcode = trim($this->partcodeAuto->Value);

			$this->barcodeAuto->Value = '';
			$this->partcodeAuto->Value = '';
		}

		if($barcode=="" && $partcode=="")
		{
			return $this->onError("Nothing to search! Please enter a barcode or part code.");
		}
		if($barcode != "" && $partcode !="")
		{
			return $this->onError("Please enter only either barcode or partcode.");
		}

		if($this->hasfieldTaskLbl->Checked)
		{
			$results = $this->getPartInstances($barcode,$partcode,null);
			if(count($results) > 0)
			{
				$partInstance = $results[0];
				if($partInstance instanceof PartInstance)
				{
					$warehouseFrom = $partInstance->getWarehouse();
					if($warehouseFrom instanceOf Warehouse)
					{
						$isStoreOkToMoveFrom = Factory::service("Warehouse")->isWarehouseIdMainStoreParentOrChildren($warehouseFrom->getId());
						$defaultWarehouse = Factory::service("Warehouse")->getDefaultWarehouse(Core::getUser());
						$isStoreOkToMoveTo = false;
						if($defaultWarehouse instanceOf Warehouse)
						{
							$isStoreOkToMoveTo = Factory::service("Warehouse")->isWarehouseIdMainStoreParentOrChildren($defaultWarehouse->getId());
						}

						if($isStoreOkToMoveFrom || $isStoreOkToMoveTo)
						{
							$partType = $partInstance->getPartType();
							$partTypeArray = array($partType);
							$html = Factory::service("PartType")->checkPendingPartsStatus($partTypeArray);
							if($html)
							{
								$this->fieldTaskLbl->setText($html);
							}
						}
					}
				}
			}
		}

		$results = array();
		$this->showQuanityListPartsInstance($barcode, $partcode);
	}

	/**
	 * Show Quantity list part instance
	 *
	 * @param unknown_type $barcode
	 * @param unknown_type $partcode
	 * @return unknown
	 */
	private function showQuanityListPartsInstance($barcode, $partcode=NULL)
	{
		$selectedFromWarehouseIds = $this->thisWarehouseService;
		$fromWarehouse = null;
		if (!empty($partcode) || (!empty($barcode) && $this->checkStringContainingNonSerialisedBarcode($barcode)))
		{
			if (!empty($selectedFromWarehouseIds))
			{
				$fromWarehouseIds = explode('/', $selectedFromWarehouseIds);
				$fromWarehouseId = end($fromWarehouseIds);
				$fromWarehouse = Factory::service("Warehouse")->getWarehouse($fromWarehouseId);
			}
		}

		$results = $this->getPartInstances($barcode, $partcode, $fromWarehouse);
		$this->searchResultPanel->Visible=false;

		if(count($results) == 0)
		{
			//added by Lin He on 28 March, 2011 , RT: #10623: disabled parts retrieval
			$msgE = "No part found!";
			if((preg_match("/^BCS\d{8}$/i", $barcode) == 1) || (preg_match("/^BS\d{8}\w$/i", $barcode) == 1))
			{
				//if i can find any serial number that is related to a deactivated part instance, then display a message box to ask the user to reactivate the part instance
				$sql="select pi.id from partinstance pi inner join partinstancealias pia on (pia.partInstanceId = pi.id and pia.partInstanceAliasTypeId = 1 and pia.alias like '$barcode') where pi.active = 0";
				$res = Dao::getResultsNative($sql);

				if(count($res) > 0)
				{
					$partInstance = Factory::service("PartInstance")->getPartInstance($res[0][0]);
					if($partInstance instanceOf PartInstance)
					{
						try
						{
							$restricted = Factory::service("PartInstance")->checkIfPartIsRestricted($partInstance);
						}
						catch (Exception $e)
						{
							return $this->onError("Part is deactivated<br /><br />" . str_replace(":::", "<br /><br />", $e->getMessage()));
						}

						$kitWithoutChildren = Factory::service("PartInstance")->checkIfPartInstanceIsEmptyKit($partInstance);
						if(!$kitWithoutChildren)
						{
							$msgE = '';
							$this->ajaxLabel->Text = JavascriptLogic::getScriptTagWithContent(array('if ($("makeTransitNotePanel")) {showPanel("makeTransitNotePanel")}',
																									'reActivatePI("' . $res[0][0] . '","' . $barcode . '")'));
						}
					}
				}
			}
			$this->onError($msgE);
			$this->loadMovingPartsList();
			return;
		}

		//make sure we get a real BS/BCS to move serialised part
		if(!Factory::service("PartInstance")->checkContainingBCP($results) && ($partcode!="" && $barcode==""))
		{
			return $this->onError("This is a serialised part. Please use the serial number to move the part.");
		}

		$partInstance = $results[0];

		//check and remove any duplicate serial numbers apart from the one they scanned (provided it matches out serial format)
		if ($partInstance->getPartType()->getSerialised() == 1)
		{
			try {Factory::service("PartInstanceAlias")->clearSN($partInstance, $barcode);}
			catch (Exception $e) {}
		}

		if (count($results)==1 && $partInstance->getQuantity()=="1")
		{
			try
			{
				$restricted = Factory::service("PartInstance")->checkIfPartIsRestricted($partInstance);
			}
			catch (Exception $e)
			{
				return $this->onError(str_replace(":::", "<br /><br />", $e->getMessage()));
			}

			//see if we are allowed to move the part
			$block = Factory::service("FieldTask")->blockPIMovingForOpenFT($partInstance, array(), $fts);
			if ($block && count($fts) > 0)
			{
				$ftIds = array_map(create_function('$a', 'return $a->getId();'), $fts);
				return $this->onError(PartInstanceLogic::getOpenTasksMessageForPartInstance($partInstance, $ftIds));
			}

			$return = $this->addPartInstanceToMovingList($partInstance, 1, true);

			if($return != false)
			{
				$this->onSuccess("Part successfully added to list.",true);
				$this->barcode->Text="";
				$this->partcode->Text="";
			}
		}
		else
		{
			$msg = Factory::service("TransitNote")->checkPartInstanceCanBeMovedOnTnDn($partInstance, $this->barcode->Text);
			if ($msg !== true)
			{
				return $this->onError($msg);
			}

			$this->searchResultPanel->Visible = true;
			$this->bindPartInLocationList($results);
			$this->loadMovingPartsList();
		}
		$this->TransferPartsButton->setText("Select Location");
		$this->checkedFieldTaskPending->setValue("false");
	}

	/**
	 * Reactivate partInstance
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function reactivatePI($sender,$param)
	{
		$this->setInfoMessage("");
		$this->setErrorMessage("");
		$this->ajaxLabel->Text="";
		try
		{
			$piId = trim($this->reactivatePIid->Value);
			$reactivateBarcode = trim($this->reactivateBarcode->Value);

			$partInstance = Factory::service("PartInstance")->get($piId);
			if(!$partInstance instanceof PartInstance)
				throw new Exception("Invalid part instance (ID=$piId)");

			$barcode = $partInstance->getAlias();

			if(!$partInstance instanceof PartInstance)
				throw new Exception("Invalid part instance (ID=$piId)");


			$partInstance->setActive(true);
			$partInstance = Factory::service("PartInstance")->save($partInstance);

			$partinstanceAlias = new PartInstanceAlias();
			$partinstanceAlias->setPartInstance($partInstance);
			$partinstanceAlias->setPartInstanceAliasType(Factory::service("PartInstance")->getPartInstanceAliasType(PartInstanceAliasType::ID_SERIAL_NO));
			$partinstanceAlias->setAlias($reactivateBarcode);
			Factory::service("PartInstance")->savePartInstanceAlias($partinstanceAlias);


			$qty = $partInstance->getQuantity();

			if($qty > 1){
				$this->showQuanityListPartsInstance($barcode);
			}else{
				$this->addPartInstanceToMovingList($partInstance, 1, true);
				$this->onSuccess("Part reactivated. Part successfully added to list.",true);
			}

			$this->barcode->Text="";
			$this->partcode->Text="";
		}
		catch(Exception $ex)
		{
			$this->setErrorMessage($ex->getMessage());
		}
		$this->reactivatePIid->Value="";
		$this->loadMovingPartsList();
	}

	/**
	 * Get PartInstance
	 *
	 * @param unknown_type $barcode
	 * @param unknown_type $partcode
	 * @param unknown_type $fromWarehouse
	 * @return unknown
	 */
	protected function getPartInstances($barcode, $partcode, $fromWarehouse=null)
	{
		if ($this->isAgent)
			$warehouse = $this->defaultWarehouse;
		else
			$warehouse = $fromWarehouse;

		return Factory::service("PartInstance")->searchPartInstanceByBarcodeAndPartcode($barcode, $partcode, null, 30, $warehouse, true);
	}

	/**
	 * Remove from  movinglist
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function removeFromMovingList($sender, $param)
	{
		$partInstanceId = $param->CommandParameter;
		$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
		if($partInstance instanceof PartInstance)
		{
			$this->removePartInstanceFromMovingList($partInstance);
			$this->TransferPartsButton->setText("Select Location");
			$this->ReceivePartsButton->setText("Receive Parts");
			$this->checkedFieldTaskPending->setValue("false");
		}
	}

	/**
	 * Add part from Multiple found
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 * @return unknown
	 */
	public function addPartFromMultileFound($sender, $param)
	{
		$qty = trim($this->partResultList_qty->Text);
		$partInstanceId = $this->partResultList->getSelectedValue();
		if ($this->dontCheckIfReservedForFieldTask->Value != "")
		{
			$qty = $this->partResultList_qtyAuto->Value;
			$partInstanceId = $this->partResultList_valueAuto->Value;
		}

		if (!is_numeric($qty) || $qty<1)
		{
			$this->partResultList_qty->Text = "0";
			return $this->onError("Invalid Quantity. Must be 1 or greater.");
		}

		$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
		if($partInstance instanceof PartInstance)
		{
			if($qty>$partInstance->getQuantity())
			{
				$this->partResultList_qty->Text = "0";
				return $this->onError("Insufficient parts. $qty selected; only ".$partInstance->getQuantity()." available.");
			}

			$this->partResultList_qtyAuto->Value = $qty;
			$this->partResultList_valueAuto->Value = $partInstanceId;

			$this->searchResultPanel->Visible = false;
			$this->addPartInstanceToMovingList($partInstance,$qty);

			$this->barcode->Text = '';
			$this->partcode->Text = '';
		}
	}

	/**
	 * Activate Transfer part panel
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function activeTranferPartPanel($sender,$param)
	{

		if ($sender->ID == "MovePartRadio")
		{
			$this->TransferPartsButton->setText("Select Location");
			$this->checkedFieldTaskPending->setValue("false");
			Factory::service("UserPreference")->setOption(Core::getUser(),'transferPartOutwardMakeTransitNote',0);
		}
		else if ($sender->ID == "MakeTransitNote")
		{
			$this->noteType->Value = "transit";
			Factory::service("UserPreference")->setOption(Core::getUser(),'transferPartOutwardMakeTransitNote',1);
		}
		else if ($sender->ID == "MakeDispatchNote")
		{
			$this->noteType->Value = "dispatch";
			Factory::service("UserPreference")->setOption(Core::getUser(),'transferPartOutwardMakeTransitNote',2);
		}
		else if ($sender->ID == "MakeAssignmentNote")
		{
			$this->noteType->Value = "assignment";
			Factory::service("UserPreference")->setOption(Core::getUser(),'transferPartOutwardMakeTransitNote',3);
		}
		$this->loadMovingPartsList();
	}

	 /**
     * Shows the warehouse comments for part instance on the Datalist
     *
     * @param PartInstance $partInstance
     * @return string $div
     */
	public function showCommentsDiv($partInstance)
	{
		$div="";
		if($partInstance instanceof PartInstance)
		{
			$div = "<div ID=\"LocationComments_".$partInstance->getId()."\" style=\"display: none; \" class=\"toolTipWindow\">";
			$prepareComments = "";
			if (Factory::service("Warehouse")->getWarehouseAliaseOfTypeComment($partInstance->getWarehouse()) != Null)
			{
				$locationAlias = Factory::service("Warehouse")->getWarehouseAliaseOfTypeComment($partInstance->getWarehouse());
				$commentsArray = explode(" [!",$locationAlias[0]->getAlias());
				$i=0;
				foreach($commentsArray as $comment)
				{
					if(strlen(trim($comment, "<br>")) > 0)
					{
						if($i % 2 ==0)
						$prepareComments .=  "<font color=blue>" . trim($comment, "<br>") . "</font> <br />";
						else
						$prepareComments .=  "<font color=black>" . trim($comment, "<br>") . "</font> <br />";
						$i++;
					}
				}
			}
			$div .=$prepareComments;
			$div .= "</div>";
		}
		return $div;
	}

	/**
    * Checks that parts are ok to move
    */
	public function checkParts($sender, $param)
	{
		$error = "";
		$warehouseIds = explode('/',$this->whTree->whIdPath->Value);
		$warehouseId = end($warehouseIds);
		$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId);

		$error .= Factory::service("Warehouse")->isWarehouseOKToReceivePartsReturnErrors($warehouse);

		if($error)
		{
			return $this->onError($error);
		}
		else
		{
			$this->Page->jsLbl->Text = "<script type=\"text/javascript\">moveParts();</script>";
			return true;
		}
	}

	/**
	 * Move parts
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function moveParts($sender, $param)
	{
		$movingPartInstances = unserialize($this->movingPartInstanceArray->Value);
		$comments = trim($this->Comments->Text);

		$warehouseIds = explode('/',$this->whTree->whIdPath->Value);
		$warehouseId = end($warehouseIds);
		$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId);


		$warehouseIsTechnician = false;
		//check If warehoue is technitian
		if(WarehouseCategoryService::$categoryId_Technician == $warehouse->getWarehouseCategory()->getId()){
			$warehouseIsTechnician = true;
		}

		//moving all part instances
		foreach($movingPartInstances as $movingPartInstanceId=>$qty)
		{
			$partInstance = Factory::service("PartInstance")->getPartInstance($movingPartInstanceId);
			if(!$partInstance instanceof PartInstance)
			continue;

			$newPartInstanceStatusObj = null;
			try {
				// now check if the status already change!
				$oldPartInstanceStatus = $partInstance->getPartInstanceStatus()->getId();
				$newPartInstanceStatus = $_POST['newStatus_'.$partInstance->getId()];
				if (!empty($newPartInstanceStatus) && $newPartInstanceStatus != $oldPartInstanceStatus)
				{
					$newPartInstanceStatusObj = Factory::service("PartInstanceStatus")->get($newPartInstanceStatus);
				}
			} catch (Exception $e) {}	// ignore any error in updating status, and then continue

			Factory::service("PartInstance")->movePartInstanceToWarehouse($partInstance, $qty, $warehouse, true, $newPartInstanceStatusObj, $comments);

			// re instaniate the part instance now that it has changed
			$partInstance = Factory::service("PartInstance")->getPartInstance($movingPartInstanceId);


			/* Add comments for facility request and field task. */
			// Facility request functions taken out till next promotion
			if($warehouseIsTechnician){
				$facilityRequest = $partInstance->getFacilityRequest();
				if($facilityRequest instanceof FacilityRequest)
				{
					$fieldTask = $facilityRequest->getFieldTask();
					if($fieldTask instanceof FieldTask)
					{
						Factory::service("FacilityRequest")->addComments($facilityRequest, 'Reserved part, moved to technician ' . $warehouse->getName());
					}
				}
			}
		}

		$this->resetFields();
		$this->onSuccess('Selected parts successfully moved to '. $warehouse,true);
		$this->TransferPartsButton->setText("Select Location");
		$this->checkedFieldTaskPending->setValue("false");
	}

	//Returns the note type checked
	/**
	 * Get Selected NoteType
	 *
	 * @return unknown
	 */
	private function getSelectedNoteType()
	{
		$noteType = TransitNote::NOTETYPE_TRANSITNOTE;
		if ((!$this->isAgent))
		{
			if ($this->MakeDispatchNote->Checked)
			{
				$noteType = TransitNote::NOTETYPE_DISPATCHNOTE;
			}
			else if ($this->MakeTransitNote->Checked)
			{
				$noteType = TransitNote::NOTETYPE_TRANSITNOTE;
			}
			else if ($this->MakeAssignmentNote->Checked)
			{
				$noteType = TransitNote::NOTETYPE_ASSIGNMENTNOTE;
			}
		}
		return $noteType;
	}

	/**
	 * Move parts on to
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 * @return unknown
	 */
	public function movePartsOntoTransitOrDispatchNote($sender,$param)
	{
		$moveWhere = $this->movingPartToWhere->Value;
		if (strstr($moveWhere, 'movePartsToSiteButton'))
		{
			$this->movePartsToSite($sender,$param);
			return false;
		}
		else if (strstr($moveWhere, 'MakeTransitNoteButton')) //to a warehouse (TN)
		{
			$selectedId = $this->TransitNoteToList->getSelectedValue();
		}
		else if (strstr($moveWhere, 'OtherTransitNoteButton')) //to a other warehouse (TN)
		{
			$selectedId = $this->OtherTransitNoteToList->getSelectedValue();
		}
		else if (strstr($moveWhere, 'thirdPartyButton')) //to 3rd party (DN)
		{
			$selectedId = $this->thirdPartyList->getSelectedValue();
		}
		else if (strstr($moveWhere, 'clientWarehouseButton')) //to client warehouse (DN)
		{
			$selectedId = $this->clientWarehouseList->getSelectedValue();
		}
		else if (strstr($moveWhere, 'assignmentNoteButton')) //to technician
		{
			$selectedIdBreadCrumbs = $this->assignmentNoteToTech->getSelectedValue();
			$warehouseIds = explode('/', $selectedIdBreadCrumbs);
			$selectedId = end($warehouseIds);
		}

		if ($selectedId == '')
		{
			$this->loadMovingPartsList();
			return false;
		}

		if ((!$this->isAgent))
		{
			if ($this->MakeDispatchNote->Checked)
			{
				if (!$this->checkIfCanReceiveParts($selectedId))
				{
					return $this->onError($this->TransitNoteToList->getText() . " - cannot accept parts... Dispatch Note not created");
				}
			}
		}

		$noteType = $this->getSelectedNoteType();
		$movingPartInstances = unserialize($this->movingPartInstanceArray->Value);

		$newStatusArray = array();
		foreach($movingPartInstances as $partInstanceId => $qty)
		{
			if(isset($_POST['newStatus_'.$partInstanceId]))
			{
				$newStatusArray[$partInstanceId] = $_POST['newStatus_'.$partInstanceId];
			}
		}

		$overrideDestinationFacilityCheck = false;
		if($noteType == TransitNote::NOTETYPE_ASSIGNMENTNOTE)
		{
			$overrideDestinationFacilityCheck = true;
		}

		$destinationLocation = Factory::service("Warehouse")->findById($selectedId);

		try
		{
			$transitNote = TransitNoteLogic::createTransitNote($movingPartInstances, $destinationLocation, $noteType, $newStatusArray, null, $this->Comments->Text, $overrideDestinationFacilityCheck);
		}
		catch (Exception $e)
		{
			return $this->onError($e->getMessage());
		}

		$this->response->redirect($this->getViewTransitNotePageURL($transitNote));
	}

	/**
	 * Move parts to site
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 * @return unknown
	 */
	public function movePartsToSite($sender, $param)
	{
		$noteType = $this->getSelectedNoteType();

		$siteId = $this->SiteList->getSelectedValue();
		$destinationSite = Factory::service("Site")->getSite($siteId);

		if(!$destinationSite instanceof Site)
			return $this->onError("Invalid site. Please select a valid site.");

		//check if site is linked to a warehouse
		$linkedWarehouses = $destinationSite->getWarehouses();
		if (count($linkedWarehouses) > 1)
			return $this->onError("The selected site is linked to more than one warehouse! Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");
		else if (count($linkedWarehouses) == 1)
			$siteWarehouse = Factory::service("Warehouse")->getWarehouse($linkedWarehouses[0]->getId());
		else
			$siteWarehouse = Factory::service("Warehouse")->getWarehouse(27); //27=sites bucket

		if(!$siteWarehouse instanceof Warehouse)
			return $this->onError("The selected site is not linked to a warehouse! Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

		if (!$this->checkIfCanReceiveParts($siteWarehouse->getId()) && $noteType==TransitNote::NOTETYPE_DISPATCHNOTE)
		{
			$siteWarehouse = Factory::service("Warehouse")->getWarehouse(27); //27=sites bucket
		}


		$movingPartInstances = unserialize($this->movingPartInstanceArray->Value);

		$newStatusArray = array();
		foreach($movingPartInstances as $partInstanceId => $qty)
		{
			if(isset($_POST['newStatus_'.$partInstanceId]))
			{
				$newStatusArray[$partInstanceId] = $_POST['newStatus_'.$partInstanceId];
			}
		}

		try
		{
			$transitNote = TransitNoteLogic::createTransitNote($movingPartInstances, $siteWarehouse, $noteType, $newStatusArray,$destinationSite, $this->Comments->Text, true);
		}
		catch (Exception $e)
		{
			return $this->onError($e->getMessage());
		}


		$this->response->redirect($this->getViewTransitNotePageURL($transitNote));
	}

	/**
	 * Enter description here...
	 *
	 * @param TransitNote $transitNote
	 * @return unknown
	 */
	protected function getViewTransitNotePageURL(TransitNote $transitNote)
	{
		$dummypath = '/transitnote/';
		if ($this->MakeDispatchNote->Checked)
			$dummypath = '/dispatchnote/';

		$agentPath = '';
		if ($this->isAgent)
			$agentPath = '/agent';


		$path = $this->getUrl() . $agentPath . $dummypath . $transitNote->getId();

		return  ($path);
	}

	/**
	 * binding the destination facility list, when transitNot is required
	 *
	 * @param unknown_type $transitNoteToList
	 * @param unknown_type $selectedIndex
	 */
	private function bindTargetFacilityList(&$transitNoteToList,$selectedIndex=null)
	{
		$facilities = Factory::service("Warehouse")->searchWarehousesWhereFacilityIsNotNull();
		// sort alphabetically
		usort($facilities, create_function('$a, $b', 'return strcasecmp($a->getName(), $b->getName() );'));
		$transitNoteToList->DataSource =  $facilities;
		$transitNoteToList->DataBind();
		if($selectedIndex!=null)
		$transitNoteToList->SelectedIndex=$selectedIndex;
	}

	/**
	 * Check if BOM is correct for non Agent
	 *
	 * @param unknown_type $partInstance
	 * @return unknown
	 */
	private function checkIfBOMIsCorrectForNonAgent($partInstance)
	{
		$missingPartTypeIds = array();
		if(!$this->isAgent){
			if($this->checkBOMIsCorrectForNonAgent->Value == ""){
				$kitType = $partInstance->getKitType();
				if($kitType instanceof KitType)
				{
					$ptChildrenIds = array();
					$children = Factory::service("PartInstance")->getPartInstanceChildrenIds($partInstance);
					foreach($children as $piId)
					{
						$tempPartInstance = Factory::service("PartInstance")->getPartInstance($piId);
						if($tempPartInstance instanceOf PartInstance){
							$ptChildrenIds[] = $tempPartInstance->getPartType()->getId();
						}
					}

					if(count($ptChildrenIds)>0){
						$sql = "select b.requiredPartTypeId from partinstance p
						left join billofmaterials b on b.partTypeId = p.partTypeId and b.active=1
						where p.id = " . $partInstance->getId() . " and p.active = 1
						and b.requiredPartTypeId not in(" . implode(",",$ptChildrenIds) . ")";
						$results = Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC);
						if(count($results)>0){
					    	foreach ($results as $r)
					    	{
					    		$missingPartTypeIds[] = $r['requiredPartTypeId'];
					    	}
						}
						if(count($missingPartTypeIds)>0){
							$msg = "Part is a kit with an incorrect Bill Of Materials,\\n";
							$msg .= "Missing part types :\\n";
							foreach($missingPartTypeIds as $partTypeId){
								$tempPartType = Factory::service("PartType")->getPartType($partTypeId);
								if($tempPartType instanceOf PartType){
									$partTypeAlias = $tempPartType->getPartTypeAlias();
									foreach ($partTypeAlias as $al)
									{
										if ($al->getPartTypeAliasType()->getId() == 1) // this is our BP number
										{

											$msg .= $al->getAliasAndType() . "\\n";
										}
									}
								}
							}
							$msg .="Do you wish to continue?";
							$this->loadMovingPartsList();
							$this->Page->jsLbl->Text = "<script type=\"text/javascript\">incompleteBOM('" . $msg . "');</script>";
							return false;
						}
					}
				}
			}else{
				$this->checkBOMIsCorrectForNonAgent->Value = "";
			}
		}else{
			$this->checkBOMIsCorrectForNonAgent->Value = "";
		}
		return true;
	}

	/**
	 * This method will check the inputs and confirm that the part quanity will not exceed the Quantty of required part
	 * and add then to the parts added to the movingPartInstanceArray var. and return the word 'success' else false on fail
	 *
	 * @todo confirm that 'success' is the correct response or should it be true
	 *
	 * @param obj $partInstance
	 * @param int $qty
	 * @param bool $isAutoLoad confirms the part was added without needing quanity
	 *
	 * @return string "success"
	 */
	private function addPartInstanceToMovingList(PartInstance $partInstance, $qty=1, $isAutoLoad=false)
	{
		//$this->searchResultPanel->Visible = false;
		$array = unserialize($this->movingPartInstanceArray->Value);

		// Facility request functions taken out till next promotion
		try
		{
			if(!PartInstanceLogic::checkIfReservedForFieldTask($partInstance,$this->dontCheckIfReservedForFieldTask->Value))
			{

				$facilityRequest = $partInstance->getFacilityRequest();
				$fieldTask = $facilityRequest->getFieldTask();
				$this->loadMovingPartsList();

				$this->Page->jsLbl->Text = "<script type=\"text/javascript\">reservedParts("  . $fieldTask->getId() . ",'" . $this->barcode->Text . "')</script>";

				return false;
			}
		}
		catch (Exception $e)
		{
			return $this->onError($e->getMessage()); //needed to add this as we were blowing up when the PartInstanceLogic function was throwing an exception
		}

		$partType = $partInstance->getPartType();
		if ($partType->getActive() == 0 && !$this->isAgent) //block if we are NOT an Agent and the part type is deactivated
		{
			return $this->onError("Part Type ($partType) for Part Instance ($partInstance) has been deactivated.");
		}

		$this->dontCheckIfReservedForFieldTask->Value = "";

		if(!$this->checkIfBOMIsCorrectForNonAgent($partInstance))
		{
			return false;
		}

		if (Factory::service("PartInstance")->checkIfPartInstanceIsEmptyKit($partInstance))
		{
			return $this->onError("Part ($partInstance) is an empty kit and cannot be moved!");
		}

		//checking whether this part is in a transitNote
		$partWarehouse = $partInstance->getWarehouse();
		if($partWarehouse instanceof Warehouse)
		{
			$transiteNotes = Factory::service("TransitNote")->findByCriteria("tn.transitNoteLocationId=?",array($partWarehouse->getId()));
			if(count($transiteNotes)>0)
			{
				return $this->onError("Part ($partInstance) is on transitNote ".$transiteNotes[0].". It cannot be moved.");
			}
		}

		//check for compulsory part instance aliases, BTs and block if not an agent
		$msg = Factory::service("TransitNote")->checkPartInstanceCanBeMovedOnTnDn($partInstance, $this->barcode->Text, $this->isAgent);
		if ($msg !== true)
		{
			$foundFeature = Session::checkRoleFeatures(array('pages_all','pages_logistics','page_logistics_partInstanceReRegister'));
			return $this->onError($msg.=($foundFeature==true ? " <input type='Button' Value='Edit this Part' onclick=\"window.open('/reregisterparts/".$partInstance->getId()."/".htmlentities($msg)."');return false;\" />" : " Please contact Logistics to edit this part then move it!" ));
		}

		//checking whether this part is within another part
		if($partWarehouse->getId() == Warehouse::ID_PARTS_IN_PARTS)
			$this->setErrorMessage("Part ($partInstance) is within another part.");


		// 16-08-2011: Validate that currently selected quantity plus new selected quantity does not exceed parts available
		$partInstanceId = $this->partResultList->getSelectedValue();
		if(!$isAutoLoad){	// if not autoload then show the select Panel to pick quanity value
			if (isset($array[$partInstance->getId()])) {
				if (($array[$partInstance->getId()] + $qty) > (Factory::service("PartInstance")->getPartInstance($partInstanceId)->getQuantity())) {
					$this->partResultList_qty->Text = "0";
					$this->searchResultPanel->Visible = true;
					return $this->onError("There are insufficient parts of ($partInstance) available.");
				}
				$array[$partInstance->getId()] += $qty;
			}
			else {
				if (($qty) > (Factory::service("PartInstance")->getPartInstance($partInstanceId)->getQuantity())) {
					$this->partResultList_qty->Text = "0";
					$this->searchResultPanel->Visible = true;
					return $this->onError("There are insufficient parts of ($partInstance) available.");
				}
				$array[$partInstance->getId()] = $qty;
			}
		}else{ // if autoLoad then add a single quanity
			$array[$partInstance->getId()] = $qty;
		}
		$this->movingPartInstanceArray->Value = serialize($array);
		$this->loadMovingPartsList();

		return 'success';
	}

	/**
	 * Remove PartInstance from Moving List
	 *
	 * @param PartInstance $partInstance
	 */
	private function removePartInstanceFromMovingList(PartInstance $partInstance)
	{
		$array = unserialize($this->movingPartInstanceArray->Value);
		if(isset($array[$partInstance->getId()]))
		unset($array[$partInstance->getId()]);
		if(count($array)==0){
			$this->movingPartInstanceArray->Value="";
			$this->TransferPartPanel->Visible=false;
		}else{
			$this->movingPartInstanceArray->Value = serialize($array);
		}

		$this->loadMovingPartsList();

	}

	/**
	 * Toggle Panel Visibility
	 *
	 * @param unknown_type $visible
	 */
	private function togglePanelVisibility($visible)
	{
		$this->facilitiesPanelParent->Visible = $visible;
		$this->otherPanelParent->Visible = $visible;
		$this->thirdPartyPanelParent->Visible = $visible;
		$this->clientWarehousePanelParent->Visible = $visible;
		$this->sitesPanelParent->Visible = $visible;
		$this->technicianPanelParent->Visible = $visible;
	}

	/**
	 * Get Barcode
	 *
	 * @param unknown_type $partInstanceId
	 * @param unknown_type $partType
	 * @return unknown
	 */
	protected function getBarCode($partInstanceId,$partType)
	{
		$barcodes = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($partInstanceId,1);

		if(count($barcodes)==0 || ($partType->getSerialised()==0 && $partType->getId() != 1))
		{
			$barcodes = Factory::service("PartType")->searchPartTypeAliaseByPartTypeAliasTypeForPartType($partType->getId(),PartTypeAliasType::ID_BP);
		}

		$barcode = count($barcodes)==0 ? "" : $barcodes[0]->getAlias();
		return $barcode;
	}

	/**
	 * Load Moving partlist
	 *
	 */
	protected function loadMovingPartsList()
	{
		$partInstanceIds = unserialize($this->movingPartInstanceArray->Value);

		// based on Noel's request, the list is now displayed bottom-up
		if (!empty($partInstanceIds) && $partInstanceIds != false && is_array($partInstanceIds))
		{
			$partInstanceIds = array_reverse($partInstanceIds, true);
		}
		$partInstances = array();

		$count = count($partInstanceIds);
		$this->PaginationPanelParts->Visible=false;

    	if($partInstanceIds!=false)
		{
			$pageNumber = $this->DataList->CurrentPageIndex;
    		$pageSize = $this->DataList->pageSize;
    		$position = 0;
			foreach($partInstanceIds as $partInstanceId=>$qty)
			{
				$process = false;
				if(!$pageNumber)
				{
					if($position < $pageSize)
					{
						$process = true;
					}
				}
				else
				{
					if($position >= ($pageNumber * $pageSize) && $position <= (($pageNumber + 1) * $pageSize))
					{
						$process = true;
					}
				}

				$position++;
				if($process)
				{
					$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
					if($partInstance instanceof PartInstance )
					{
						$partType = $partInstance->getPartType();
						$partTypeId = $partType->getId();

						$barcode = $this->getBarCode($partInstanceId,$partType);

						$partcodes = Factory::service("PartType")->searchPartTypeAliaseByPartTypeAliasTypeForPartType($partTypeId,1);
						$partcode = count($partcodes)==0 ? "" : $partcodes[0]->getAlias();

						$hotmessagePT = Factory::service("PartType")->searchPartTypeAliaseByPartTypeAliasTypeForPartType($partTypeId,PartTypeAliasType::ID_HOT_MESSAGE);
						$hotmessagePT = count($hotmessagePT)==0 ? "" : $hotmessagePT[0]->getAlias();

						$hotmessagePI = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($partInstanceId,PartInstanceAliasType::ID_HOT_MESSAGE);
						$hotmessagePI = count($hotmessagePI)==0 ? "" : $hotmessagePI[0]->getAlias();

						//logic function to return part instance status list
						$pisArr = DropDownLogic::getPartInstanceStatusList(array(), $partType->getContracts(), $partInstance->getPartInstanceStatus());

						$newStatusArr = array();
						foreach ($pisArr as $pis)
						{
							// if user already selected/changed the status, keep it, otherwise, use the status from database
							if (!empty($_POST['newStatus_'.$partInstance->getId()]))
								$isChecked = ($pis->getId() == $_POST['newStatus_'.$partInstance->getId()] ? "selected" : '');
							else
								$isChecked = ($pis->getName() == $partInstance->getPartInstanceStatus() ? "selected" : '');

							$newStatusArr[] = "<option value='".$pis->getId()."' $isChecked>".$pis->getName()."</option>";
						}
						$partInstances[]= array("partInstance" => $partInstance,
			   									"qty" => $qty,
			   									"barcode" => $barcode,
			   									"partcode" => $partcode,
												"hotmessagePI" => $hotmessagePI,
												"hotmessagePT" => $hotmessagePT,
		   										"newStatusHtml" => join("", $newStatusArr));
					}
				}
			}
		}


		$this->DataList->DataSource = $partInstances;
		$this->DataList->DataBind();

    	$this->itemCount = $count;
    	$this->DataList->VirtualItemCount = $this->itemCount;
    	$this->DataList->DataBind();

		if($this->itemCount > $this->DataList->PageSize)
			$this->PaginationPanelParts->Visible=true;
		else
			$this->PaginationPanelParts->Visible=false;

		if(count($partInstances)>0)
		{
			$this->TransitNotePanel->Visible=false;
			$this->TransferPartPanel->Visible=false;

				if ($this->inOrOut == "out")
				{
					$this->togglePanelVisibility(false);

					if($this->MakeTransitNote->Checked || $this->MakeDispatchNote->Checked || $this->MakeAssignmentNote->Checked)
					{
						$this->TransitNotePanel->Visible = true;
						if ($this->MakeTransitNote->Checked)
						{
							$this->facilitiesPanelParent->Visible = true;
							$this->otherPanelParent->Visible = true;
						}
						else if ($this->MakeDispatchNote->Checked)
						{
							$this->thirdPartyPanelParent->Visible = true;
							$this->clientWarehousePanelParent->Visible = true;
							$this->sitesPanelParent->Visible = true;
						}
						else if ($this->MakeAssignmentNote->Checked)
						{
							$this->technicianPanelParent->Visible = true;
						}
					}
					else $this->TransferPartPanel->Visible=true;

				}
				else $this->TransferPartPanel->Visible=true;

			$this->totalItems = 'Total ' . count($partInstances) . ' Parts';
		}
		else
		{
			$this->TransitNotePanel->Visible=false;
			$this->totalItems = '';
		}

		if ($this->inOrOut == "out")
		{
			$this->LocationWrapper->Visible = $this->MovePartRadio->Checked;
		}
		else
			$this->LocationWrapper->Visible = !$this->MakeTransitNote->Checked;

	}

	/**
     * This method revoked for pagination
     */

 	public function ReceivedPartPageChanged($sender, $param)
    {
      	$this->DataList->CurrentPageIndex = $param->NewPageIndex;
      	$this->loadMovingPartsList();
    }
	/**
	 * Reset Fields
	 *
	 */
	protected function resetFields()
	{
		$this->movingPartInstanceArray->Value="";
		$this->barcode->Text = "";
		$this->partcode->Text = "";
		$this->searchResultPanel->Visible = false;
		$this->TransitNotePanel->Visible = false;
		$this->TransferPartPanel->Visible = false;
		$this->partResultList->DataSource = array();
		$this->partResultList->DataBind();
		$this->partResultList_qty->Text=0;
		$this->DataList->DataSource = array();
		$this->DataList->DataBind();
		$this->Comments->Text="";
		$this->TransitNoteToList->DataSource = array();
		$this->TransitNoteToList->DataBind();
		$this->whTree->whIdPath->Value="";
		$this->SiteList->Text="";
	}

	// Facility request functions taken out till next promotion
	/**
	 * Save Facility Request with comment
	 *
	 * @param unknown_type $partInstance
	 * @param unknown_type $warehouse
	 */
	private function saveFacilityRequestWithComment($partInstance,$warehouse)
	{
		$facilityRequest = $partInstance->getFacilityRequest();
		$facilityRequestComments = $facilityRequest->getComment();
		$facilityRequestComments .= " [!" . $this->User->getUserAccount()->getPerson()->__toString() .  " - " . (string)DateUtils::now() .  " - Part Issued to  " . $warehouse . "!]" ;
		$facilityRequest->setComment($facilityRequestComments);
		Factory::service("FacilityRequest")->save($facilityRequest);
	}

	/**
	 * onError
	 *
	 * @param unknown_type $errorMessage
	 * @param unknown_type $sound
	 * @return unknown
	 */
	protected function onError($errorMessage, $sound = false)
	{
		$this->Page->jsLbl->Text = "<script type=\"text/javascript\">Modalbox.hide();</script>";
		$this->page->formPanel->Visible=true;
		$this->page->formPanel->Text = "<font color='red'><b>".$errorMessage."</b></font>";

		if ($sound)
			$this->setErrorMessageSound('<br />'.$errorMessage);
		else
			$this->setErrorMessageSound($errorMessage);

		$this->loadMovingPartsList();
		return false;
	}

	/**
	 * onSuccess
	 *
	 * @param unknown_type $infoMessage
	 * @param unknown_type $sound
	 * @return unknown
	 */
	protected function onSuccess($infoMessage, $sound = false)
	{
		$this->Page->jsLbl->Text = "<script type=\"text/javascript\">Modalbox.hide();</script>";
		$this->page->formPanel->Visible=true;
		$this->page->formPanel->Text = "<font color='green'><b>".$infoMessage."</b></font>";

		if ($sound)
			$this->setInfoMessageSound($infoMessage);
		else
			$this->setInfoMessage('<br />'.$infoMessage);

		$this->loadMovingPartsList();
		return false;
	}

	/**
	 * Find Sites
	 *
	 * @param unknown_type $searchString
	 * @return unknown
	 */
	private function findSites($searchString)
	{
		$query = new DaoReportQuery("Site");
		$query->column("s.id");
		$query->column("concat(s.siteCode,' - ',s.commonName)","name");
		$query->page(1,50);
		$query->where("(s.siteCode like '$searchString%' or s.commonName like '$searchString%') and s.active = 1");
		$query->orderBy("s.siteCode");

		$result = $query->execute();

		if(sizeof($result) == 0)
		return array(array(null,"No Data Returned."));

		return $result;
	}

	/**
	 * Find Facilities
	 *
	 * @param unknown_type $searchString
	 * @return unknown
	 */
	private function findFacilities($searchString)
	{
		$sql = "SELECT id,name FROM warehouse where (name like '%".$searchString ."%') and (facilityid != '' and active=1)";
		$result = Dao::getResultsNative($sql,array());

		if(sizeof($result) == 0)
		return array(array(null,"No Data Returned."));

		return $result;
	}

	/**
	 * Get Default Warehouse
	 *
	 * @param UserAccount $userAccount
	 * @return unknown
	 */
	private function getDefaultWarehouse(UserAccount $userAccount)
	{
		//check whether we've already got it, to improve performance!
		if($this->defaultWarehouse instanceof Warehouse)
			return $this->defaultWarehouse;

		$defaultWarehouse = Factory::service("Warehouse")->getDefaultWarehouse($userAccount);

		$person = $userAccount->getPerson();
		//if there is no default warehouse set up for current user.
		if(!$defaultWarehouse instanceof Warehouse)
		{
			$this->MainContent->Enabled = false;
			return $this->onError('No Default Warehouse for '.$person->getFullName());
		}

		if($this->MakeTransitNote->Checked || (!$this->isAgent && $this->inOrOut == "out" && $this->MakeDispatchNote->Checked))
		{
			if(!$defaultWarehouse->getFacility() instanceof Facility)
			{
				if(!$defaultWarehouse->getNearestFacility() instanceof Facility)
				{
					$this->MainContent->Enabled = false;
					return $this->onError('The default warehouse ('.$defaultWarehouse.') or nearest facility ('.$defaultWarehouse->getNearestFacility().') for '.$person->getFullName().' do not contain address details.');
				}
			}
		}

		$this->thisWarehouseService = Factory::service("Warehouse")->getWarehouseIdBreadCrumbs($defaultWarehouse);
		Factory::service("Warehouse")->getWarehouseIdBreadCrumbs($defaultWarehouse);
		$this->TransitNoteFromList->Text = $defaultWarehouse->getName();
		return $defaultWarehouse;
	}

	/**
	 * Check if Warhouse can recieve parts
	 *
	 * @param unknown_type $id
	 * @return unknown
	 */
	public function checkIfCanReceiveParts($id)
	{
		$query = new DaoReportQuery("Warehouse");
		$query->column("parts_allow");
		$query->where("id=$id");
		$result = $query->execute();
		if ($result[0][0] == "1") return true;
		else return false;
	}

	/**
	 * Bind Part in Location list
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
			$warehouse = "$site($warehouse)";

			$partInstanceDescription = $partInstance->getPartType()." : ";
			foreach($partInstance->getPartInstanceAlias() as $alias)
			{
				if(in_array($alias->getPartInstanceAliasType()->getId(),array(1,2,3,4,6)))
				$partInstanceDescription .= $alias;
			}
			$ftId = PartInstanceLogic::getFieldtaskIdByPartInstance($partInstance);//only  for pre-allocated
			$array[] = array("id"=>$partInstance->getId(),"name"=>$warehouse." | ".$partInstance->getPartInstanceStatus().$ftId." | ".$partInstance->getQuantity());
		}
		usort($array, create_function('$a, $b', 'return strcmp($a["name"], $b["name"]);'));
		if(count($array)==0)
			$array[] = array("id"=>"","name"=>"No parts found.");
		$this->partInstanceDesc->Text = $partInstanceDescription;
		$this->partResultList->DataSource = $array;
		$this->partResultList->DataBind();
	}

	/**
	 * Get URL
	 *
	 * @return unknown
	 */
	public function getUrl()
	{
		$url = "";
		try
		{
			$url = $this->Request->Url->getUri();
			$urlParseArray = parse_url($_SERVER["HTTP_REFERER"]);
			if(isset($urlParseArray["scheme"]))
				$scheme = $urlParseArray["scheme"];
			if(isset($urlParseArray["host"]))
				$host = $urlParseArray["host"];
			if(isset($scheme) && isset($host))
				$url = $scheme."://".$host;

		}
		catch (Exception $e)
		{

		}

		return $url;

	}

	/**
	 * Get Path
	 *
	 * @return unknown
	 */
    public function getPath()
	{
		$path = "";
		try
		{
			$path = $this->Request->Url->getPath();
		}
		catch (Exception $e)
		{

		}
		return $path;
	}

	public function onTreeLoadError($errMsg)
	{
		//$this->setErrorMessage($errMsg);
		$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('onTreeLoadError()'));
	}
}

?>
