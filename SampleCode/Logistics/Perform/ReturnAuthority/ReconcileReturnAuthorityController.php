<?php
/**
 * @package	Hydra-Web
 * @subpackage Controller
 * @version	1.0
 * @author  Jeremy Todter<jtodter@bytecraft.com.au>
 */
class ReconcileReturnAuthorityController extends CRUDPage
{
	/**
	 * @var Warehouse
	 */
	private $repairer = null;

	/**
	 * @var totalRows
	 */
	public $totalRows;

	private $_clientRmaRequirement = null;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = "reconcilera";
		$this->roleLocks = "pages_all,feature_allow_Reconcile_ReturnAuthority";
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
    public function onLoad($param)
    {
        parent::onLoad($param);

        $this->jsLbl->Text = '';

        $this->repairerWhId->Value = '';
        //we've got the repairer id in the url
		if ($this->Request['repairerWhId'] !== null)
		{
			$wh = Factory::service("Warehouse")->get($this->Request['repairerWhId']);
			if (!$wh instanceof Warehouse || $wh->getWarehouseCategory()->getId() != WarehouseCategory::ID_3RD_PARTY_REPAIRER)
			{
				$this->setErrorMessage("Invalid Repairer.");
				return;
			}
			$this->repairer = $wh;
			$this->repairerWhId->Value = $wh->getId();

			$this->searchRepairerBtn->Text = 'Change Repairer';
			$this->currentRepairerLbl->Text = $this->repairer->getName();

			$this->clientRaPanel->Style= '';
			$this->loadData();
		}

        if (!$this->IsPostBack || $param == "reload")
        {
        	//logic function to return part instance status list
        	$this->partStatusList->DataSource = DropDownLogic::getPartInstanceStatusList(array(), array(), Factory::service("PartInstanceStatus")->get(PartInstanceStatus::ID_PART_INSTANCE_STATUS_GOOD));;
        	$this->partStatusList->DataBind();
        }

        if ($this->activeMessage->Value != '')
        {
        	$this->setActiveInfoMessage($this->activeMessage->Value);
        }

        $this->activeMessage->Value = '';
        $this->barcodeFromRepairer->Focus();
     }

	/**
	 * Populate Edit Panel
	 *
	 */
	public function populateEditPanel()
	{
		if ($this->ra instanceof ReturnAuthority)
		{
			$this->byteRaNo->Text = $this->ra->getRaNo();
			$this->clientRaNo->Text = $this->ra->getClientRaNo();
			if ($this->clientRaNo->Text != '' && !UserAccountService::isSystemAdmin()) //block the editing of this field if it has a value and not sys admin
			{
				$this->clientRaNo->Enabled = false;
			}
			$this->comments->Text = $this->ra->getComments();
		}
	}

	/**
	 * Save Edit
	 *
	 */
	public function saveEdit()
	{
		$save = false;
		if (trim($this->clientRaNo->Text) !== $this->ra->getClientRaNo())
		{
			$this->ra->setClientRaNo(trim($this->clientRaNo->Text));
			$save = true;
		}

		if (trim($this->comments->Text) != $this->ra->getComments())
		{
			$this->ra->setComments(trim($this->comments->Text));
			$save = true;
		}

		$msg = 'Nothing to update...';
		if ($save)
		{
			Dao::save($this->ra);
			$msg = 'RA updated...';
		}
		$this->setInfoMessage($msg);
	}

