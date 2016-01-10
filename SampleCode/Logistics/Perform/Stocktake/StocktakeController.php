<?php
/**
 * StockTake Controller page
 *
 * @package	Hydra-Web
 * @subpackage Controller
 * @version	1.0
 */
class StocktakeController extends HydraPage
{
	/**
	 * @var menuContext
	 */
	public $menuContext;

	/**
	 * @var email
	 */
	public $email;

	private $_foundArr = array();

	private $_shelfPartInstanceStatusId = null;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = "stocktake";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_stocktake";
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
       	parent::onLoad($param);


		$warehouse = $this->getWarehouse();
		if (!$warehouse instanceof Warehouse)
		{
			$this->setErrorMessage("Invalid Warehouse!");
			$this->MainContent->Enabled=false;
			return;
		}

		$messages = $this->showMessages($warehouse);
	    if(!$this->IsPostBack || $param == "reload")
        {
        	$this->whId->Value = $warehouse->getId();

			if (!$warehouse->getParts_allow())
			{
				$this->setErrorMessage("Parts cannot be stored in '" . Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,"/"). "'.<br />Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ." to enable part storage.");
				$this->MainContent->Enabled=false;
				return;
			}

			//check whether the user have access to this warehouse
			try
			{
				Factory::service("Warehouse")->checkAccessToWarehouse($warehouse);
			}
			catch(Exception $ex)
			{
				$this->setErrorMessage("You don't have access to '".Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,"/")."'");
				$this->MainContent->Enabled=false;
				return;
			}

