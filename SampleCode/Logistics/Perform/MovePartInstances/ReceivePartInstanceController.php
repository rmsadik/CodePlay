<?php
/**
 * This is the "Receive Parts from transit note" Page
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @filesource
 * @version	1.0
 * @author  Lin He <lhe@bytecraft.com.au>
 */
class ReceivePartInstanceController extends MovePartInstancesWithTransitNoteController
{
	/**
	 * @var TransitNote
	 */
	private $transitNote=null;

	/**
	 * @var unknown_type
	 */
	public $itemCount;

	/**
	 * @var isAgent
	 */
	protected $isAgent;

	/**
	 * @var nonSerializedQtyRecieved
	 */
	protected $_nonSerializedQtyRecieved = array();
	/**
	 * On Pre Initialization
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
		$str = explode('/',$_SERVER['REQUEST_URI']);
		$this->menuContext = "consignment";
		if ($str[1] == 'agent')
		{
			$this->isAgent = true;
			$this->getPage()->setMasterClass("Application.layouts.NoExtJs.AgentLogisticsLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_agent_logistics_listTransitNote";
		}
		else if ($str[1] == 'staging')
		{
			$this->isAgent = false;
			$this->getPage()->setMasterClass("Application.layouts.NoExtJs.StagingLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_transferPartInward,menu_staging";
		}
		else
		{
			$this->isAgent = false;
			$this->getPage()->setMasterClass("Application.layouts.NoExtJs.LogisticsLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_transferPartInward";
		}
	}

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->inOrOut = 'in';
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
		$this->fieldTaskLbl->setText("");
		$this->Page->jsLbl->setText("");
		$this->page->formPanel->Visible=false;

		if(!Session::checkRoleFeatures(array('pages_all','pages_logistics','feature_displayPending')))
        {
			$this->hasfieldTaskLbl->Checked = false;
        }

        $this->PaginationPanel->Visible=false;
        $this->PaginationPanelParts->Visible=false;
		/* Facility request functions taken out till next promotion
		if(!$this->IsPostBack && !$this->IsCallBack)
		{
			$this->dontCheckIfRetrievedAndAwaitingReturnForFieldTask->Value = "";
			$this->PartInstanceIdAuto->Value = "";
		}
		*/

        try
        {
			if ($this->isAgent)
			{
				$this->agent->Value = "true";
				if (!Factory::service("UserAccountFilter")->hasFilter(Core::getUser(),Core::getRole(),"MoveWarehouse"))
					throw new Exception("User Account Filter [Move Warehouse] is not set.<br />Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

				if (!Factory::service("UserAccountFilter")->hasFilter(Core::getUser(),Core::getRole(),"ViewWarehouse"))
					throw new Exception("User Account Filter [View Warehouse] is not set.<br />Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");
			}
			else
			{
				$this->agent->Value = "false";
			}
		}
		catch (Exception $e)
		{
			$this->setErrorMessage($e->getMessage());
			$this->Page->MainContent->Enabled=false;
			$this->whTree->Visible=false;
		}

		$this->loadMovingPartsList();

		$this->MakeTransitNote->Visible=false;
		$this->MakeTransitNote->Checked=false;
		$this->getTransitNote();

		if ($this->transitNote instanceof TransitNote)
		{
			$this->showTransitNotePartsPanel();
		}
		$this->barcodeFromTransitNote->focus();
	}