	/**
	 * Search Repairer
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function searchRepairer($sender,$param)
	{
		$repairerId = $this->thirdPartyList->getSelectedValue();
		if ($repairerId === '')
		{
			$this->setErrorMessage("Please select a Repairer.");
			return;
		}

		$wh = Factory::service("Warehouse")->get($repairerId);
		if (!$wh instanceof Warehouse)
		{
			$this->setErrorMessage("Invalid Repairer.");
			return;
		}
		//load the page for the current repairer
		$this->response->Redirect('/reconcileRA/' . $wh->getId());
	}

	/**
	 * Search
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function search($sender,$param)
	{
		$msgs = array();

		$repairerId = $this->repairerWhId->Value;
		if ($repairerId === '')
		{
			$msgs[] = "Please select a Repairer.";
		}

		if ($this->searchClientRaNo->Text == '')
		{
			$msgs[] = "Invalid Client RA No.";
		}

		if (!empty($msgs))
		{
			$this->setErrorMessage(implode('<br />', $msgs));
			return;
		}

		$this->reconcileStartTime->Value = new HydraDate();
		$this->clientRaNo->Value = $this->searchClientRaNo->Text;
		$this->searchClientRaNo->Text = '';
		$this->loadData();
	}

	/**
	 * Show DispatchNote PartsPanel
	 *
	 */
	private function loadData()
	{
		$this->setInfoMessage('');
		$this->setErrorMessage('');

		$this->clientRaReqLbl->Text = '';
		$repairerClientRmaRequirement = ReturnAuthorityLogic::getClientRmaRequirementForRepairer($this->repairer);
		if ($repairerClientRmaRequirement !== null)
			$this->clientRaReqLbl->Text = "<br />* This repairer enforces a Client RMA No - '$repairerClientRmaRequirement'";

		if ($this->clientRaNo->Value == '')
			return;

		$repairerId = $this->repairerWhId->Value;
		if ($repairerId === '')
		{
			$this->setErrorMessage("Please select a Repairer.");
			return;
		}

		$this->repairer = Factory::service("Warehouse")->get($repairerId);
		if (!$this->repairer instanceof Warehouse)
		{
			$this->setErrorMessage("Invalid Repairer.");
			return;
		}

		$this->currentClientRALbl->Text = $this->clientRaNo->Value;

		//check here if there are any at other repairers, we need to show an error message
		$diffRepairerCount = $sameRepairerCount = 0;
		$raps = Factory::service("ReturnAuthorityPart")->findByCriteria("clientrmanumber='{$this->clientRaNo->Value}'");
		foreach ($raps as $rap)
		{
			$dest = $rap->getReturnAuthority()->getTransitNote()->getDestination();
			if ($repairerId != $dest->getId())
			{
				$diffRepairerCount++;
			}
			else
			{
				$sameRepairerCount++;
			}
		}

		//we only want to show this message if there are none at the current repairer and some at another repairer.
		if ($diffRepairerCount > 0 && $sameRepairerCount == 0 && $this->confirmWarning->Value == '')
		{
			$msg = 'There are ' . $diffRepairerCount . ' part(s) with Client RMA number [' . $this->clientRaNo->Value . '] that exist for a different repairer [' . $dest->getName() . ']';
			$html =  '<div style="margin:10px; padding:5px; width:90%; text-align:center;"><b><font color="red">WARNING!</font></b> : '.$msg.'</div>';
			$html .= '<div style="margin:10px; padding:5px; border-radius:5px; -moz-border-radius:5px; border: 2px solid #000000;">';

			$inputButtons = 'If the RMA is for the same repairer but a different state click <b>`Cancel`</b>  and investigate withe the repairer and the state logistics department, all other 3rd party repairers click <b>`Continue`.</b> &nbsp;&nbsp;&nbsp';
			$inputButtons .= '<br/><br/><br/>';
			$inputButtons .= '<input type="button" value="Continue" id="continueBtn" onclick="confirmWarning();"/> &nbsp;&nbsp;&nbsp';
			$inputButtons .= '<input type="button" value="Cancel" id="cancelBtn" onclick="cancelWarning();" /> &nbsp;&nbsp;&nbsp;';
			$inputButtons .= '<input type="hidden" value="'.$this->clientRaNo->Value.'" id="rmaNo"  /> &nbsp;&nbsp;&nbsp;';

			$html .=  '<div style="margin:10px; padding:5px; width:90%; text-align:center;">'.$inputButtons.'</div>';

			$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array("mb.show('" . $html . "', '', '', {overlayClose: false, transitions: false, width: 500})"));
			return;
		}