			$lostStockWarehouse = $warehouse->getLostStockWarehouse();
			if(!$lostStockWarehouse instanceof Warehouse)
			{
				$this->setErrorMessage("'".Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,"/")."' does not have a 'Lost Stock warehouse'. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");
				$this->MainContent->Enabled=false;
				return;
			}

			if ($messages != '')
			{
				$this->loadStockTakeInfo($warehouse);
 				$this->listPanel->Visible = false;
				$this->scanPanel->Visible = false;
	 			$this->confirmPanel->Visible = true;
			}
			else
			{
				$this->confirmPanel->Visible = false;
				$this->continueStockTake(null, null);
			}
        }

        $this->searchPart->serialNo->focus();

        //get the email to set
        $personObj = Factory::service("UserAccount")->getUserByUsername(Core::getUser());
        $personEmail = $personObj->getPerson()->getEmail();

        if (empty($personEmail))
        	$this->email = Config::get("SupportHandling", "Email");
        else
        	$this->email = $personEmail;
    }

    /**
     * Get Amount of Time Message
     *
     * @param unknown_type $time_diff
     * @return unknown
     */
    private function getAmountOfTimeMessage($time_diff){
    	$ret = "";
    	$minutes = $time_diff / 60;
    	$hours = $minutes / 60;
    	if(intval($hours)>0){
    		$minutes = 	$minutes % 60;
    	}
    	$days = $hours / 24;
    	if(intval($days)>0){
    		$hours = $hours % 24;
    	}

    	$days = intval($days);
    	$hours = intval($hours);
    	$minutes = intval($minutes);

    	if($days != 0){
    		$ret = $days . " days " . $hours . " hours and " . $minutes . " minutes";
    	}elseif($hours != 0){
    		$ret = $hours . " hours and " . $minutes . " minutes";
    	}elseif($minutes != 0){
    		$ret = $minutes . " minutes";
    	}


    	return $ret . " ago";
    }

    /**
     * Show Messages
     *
     * @param unknown_type $warehouse
     */
    private function showMessages($warehouse)
    {
    	$messages = "";
	    $createdByDifferentUser = false;
	    $updatedByDifferentUser = false;
	    $created = "";
	    $updated = "";
	    $differenceInTimeFromCreated = 0;
	    $differenceInTimeFromUpdated = 0;

   	 	$qryResult = Factory::service("StockTake")->getLogStocktakeDetails($warehouse);
   	 	foreach($qryResult as $row)
	    {
	    	$differenceInTimeFromCreated = (time() - $row['createdTimestamp']);
			$differenceInTimeFromUpdated = (time() - $row['updatedTimestamp']);
			$createdUserId = $row['createdById'];
			$updatedUserId = $row['updatedById'];
			$created = $row['created'];
			$updated = $row['updated'];

			$createdUser = UserAccountService::getFullName($createdUserId);
			$updatedUser = UserAccountService::getFullName($updatedUserId);

	   	 	if($updatedUserId != Core::getUser()->getId())
				$updatedByDifferentUser = true;

			if($createdUserId != Core::getUser()->getId())
				$createdByDifferentUser = true;
	    }

	    if (!$createdByDifferentUser && $differenceInTimeFromCreated <= 86400)
	    	return '';
	    else if (!$updatedByDifferentUser && $differenceInTimeFromUpdated <= 86400)
	    	return '';
	    else
	    {
			$messages = "<div>";
			$messages .= "<b><span style='color:red;font-size:140%;margin-left:3px;text-decoration:underline'>Warning</span></b>";

			if($createdByDifferentUser)
			{
				if(sizeof($createdUser)>"" && count($createdUser)>0)
				{
					$messages .= "<BR><b>Stocktake was created by " . $createdUser[0][0] . ", " . $this->getAmountOfTimeMessage($differenceInTimeFromCreated) . " on " . $created . " UTC.</b>";
				}
				else
				{
					$messages .= "<BR><b><span style='color:red;'><blink>Note: Open stocktake done by deactivated user.</blink></span> <BR>This Stocktake was created " . $this->getAmountOfTimeMessage($differenceInTimeFromCreated) . " on " . $created . " UTC.</b>";
				}

			}
			else
			{
				$messages .= "<BR><b>Stocktake was created  " . $this->getAmountOfTimeMessage($differenceInTimeFromCreated) . " on " . $created . "  UTC.</b>";
			}

			if($created != $updated)
			{
				if($updatedByDifferentUser)
				{
					$messages .= "<BR><b>Stocktake was updated by " . $updatedUser[0][0] . ", " . $this->getAmountOfTimeMessage($differenceInTimeFromUpdated) . " on " . $updated . "  UTC.</b>";
				}
				else
				{
					$messages .= "<BR><b>Stocktake was updated  " . $this->getAmountOfTimeMessage($differenceInTimeFromUpdated) . " on " . $updated . "  UTC.</b>";
				}
			}
			$messages .= "<BR><p>Parts that have been moved since the stocktake was started will not be included.</p>";
	   	 	$messages .= "</div>";
	   	 	$this->messagePanel->getControls()->add($messages);
	    }
	    return $messages;
    }

    /**
     * Get Warehouse
     *
     * @return unknown
     */
    private function getWarehouse()
    {
		return Factory::service("Warehouse")->getWarehouse($this->Request['id']);
    }

    /**
     * Load Lists
     *
     * @param Warehouse $warehouse
     */
    private function loadLists(Warehouse $warehouse)
    {
    	$this->loadStockTakeInfo($warehouse);
		$this->loadFoundList();
		$this->loadOriginalList();
    }


    /**
     * Load StockTake Info
     *
     * @param Warehouse $warehouse
     */
    private function loadStockTakeInfo(Warehouse $warehouse)
    {
    	$html="<b>Stocktaking:</b> ".Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,'/');
    	$html.="<br /><span style='color:red;' ><b>All lost stock will be moved to:</b> <span id='lostStockWarehouse'>".Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse->getLostStockWarehouse(),true,'/')."</span></span>";
    	$html.="<br /><br /><b>Last Stocktake:</b> ".$warehouse->getAlias(WarehouseAliasType::$aliasTypeId_lastStocktakeDate);
    	$html.="<br /><b>Next Stocktake Due:</b> ".$warehouse->getAlias(WarehouseAliasType::$aliasTypeId_nextStocktakeDate);
    	$html.="<br /><br /><span style='color:red;'><b>You will need to click the 'Save Scanned Parts' button after scanning 10 items to ensure data is not accidentally lost.</b></span>";

    	$partInstanceStatusId = Factory::service("Warehouse")->getWarehousePartsStatusId($warehouse);
    	if ($partInstanceStatusId !== null)
    	{
    		$partInstanceStatus = Factory::service("PartInstanceStatus")->get($partInstanceStatusId);
    		if ($partInstanceStatus instanceOf PartInstanceStatus)
    		{
    			$this->Page->shelfPartInstanceStatusId->Value = $partInstanceStatusId;
		    	$html.="<br /><br /><span style='color:red;'>The location has a default part status setting of <b>'" . $partInstanceStatus->getName() . "'</b>, you will be unable to manually change the part instance status.</span>";
    		}
    	}
    	$this->StocktakeInfo->getControls()->add($html);
    }

    /**
     * View Move Parts
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
 	protected function viewMovedParts($sender, $param)
 	{
 		if ($this->movedParts->Text == 'Click here to view.')
 		{
 			$sql = $this->_getSqlForStocktakeTable($this->targetWarehouseId->Value, $this->logStocktakeId->Value, 0);
	    	$result = Dao::getResultsNative($sql, array(), PDO::FETCH_ASSOC);
	    	if(count($result) > 0)
	    	{
	    		$html = "<table width=\"100%\" style='border:#666666 1px solid;' cellspacing=0 ><tr style='background-color:#EEEEEE;'>";
	    		$html .= "<th width=\"50%\" style='padding-top:3px; padding-bottom:3px;border-bottom:#666666 solid 1px'><b>Part Name</b></th>";
	    		$html .= "<th width=\"15%\" style='padding-top:3px; padding-bottom:3px;border-bottom:#666666 solid 1px'><b>Status</b></th>";
	    		$html .= "<th width=\"5%\" style='padding-top:3px; padding-bottom:3px;border-bottom:#666666 solid 1px'><b>Qty</b></th>";
	    		$html .= "<th width=\"15%\" style='padding-top:3px; padding-bottom:3px;border-bottom:#666666 solid 1px'><b>S/N</b></th></tr>";
	    		$rowNo=0;
	 			foreach($result as $row)
		    	{
		    		$ftId='';
		    		if($row['statusId'] == PartInstanceStatus::ID_PART_INSTANCE_STATUS_PREALLOCATED)
		    			$ftId = "23232333";

		    		if ($row['piWhId'] == $row['warehouseid'])
		    			continue;

		    		$html .= "<tr class='".($rowNo%2==0 ? 'DataListItem' : 'DataListAlterItem')."'>";
				    	$html .= "<td><b>{$row['partcode']}</b><br /><font style='font-size:10px;'>{$row['parttype']}</font></td>";
				    	$html .= "<td>".$row['status']."-".$row['statusId']."</td>";
				    	$html .= "<td>{$row['quantity']}</td>";
				    	$html .= "<td>";
		    				if($row['serialised'])
				    		{
				    			$serials = array($row['bs']);
				    			if ($row['bx'] != '')
				    				$serials[] = $row['bx'];
				    			$html .= implode(',<br />', $serials);
				    		}
				    		else
					    		$html .= "<b>{$row['bp']}</b>";
				    	$html .= "</td>";
	    			$html .= "</tr>";
	    			$rowNo++;
		    	}
		    	$html .= "</table>";

		    	$this->movedPartsPanel->Text = $html;
		    	$this->movedParts->Text = "Click here to hide.";
		    	$this->movedPartsPanel->Visible = true;
	    	}
 		}
 		else
 		{
 			$this->movedPartsPanel->Text = "";
 			$this->movedPartsPanel->Visible = false;
 			$this->movedParts->Text = "Click here to view.";
 		}
    }

    /**
     * Load Found List (parts already in the stocktake)
     *
     */
    private function loadFoundList()
    {
    	$warehouseId = $this->Request['id'];
    	$warehouse = Factory::service("Warehouse")->get($warehouseId);
    	if(!$warehouse instanceof Warehouse)
    		return;

    	$logStocktakeId = trim(StockTakeService::getStocktakeId($warehouse));
    	if(empty($logStocktakeId))
    		return;

    	//remove moved parts from the stocktake
    	Factory::service("StockTake")->removeMovedPartsFromStocktake($warehouseId, $logStocktakeId);

    	//find parts that have been moved since the stocktake started (not including ones from right-to-left)
    	$sql = "SELECT COUNT(s.id) FROM stocktake s
				INNER JOIN partinstance pi ON pi.id=s.partinstanceid AND pi.active=1 AND pi.warehouseid!=$warehouseId
				WHERE s.targetWarehouseId=$warehouseId AND s.logStocktakeId=$logStocktakeId AND s.active=0";
		$res = Dao::getSingleResultNative($sql);
		if ($res !== false && $res[0] > 0)
		{
			$this->targetWarehouseId->Value = $warehouseId;
			$this->logStocktakeId->Value = $logStocktakeId;

			$this->movedPartsPanelWrapper->Visible = true;
			$this->movedPartsLabel->Text = "<b><span style='color:#FF8C00;font-size:140%;margin-left:3px;text-decoration:underline'>Warning</span><br />" . $res[0] . " parts have been moved since this stocktake was started.</b><br />These have been removed from the stocktake.";
		}

    	$sql = $this->_getSqlForStocktakeTable($warehouseId, $logStocktakeId);
//     	Debug::inspect($sql);
    	$this->userList->getControls()->add($this->getTable(Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC),false));
    }

    /**
     * Returns the SQL to fetch parts from the stocktake table
     * @param int $warehouseId
     * @param int $logStocktakeId
     * @param int $stocktakeActive
     * @return string
     */
    private function _getSqlForStocktakeTable($warehouseId, $logStocktakeId, $stocktakeActive = 1)
    {
    	return "SELECT
	    			st.id,
	    			st.warehouseid,
	    			st.partInstanceid `piId`,
	    			pta.alias `partcode`,
	    			pt.name `parttype`,
	    			pis.name `status`,
	    			pis.id `statusId`,
    				pi.facilityRequestId `frId`,
	    			st.quantity,
	    			pt.serialised,
	    			piaBS.alias `bs`,
	    			piaBX.alias `bx`,
	    			ptaBP.alias `bp`,
    				pi.warehouseid `piWhId`,
	    			IF(pt.serialised=0, CONCAT(pt.id, '_', pis.id,'_', pi.warehouseId,'_',pi.id), st.partInstanceid) `match`
				FROM stocktake st
				LEFT JOIN partinstance pi ON (pi.id = st.partInstanceId)
				LEFT JOIN parttype pt ON (pt.id = st.partTypeId)
				LEFT JOIN parttypealias pta ON (pta.partTypeId=pt.id AND pta.active=1 AND pta.partTypeAliasTypeId=" . PartTypeAliasType::ID_PARTCODE . ")
				LEFT JOIN partinstancestatus pis ON pis.id=st.partinstancestatusid
				LEFT JOIN partinstancealias piaBS ON piaBS.partInstanceId=pi.id AND piaBS.partInstanceAliasTypeId=" . PartInstanceAliasType::ID_SERIAL_NO . " AND piaBS.active=1
 				LEFT JOIN partinstancealias piaBX ON piaBX.partInstanceId=pi.id AND piaBX.partInstanceAliasTypeId=" . PartInstanceAliasType::ID_BOX_LABEL . " AND piaBX.active=1
 				LEFT JOIN parttypealias ptaBP ON ptaBP.partTypeId=pt.id AND ptaBP.active=1 and ptaBP.partTypeAliasTypeId= " . PartTypeAliasType::ID_BP . "
				WHERE st.targetWarehouseId=$warehouseId AND st.logStocktakeId=$logStocktakeId AND st.active=$stocktakeActive
				GROUP BY st.id
    			ORDER BY pt.serialised, pta.alias, pis.name";
    }

    /**
     * Load Original List
     *
     */
	private function loadOriginalList()
    {
    	$warehouseId = $this->Request['id'];
    	$sql = "SELECT
	    			pi.id,
	    			pta.alias `partcode`,
	    			pt.name `parttype`,
	    			pis.name `status`,
	    			pis.id `statusId`,
    				pi.facilityRequestId `frId`,
	    			pi.quantity,
	    			pt.serialised,
	    			piaBS.alias `bs`,
	    			piaBX.alias `bx`,
	    			ptaBP.alias `bp`,
	    			IF(pt.serialised=0, CONCAT(pt.id, '_', pis.id,'_', pi.warehouseId,'_',pi.id), pi.id) `match`
				FROM partinstance pi
				INNER JOIN parttype pt ON (pt.id = pi.partTypeId)
				INNER JOIN parttypealias pta ON (pta.partTypeId=pt.id AND pta.active=1 AND pta.partTypeAliasTypeId=" . PartTypeAliasType::ID_PARTCODE . ")
				INNER JOIN partinstancestatus pis ON pis.id=pi.partinstancestatusid
				LEFT JOIN partinstancealias piaBS ON piaBS.partInstanceId=pi.id AND piaBS.partInstanceAliasTypeId=" . PartInstanceAliasType::ID_SERIAL_NO . " AND piaBS.active=1
				LEFT JOIN partinstancealias piaBX ON piaBX.partInstanceId=pi.id AND piaBX.partInstanceAliasTypeId=" . PartInstanceAliasType::ID_BOX_LABEL . " AND piaBX.active=1
				LEFT JOIN parttypealias ptaBP ON ptaBP.partTypeId=pt.id AND ptaBP.active=1 and ptaBP.partTypeAliasTypeId= " . PartTypeAliasType::ID_BP . "
				WHERE pi.warehouseid=$warehouseId AND pi.active=1
				GROUP BY pi.id
				ORDER BY pt.serialised, pta.alias, pis.name";
    	$this->orginalList->getControls()->add($this->getTable(Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC)));
    }

    /**
     * Get Table
     *
     * @param array $result
     * @param unknown_type $foundParts
     * @return unknown
     */
    private function getTable(array $result,$foundParts=true)
    {
    	$this->shelfListWarningMsg->Text = '';
    	$this->commitBtnTop->Enabled = true;
    	$this->commitBtnBtm->Enabled = true;

    	if(count($result)==0)
    		return "";

    	$openTasksByPiId = array();

    	$resultSize = count($result);
    	if($foundParts) //parts on shelf (not yet scanned)
    	{
    		$checkBoxPrefix = $this->orginalList->getId();
    		$buttonHtml = "<input type='button' onclick=\"submitParts(this,$resultSize,'$checkBoxPrefix','".$this->recordFoundPartsBtn->getClientId()."');\" value='Found Selected Parts' class='submitSelectedDataBtn' />";
    		$disableQty = false;

    		//check here for parts with open tasks
    		$serialisedPiIds = $shelfPiIds = array();
    		foreach ($result as $r)
    		{
    			$shelfPiIds[] = $r['id'];

    			if ($r['serialised'])
    				$serialisedPiIds[] = $r['id'];
    		}
    		$this->shelfPartInstanceIds->Value = serialize($shelfPiIds);
    		$this->shelfOpenTasksErrorCount->Value = 0;
    		$openTasksByPiId = Factory::service("FieldTask")->getOpenTaskIdsForPIIds($serialisedPiIds);
    	}
    	else
    	{
    		$checkBoxPrefix = $this->userList->getId();
    		$buttonHtml = "<input type='button' onclick=\"submitParts(this,$resultSize,'$checkBoxPrefix','".$this->removeFromFoundPartsBtn->getClientId()."');\" value='Remove Selected Parts' class='submitSelectedDataBtn' />";
    		$disableQty=true;
    	}

    	$html = $buttonHtml;
    	$html .= "<table width='100%' class='DataList'>";
	    	$html .= "<thead>";
		    	$html .= "<tr height='25px'>";
			    	$html .= "<th width='15px'><input type='checkbox' id='".$checkBoxPrefix."_default' onclick=\"selectAll(this,$resultSize,'$checkBoxPrefix');\"/></th>";
			    	$html .= "<th>Part Name</th>";
			    	$html .= "<th width='50px'>Status</th>";
		    		$html .= "<th width='40px'>Qty</th>";
			    	$html .= "<th width='100px'>S/N</th>";
		    	$html .= "</tr>";
	    	$html .= "</thead>";

	    	$html .= "<tbody>";
	    		$totalLossQty = 0;
	    		$rowNo=-1;
	    		$totalParts = 0;
	    		$totalRows = 0;
		    	foreach($result as $row)
		    	{
		    		$openTasksColour = '';
		    		$openTaskLinks = array();
		    		$chkStyle = 'style="width:40px;"';
		    		$chkTitle = '';
					$fieldtaskURL = FacilityRequestLogic::getFieldtaskURLByFrIdAndPIStatusId($row['statusId'], $row['frId']);
	    			$quantityToDisplay = $row['quantity'];

	    			$rowNo++;
		    		if ($foundParts == false)		//parts in the stocktake
		    		{
		    			$this->_foundArr[$row['match']] = $row['quantity'];
		    		}
		    		else  //parts on the shelf
		    		{
 		    			if (array_key_exists($row['match'], $this->_foundArr))
		    			{
		    				$scannedQty = $this->_foundArr[$row['match']];
		    				$shelfQty = $row['quantity'];
		    				$quantityToDisplay = $shelfQty - $scannedQty;
		    				$chkStyle = 'style="width:40px;background-color:salmon;"';
		    				$chkTitle = 'title="Indicates a potential loss of ' . $quantityToDisplay . ' part(s)."';
		    			}
	    				if ($quantityToDisplay <= 0) //there are no losses left on the shelf so continue (don't add to the left hand side)
	    					continue;

	    				$totalLossQty += $quantityToDisplay;

	    				//check for open tasks
	    				if (array_key_exists($row['id'], $openTasksByPiId))
	    				{
	    					$this->shelfOpenTasksErrorCount->Value += 1;

	    					$openTasksColour = 'background-color:orangered;';
	    					$openTaskLinks = $this->getTaskLinks($openTasksByPiId[$row['id']]);
	    				}
		    		}

	    			$totalParts += $quantityToDisplay;
		    		$totalRows++;

		    		$html .= "<tr class='".($rowNo%2==0 ? 'DataListItem' : 'DataListAlterItem')."'>";
				    	$html .= "<td><input type='checkbox' class='" . $checkBoxPrefix . "_chk' id='".$checkBoxPrefix."_$rowNo' value='{$row['id']}' onclick=\"$('".$checkBoxPrefix."_default').checked='';\"/></td>";
				    	$html .= "<td><b>{$row['partcode']}</b><br /><font style='font-size:10px;'>{$row['parttype']}</font></td>";
				    	$html .= "<td>".$row['status'].$fieldtaskURL."</td>";
				    	$html .= "<td><input $chkTitle type='text' $chkStyle class='" . $checkBoxPrefix . "_qty' id='".$checkBoxPrefix."_qty_$rowNo' value='{$quantityToDisplay}' ".(($disableQty || $row['serialised']) ? "disabled='true'" : "")."/></td>";
				    	$html .= "<td align='center' style='text-align:center; vertical-align:middle; " . $openTasksColour . "'>";
				    		if($row['serialised'])
				    		{
				    			$serials = array($row['bs']);
				    			if ($row['bx'] != '')
				    				$serials[] = $row['bx'];
				    			$html .= implode(',<br />', $serials) . (!empty($openTaskLinks) ? '<br />[FT: ' . implode(']<br />[FT: ', $openTaskLinks) . ']' : '');
				    		}
					    	else
					    		$html .= "<b>{$row['bp']}</b>";
				    	$html .= "</td>";
	    			$html .= "</tr>";
		    	}

		    	$this->totalLossQty->Value = $totalLossQty;

		    	if ($this->Page->shelfOpenTasksErrorCount->Value > 0)
		    	{
		    		$this->shelfListWarningMsg->Text = '<span style="font-weight:bold; color:orangered;">There are ' . $this->Page->shelfOpenTasksErrorCount->Value  . ' part instances that have open workshop tasks, unable to commit stocktake until part is found or task is cancelled.</span>';
		    		$this->commitBtnTop->Enabled = false;
		    		$this->commitBtnBtm->Enabled = false;
		    	}

	    	$html .= "</tbody>";

	    	$html .= "<tfoot>";
		    	$html .= "<tr height='25px'>";
			    	$html .= "<td colspan='5' style='text-align:center; font-weight:bold;'> Total $totalRows record(s)<br />Total $totalParts part(s)</td>";
		    	$html .= "</tr>";
	    	$html .= "</tfoot>";
    	$html .= "</table>";
    	$html .= $buttonHtml;

    	return $html;
    }

    public function getTaskLinks($ftIds)
    {
    	$openTaskLinks = array();
    	foreach ($ftIds as $ftId)
    	{
    		$openTaskLinks[] = '<a onclick="window.open(\'/task/edit/workshop/' . $ftId . '\'); return false;" href="/task/edit/workshop/' . $ftId . '">' . $ftId . '</a>';
    	}
    	return $openTaskLinks;
    }

    /**
     * Record Found Parts
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function recordFoundParts($sender,$param)
    {
    	$this->setInfoMessage("");
    	$this->setErrorMessage("");

    	$requestedArray = json_decode(trim($this->selectedPIIds->Value));
    	$this->selectedPIIds->Value="";
    	$warehouse = $this->getWarehouse();

    	if(!$warehouse instanceof Warehouse)
    	{
    		$this->stockTakePanel->Visible=false;
    		$this->StocktakeInfo->Text="<h3 style='color:red;'>Invalid Warehouse!</h3>";
    		return;
    	}

    	if(!StockTakeService::checkWHUnderST($warehouse))
    	{
    		$this->stockTakePanel->Visible=false;
    		$this->StocktakeInfo->Text="<h3 style='color:red;'>'$warehouse' is not under stocktake!</h3><br /><a href='/stock/warehouse/".$warehouse->getId()."/'>Back to the stock page</a>";
    		return;
    	}

    	if(count($requestedArray)==0)
    		$this->setErrorMessage("Nothing to be recorded from!");
    	else
    	{
    		$logStocktakeId = Factory::service("StockTake")->getStocktakeId($warehouse);
    		foreach($requestedArray as $partInstanceId => $qty)
    		{
    			if($qty>0)
    				Factory::service("StockTake")->recordPartForST($logStocktakeId,$partInstanceId,$qty);
    		}
    		$this->setInfoMessage("Successfully recorded data from selected record(s).");
    	}

    	$this->loadLists($warehouse);
    }

    /**
     * Remove From Found Parts
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function removeFromFoundParts($sender,$param)
    {
    	$this->setInfoMessage("");
    	$this->setErrorMessage("");

    	$requestedArray = json_decode(trim($this->selectedPIIds->Value));
    	$this->selectedPIIds->Value="";
    	$warehouse = $this->getWarehouse();
    	if(count($requestedArray)==0)
    		$this->setErrorMessage("Nothing to be removed from!");
    	else
    	{
    		$logStocktakeId = Factory::service("StockTake")->getStocktakeId($warehouse);
    		foreach($requestedArray as $stocktakeId => $qty)
    		{
    			$stockTake = Factory::service("StockTake")->get($stocktakeId);
    			if(!$stockTake instanceof Stocktake)
    				continue;
    			$stockTake->setActive(false);
    			Factory::service("StockTake")->save($stockTake);
    		}
    		$this->setInfoMessage("Successfully removed selected record(s) from list.");
    	}

    	$this->loadLists($warehouse);
    }

    /**
     * Found Parts From Scanning
     *
     * @param unknown_type $rawData
     */
    public function foundPartsFromScanning($rawData)
    {
    	$requestedArray = json_decode($rawData,true);
    	$warehouse = $this->getWarehouse();
    	$logStocktakeId = Factory::service("StockTake")->getStocktakeId($warehouse);
    	foreach($requestedArray as $key => $row)
    	{
    		if($row["quantity"]>0){
    			if(!$row["partInstanceId"])
    			{
    				$partInstances = Factory::service("PartInstance")->searchPartInstancesByFilters($row["partTypeId"],null,$warehouse->getId(),null,$row["partInstanceStatusId"]);
    				if(count($partInstances)>0)
    				{
    					$partInstance = $partInstances[0];
    					if($partInstance instanceOf PartInstance)
    					{
    						$row["partInstanceId"] = $partInstance->getId();
    					}
    				}
    			}
    			Factory::service("StockTake")->recordPartForST($logStocktakeId,$row["partInstanceId"],$row["quantity"],$row["partTypeId"],Factory::service("PartInstanceStatus")->get($row["partInstanceStatusId"]));
    		}
    	}
    	$this->setInfoMessage("Successfully recorded data from selected record(s).");
    	$this->loadLists($warehouse);
    }

    /**
     * Continue StockTake
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function continueStockTake($sender,$param)
    {
    	$this->setInfoMessage("");
    	$this->setErrorMessage("");

    	$warehouse = $this->getWarehouse();

    	$this->confirmBtnPanel->Visible = false;
    	$this->listPanel->Visible = true;
    	$this->scanPanel->Visible = true;

    	$this->loadLists($warehouse);

    	Factory::service("StockTake")->startStockTake($warehouse);
    }

    /**
     * Cancel StockTake
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function cancelStockTake($sender,$param)
    {
    	$this->setInfoMessage("");
    	$this->setErrorMessage("");

    	$this->stockTakePanel->Visible=false;
    	$warehouse = $this->getWarehouse();
    	try{Factory::service("StockTake")->cancelStockTake($warehouse);}
    	catch(Exception $ex){}

    	$this->StocktakeInfo->Text="<h3>Stocktake cancelled for '".Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,'/')."'</h3><br /><a href='/stock/warehouse/".$warehouse->getId()."'>Back to the stock page</a> or <a href='/stocktake/stock/".$warehouse->getId()."'>Start a new stocktake</a>";
    }

    /**
     * Finish StockTake
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function finishStockTake($sender,$param)
    {
    	$this->setInfoMessage("");
    	$this->setErrorMessage("");

    	$this->stockTakePanel->Visible=false;
    	$warehouse = $this->getWarehouse();

    	try
    	{
    		$time = time();
    		Factory::service("StockTake")->finishStockTake($warehouse);
    		$totalTime = time() - $time;

    		$html = "<h3>Stocktake finished for '".Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,'/')."'</h3>";
    		$html .="<br />Details Updated:<br />";
    		$html .="Last Stocktake: ".$warehouse->getAlias(WarehouseAliasType::$aliasTypeId_lastStocktakeDate)."<br />";
       		$html .="Next Stocktake Due: ".$warehouse->getAlias(WarehouseAliasType::$aliasTypeId_nextStocktakeDate)."<br />";
    		$html .="<br /><a href='/stock/warehouse/".$warehouse->getId()."/'>Back to the stock page</a> or <a href='/stocktake/stock/".$warehouse->getId()."'>Start a new stocktake</a>";

    		if($totalTime > 29){
    			$str  = "Stocktake finished for '".Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,'/')."'\n";
    			$str .="\nDetails Updated:\n";
    			$str .="Last Stocktake: ".$warehouse->getAlias(WarehouseAliasType::$aliasTypeId_lastStocktakeDate)."\n";
    			$str .="Next Stocktake Due: ".$warehouse->getAlias(WarehouseAliasType::$aliasTypeId_nextStocktakeDate)."\n";
    			$str .="\nTime to run = $totalTime Seconds";
    			Factory::service("Message")->email($this->email, "Stocktake completed for ".Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,'/'), $str);
       		}
    		$this->StocktakeInfo->Text=$html;
    	}
    	catch(Exception $ex)
    	{
    		$this->StocktakeInfo->Text = "<h3 style='color:red;'>Error Occurred: ".$ex->getMessage()."</h3><br />Please take a screen shot of this, and email technology for this, thanks! <br />Or: <a href='/stock/warehouse/".$warehouse->getId()."/'>Back to the stock page</a>";
    	}
    }

    /**
     * StockTake User Info
     *
     * @return unknown
     */
    public function stockTakeGetUserInfo()
    {
    	return '{"Email" : "'.$this->email.'", "Phone" : "'.Config::get("SupportHandling", "Phone").'"}';
    }

    /**
     * Caluclates if there will be any stock lost for the confirmation message
     * @param array $sender
     * @param array $param
     * @throws Exception
     */
    public function getLostPartsInfoBeforeSubmit($sender, $param)
    {
    	$errors = $result = array();
    	$shelfQty = $scannedQty = 0;

    	try
    	{
	    	if (!isset($param->CallbackParameter->whId))
	    		throw new Exception("Invalid warehouse");

	    	$wh = Factory::service("Warehouse")->getWarehouse($param->CallbackParameter->whId);
	    	if (!$wh instanceof Warehouse)
	    		throw new Exception("Invalid warehouse [ID=" . $param->CallbackParameter->whId . "]");

	    	//find quantity on shelf
	    	$sql = "SELECT SUM(quantity) FROM partinstance WHERE active=1 AND warehouseid=" . $wh->getId();
	    	$res = Dao::getSingleResultNative($sql);
	    	if ($res !== false)
	    		$shelfQty = $res[0];

	    	//find quantity on scanned
	    	$sql = "SELECT SUM(quantity) FROM stocktake WHERE active=1 AND warehouseid=" . $wh->getId();
	    	$res = Dao::getSingleResultNative($sql);
	    	if ($res !== false)
	    		$scannedQty = $res[0];

	    	$result[] = $shelfQty - $scannedQty;
    	}
    	catch (Exception $e)
    	{
    		$errors[] = $e->getMessage();
    	}
    	$this->responseLabel->Text = htmlentities(Core::getJSONResponse($result, $errors));
    }
}
?>