	/**
	 * Add Parts From TransitNote
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function addPartsFromTransitNote($sender, $param)
	{
		$this->Page->jsLbl->Text = "";
		if($this->PartInstanceIdAuto->Value != "")
		{
			$partInstanceIds =  array($this->PartInstanceIdAuto->Value);
			$this->PartInstanceIdAuto->Value = "";
		}
		else
		{
			$this->PartInstanceIdAuto->Value = "";
			$partInstanceIds = array($param->CommandParameter);
			if(count($partInstanceIds)==0)
				return;
		}

		$array = unserialize($this->movingPartInstanceArray->Value);

		$partInstanceQuery = new DaoReportQuery("PartInstance");
		$partInstanceQuery->column("pi.id");
		$partInstanceQuery->column("pi.quantity");
		$partInstanceQuery->where("pi.id in (".implode(",",$partInstanceIds).") AND pi.active=1");
		$partInstanceQuery->where("pi.active=1");
		$partInstances = $partInstanceQuery->execute(false);
		$errorMsg = "";
		foreach($partInstances as $partInstance)
		{
			$partInstanceId = $partInstance[0];
			$error = "";
			if(!$this->checkAliasCompulsory($partInstanceId,$error))
			{
				$errorMsg .="<div>$error - <input type='button' onclick=\"openEditPartWindow($partInstanceId,'".htmlentities($error)."')\" value='Edit this part'/></div>";
				continue;
			}
   			$array[$partInstanceId] = $partInstance[1];
		}

		if($errorMsg!="")
		{
			$this->setErrorMessage($errorMsg);
		}

    	$this->movingPartInstanceArray->Value = serialize($array);
    	$this->loadMovingPartsList();
	}

	/**
	 * Add All Parts From TransitNote
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function addAllPartsFromTransitNote($sender, $param)
	{
		$extraSql = "";
		/* Facility request functions taken out till next promotion
		$partInstanceIdsArray = array();
		if($this->PartInstanceIdAuto->Value != ""){
			$partInstanceIdsSplit = explode(",", $this->PartInstanceIdAuto->Value);
			for($i=0; $i < count($partInstanceIdsSplit);$i++){
				if($partInstanceIdsSplit[$i]!=""){
					$partInstanceIdsArray[] = $partInstanceIdsSplit[$i];
				}
			}
			if(count($partInstanceIdsArray)>0){
				$extraSql = " AND pia.partInstanceId IN (" .implode(",",$partInstanceIdsArray) . ")";
			}
			$this->PartInstanceIdAuto->Value = "";
		}
		*/
		$sql = "select pi.id, pi.quantity, pia.alias, pt.serialised, pt.active, pt.name
				from partinstance pi
				INNER JOIN parttype pt ON pt.id=pi.parttypeid
				left join partinstancealias pia on (pia.partInstanceId = pi.id and pia.partInstanceAliasTypeId = 1 and pia.active = 1)
				where pi.warehouseId = ".$this->transitNote->getTransitNoteLocation()->getId()."
				AND pi.active=1 " . $extraSql;

		$partInstances = Dao::getResultsNative($sql);
		$array= array();
		$errorMsg = "";
		$rowNo =0;
		foreach($partInstances as $partInstance)
		{
			$rowNo++;
			$partInstanceId = $partInstance[0];
			$error = "";
			if(!$this->checkAliasCompulsory($partInstanceId,$error))
			{
				$errorMsg .="<div>Row $rowNo: $error - <input type='button' onclick=\"openEditPartWindow($partInstanceId,'".htmlentities($error)."')\" value='Edit this part'/></div>";
				continue;
			}

/*			//Check if part type is active
			if ($partInstance[4] == 0)
			{
				$this->jsLbl->Text = "<script type=\"text/javascript\">alert('Part Type (" . StringUtils::addOrRemoveSlashes($partInstance[5]). ") for Part Instance (" . StringUtils::addOrRemoveSlashes($partInstance). ") has been deactivated!');</script>";
				continue;
			}
*/
			$serialNo = $partInstance[2];
			$serialised = $partInstance[3];

			//add nonserialised and non-BT items
			if ($serialised == 0 || (preg_match("/^(BT)(\d{8})(\w)$/", $serialNo) == 0))
   				$array[$partInstanceId] = $partInstance[1];
		}

		if($errorMsg!="")
		{
			$this->setErrorMessage($errorMsg);
		}