		//they've searched on client RA, so either show or hide the tree
		if ($this->clientRaNo->Value !== '' && $this->currNodeId->Value === '')
		{
			$this->onLoadAction->Value = 'treeSelection';
		}
		else if ($this->clientRaNo->Value !== '' && $this->currNodeId->Value !== '')
		{
			$this->onLoadAction->Value = 'locationSelected';
		}
		$this->scanPartsFromRepairerPanel->GroupingText = "Reconcile parts from <b>" . $this->repairer->getName() . "</b>";
		//find the parts
		$parts = Factory::service("ReturnAuthorityPart")->searchOutstandingRAPs($this->repairer, $this->clientRaNo->Value, $this->reconcileStartTime->Value);
		if(count($parts) == 0 && $this->confirmRMAWarning->Value == '')
		{
			$html =  '<div style="margin:10px; padding:5px; width:90%; text-align:center;"><b><font color="red">WARNING!</font></b> : Client RMA number [<b>'.$this->clientRaNo->Value.'</b>] exist for a different repairer <b>'.$this->repairer->getName().'</b></div>';
			$html .= '<div style="margin:10px; padding:5px; border-radius:5px; -moz-border-radius:5px; border: 2px solid #000000;">';

			$inputButtons = 'If the RMA is for the same repairer but a different state click <b>`Cancel`</b>  and investigate with the repairer and the state logistics department, all other 3rd party repairers click <b>`Continue`.</b> &nbsp;&nbsp;&nbsp';
			$inputButtons .= '<br/><br/><br/>';
			$inputButtons .= '<input type="button" value="Continue" id="continueBtn" onclick="confirmRMAWarning();"/> &nbsp;&nbsp;&nbsp';
			$inputButtons .= '<input type="button" value="Cancel" id="cancelBtn" onclick="cancelRMAWarning();" /> &nbsp;&nbsp;&nbsp;';
			$inputButtons .= '<input type="hidden" value="'.$this->clientRaNo->Value.'" id="rmaNo"  /> &nbsp;&nbsp;&nbsp;';
			$html .=  '<div style="margin:10px; padding:5px; width:90%; text-align:center;">'.$inputButtons.'</div>';

			$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array("mb.show('" . $html . "', '', '', {overlayClose: false, transitions: false, width: 500})"));
			return;
		}
		if(count($parts) == 0)
		{
			if ($this->onLoadAction->Value == 'treeSelection')
				$this->onLoadAction->Value = '';

			$this->partsInRepairerPanel->Style = "display:none;";
			$this->scanPartsFromRepairerPanel->Style = "display:none;";
		}

		$reconciledParts = array();
		if ($this->Page->reconciledParts->Value != '')
		{
			$reconciledParts = unserialize($this->Page->reconciledParts->Value);
		}

		$allReceived = true;
		for ($i=0; $i<count($parts); $i++)
		{
			$ptId = $parts[$i]['ptId'];
			//check if we've received any of these so we can show the quantity
			if (array_key_exists($ptId, $reconciledParts))
			{
				$parts[$i]['qtyReconciled'] = $reconciledParts[$ptId];
				$parts[$i]['visible'] = true;
			}
			else
			{
				$parts[$i]['qtyReconciled'] = 0;
				$parts[$i]['visible'] = true;
			}

			$parts[$i]['qtyLeft'] = $parts[$i]['qtySent'] - $parts[$i]['qtyReturned'];
			if ($parts[$i]['qtySent'] > $parts[$i]['qtyReturned'])
				$allReceived = false;
		}

		if ($allReceived)
		{
// 			$this->partsInRepairerPanel->Style = "display:none;";
			$this->scanPartsFromRepairerPanel->Style = "display:none;";
			$this->setActiveErrorMessage('All Parts from ' . $this->repairer->getName() . ' have been received...');

			$this->onLoadAction->Value = 'hideScanPanel';
		}
		else if (empty($reconciledParts))
		{
			$this->dataListPanel->Visible = true;
			$this->partsInRepairerPanel->Style = "";
			$this->scanPartsFromRepairerPanel->Style = "";
			$this->setActiveErrorMessage('');
		}
		else
		{
			$this->partsInRepairerPanel->Style = "";
			$this->scanPartsFromRepairerPanel->Style = "";
			$this->setActiveErrorMessage('');
		}

		if (count($parts) > 0)
		{
			$this->partsInRepairer->visible = true;
	  		$this->partsInRepairer->DataSource = $parts;
	  		$this->partsInRepairer->DataBind();

	  		$infoMsg = $errMsg = array();
	  		if ($allReceived == false)
	  		{
		  		$piIds = array();
		  		foreach ($parts as $part)
		  			$piIds[] = $part['piId'];

		  		try
		  		{
		  			$tasks = Factory::service("ReturnAuthority")->getFieldTaskInfoForReceivedPartInstancesFromFieldTaskProperty($piIds, 'dispatch', true);
		  			if (count($tasks) > 1)
		  			{
		  				throw new Exception("This Return Authority is linked to multiple tasks (" . implode(", ", $tasks) . ") please contact your supervisor to continue...");
		  			}
		  		}
		  		catch (Exception $e)
		  		{
		  			$this->setErrorMessage($e->getMessage());
		  			$this->scanPartsFromRepairerPanel->Style = "display:none;";
		  			return;
		  		}

		  		$ftpInfo = Factory::service("ReturnAuthority")->getRequireMandatoryClientRmaNumber($tasks);		//see if we have any FTPs matching the part instance id
		  		if ($ftpInfo !== false)
		  		{
		  			if (array_key_exists(FieldTaskPropertyService::CLIENT_RA_NUMBER_NAME, $ftpInfo))
		  			{
		  				$infoMsg[] = "<br />This Return Authority is linked to task (" . $ftpInfo['ftId'] . " : " . $ftpInfo['status'] . "), the status will be progressed upon successful reconciliation.";
			  			if ($ftpInfo[FieldTaskPropertyService::CLIENT_RA_NUMBER_NAME] != $this->ra->getClientRaNo())
			  			{
			  				$errMsg[] = "This Return Authority is linked to task (" . $ftpInfo['ftId'] . " : " . $ftpInfo['status'] . "), though the Field Task Property (" . FieldTaskPropertyService::CLIENT_RA_NUMBER_NAME . ") has a different value (" . $ftpInfo[FieldTaskPropertyService::CLIENT_RA_NUMBER_NAME] . ") to the current client RA No (" . $this->ra->getClientRaNo() . "), please contact your supervisor to continue...";
			  			}
		  			}
		  			else
		  			{
			  			$errMsg[] = "There is a part instance on this Return Authority that is linked to task (" . $ftpInfo['ftId'] . " : " . $ftpInfo['status'] . "), though the Field Task Property (" . FieldTaskPropertyService::CLIENT_RA_NUMBER_NAME . ") is empty, please contact your supervisor to continue...";
		  			}
		  		}
		  		if (!empty($errMsg))
		  		{
		  			$this->setErrorMessage(implode('<br />', $errMsg));
					$this->scanPartsFromRepairerPanel->Style = "display:none;";
		  			return;
		  		}
	  		}
	  		$this->setInfoMessage(implode('<br />', $infoMsg));
		}
	}

	public function registerPartClicked($sender, $param)
	{
		$errMsg = array();

		$ptId = $this->partType->getSelectedValue();
		if ($ptId == null || !($pt = Factory::service("PartType")->get($ptId)) instanceof PartType)
		{
			$errMsg[] = 'Please select a valid Part Type.';
		}

		$whId = $this->currNodeId->Value;
		if ($whId == null || !($wh = Factory::service("Warehouse")->get($whId)) instanceof Warehouse)
		{
			$errMsg[] = 'Please select a valid location from the tree.';
		}

		if ($wh->getParts_allow() == false)
			$errMsg[] = "'{$wh->getName()}' does not allow parts.";

		if (!empty($errMsg))
		{
			$this->setActiveErrorMessage(implode('<br />', $errMsg));
			return;
		}

		//check here if there are any of the part type to reconcile
		if ($this->_checkPartTypeAtRepairer($ptId, $this->repairer) == false)
		{
			$this->setActiveErrorMessage('There are no part instances with Part Type [' . $pt->getAlias() . ' : ' . $pt->getName() . '] at the repairer.');
			return;
		}

		$this->setActiveWarningMessage("Please re-scan the part after registration.");
		$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('mb.hide()', "window.open('/registerparts/{$pt->getId()}/{$wh->getId()}/')"));
	}

	/**
	 * Get Recieve Tooltip
	 *
	 * @param unknown_type $recBy
	 * @param unknown_type $dateRec
	 * @return unknown
	 */
	public function getReceivedTooltip($recBy, $dateRec)
	{
		$recBy = explode(',', $recBy);
		$dateRec = explode(',', $dateRec);

		$str = " Received by:";
		for ($i=0; $i < count($recBy); $i++)
		{
			$date = new HydraDate($dateRec[$i]);
			$date->setTimeZone('Australia/Melbourne');
			$str .= "<br />&nbsp;&nbsp;$recBy at $date (Australia/Melbourne)";
		}
		return $str;
	}

	/**
	 * Get Recievign Repair code
	 *
	 * @param unknown_type $rapId
	 * @return unknown
	 */
	public function getReceivingRepairCode($rapId)
	{
		$receivingRepairCodes = array();
		if ($this->Page->receivingRepairCodes->Value != '')
    		$receivingRepairCodes = unserialize($this->Page->receivingRepairCodes->Value);
		$receivingRepairCode =$receivingRepairCodes[$rapId];

		return $receivingRepairCode;
	}

	/**
	 * Check whether there are parts of $ptId at the repairer (or under it)
	 * @param int $ptId
	 * @param Warehouse $repairerWh
	 * @return boolean
	 */
	private function _checkPartTypeAtRepairer($ptId, $repairerWh)
	{
		//make sure there are parts at the repairer before register
		$raps = Factory::service('ReturnAuthorityPart')->getRAPsForPT($this->repairer, Factory::service("PartType")->get($ptId));
		return (count($raps) == 0 ? false : true);
	}

	/**
	 * Display the modal box for selecting the correct part type
	 * @param string $barcode
	 * @param array unknown $partInstances
	 */
	private function _getMultiplePiModalBox($barcode, $partInstances)
	{
		$html =  '<div style="margin:10px; padding:5px; width:90%; text-align:center;">There are multiple results for <b>' . $barcode . '</b>, please select the correct one.</div>';
		$html .= '<div style="margin:10px; padding:5px; border-radius:5px; -moz-border-radius:5px; border: 2px solid #000000;">';
		$html .=    '<table border="0" width="100%" class="DataList">';
		$html .=        '<thead>';
		$html .=        '<tr style="background-color:black; color:white;">';
		$html .= 			'<td width="20px">&nbsp;</td>';
		$html .= 			'<td>Part Type</td>';
		$html .= 			'<td>Location</td>';
		$html .= 			'<td>Serial</td>';
		$html .= 			'<td>Client Asset No</td>';
		$html .= 			'<td>Manuf. Serial No</td>';
		$html .=        '</tr>';
		$html .=        '</thead>';

		$row = 1;
		foreach ($partInstances as $pi)
		{
			$pt = $pi->getPartType();
			$serial = $pi->getAlias();

			$style = 'style="background:#C0C0C0;"';
			if ($row%2 == 0)
				$style = 'style="background:#D8D8D8;"';

			$html .=        '<tr ' . $style . '>';
			$html .=            '<td width="20px" style="text-align:center;"><input type="radio" name="multRadBtn" value="' . $pi->getId() . '" onclick="multipleRadioClicked(this)" /></td>';
			$html .=            '<td><b>' . $pt->getAlias() . '</b><br />' . $pt->getName() . '</td>';
			$html .=            '<td>' . Factory::service("Warehouse")->getWarehouseBreadcrumbs($pi->getWarehouse(), false, '/') . '</td>';
			$html .=            '<td>' . $serial . '</td>';
			$html .=            '<td>' . $pi->getAlias(PartInstanceAliasType::ID_CLIENT_ASSET_NUMBER) . '</td>';
			$html .=            '<td>' . $pi->getAlias(PartInstanceAliasType::ID_MANUFACTUR_SERIAL) . '</td>';
			$html .=        '</tr>';

			$row++;
		}
		$html .=    '</table>';
		$html .= '</div>';
		$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array("mb.show('" . $html . "', '', '', {overlayClose: false, transitions: false, width: 880})"));
	}

	/**
	 * Display the modal box for confirming the mandatory aliases
	 * @param string $barcode
	 * @param array unknown $partInstances
	 */
	private function _getConfirmAliasesModalBox($pi, $aliases)
	{
		$reconfirm =$this->reconfirm->Value;
		$pt = $pi->getPartType();
		$serialNo = $pi->getAlias();

		$html =  '<div style="margin:10px; padding:5px; width:90%; text-align:center;">Please confirm the aliases for <b>' . $pi->getAlias() . '</b><br /><br /><b>' . $pt->getAlias() . '</b> - ' . $pt->getName() . '</div>';
		$html .= '<div style="margin:10px; padding:5px; border-radius:5px; -moz-border-radius:5px; border: 2px solid ##0099FF;">';
		$html .=    '<table border="0" width="100%" class="DataList" id="ConfirmAlias">';
		$html .=        '<thead>';
		$html .=        '<tr style="background-color:black; color:white;">';
		$html .= 			'<td>&nbsp;</td>';
		$html .=        '</tr>';
		$html .=        '</thead>';
		$html .=        '<tr>';
		$html .=            '<td>';
		$html .=            	'<table width="100%">';

		foreach ($aliases as $lu)
		{
			$partInstanceAliasType = $lu->getPartInstanceAliasType();
			if ($partInstanceAliasType instanceOf PartInstanceAliasType)
			{
				$html .=            	'<tr>';
				$html .=            		'<td width="150px"><b>' . $partInstanceAliasType->getName() . ': </b></td>';
				$html .=            		'<td>' . $pi->getAlias($partInstanceAliasType->getId()) . '</td>';
				$html .=            	'</tr>';
			}
		}
		$html .=            	'</table>';
		$html .=            '</td>';
		$html .=        '</tr>';

		$html .=    '</table>';
		$html .= '</div>';
		if($reconfirm == 'reconfirm')
		{
			$inputButtons = '<input type="button" value="Confirm After Edit" id="confirmAliasBtn" onclick="confirmAliasesClicked('.$pi->getId(). ');" /> &nbsp;&nbsp;&nbsp';
			$inputButtons .= '<input type="button" value="Re Confirm" id="reconfirmAliasBtn" onclick="reconfirmAliasesClicked();" style="display:none;" /> &nbsp;&nbsp;&nbsp;';
			$inputButtons .= '<input type="button" id="editAliasBtn" value="Edit Again" onclick="editAliasesClicked(this,' . $pi->getId() . ');" /> &nbsp;&nbsp;&nbsp;';
		}
		else
		{
			$inputButtons = '<input type="button" value="Confirm" id="confirmAliasBtn" onclick="confirmAliasesClicked('.$pi->getId(). ');" /> &nbsp;&nbsp;&nbsp';
			$inputButtons .= '<input type="button" value="Re Confirm" id="reconfirmAliasBtn" onclick="reconfirmAliasesClicked();" style="display:none;" /> &nbsp;&nbsp;&nbsp;';
			$inputButtons .= '<input type="button" id="editAliasBtn" value="Edit Part Instance" onclick="editAliasesClicked(this,' . $pi->getId() . ');" /> &nbsp;&nbsp;&nbsp;';
			$inputButtons .= '<input type="button" id="cancelBtn" value="Cancel" onclick="cancelConfirmClicked();" />';
		}

			$inputButtons .= '<input type="hidden" id="serialNo" value='.$serialNo.' />';
		$html .=  '<div style="margin:10px; padding:5px; width:90%; text-align:center;">'.$inputButtons.'</div>';
		$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array("mb.show('" . $html . "', '', '', {overlayClose: false, transitions: false, width: 600})"));
	}

    /**
     * Search PartInstance From RA
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function searchPartInstanceFromRepairer($sender, $param)
	{
		$this->setErrorMessage('');
		$this->repairer = Factory::service("Warehouse")->get($this->repairerWhId->Value);

    	$barcode = trim($this->barcodeFromRepairer->Text);
    	if ($barcode == '' && $this->bypassPartSearchUsingPiId->Value == '')
    	{
    		$this->setActiveErrorMessage("Nothing to search. Please scan/enter a barcode.");
    		$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('mb.hide()'));
    		return;
    	}

    	$nonSerialisedScan = false;
    	$foundParts = array();

    	//they've selected a part from the multiple modal box
    	if ($this->bypassPartSearchUsingPiId->Value !== '')
    	{
    		$foundParts[] = Factory::service("PartInstance")->get($this->bypassPartSearchUsingPiId->Value);
    		$barcode = $foundParts[0]->getAlias();
    	}
    	else if (Factory::service("Barcode")->checkBarcodeType($barcode, BarcodeService::BARCODE_REGEX_CHK_PART_TYPE))
    	{
    		$nonSerialisedScan = true;
    	}
    	else
    	{
    		//see if we can find a part instance
    		$query = new DaoReportQuery("PartInstance");
    		$query->column('pi.id');
    		$piatArray = array(PartInstanceAliasType::ID_SERIAL_NO, PartInstanceAliasType::ID_MANUFACTUR_SERIAL, PartInstanceAliasType::ID_CLIENT_ASSET_NUMBER);
    		$join = "INNER JOIN partinstancealias pia ON pia.partinstanceid=pi.id AND pia.partinstancealiastypeid IN (" . implode(',', $piatArray) . ") AND pia.active=1 AND pia.alias='$barcode'";
	    	$query->setAdditionalJoin($join);
	    	$res = $query->execute(false);
	    	if (!empty($res))
	    	{
	    		$piIds = array();
		    	foreach ($res as $r)
		    		$piIds[] = $r[0];

		    	$foundParts = Factory::service("PartInstance")->findByCriteria('pi.id IN (' . implode(',', $piIds) . ')');
	    	}
    	}

    	$this->selectPtPanel->Visible = false;

    	$qtyReturned = 1;
    	if ($nonSerialisedScan)
    	{
    		//we need to return and get the quantity returned
    		if ($this->Page->qtyReturned->Value == "0")
    		{
    			$this->Page->promptForQty->Value = 1;
    			return;
    		}
    		$qtyReturned = $this->Page->qtyReturned->Value;
    		$this->Page->qtyReturned->Value = "0";
    		$this->barcodeFromRepairer->Text = '';
    	}
    	else
    	{
    		$this->barcodeFromRepairer->Text = '';
    		if (empty($foundParts)) //we didn't find any
    		{
    			$jsArray = array('mb.hide()');
    			$this->setActiveErrorMessage("$barcode | No part instance found! Please select Part Type above to register the part.");
    			$this->selectPtPanel->Visible = true;
    			$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent($jsArray);
    			return;
    		}
    		else if (count($foundParts) > 1) //we've got multiple to choose from
    		{
    			$this->_getMultiplePiModalBox($barcode, $foundParts);
    			$this->setActiveErrorMessage('There are multiple results, please select one.');
    			return;
    		}
    		else //we've got one part
    		{
    			$byteSerialScanned = Factory::service("Barcode")->checkBarcodeType($barcode, BarcodeService::BARCODE_REGEX_CHK_PART_INSTANCE);

	    		//check here for missing aliases etc, or if it is a BT, or not a BCS/BS scanned
	    		$errMsg = '';
	    		if (Factory::service('PartInstance')->getCompulsoryPartInstanceAliasType($foundParts[0], $errMsg, false, true) === false ||
	    			($this->bypassPartSearchUsingPiId->Value == '' && $byteSerialScanned == false) ||
	    			preg_match("/^(BT)(\d{8})(\w)$/", trim($barcode)) != 0)
	    		{
	    			if ($byteSerialScanned)
	    				$this->setActiveErrorMessage($errMsg . '<br />Please re-scan the part after editing.');
	    			else
	    				$this->setActiveErrorMessage($errMsg . '<br />You must scan a Bytecraft serial number to proceed.');

	    			$this->bypassPartSearchUsingPiId->Value = '';
	    			$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('mb.hide()', "window.open('/reregisterparts/true/{$foundParts[0]->getId()}/')"));
	    			$this->barcodeFromRepairer->Text = '';
	    			return;
	    		}
    		}
    	}

    	//we seem to be good to go, so see if we need to show the confirm dialog
    	if ($this->bypassPartSearchUsingPiId->Value === ''  && !empty($foundParts))
    	{
    		$pt = $foundParts[0]->getPartType();

    		$perPart = $this->repairer->getAlias(WarehouseAliasType::ALIASTYPEID_CLIENT_RA_REQUIREMENT);
    		$clientRaNo = null;
    		if(strtoupper($perPart) === 'PER PART')
    			$clientRaNo = $this->clientRaNo->Value;
    		//make sure there are parts at the repairer before show confim dialog
    		$raps = Factory::service('ReturnAuthorityPart')->getRAPsForPT($this->repairer, $pt, $clientRaNo, $qtyReturned, 1);
			if (!empty($raps))
			{
	    		$mandatoryAliases = Factory::service('Lu_PartType_PartInstanceAliasPattern')->getMandatoryUniquePatternsForPtPiat($pt, null, true, null);
	    		if (!empty($mandatoryAliases))
	    		{
	    			$this->setActiveWarningMessage($errMsg . '<br />Please confirm the mandatory aliases.');
	    			$this->_getConfirmAliasesModalBox($foundParts[0], $mandatoryAliases);
	    			return;
	    		}
			}
			else
			{
				$this->setErrorMessage('Cannot find any unreconciled RA for this part  `'.$pt->__toString()." `");
				return false;
			}
    	}

    	try
    	{
    		$this->saveReceivedParts($barcode, $qtyReturned);
    	}
    	catch (Exception $e)
    	{
    		$this->setActiveErrorMessage($e->getMessage());
    	}

    	//reset the bypass value
    	$this->bypassPartSearchUsingPiId->Value = '';
	}

	/**
	 * Save Recieve Parts
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function saveReceivedParts($barcode, $qty)
	{
		$targetWh = Factory::service("Warehouse")->get($this->currNodeId->Value);
		if (!$targetWh instanceof Warehouse)
		{
			$this->setActiveErrorMessage("Invalid target warehouse.");
			return;
		}
		else if ($targetWh->getParts_allow() == false)
		{
			$this->setActiveErrorMessage("Target warehouse [" . Factory::service("Warehouse")->getWarehouseBreadcrumbs($targetWh, false, '/') . "] does not allow parts.");
			return;
		}

		try
		{
			$pis = Factory::service("PartInstanceStatus")->get($this->partStatusList->getSelectedValue());
			$raps = Factory::service("ReturnAuthority")->reconcileRAP($barcode, $this->repairer, $this->clientRaNo->Value, $targetWh, $pis, $qty);
			if (empty($raps))
				throw new Exception('There were no parts reconciled.');

			$pi = $raps[0]->getPartInstance();

			//this part is to remember what was reconciled for display on the screen
			$reconciledParts = array();
			if ($this->Page->reconciledParts->Value != '')
				$reconciledParts = unserialize($this->Page->reconciledParts->Value);

			$ptId = $pi->getPartType()->getId();
			if (!array_key_exists($ptId, $reconciledParts))
				$reconciledParts[$ptId] = 0;

			$reconciledParts[$ptId] += $qty;
			$this->reconciledParts->Value = serialize($reconciledParts);

			$msg = $barcode . ' was successfully reconciled.';
			$this->setActiveInfoMessage($msg);
		}
		catch (Exception $e)
		{
			$this->setActiveErrorMessage($e->getMessage());
			return;
		}
    	$this->onLoad('reload');
	}

	/**
	 * Set Active Info Message
	 *
	 * @param unknown_type $msg
	 */
	public function setActiveInfoMessage($msg)
	{
		$this->activeMessage->Value = $msg;
		$this->Page->infoMsg->Text = $msg;
		$this->Page->errMsg->Text = '';
		$this->Page->warningMsg->Text = '';
	}

	/**
	 * Set Active Error Message
	 *
	 * @param unknown_type $msg
	 */
	public function setActiveErrorMessage($msg)
	{
		$this->Page->infoMsg->Text = '';
		$this->Page->warningMsg->Text = '';
		$this->Page->errMsg->Text = $msg;
	}

	/**
	 * Set Active Warning Message
	 *
	 * @param unknown_type $msg
	 */
	public function setActiveWarningMessage($msg)
	{
		$this->Page->infoMsg->Text = '';
		$this->Page->errMsg->Text = '';
		$this->Page->warningMsg->Text = $msg;
	}
}
?>