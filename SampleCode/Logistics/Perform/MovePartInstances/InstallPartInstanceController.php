<?php
ini_set("memory_limit", "256M");
/**
 * Install Part Instance Controller Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class InstallPartInstanceController extends CRUDPage
{
	/**
	 * @var unknown_type
	 */
	public $totalSubParts;

	/**
	 * @var Warehouse
	 */
	private $defaultWarehouse=null;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_installPartIntoParent";
		$this->totalSubParts = 0;
	}

	/**
	 * On Pre Init
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
		$str = explode('/',$_SERVER['REQUEST_URI']);
		if ($str[1] == 'staging')
		{
			$this->getPage()->setMasterClass("Application.layouts.StagingLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_installPartIntoParent,menu_staging";
			$this->menuContext = 'staging/installpartinstance';
		}
		else
		{
			$this->getPage()->setMasterClass("Application.layouts.LogisticsLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_installPartIntoParent";
			$this->menuContext = 'installpartinstance';
		}

	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
       	parent::onLoad($param);

       	$this->jsLbl->Text = "";
       	if(!$this->Page->IsPostBack)
       	{
       		$this->ToInstance->Text = " ";
       	}

       	$this->Page->setFocus($this->ToInstance->getClientID());
       	$this->setDefaultWarehouse(Core::getUser());
    }


    /**
     * Search To Parts
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
	public function searchToParts($sender,$param)
    {
        $toStr = trim($this->ToInstance->Text);
        $this->ViewPartsWithin->Text = '';
    	$candidates = Factory::service("PartInstance")->searchPartInstancesBySerialNo($toStr);
    	if(empty($candidates))
    		$candidates = Factory::service("PartInstance")->searchPartInstancesByPartInstanceAlias($toStr,array(PartInstanceAliasType::ID_BOX_LABEL));

		if (empty($candidates))
		{
			$this->onError("No parts found for '$toStr'!");
		}
		$toStr = strtoupper($toStr);
		if(strstr($toStr,"BCS")===false && strstr($toStr,"BS")===false && strstr($toStr,"BX")===false && strstr($toStr,"BOX")===false)
			$this->onError("Must be a serialised part.");

		if (count($candidates) == 1 && $candidates[0]->getQuantity()==1)
		{
			$tmpKitType = $candidates[0]->getKitType();
			if (!empty($tmpKitType))
				$this->onError("Part '$toStr' is a Kit. Please select a Non-Kittype part to insert parts into.");

			$return = $this->addPartInstanceToList($candidates[0],1,$this->toBeInstalledPartInstances_parent);
			// now set the kit type!
    		if($return!==false)
    		{
    			$this->onSuccess("Part successfully assigned.");
				$this->ToInstance->Text="";
    			$this->SearchPartsPanel->SearchInstance->focus();
    		}
		}
		else
			$this->onError("Multiple parts found.");
    }

    /**
     * Get Total parts to be Installed
     *
     * @return unknown
     */
    public function getTotalPartsToBeInstalled()
    {
    	$array = unserialize($this->toBeInstalledPartInstances->Value);
    	return count($array);
    }

    public function addPart()
    {
    	$partInstanceId = $this->SearchPartsPanel->partInstanceId->Value;
    	$qty = $this->SearchPartsPanel->partInstanceQuantity->Value;


    	$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
   		if($partInstance instanceof PartInstance)
   		{
   			$return = $this->addPartInstanceToList($partInstance, $qty, $this->toBeInstalledPartInstances);
   			if($return !== false)
    		{
    			$this->onSuccess("Part successfully added.");
    			$this->SearchPartsPanel->SearchInstance->Text = "";
    			$this->SearchPartsPanel->SearchInstance->focus();
    		}
   		}

    	$this->loadPartsList();
    }




    /**
     * Remove From Installing List
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function removeFromInstallingList($sender, $param)
    {
    	$partInstanceId = $param->CommandParameter;
   		$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
   		if($partInstance instanceof PartInstance)
   		{
   			$this->removePartInstanceFromList($partInstance,$this->toBeInstalledPartInstances);
   		}
    }

    /**
     * Remove To Installing List
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function removeToInstallingList($sender, $param)
    {
    	$partInstanceId = $param->CommandParameter;
   		$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
   		if($partInstance instanceof PartInstance)
   		{
   			$this->removePartInstanceFromList($partInstance,$this->toBeInstalledPartInstances_parent);
   		}
    }

    public function finishProcessing()
    {
    	$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessingInstallParts('" . $this->InstallPartsMessage->Value . "','" . $this->InstallPartsError->Value . "');</script>";
    }


	/**
	 * Finish Processing Install Parts
	 */

    public function finishProcessingInstallParts()
    {
   		//get the part to install part to!!!!
    	$toPart = null;
    	$toParts =  unserialize($this->toBeInstalledPartInstances_parent->Value);
    	if($toParts && is_array($toParts))
    	{
	   		foreach($toParts as $partInstanceId =>$qty)
	    	{
	    		$toPart = Factory::service("PartInstance")->getPartInstance($partInstanceId);
	    	}
    	}

    	if($toPart instanceOf PartInstance)
    	{
	    	if($this->InstallPartsError->Value)
	    	{
	    		$this->onError("<br />" . $this->InstallPartsError->Value);

	    		if($this->InstallPartsMessage->Value)
	    		{
	    			$this->showPartsWithin($toPart);
	    		}
	    		$this->loadPartsList();
	    	}

	    	if($this->InstallPartsMessage->Value)
	    	{

	    		$this->onSuccess("<br />" . $this->InstallPartsMessage->Value);

	    		if(!$this->InstallPartsError->Value)
	    		{
	    			$this->resetPageFields();
	    		}

	    		$serialNumbers = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($toPart->getId(),1);
				if(count($serialNumbers)>0)
				{
					$this->ViewPartsWithin->Text = $serialNumbers[0]->getAlias();
				}
				$this->showPartsWithin($toPart);
	    	}
    	}
    	else
    	{
    		$this->loadPartsList();
    		$this->onError("An unexpected error has occured! Can't find into part.");
    	}
    	$this->InstallPartsMessage->Value = "";
	    $this->InstallPartsError->Value = "";
    }


    /**
     * Process Install Parts
     */

    public function  processInstallParts()
    {

    	//get the part to install part to!!!!
    	$toPart = null;
    	$toParts =  unserialize($this->toBeInstalledPartInstances_parent->Value);

    	//get all parts that are to be installed to $toPart
    	$fromParts =  unserialize($this->toBeInstalledPartInstances->Value);

   		foreach($toParts as $partInstanceId => $qty)
    	{
    		$toPart = Factory::service("PartInstance")->getPartInstance($partInstanceId);
    	}

    	try {
	    	//install all parts

	    	foreach($fromParts as $partInstanceId => $qty)
	    	{
	    		$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
	    		if(!$partInstance instanceof PartInstance)
	    			continue;

	    		$error = $this->performInstall($partInstance, $qty, $toPart);

	    		//if there is no error when performInstall
				if($error === true)
				{
					unset($fromParts[$partInstanceId]);

					if(count($fromParts)==0)
						$this->toBeInstalledPartInstances->Value = "";
					else
						$this->toBeInstalledPartInstances->Value = serialize($fromParts);

					$this->InstallPartsMessage->Value .= "Successfully installed " . $partInstance->getAlias() . "<br>";

					if(count($fromParts) == 0)
			    	{
			    		$this->InstallPartsMessage->Value .= "<br>Selected parts succesfully installed.";
			    		return array('stop' => true);
			    	}
				}
				else
				{
					$this->InstallPartsError->Value = "Part: " . $partInstance->getAlias() . " Error: " . addslashes($error) . "<br>";
					return array('stop' => true);
				}
				return array('stop' => false);
	    	}
    	}
    	catch(Exception $e)
    	{
    		$this->InstallPartsError->Value = "Part: " . $partInstance->getAlias() . " Error: " .  addslashes($e->getMessage());
    		return array('stop' => true);
    	}

    	if(count($fromParts) == 0)
    	{
    		$this->InstallPartsMessage->Value .= "<br>Selected parts succesfully installed.";
    		return array('stop' => true);
    	}
    	return array('stop' => false);
    }


    /**
     * Attempt to install
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
	public function attemptToInstall($sender,$param)
    {

    	$error = "";

    	//get the part to install part to!!!!
    	$toPart = null;
    	$toParts =  unserialize($this->toBeInstalledPartInstances_parent->Value);

    	if($toParts==false ||count($toParts)<1)
    	{
    		$error .= "Enter part to install to.<br>";
    	}

    	if(count($toParts)>1)
    	{
    		$error .= "Enter one part only, to install to.<br>";
    	}

    	foreach($toParts as $partInstanceId =>$qty)
    	{
    		$toPart = Factory::service("PartInstance")->getPartInstance($partInstanceId);
    	}
    	if(!$toPart instanceof PartInstance)
    	{
    		$error .= "Invalid part ($toPart) to install to! Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!<br>";
    	}
    	//get all parts that are to be installed to $toPart
    	$fromParts =  unserialize($this->toBeInstalledPartInstances->Value);

    	if($fromParts==false ||count($fromParts)<1)
    	{
    		$error .= "Enter part/s to install.<br>";
    	}

    	if($error)
    	{
    		$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessingInstallParts('','" . $error . "');</script>";
    	}
    	else
    	{
    		$this->jsLbl->Text = "<script type=\"text/javascript\">installParts();</script>";
    	}
    }

    /**
     * Show Within Part
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
    public function showWithinPart($sender,$param)
    {
    	if($sender->getId()!="ViewPartsBtn")
    	{
    		$this->ViewPartsWithin->Text = $sender->Text;
	    	$this->Page->setFocus($this->ToInstance->getClientID());
    	}
    	$this->DataList->Visible = false;

    	$serialNo = trim($this->ViewPartsWithin->Text);
    	$partInstances =Factory::service("PartInstance")->searchPartInstancesBySerialNo($serialNo);
    	if(count($partInstances)<1)
    		$this->onError("Part $serialNo not found.");
    	if(count($partInstances)>1)
    		$this->onError("Multiple parts found for $serialNo! Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

    	$tmpKitType="";
    	if(count($partInstances)>0)
   	 		$tmpKitType = $partInstances[0]->getKitType();
		if (!empty($tmpKitType))
			$this->onError("Part '$serialNo' is a Kit. Please select a Non-Kittype part.");

    	$this->DataList->Visible = true;
    	if(count($partInstances)>0)
	    	$this->showPartsWithin($partInstances[0]);
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
    	$partInstanceId = $this->DataList->DataKeys[$sender->Parent->ItemIndex];
    	$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
    	if(!$partInstance instanceof PartInstance)
    		$this->onError("Invalid part. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."! ");

    	if($param != null)
			$itemIndex = $sender->Parent->ItemIndex;
		else
			$itemIndex = 0;

		$this->DataList->SelectedItemIndex = -1;
		$this->DataList->EditItemIndex = $itemIndex;
    	$this->loadPartsList();

    	$this->DataList->getEditItem()->removingPartInstance_SerialNo->Text=$partInstance;
    	$this->DataList->getEditItem()->removingPartInstance_Id->Value=$partInstanceId;
    	$this->DataList->getEditItem()->targetWarehouseId->Value="";
    }

    /**
     * Remove part
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
    public function removePart($sender, $param)
    {
    	$targetWarehouseIds = explode("/",$this->DataList->getEditItem()->targetWarehouseId->Value);
    	$targetWarehouseId = end($targetWarehouseIds);
    	$targetWarehouse = Factory::service("Warehouse")->getWarehouse($targetWarehouseId);
    	if(!$targetWarehouse instanceof Warehouse)
    		$this->onError("Invalid Warehouse. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

    	$movingPartInstanceId= $this->DataList->getEditItem()->removingPartInstance_Id->Value;
    	$movingPartInstance = Factory::service("PartInstance")->getPartInstance($movingPartInstanceId);
    	if(!$movingPartInstance instanceof PartInstance)
    		$this->onError("Invalid part. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

    	$parentPartInstance = $movingPartInstance->getParent();
    	$movedPartInstance = Factory::service("PartInstance")->movePartInstanceToWarehouse($movingPartInstance, $movingPartInstance->getQuantity(), $targetWarehouse,false,null,'Removed Part from');
    	Factory::service("PartInstance")->installPartInstance(null,$movedPartInstance);

    	$this->DataList->EditItemIndex = -1;
    	$this->onSuccess("Part successfully removed.");
    	$this->showPartsWithin($parentPartInstance);
    }

    /**
     * Cancel Remove Part
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
    public function cancelRemovePart($sender, $param)
    {
    	$movingPartInstanceId= $this->DataList->getEditItem()->removingPartInstance_Id->Value;
    	$movingPartInstance = Factory::service("PartInstance")->getPartInstance($movingPartInstanceId);
    	if(!$movingPartInstance instanceof PartInstance)
    		$this->onError("Invalid part. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

    	$parentPartInstance = $movingPartInstance->getParent();
    	$this->DataList->EditItemIndex = -1;
    	$this->showPartsWithin($parentPartInstance);
    }

    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    //// Private Functions /////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////
    ////////////////////////////////////////////////////////////////////////////////////////////////////////////////

    /**
     * Add Part Instance To List
     *
     * @param PartInstance $partInstance
     * @param unknown_type $qty
     * @param unknown_type $hiddenField
     * @return unknown
     */
    private function addPartInstanceToList(PartInstance $partInstance,$qty=1,&$hiddenField)
    {
    	$array = unserialize($hiddenField->Value);
    	$facilityRequest = $partInstance->getFacilityRequest();
    	if($facilityRequest instanceof FacilityRequest)
    	{
    		$this->onError("There is a Facility Request against part ".$partInstance);
    	}
    	if($partInstance->getWarehouse() instanceof Warehouse)
    	{
	    	$transiteNotes = Factory::service("TransitNote")->findByCriteria("tn.transitNoteLocationId=?",array($partInstance->getWarehouse()->getId()));
	    	if(count($transiteNotes)>0)
	    		$this->onError("Part($partInstance) is on transit note ".$transiteNotes[0].". Transit note must be reconciled before installation.");
    	}

    	$parent  =$partInstance->getDirectParent();
    	if( $parent instanceof PartInstance)
    		$this->onError("Part is within another part ($parent). It cannot be installed independently.");

    	$array[$partInstance->getId()] = $qty;
    	$hiddenField->Value = serialize($array);
    	$this->loadPartsList();
    }

    /**
     * Remove Part Instance From List
     *
     * @param PartInstance $partInstance
     * @param unknown_type $hiddenField
     */
    private function removePartInstanceFromList(PartInstance $partInstance,&$hiddenField)
    {
    	$array = unserialize($hiddenField->Value);
    	if(isset($array[$partInstance->getId()]))
    		unset($array[$partInstance->getId()]);
    	if(count($array)==0)
    		$hiddenField->Value="";
    	else
    		$hiddenField->Value = serialize($array);
    	$this->loadPartsList();
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
    	$this->setInfoMessageSound($infoMessage);
    	$this->loadPartsList();
    	return false;
    }


    /**
     * On Error
     *
     * @param unknown_type $errorMessage
     * @return unknown
     */
	protected function onError($errorMessage)
	{
		$this->setErrorMessage('');
		$this->setErrorMessageSound($errorMessage);
		$this->loadPartsList();
		return false;
	}

	/**
	 * Load PartsList
	 *
	 * @param unknown_type $loadDataList
	 */
	private function loadPartsList($loadDataList=true)
   	{
   		$this->loadDataList($this->FromCandidateDataList,$this->toBeInstalledPartInstances,$this->FromCandidateDataList_label,"Parts to be installed:");
   		$this->loadDataList($this->ToCandidateDataList,$this->toBeInstalledPartInstances_parent,$this->ToCandidateDataList_label,"Installing to part:");
   		if($loadDataList==true)
   			$this->loadDataList($this->DataList,$this->withinParts,$this->showWithinPartLabel,"Parts within Part '".$this->ViewPartsWithin->Text."':");

   		if($this->toBeInstalledPartInstances->Value=="")
   		{
   			$this->installButton->Text="No parts to install.";
   			$this->installButton->Enabled=false;
   		}
   		else if($this->toBeInstalledPartInstances_parent->Value=="")
   		{
   			$this->installButton->Text="No parts to install into.";
   			$this->installButton->Enabled=false;
   		}
   		else
   		{
   			$this->installButton->Text="Install &raquo;";
   			$this->installButton->Enabled=true;
   		}
   	}

   	/**
   	 * Load DataList
   	 *
   	 * @param TDataList $tDataList
   	 * @param unknown_type $hiddenField
   	 * @param unknown_type $dataListLabel
   	 * @param unknown_type $dataListLabelText
   	 */
   	private function loadDataList(TDataList &$tDataList,$hiddenField,&$dataListLabel,$dataListLabelText)
   	{
   		$partInstanceIds = unserialize($hiddenField->Value);
   		$partInstances = array();
   		if($partInstanceIds!=false)
   		{
   			foreach($partInstanceIds as $partInstanceId=>$qty)
   			{
   				$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
   				if($partInstance instanceof PartInstance )
   				{
	   				$partTypeId = $partInstance->getPartType()->getId();
   					$barcodes = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($partInstanceId,1);

	   				$barcode = count($barcodes)==0 ? "-" : $barcodes[0]->getAlias();

	   				$partcodes = Factory::service("PartType")->searchPartTypeAliaseByPartTypeAliasTypeForPartType($partTypeId,1);
	   				$partcode = count($partcodes)==0 ? "" : $partcodes[0]->getAlias();

   					$partInstances[]= array(
	   									"partInstance"=>$partInstance,
	   									"qty"=>$qty,
	   									"barcode"=>$barcode,
	   									"partcode"=>$partcode,
	   									"id"=>$partInstance->getId()
	   									);
   				}
   			}
   		}

   		if(count($partInstances)>0)
   			$dataListLabel->Text = $dataListLabelText;
   		else
   			$dataListLabel->Text = "";

   		$partInstances = array_reverse($partInstances);
   		$tDataList->DataSource = $partInstances;
   		$tDataList->DataBind();
   	}


    /**
     * Perform Install
     *
     * @param PartInstance $partInstance
     * @param unknown_type $qty
     * @param PartInstance $installToInstance
     * @param KitType $kitType
     * @return unknown
     */
    private function performInstall(PartInstance $partInstance, $qty, PartInstance $installToInstance, KitType $kitType = null)
    {
    	try {
	    	Factory::service("PartInstance")->performInstall($partInstance, $qty, $installToInstance, $kitType);
	    	return true;
    	}
    	catch (Exception $e)
    	{
    		return $e->getMessage();
    	}
    }

    /**
     * Reset Page Fields
     *
     * @param unknown_type $showWithinPartPanel
     */
	private function resetPageFields($showWithinPartPanel=true)
	{
       	$this->SearchPartsPanel->SearchInstance->Text = "";
       	$this->ToInstance->Text = "";
       	$this->FromCandidateDataList_label->Text = "";
       	$this->ToCandidateDataList_label->Text = "";
       	$this->FromCandidateDataList->DataSource = array();
       	$this->FromCandidateDataList->DataBind();
       	$this->toBeInstalledPartInstances->Value="";
       	$this->ToCandidateDataList->DataSource = array();
       	$this->ToCandidateDataList->DataBind();
       	$this->toBeInstalledPartInstances_parent->Value="";
       	$this->SearchPartsPanel->hideResultPanel();

       	if($showWithinPartPanel!==true)
       	{
       		$this->showWithinPartLabel->Text="";
	       	$this->DataList->DataSource = array();
	       	$this->DataList->DataBind();
       	}

	}

	/**
	 * Show Parts Within
	 *
	 * @param PartInstance $partInstance
	 */
	private function showPartsWithin(PartInstance $partInstance)
	{
		$array = array();

		$piIds = Factory::service("PartInstance")->getPartInstanceChildrenIds($partInstance);
		if(count($piIds)==0)
		{
			$this->totalSubParts=0;
			$this->withinParts->Value="";

			$this->outer->Display="None";
			$this->inner->Display="None";

			$partInstanceActive = $partInstance->getActive();

			if($partInstanceActive == 1)
			{
				$parentId=$partInstance->getId();
				$this->parentPartId->Value = $parentId;
				if($partInstance->getKitType())
				{
					// prompt deactivate of parent when no child only for kits
	 				if($partInstance->getKitType()->getId() > 1)
	 				{
						$this->outer->Display="Hidden";
						$this->inner->Display="Dynamic";
	 				}
				}
				else
					$this->onError("There are no parts within : ".$partInstance);
			}
			else
				$this->onError("Part '$partInstance' has been deactivated");
		}
		else
		{
			$daoQuery = new DaoReportQuery("PartInstance");
			$daoQuery->column("id");
			$daoQuery->column("quantity");
			$daoQuery->where("pi.id in(".implode(",",$piIds).")");
			$results = $daoQuery->execute(false);
			foreach($results as $r)
			{$array[$r[0]] = $r[1];}
			$this->totalSubParts = count($array);
			$this->withinParts->Value=serialize($array);
		}

		$this->loadPartsList();
	}

	/**
	 * Get Default Warehouse
	 *
	 * @param UserAccount $userAccount
	 */
	private function setDefaultWarehouse(UserAccount $userAccount)
	{
		$defaultWarehouseId = Factory::service("UserPreference")->getOption($userAccount,'defaultWarehouse');
		$defaultWarehouse = Factory::service("Warehouse")->getWarehouse($defaultWarehouseId);
        //if there is no default warehouse set up for current user.
		if(!$defaultWarehouse instanceof Warehouse)
		{
			$person = $userAccount->getPerson();
			$this->onError($person->getFullName().' requires a default warehouse.');
			return;
		}
		$this->defaultWarehouse = $defaultWarehouse;
		$this->setErrorMessage('');
	}



	/**
	 * Deactivate Part
	 *
	 */
	public function deactivatePart()
	{
		$parentPartId = $this->parentPartId->Value;
		$serialNo = trim($this->ViewPartsWithin->Text);
		$partInstance = Factory::service("PartInstance")->getPartInstance($parentPartId);

		$partInstance->setActive(0);
		$partInstance->setKitType(null);
		Factory::service("PartInstance")->save($partInstance);

		$this->onSuccess("Part with serialno '$serialNo'' has been successfully deactivated.");
		$this->outer->Display="None";
		$this->inner->Display="None";
	}

	/**
	 * Kepp Part Active
	 *
	 */
	public function keepPartActive()
	{
		$serialNo = trim($this->ViewPartsWithin->Text);
		$this->setErrorMessage("Part with serialno '$serialNo' active - contains no parts");
		$this->outer->Display="None";
		$this->inner->Display="None";
	}
}
?>