    	$this->movingPartInstanceArray->Value = serialize($array);
    	$this->onSuccess("All parts added, except BT ones!",true);
    	$this->loadMovingPartsList();
	}

	/**
	 * Check Alias Compulsory
	 *
	 * @param unknown_type $partInstanceId
	 * @param unknown_type $errorMsg
	 * @return unknown
	 */
	private function checkAliasCompulsory($partInstanceId,&$errorMsg)
	{
		if($this->isAgent)	return true;

		$partInstance = Factory::service("PartInstance")->get($partInstanceId);
		if(!$partInstance instanceof PartInstance)
		{
			$errorMsg .=" Invlid Instance(ID=$partInstanceId)!";
			return false;
		}

		$partType = $partInstance->getPartType();
		if(!$partType instanceof PartType)
		{
			$errorMsg .=" Invalid PartType(part instance ID=$partInstanceId)!";
			return false;
		}
		$expectedAliasTypeIds = array();
		$mandatoryAliasIds = '';
		$piatList = Factory::service("Lu_PartType_PartInstanceAliasPattern")->getMandatoryUniquePatternsForPtPiat($partType,null,null,null);
		if (count($piatList)>0)
		{
			foreach($piatList as $piatObj)
			{
				if ($piatObj->getIsMandatory()==true)
				{
					if ($mandatoryAliasIds === '')
						$mandatoryAliasIds .= $piatObj->getPartInstanceAliasType()->getId();
					else
						$mandatoryAliasIds .= ",".$piatObj->getPartInstanceAliasType()->getId();
				}
			}

		}
    	if ($mandatoryAliasIds != '')
    	{
    		$expectedAliasTypeIds = explode(',',$mandatoryAliasIds);
    	}

    	$passed = true;
    	foreach($expectedAliasTypeIds as $partInstanceAliasTypeId)
    	{
    		$aliases = trim($partInstance->getAlias($partInstanceAliasTypeId));
    		$partInstanceAliasType = Factory::service("PartInstanceAliasType")->get($partInstanceAliasTypeId);
    		if(!$partInstanceAliasType instanceof PartInstanceAliasType) continue;
    		if($aliases=="")
    		{
    			$errorMsg .=" Alias (".$partInstanceAliasType.") is compulsory for the selected contract!";
				$passed = false;
    		}

    		foreach(explode(",",$aliases) as $alias)
    		{
    			if(!$this->checkAliasUnique($partInstanceAliasTypeId, $alias,$partType->getId(),$partInstance->getId(),$errorMsg))
    				$passed = false;
    		}
    	}
    	return $passed;
	}

	/**
	 * Check Alias Unique
	 *
	 * @param unknown_type $partInstanceAliasTypeId
	 * @param unknown_type $alias
	 * @param unknown_type $partTypeId
	 * @param unknown_type $currentPartInstanceId
	 * @param unknown_type $errorMsg
	 * @return unknown
	 */
	private function checkAliasUnique($partInstanceAliasTypeId, $alias,$partTypeId,$currentPartInstanceId,&$errorMsg)
	{
		$sql="select pi.id from partinstancealias pia
				inner join partinstance pi on (pi.id = pia.partInstanceId and pi.active = 1 and pi.id != $currentPartInstanceId)
				inner join parttype pt on (pt.id = pi.partTypeId and pt.active = 1 and pt.id =$partTypeId)
				where pia.alias like '$alias' and pia.active = 1 and pia.partInstanceAliasTypeId =".$partInstanceAliasTypeId;
		$result = Dao::getResultsNative($sql);
		if(count($result)>0)
		{
			$partInstance = Factory::service("PartInstance")->get($result[0][0]);
			if(!$partInstance instanceof PartInstance)
				return true;

			$partInstanceAliasType = Factory::service("PartInstanceAliasType")->get($partInstanceAliasTypeId);
			$errorMsg .=" Alias (".$partInstanceAliasType.") is not unique for the selected contract!";
			return false;
		}

		return true;
	}

	/**
	 * Finish ajax processing, show messages
     *
     */

    public function finishProcessParts()
    {
 	  if ($this->exceptionMessage->Value)
    	{
    		$this->setErrorMessage($this->exceptionMessage->Value);
    		$this->loadMovingPartsList();
    	}

    	else
    	{
    		$comments = trim($this->Comments->Text);
    		if($comments!="")
			{
				//reget the transit note, just incase anything has changed.
				$this->transitNote = Factory::service("TransitNote")->getTransitNote($this->Request["id"]);
				$timeZone = Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
				$now = new HydraDate("now",$timeZone);
				$comments = Core::getUser()->getPerson()." - $now($timeZone) - $comments";
				$this->transitNote->setComments(Factory::service("TransitNote")->appendComments($this->transitNote,$comments));
				Factory::service("TransitNote")->saveTransitNote($this->transitNote);
			}


			$this->resetFields();
			$warehouseId = $this->targetWarehouseId->Value;
			$warehouse = Factory::service("Warehouse")->get($warehouseId);

	        $this->onSuccess('All selected parts have been successfully moved to ' . $warehouse,true);

			$this->whTree->whIdPath->Value = $this->targetWarehouseId->Value;
			$this->nonSerializedQty->Value="";



    	}
    }

 	/**
     * call javascript function to hide modal box and call finishProcessReceiveParts()
     *
     */

	public function finishProcessReceiveParts()
	{
    	$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessParts();</script>";
    }

     /**
     * cycle through via ajax until all the parts have moved
     *
     * @return unknown
     */

	public function processReceiveParts()
    {
    	$counter = 0;
    	$numberOfPartsProcessedPerAjaxCallForReceiveParts = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'numberOfPartsProcessedPerAjaxCallForRecieveParts',true);
    	if (!is_numeric($numberOfPartsProcessedPerAjaxCallForReceiveParts))
    	{
    		$numberOfPartsProcessedPerAjaxCallForReceiveParts = 10;
    	}

    	try
		{
			$movingPartInstances = unserialize($this->movingPartInstanceArray->Value);
	   		$comments = trim($this->Comments->Text);
	   		$warehouseIds = explode('/',$this->whTree->whIdPath->Value);
	    	$warehouseId = end($warehouseIds);
			$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId);

			//moving all selected part instances
			$timeZone = Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
			$now = new HydraDate("now",$timeZone);

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
				$this->_nonSerializedQtyRecieved = json_decode($this->nonSerializedQty->Value,true);
				$tmp = json_decode($this->nonSerializedQty->Value,true);

				if(isset($tmp[$partInstance->getId()]) && $tmp[$partInstance->getId()] >'')
					$qty = (int) $tmp[$partInstance->getId()];

				Factory::service("TransitNote")->receivePartFromTransitNote($this->transitNote,$partInstance,$warehouse,$qty,$newPartInstanceStatusObj,"");

				unset($movingPartInstances[$movingPartInstanceId]);
				$this->movingPartInstanceArray->Value = serialize($movingPartInstances);

				$counter++;
				if($counter == $numberOfPartsProcessedPerAjaxCallForReceiveParts)
				{
					return array('stop' => false);
				}
			}

			if (count($movingPartInstances) == 0)
	    	{
	    		return array('stop' => true);
	    	}


		}
		catch (Exception $ex)
		{
			//$this->setErrorMessage($ex->getMessage());
			//$this->loadMovingPartsList();
			$this->exceptionMessage->Value = $ex->getMessage();
			return array('stop' => true);

		}
		return array('stop' => false);
    }


	/**
	 * Check Parts
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 * @return unknown
	 */
	public function checkParts($sender, $param)
	{
		$bMoveParts = false;
		$error = "";
		$bErrorSound = false;
		if(!$this->transitNote instanceof TransitNote)
		{
			$error .= "Invalid Transit Note!<br>";
			$this->loadMovingPartsList();
		}

		/* COMMON */
		$movingPartInstances = unserialize($this->movingPartInstanceArray->Value);
		$comments = trim($this->Comments->Text);
		$warehouseIds = explode('/',$this->whTree->whIdPath->Value);
    	$warehouseId = end($warehouseIds);
		$warehouse = Factory::service("Warehouse")->getWarehouse($warehouseId);
		$error .= Factory::service("Warehouse")->isWarehouseOKToReceivePartsReturnErrors($warehouse,true);
		/* COMMON */

		if($this->hasfieldTaskLbl->Checked AND ($this->checkedFieldTaskPending->Value == "false" OR $error)){
			$partInstanceIds = unserialize($this->movingPartInstanceArray->Value);

			// based on Noel's request, the list is now displayed bottom-up
			if (!empty($partInstanceIds) && $partInstanceIds != false && is_array($partInstanceIds))
			{
				$partInstanceIds = array_reverse($partInstanceIds, true);
			}
			$partInstances = array();
			$partTypeArray = array();
			if($partInstanceIds!=false)
			{
				foreach($partInstanceIds as $partInstanceId=>$qty)
				{
					$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
					if($partInstance instanceof PartInstance )
					{
						$partType = $partInstance->getPartType();
						array_push($partTypeArray,$partType);
					}
				}
			}

			$isStoreOkToMoveFrom = false;
			$isStoreOkToMoveTo = Factory::service("Warehouse")->isWarehouseIdMainStoreParentOrChildren($warehouseId);

			if($isStoreOkToMoveFrom || $isStoreOkToMoveTo){
				$html = Factory::service("PartType")->checkPendingPartsStatus($partTypeArray);
				if(!$html){
					$this->Page->jsLbl->Text = "<script type=\"text/javascript\">receiveParts();</script>";
					return true;
				}else{
					$this->fieldTaskLbl->setText($html);
					$this->ReceivePartsButton->Text = "Please review messages then Receive Parts";
					$this->checkedFieldTaskPending->Value = "true";
					$error .= "There are part types pending.<br>";
					$bErrorSound = true;
				}
			}
			else
			{
				$bMoveParts = true;
			}
		}else{
			$bMoveParts = true;
		}

		if($error){
			if($bErrorSound){
				return $this->onError($error,true);
			}else{
				return $this->onError($error);
			}
		}else{
			if($bMoveParts){
				$this->checkedFieldTaskPending->Value = "false";
				$this->Page->jsLbl->Text = "<script type=\"text/javascript\">receiveParts();</script>";
				return true;
			}
		}

	}

	/**
	 * Search PartInstance From TransitNote
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function searchPartInstanceFromTransitNote($sender, $param)
	{
		$barcode = trim($this->barcodeFromTransitNote->Text);
		$partcode="";
    	if($barcode=="" && $partcode=="")
    	{
    		$this->setErrorMessage("Nothing to search. Please enter barcode or part code.");
    		return;
    	}

    	if(!$this->transitNote->getTransitNoteLocation() instanceof Warehouse)
    	{
    		$this->setErrorMessage("Invalid location for this Transit Note.");
    		return;
    	}

    	$results = Factory::service("PartInstance")->searchPartInstanceByBarcodeAndPartcode($barcode,$partcode,null,30,$this->transitNote->getTransitNoteLocation());
        $this->searchResultPanel->Visible=false;
    	if(count($results)==0)
    	{
    		$this->onError("No part found!",true);
    		$this->loadMovingPartsList();
    	}
    	else if(count($results)==1 && $results[0]->getQuantity()==1)
    	{
    		$return =$this->addPartMovingList($results[0]);
    		if($return!==false)
    			$this->onSuccess("Part successfully added.",true);
    	}
    	else
    	{
    		$this->onError("Multiple parts found; please select from the list box.",true);
    		return;
    	}
    	$this->barcodeFromTransitNote->Text="";
	}

	/**
	 * Update PartInstance Status
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function updatePartInstanceStatus($sender, $param)
	{
		$partInstanceId = $this->DataList->DataKeys[$sender->Parent->ItemIndex];
	   	$partInstanceId = $param->CommandParameter;
   		$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
   		if($partInstance instanceof PartInstance)
   		{
   			$this->removePartInstanceFromMovingList($partInstance);
   		}
	}

	/*****************************************************************************************
	 *****************************************************************************************
	 * Private Function **********************************************************************
	 *****************************************************************************************
	 *****************************************************************************************
	 */

	/**
	 * Load Moving Parts List
	 *
	 */
	protected function loadMovingPartsList()
	{
		if($this->transitNote!=null && $this->transitNote instanceof TransitNote)
		{
			$this->showTransitNotePartsPanel();
		}

		parent::loadMovingPartsList();
	}

	/**
	 * Get TransitNote
	 *
	 */
	private function getTransitNote()
	{
		if(isset($this->Request["searchby"]) && $this->Request["searchby"] == "transitnote" && isset($this->Request["id"]))
		{
			$this->AddPartPanel->Visible = false;

			$transitNote = Factory::service("TransitNote")->getTransitNote($this->Request["id"]);
			if ($transitNote!=null && $transitNote instanceof TransitNote)
			{
				$this->transitNote = $transitNote;

				try
				{
					if (!$this->IsPostBack)
					{
						$destinationWarehouse = $this->transitNote->getDestination();
						$this->whTree->whIdPath->Value = $destinationWarehouse->getId();
						$this->targetWarehouseId->Value = $this->whTree->whIdPath->Value;
					}
				}
				catch(Exception $e)
				{
					$this->whTree->whIdPath->Value = "";
				}
				if ($this->transitNote->getTransitNoteStatus()!=TransitNote::STATUS_TRANSIT)
				{
					if (!$this->IsPostBack)
					{
						$this->setErrorMessage("You can't receive part from TransitNotes with status NOT in 'Transit'!");
					}
					$this->transitNote=null;
					$this->whTree->whIdPath->Value = "";
				}
			}
			else
			{
				$this->setErrorMessage("No TransitNote Found!");
				$this->transitNote=null;
			}
		}
	}

	/**
	 * Show TransitNote Parts panel
	 *
	 */
	private function showTransitNotePartsPanel()
	{
		$partsAddedToList = unserialize($this->movingPartInstanceArray->Value);
		$this->AddPartPanel->Visible=false;
		$this->partsInTransitNotePanel->Visible=true;
		$this->TransferPartsButton->Visible=false;
		$this->ReceivePartsButton->Visible=true;

		$this->partsInTransitNote->DataSource =array();
    	$this->partsInTransitNote->DataBind();
    	$this->PaginationPanel->Visible=false;

    	$pageNumber = $this->partsInTransitNote->CurrentPageIndex;
    	$pageSize = $this->partsInTransitNote->pageSize;

		if(!$this->transitNote instanceof TransitNote)
			return;

		$this->partsInTransitNoteLabel->Text = "Receive parts from TransitNote <b>".$this->transitNote->getTransitNoteNo()."</b>:";

		//check ismainstore
		$sql="select warehousealias.alias from warehousealias, warehouse, transitnote where warehousealias.warehouseId = warehouse.id and transitnote.destinationId=warehouse.id
		and warehousealias.warehouseAliasTypeId = 13 and warehousealias.active =1 and transitnote.id =".$this->transitNote->getID();

		$result = Dao::getResultsNative($sql);
		if(count($result) > 0) $this->ismainstore = $result[0][0];
		else $this->ismainstore = 0;
		if(!$pageNumber)
		{
			$limit = " limit " . $pageNumber . ",". $pageSize;
		}
		else
		{
			$limit = " limit " . ($pageNumber*$pageSize) . ",". $pageSize;
		}

		$columns = "select pi.id,
					pia.alias,
					pia_m.alias,
					pta.alias,
					fr.fieldTaskId,
					st.name,
					pi.quantity,
					pt.name,
					ptabc.alias,
					pt.serialised,
					ptahm.alias";

		$baseSql =" from partinstance pi
				left join partinstancealias pia on (pia.partInstanceId = pi.id and pia.partInstanceAliasTypeId=1 and pia.active = 1)
				left join partinstancealias pia_m on (pia_m.partInstanceId = pi.id and pia_m.partInstanceAliasTypeId=1 and pia.active = 6)
				left join parttype pt on (pt.id =pi.partTypeId)
				left join parttypealias pta on (pt.id =pta.partTypeId and pta.active = 1 and pta.partTypeAliasTypeId=1)
				left join parttypealias ptabc on (pt.id =ptabc.partTypeId and ptabc.active = 1 and ptabc.partTypeAliasTypeId=2)
				left join parttypealias ptahm on (pt.id = ptahm.partTypeId and ptahm.active = 1 and ptahm.partTypeAliasTypeId=15)
				left join partinstancestatus st on (st.id =pi.partInstanceStatusId and st.active = 1)
				left join facilityrequest fr on (pi.facilityRequestId = fr.id)
				where pi.active = 1
				and pi.warehouseId = ".$this->transitNote->getTransitNoteLocation()->getId();

		$partinstanceIdsAddedToList = array();
		$excludePartInstanceIds = "";


		if($partsAddedToList)
		{
			$partinstanceIdsAddedToList = array_keys($partsAddedToList);
			$excludePartInstanceIds = " and pi.id not in(" . implode(",",$partinstanceIdsAddedToList) . ") ";
		}

		$sql = $columns . $baseSql . $excludePartInstanceIds  . $limit;
		$sqlCount = "select count(*) " . $baseSql;

		$count = 0;
		$countResult = Dao::getResultsNative($sqlCount);
		if($countResult)
		{
			$countPartsAddedToList = 0;
			if(count($partsAddedToList)>0)
			{
				if($partsAddedToList)
				{
					$countPartsAddedToList = count($partsAddedToList);
				}
			}

			$count = $countResult[0][0] - $countPartsAddedToList;
			if ($count < 0)
			{
				throw new Exception('Unable to continue, please refresh the page and try again');
			}

		}

		$parts = Dao::execSql($sql);

		$listParts = array();
		foreach($parts as $part)
  		{
  			$serialised = $part[9];
  			$serialNo = $part[1];
  			if(is_null($serialNo) && $serialised == 1)
  			{
				$sqlDeactivatedBS ="select pia.alias from partinstancealias pia where pia.partInstanceId=".$part[0]." and pia.partInstanceAliasTypeId=1 order by pia.id desc limit 1";
				$deactivatedBS = Dao::getResultsNative($sqlDeactivatedBS);
				if(count($deactivatedBS)>0 && sizeof($deactivatedBS)>'')
				{
	  				$serialNo ="<font color=red >".$deactivatedBS[0][0]."</br>(De-active)</font>";
				}
  			}
  			else if ($serialNo == '' || $serialised == 0)
  				$serialNo = $part[8];

			$partInstance = Factory::service("PartInstance")->getPartInstance($part[0]);
			if($partInstance instanceof PartInstance )
			{
				$partType = $partInstance->getPartType();
				$partTypeId = $partType->getId();
				if ($partType->getActive() == 0)
				{
					$this->jsLbl->Text = "<script type=\"text/javascript\">alert('Part Type (" . StringUtils::addOrRemoveSlashes($partType). ") for Part Instance (" . StringUtils::addOrRemoveSlashes($partInstance). ") has been deactivated!');</script>";
				}
			}

  			$hotmessagePI = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($part[0],18);
			$hotmessagePI = count($hotmessagePI)==0 ? "" : $hotmessagePI[0]->getAlias();
			$listParts[] = array(
							"id"=>$part[0],
  							"serialNo"=>$serialNo,
  							"manfSerialNo"=>$part[2],
  							"partCode"=>$part[3],
  							"taskNo"=>$part[4],
  							"status"=>$part[5],
  							"qty"=>$part[6],
  							"partDescription"=>$part[7],
							"hotmessagePT"=>$part[10],
							"hotmessagePI"=>$hotmessagePI
						);
  		}

  		$this->itemCount = $count;

    	$this->partsInTransitNote->VirtualItemCount = $this->itemCount;
		$this->partsInTransitNote->DataBind();

		if($this->itemCount > $this->partsInTransitNote->PageSize)
			$this->PaginationPanel->Visible=true;
		else
    		$this->PaginationPanel->Visible=false;

  		//arsort($listParts,SORT_LOCALE_STRING);


  		$this->partsInTransitNote->DataSource = $listParts;
  		$this->partsInTransitNote->DataBind();

  		//loading transiteNote details
  		$this->loadTransitNoteDetails($this->transitNote);
	}

	/**
     * This method revoked for pagination
     */

 	public function pageChanged($sender, $param)
    {
    	$this->partsInTransitNote->CurrentPageIndex = $param->NewPageIndex;
      	$this->showTransitNotePartsPanel();
    }

	/**
	 * Replace String With Length
	 *
	 * @param unknown_type $string
	 * @param unknown_type $maxLength
	 * @return unknown
	 */
	private function replaceStringWithLength($string,$maxLength=null)
	{
		if($maxLength==null || $maxLength<strlen($string))
			$maxLength = strlen($string);

		return $string.str_repeat("&nbsp;",$maxLength-strlen($string));
	}

	/**
	 * Load TransitNote Details
	 *
	 * @param TransitNote $transitNote
	 */
	private function loadTransitNoteDetails(TransitNote $transitNote)
    {
    	//get From and To location address!
  		$sourceLocation = $transitNote->getSource();
  		$destinationLocation = $transitNote->getDestination();
  		$noteType = $transitNote->getNoteType();

  		if($sourceLocation->getFacility()!=Null && $sourceLocation->getFacility()->getAddress() != Null)
  		{
  			$facilityName = $sourceLocation->getAlias(WarehouseAliasType::$aliasTypeId_facilityName);
  			if (!is_null($facilityName) && $facilityName != '')
  				$facilityName = '<br /><span style="font-style:italic;">' . $facilityName . '</span><br />';

  			$this->TransitNoteFrom->Text = '<span style="font-weight:bold;">' . $sourceLocation->getName() . " <br />" . $facilityName . '</span>' . Factory::service("Address")->getAddressInDisplayFormat($sourceLocation->getFacility()->getAddress());
  		}
  		else
  			$this->TransitNoteFrom->Text = "<b style='color:#ff0000'>Warehouse is missing address details.</b>";

  		if($destinationLocation->getFacility()!=Null && $destinationLocation->getFacility()->getAddress() != Null)
  		{
  			$facilityName = $destinationLocation->getAlias(WarehouseAliasType::$aliasTypeId_facilityName);
	  			if (!is_null($facilityName) && $facilityName != '')
	  				$facilityName = '<br /><span style="font-style:italic;">' . $facilityName . '</span><br />';

	  		$this->TransitNoteTo->Text = '<span style="font-weight:bold;">' . $destinationLocation->getName() . " <br />" . $facilityName . '</span>' . Factory::service("Address")->getAddressInDisplayFormat($destinationLocation->getFacility()->getAddress());
  		}
  		else
		{
			if($noteType == TransitNote::NOTETYPE_ASSIGNMENTNOTE)
			{
				$this->TransitNoteTo->Text = '<span style="font-weight:bold;">' . Factory::service("Warehouse")->getWarehouseBreadCrumbs($destinationLocation,true) . '</span>';
			}
			else
			{
  				$this->TransitNoteTo->Text = "<b style='color:#ff0000'>Warehouse is missing address details.</b>";
			}
		}

  		//get information
      	$this->SpecialDeliveryInstructionsLabel->Text = $transitNote->getSpecialDeliveryInstructions();
  		$this->TransitNoteStatus->Text = $transitNote->getTransitNoteStatus();

  		// convert this UTC time to time of User viewing it!
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
       	$this->TotalItemsLabel->Text = (string)$transitNote->getTotalItems();
       	$this->TotalPackagesLabel->Text = (string)$transitNote->getNoOfPackages();

  		$this->ExistingComments->Text=Factory::service("TransitNote")->formatCommentsForHTML($transitNote);
       	$this->ClientJobNosLabel->Text=$transitNote->getClientJobNos();
       	$this->CourierJobNoLabel->Text=$transitNote->getCourierJobNo();
  		$this->CourierLabel->Text = $transitNote->getCourier();

  		if(!$this->Page->IsPostBack)
		{
			$this->barcodeFromTransitNote->focus();
		}
    }

    /**
     * Add Part Moving List
     *
     * @param PartInstance $partInstance
     * @return unknown
     */
    private function addPartMovingList(PartInstance $partInstance)
    {
    	$partInstanceId = $partInstance->getId();
    	$errorMsg = "";
    	if(!$this->checkAliasCompulsory($partInstanceId,$errorMsg))
    		return $this->onError("<div>$errorMsg - <input type='button' onclick=\"openEditPartWindow($partInstanceId,'".htmlentities($errorMsg)."')\" value='Edit this part'/></div>");

    	$array = unserialize($this->movingPartInstanceArray->Value);
   		$array[$partInstanceId] = $partInstance->getQuantity();
    	$this->movingPartInstanceArray->Value = serialize($array);
    	$this->loadMovingPartsList();
    }

    /**
     * Get PartType Details
     *
     * @param PartType $partType
     * @param unknown_type $partInstanceId
     * @param unknown_type $prefix
     * @return unknown
     */
    public function getParTypeDetails(PartType $partType, $partInstanceId,$prefix="PartTypeDetails_")
    {
    	$details = "<div id=\"$prefix$partInstanceId\" style=\"display:none;\">";
	    	$details .= "<b>Description</b>: ".$partType->getDescription()."<br />";
	    	$sql = "select ptat.name,pta.alias
	    				from parttypealias pta
	    				left join parttypealiastype ptat on (ptat.id = pta.partTypeAliasTypeId and ptat.active = 1)
	    				where pta.active = 1
	    				and pta.partTypeAliasTypeId !=1
	    				and pta.partTypeId = ".$partType->getId();
	    	$result = Dao::getResultsNative($sql);
	    	foreach($result as $row)
	    	{
		    	$details .= "<b>{$row[0]}</b>: {$row[1]}<br />";
	    	}
	    $details .= "</div>";

		$cg = $partType->getContractGroup();
	    if($cg == null)
	    {
   	 		$sql = "select con.contractName
    				from contract_parttype x
    				inner join contract con on (x.contractId = con.id and con.active = 1)
    				where x.partTypeId = ".$partType->getId();
    		$result = Dao::getResultsNative($sql);


		    if(count($result)>0)
		    {
	    		$details .= "<b>Contracts</b>: <br />";
		    	$details .= "<ul style='list-style:disc; margin: 0 0 0 50px;'>";
	    		foreach($result as $row)
	    		{
			    	$details .= "<li>{$row[0]}</li>";
	    		}
		    	$details .= "<ul>";
		    }
	     }
	     else
	     {
	     		$details .= "<b>Contract Group</b>: <br />";
		    	$details .= "<ul style='list-style:disc; margin: 0 0 0 50px;'>";
		    	$details .= $cg;
	     }

	    return $details;
    }
}
?>