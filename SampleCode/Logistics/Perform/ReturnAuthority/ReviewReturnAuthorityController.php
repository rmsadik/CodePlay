<?php
/**
 * @package	Hydra-Web
 * @subpackage Controller
 * @version	1.0
 * @author  Jeremy Todter<jtodter@bytecraft.com.au>
 */
class ReviewReturnAuthorityController extends CRUDPage
{
	/**
	 * @var totalRows
	 */
	public $totalRows;

	/**
	 * @var orderStatusArray
	 */
	public $orderStatusArray = array(PurchaseOrder::STATUS_ORDERED, PurchaseOrder::STATUS_SHIPPED, PurchaseOrder::STATUS_TRANSIT, PurchaseOrder::STATUS_RECEIVED, PurchaseOrder::STATUS_CANCELLED);

	/**
	 * @var secsToRemainEditable
	 */
	private $secsToRemainEditable;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = "purchaseorder";
		$this->roleLocks = "pages_all,feature_allow_Raise_ReturnAuthority";
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
       	if(!$this->IsPostBack || $param == "reload")
        {
	    	$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
			if ($this->Request['tnId'] != null)
			{
				$this->SearchAddPanel->Style = "display:none;";
				$this->dataLoad();
			}
        }
     }

    /**
     * On Save
     *
     */
    public function onSave()
    {
    	$this->resetReload();
    }

    /**
     * On Cancel
     *
     */
    public function onCancel()
    {
    	$this->resetReload();
    }

    /**
     * Reset Reload
     *
     */
    public function resetReload()
    {
		$this->SearchText->Text = '';
    	$this->AddPanel->Visible = false;
		$this->DataList->EditItemIndex = -1;
		$this->dataLoad();
    }

    /**
     * Toggle Active
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	protected function toggleActive($sender,$param)
	{
		$id = $this->DataList->DataKeys[$sender->Parent->ItemIndex];
		$po = Factory::service("PurchaseOrder")->get($id);
		if (!$po instanceof PurchaseOrder)
		{
			$this->setErrorMessage('Invalid PO ID: ' . $id);
		}
		else
		{
			if (!$sender->Parent->Active->Checked && Factory::service("PurchaseOrder")->hasPartsRegisteredAgainstPo($po->getId())) //cannot deactivate, has parts against it
			{
				$this->setErrorMessage('Cannot deactivate PO as it has parts registered against it, please contact BSuiteHelp');
			}
			else if (!UserAccountService::isSystemAdmin() && $po->getCreatedByImport()==1) //cannot deactivate, not sysadmin and was imported
			{
				$this->setErrorMessage('Cannot deactivate PO as it was imported from TechnologyOne, please contact BSuiteHelp');
			}
			else
			{
				$po->setActive((int)$sender->Parent->Active->Checked);
				Dao::save($po);
				$this->setInfoMessage('PO successfully ' . ($sender->Parent->Active->Checked==false?'DE':'') . 'ACTIVATED');
			}
		}
		$this->dataLoad();
	}

	/**
	 * Get Data
	 *
	 * @param unknown_type $searchString
	 * @return unknown
	 */
    private function getData($searchString = '')
    {
    	if ($this->Request['tnId'] != null)
    	{
    		$tn = Factory::service("TransitNote")->get($this->Request['tnId']);
    	}
    	else
    	{
	    	$this->Page->ListRaLabel->Text = '';
	    	if ($searchString == '')
	    	{
	    		$this->setErrorMessage('<br />Please enter a TN/DN No to search...');
	    		return;
	    	}

			$tn = Factory::service("TransitNote")->getTransitNoteByTransitNoteNo($searchString);
			if (count($tn) > 0)
				$tn = $tn[0];
    	}

		if ($tn instanceof TransitNote)
		{
			$results = Factory::service("ReturnAuthority")->getGenerateRaData($tn);
			$this->totalRows = count($results);
			if ($this->totalRows > 0)
			{
				$raNo = str_replace(array('TN','DN'), 'RA', $tn->getTransitNoteNo());
				$this->Page->ListRaLabel->Text = "List of Parts to go on Return Authority '$raNo'";
				$this->toggleButtons(true);
			}
			$piIds = array();

			//sort out the field tasks
			for ($i=0; $i<count($results); $i++)
			{
				$ftIds = explode(',', $results[$i]['ftId']);
				$results[$i]['ftId'] = $ftIds[0];

				//if non-serialised
				if ($results[$i]['serialised'] == "0")
				{
					$results[$i]['serialNo'] = $results[$i]['bp'];
					$results[$i]['ftId'] = 'N/A';
					$results[$i]['assetNo'] = 'N/A';
					$results[$i]['manufNo'] = 'N/A';
					$results[$i]['problemDesc'] = 'N/A';
				}
				$piIds[] = $results[$i]['id'];
			}

			try
			{
				$taskInfo = Factory::service("ReturnAuthority")->getFieldTaskInfoForReceivedPartInstancesFromFieldTaskProperty($piIds, 'awaiting_dispatch', true);
				if (count($taskInfo) > 1)
				{
					throw new Exception("This Return Authority has part instances linked to multiple tasks (" . implode(", ", array_keys($taskInfo)) . ") please contact your supervisor to continue...");
				}
			}
			catch (Exception $e)
			{
				$this->toggleButtons(false);
				$this->closeButtonTop->Style = '';
				$this->clientRaLabel->Visible = false;
				$this->clientRmaNumber->Visible = false;
				$this->ListRaLabel->Visible = false;
				$this->setErrorMessage($e->getMessage());
				return;
			}

			//check here if we are to mandate a client RMA number
			$ftpInfo = Factory::service("ReturnAuthority")->getRequireMandatoryClientRmaNumber($taskInfo);		//see if we have any FTPs matching the part instance id
			if ($ftpInfo !== false)
			{
				$this->Page->fieldTaskIdToProgress->Value = $ftpInfo['ftId'];			//the task id
				if (array_key_exists(FieldTaskPropertyService::CLIENT_RA_NUMBER_NAME, $ftpInfo))
				{
					$clientRmaNumber = $ftpInfo[FieldTaskPropertyService::CLIENT_RA_NUMBER_NAME];
					$this->Page->clientRmaNumber->Text = $clientRmaNumber;			//populate the value from the FTP table

					if ($clientRmaNumber != '')
					{
						$this->clientRmaNumberHidden->Value = $clientRmaNumber;					//remember the initial value for prompt later
						$this->Page->clientRmaNumber->Style = 'background-color:#b5ffa6;';		//set to green
					}
				}
			}
			else
			{
				$this->Page->clientRmaNumber->Style = 'background-color:#ffffff;';			//set to white
			}
			return $results;
		}
		$this->toggleButtons(false);
    	$this->setErrorMessage('<br />There are no matching results...');
    	return array();
    }

    /**
     * Toggle Button
     *
     * @param unknown_type $visible
     */
    public function toggleButtons($visible)
    {
    	$style = '';
    	if (!$visible)
    		$style = 'display:none';

    	$this->reviewedButtonBtm->Style = $style;
    	$this->closeButtonBtm->Style = $style;
    	$this->reloadButtonBtm->Style = $style;

    	$this->reviewedButtonTop->Style = $style;
    	$this->closeButtonTop->Style = $style;
    	$this->reloadButtonTop->Style = $style;
    }

    /**
     * Reload Data
     *
     */
    public function reloadData()
    {
		$this->dataLoad();
    }

    /**
     * Error on Element
     *
     * @param unknown_type $elId
     * @param unknown_type $msg
     * @param unknown_type $colour
     */
    public function errorOnElement($elId, $msg, $colour = 'ffa6a6')
    {
    	$this->jsLbl->Text = '<script type="text/javascript">alert("' . $msg . '"); $("' . $elId . '").style.backgroundColor="#' . $colour . '"; $("' . $elId . '").focus();</script>';
    }

    /**
     * Set Background Color
     *
     * @param unknown_type $elId
     * @param unknown_type $colour
     */
    public function setBgColor($elId, $colour = 'b5ffa6')
    {
    	$this->jsLbl->Text = '<script type="text/javascript">$("' . $elId . '").style.backgroundColor="#' . $colour . '";</script>';
    }

    /**
     * Reviewed RA
     *
     */
    public function reviewedRa()
    {
    	$tnId = $this->Request['tnId'];
    	$sessionArr[$tnId] = array();
    	$sessionArr[$tnId]['clientRmaNo'] = $this->Page->clientRmaNumber->Text;

    	if ($this->Page->fieldTaskIdToProgress->Value != "")
    	{
    		if ($this->Page->clientRmaNumber->Text == '')
    		{
    			$this->errorOnElement($this->Page->clientRmaNumber->getClientId(), 'You must enter a Client RMA Number...');
    			return;
    		}
    		else $this->setBgColor($this->Page->clientRmaNumber->getClientId());

    		//check the task is valid
    		$ft = Factory::service("FieldTask")->getFieldTask($this->Page->fieldTaskIdToProgress->Value);
    		if (!$ft instanceof FieldTask)
    		{
    			$this->errorOnElement($this->Page->clientRmaNumber->getClientId(), 'Client RMA Number matches an invalid Field Task, unable to continue...');
    			return;
    		}

    		//get the task statuses that we are to progress to
    		$dhc = Factory::service("DontHardcode")->getParamValueForParamName('FieldTaskPartReturn', true);

    		$wtId = $ft->getWorkType()->getId();
    		foreach ($dhc['workTypes'] as $wt)
    		{
    			if ($wt['id'] == $wtId)
    			{
    				foreach ($wt["statusInfo"] as $key => $status)
    				{
    					if ($key == 'dispatch')
    					{
    						$sessionArr[$tnId]['ftIdToProgress'] = array($ft->getId() => $status);
    					}
    				}
    			}
    		}

    		if (!array_key_exists('ftIdToProgress', $sessionArr[$tnId]))
    		{
    			$this->errorOnElement($this->Page->clientRmaNumber->getClientId(), 'Cannot find valid status to progress the task (' . $ft->getId() . ') to on dispatch, unable to continue...');
    			return;
    		}
    	}

    	$sessionArr[$tnId]['parts'] = array();

    	//go through each part
    	foreach ($this->DataList->items  as $item)
    	{
    		$data = array();

    		if ($item->ftIdTxt->Visible)
    		{
    			if ($item->ftIdTxt->Text == '')
    			{
    				$this->errorOnElement($item->ftIdTxt->getClientId(), 'You must enter a Field Task No...');
    				return;
    			}
    			else $this->setBgColor($item->ftIdTxt->getClientId());

    			$data['ftId'] = $item->ftIdTxt->Text;
    		}

    		if ($item->techComments->Visible)
    		{
    			if ($item->techComments->Text == '')
    			{
    				$this->errorOnElement($item->techComments->getClientId(), 'You must enter a Tech Fault...');
    				return;
    			}
    			else $this->setBgColor($item->techComments->getClientId());

    			$data['techComments'] = $item->techComments->Text;
    		}
    		$sessionArr[$tnId]['parts'][$item->piId->Value] = $data;
    	}
    	Session::setReturnAuthorityReviewedStatus($sessionArr);

    	$this->jsLbl->Text = '<script type="text/javascript">opener.location.reload();window.close();</script>';
    }

    /**
     * Cancel RA
     *
     */
    public function cancelRa()
    {
    	Session::setReturnAuthorityReviewedStatus(null);
    	$this->jsLbl->Text = '<script type="text/javascript">opener.location.reload();window.close();</script>';
    }

    /**
     * Get MultiLine Text Box Height
     *
     * @param unknown_type $text
     * @return unknown
     */
    public function getMultilineTextBoxHeight($text)
    {
    	$len = strlen($text);
    	$rows = ceil($len / 100);
    	return ($rows == 0) ? 1 : $rows;
    }

    /**
     * Get Tech Fault Description
     *
     * @param unknown_type $fd
     * @return unknown
     */
    public function getTechFaultDescription($fd)
    {
    	$fd = substr($fd, 0, 50);
    	if (strpos($fd, 'ok') !== false || strpos($fd, 'OK') !== false) return 'OK';
    	else if (strpos($fd, 'cc') !== false || strpos($fd, 'CC') !== false) return 'CC';
    	else if (strpos($fd, 'cd') !== false || strpos($fd, 'CD') !== false) return 'CD';
    	else if (strpos($fd, 'dp') !== false || strpos($fd, 'DP') !== false) return 'DP';
    	else if (strpos($fd, 'fo') !== false || strpos($fd, 'FO') !== false) return 'FO';
    	else if (strpos($fd, 'ls') !== false || strpos($fd, 'LS') !== false) return 'LS';
    	else if (strpos($fd, 'mb') !== false || strpos($fd, 'MB') !== false) return 'MB';
    	else if (strpos($fd, 'mc') !== false || strpos($fd, 'MC') !== false) return 'MC';
    	else if (strpos($fd, 'ms') !== false || strpos($fd, 'MS') !== false) return 'MS';
    	else return 'OK';
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
    	$results = $this->getData(Dao::_cleanSql(trim($searchString)));
    	return $results;
    }

    /**
     * Get All of Entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject=null, $pageNumber=null, $pageSize=null)
    {
    	$results = $this->getData();
    	return $results;
    }
}
?>