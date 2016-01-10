<?php
/**
 *  PartType AliasType Controller
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class PartTypeController extends CRUDPage
{
	/**
	 * @var UserAccount
	 */
	private $userAccount;

	/**
	 * @var aliasList
	 */
	protected $aliasList = array();
	/**
	 * @var bomList
	 */
	protected $bomList = array();

	/**
	 * @var totalRows
	 */
	protected $totalRows = 0;

	/**
	 * @var canDeactivatPartType
	 */
	public $canDeactivatPartType;

	/**
	 * @var instanceQty
	 */
	private $instanceQty = null;

	/**
	 * Constrctor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->allowOutPutToExcel = true;
		$this->menuContext = 'parttypes';
		$this->roleLocks = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'roleLocks',true);
		$this->userAccount = new UserAccount();
		$this->canDeactivatPartType=false;

		if(UserAccountService::isSystemAdmin())
			$this->canDeactivatPartType=true;

		else if (Session::checkRoleFeatures(array('feature_PartType_deactivate')))
			$this->canDeactivatPartType=true;
	}

	/**
	 * Results Per Page Changed
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
     * Get PartType AliasType List
     *
     * @param unknown_type $mode
     * @return unknown
     */
	private function getPartTypeAliasTypeList($mode = "")
    {
    	$data = array();
    	$query = "";
    	$excludedIdArray = array();
    	if($mode == "")
    	{
    		$excludedIdArray[] = 3;
    		if(UserAccountService::isSystemAdmin() == false)
			{
				$excludedIdArray[] = 2;
			}
    	}

    	if(count($excludedIdArray) > 0)
    	{
    		$query = "select id, name, valueType from parttypealiastype ptat where ptat.active = 1 and ptat.lu_entityaccessoptionId NOT IN (".implode(",", $excludedIdArray).")";
    	}
    	else
    	{
    		$query = "select id, name, valueType from parttypealiastype ptat where ptat.active = 1";
    	}

    	$result = Dao::getResultsNative($query);
    	$data[] = array("id" => '', "name" => '', "valueType" => '');

    	foreach($result as $row)
    	{
    		$data[] = array("id" => $row[0], "name" => $row[1], "valueType" => $row[2]);
    	}
    	return $data;
    }

    /**
     * OnLoad
     *
     * @param unknown_type $param
     */
	public function onLoad($param)
    {
       	$this->userAccount = $this->User->getUserAccount();
    	parent::onLoad($param);
        $this->setInfoMessage("");
        $this->setErrorMessage("");
		$this->PaginationPanel->Visible = false;
		$this->Page->jsLbl->setText("");
	    if(!$this->IsPostBack || $param == "reload")
        {
        	if (!empty($this->Request['id']))
        	{
        		$q = new DaoReportQuery("PartType");
        		$q->setAdditionalJoin("INNER JOIN parttypealias pta ON pta.partTypeId=pt.id AND pta.partTypeAliasTypeId=1 AND pta.active=1 ");
        		$q->where("pt.id=".$this->Request['id']);
        		$q->column("pta.alias");
        		$res = $q->execute(false);
        		if (!empty($res))
        		{
        			$this->SearchPartCode->Text = $res[0][0];
					$searchArray = $this->buildSearchArray();
			    	$searchQueryString = serialize($searchArray);
					$this->SearchString->Value = $searchQueryString;
        		}
        	}

			$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
			$this->dataLoad();

			$this->bindPartTypeAliasTypeList();

			$this->SearchPartCode->Focus();

			$this->bindKitTypeList();

        }

    }

    private function bindPartTypeAliasTypeList()
    {
    	$q = new DaoReportQuery("PartTypeAliasType");
		$q->where("ptat.active=1");
		$q->column("ptat.id");
		$q->column("ptat.name");
		$q->orderBy("ptat.name");
		$res = $q->execute(false);
		if(Core::getRole() == "System Admin")
		{
			foreach($res as $k => $pta)
				$res[$k][1] = $res[$k][1]." - (".$res[$k][0].")";
		}
		$res = array_merge(array(array(0, " ")), $res);
		$this->bindDropDownList($this->SearchAliasType, $res);
    }


    private function bindKitTypeList()
    {
    	// Selecting Kit Type
		$sql = "SELECT id, name FROM kittype";
		$results = Dao::getResultsNative($sql);

		$result = array();
		$result[] = array("id" => "All", "Name" => "All");

		foreach ($results as $row)
		{
			$result[]= array("id" => $row[0], "Name" => $row[1]);
		}

		$this->bindDropDownList($this->selectkitlist, $result);

    }


    /**
     * DataLoad
     *
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     */
	public function dataLoad($pageNumber=null,$pageSize=null)
    {
    	parent::dataLoad();
    	//$this->setErrorMessage('');
    	if(count($this->DataList->DataSource) > 0)
    		$this->DataListPanel->Visible=true;
    	else
    		$this->DataListPanel->Visible=false;
    }

	/**
     * Toggle the Active flag in DataList
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	protected function toggleActive($sender,$param)
	{
		$ptId = $sender->Parent->Parent->DataKeys[$sender->Parent->ItemIndex];
		$active = $sender->Parent->Active->Checked;

		if ($active == 0 && $this->Page->deactivateEmailAddress->Value == '')
		{
			$this->setErrorMessage('You must enter the requester email address to deactivate a Part Type.');
			$this->dataLoad();
			return;
		}

		$partType = $this->lookupEntity($ptId);

		//we need the part type, and we need to do our stuff before it is updated
		DepreciationService::handlePartTypeActiveToggle($partType, $active);

		if ($active == 1) //we are reactivating
		{
    		$partType->setActive($active);
    		Factory::service("PartType")->save($partType);
		}
		else //we are deactivating
		{
			try
			{
				Factory::service("PartType")->deletePartType($partType, $this->Page->deactivateEmailAddress->Value);
			}
			catch (Exception $e)
			{
				$this->setErrorMessage($e->getMessage());
			}
		}
		$this->dataLoad();
	}

	/**
	 * Search
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function search($sender,$param)
    {
    	$this->setErrorMessage('');
    	$this->setInfoMessage('');
    	$this->AddPanel->Visible = false;
    	$this->ListingPanel->Visible = true;
    	if(trim($this->SearchPartCode->Text) >'' &&!is_numeric(trim($this->SearchPartCode->Text)))
    	{
    		$this->setErrorMessage('Partcode has to be a number');
    		return false;
    	}

    	$searchArray = $this->buildSearchArray();
    	$searchQueryString = serialize($searchArray);
		$this->SearchString->Value = $searchQueryString;
		$this->DataList->EditItemIndex = -1;
		$this->dataLoad();
    }

    /**
     * How Big Was That Query
     *
     * @return unknown
     */
    protected function howBigWasThatQuery()
    {
		return $this->totalRows;
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
       	$searchArray = unserialize($searchString);
    	//checking whether there is some search criteria
    	$nothingToSearch=true;
    	foreach($searchArray as $key=>$value)
    	{
    		if($key!="active" && trim($value)!="")
    		{
    			$nothingToSearch=false;
    			break;
    		}
    	}
    	if($nothingToSearch)
    	{
    		$this->setErrorMessage("There is nothing to search!");
    	}
		$partTypes = $this->actualDbSearch($searchArray,false, $pageNumber, $pageSize);
   		if(count($partTypes[0]) < 1)
		{
			$this->setErrorMessage("No Part Type(s) as per search criteria");
			return;
		}
		$newArr = array();
		foreach ($partTypes[0] as $pt)
		{
			$tmp = $pt;
			$tmp->getPartTypeGroups();
			$newArr[] = $tmp;
		}
		$partTypes = $newArr;
		return $partTypes;
    }

    /**
     * Create New Entity
     *
     * @return unknown
     */
    protected function createNewEntity()
    {
    	return new PartType();
    }

    /**
     * Lookup Entity
     *
     * @param unknown_type $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	$res = Factory::service("PartType")->getPartType($id);
    	return $res;
    }

    /**
     * Save Entity
     *
     * @param unknown_type $object
     */
    protected function saveEntity(&$object)
    {
    	//Factory::service("PartType")->save($object);
    }

    /**
     * Get Owner Item
     *
     * @return unknown
     */
    private function getOwnerItem()
    {
    	$item = $this;
    	if($this->AddPanel->Visible==false)
	    	$item  = $this->DataList->getEditItem();

	    return $item;
    }

    /**
     * Validate PartType
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
    public function validatePartType($sender,$param)
    {
    	$item = $this;
    	$error = "";
    	$clientIds = array();
    	$aliasTypeIds = array();
    	if($this->AddPanel->Visible==false)
    	{
    		$item = $this->DataList->getEditItem();
    		$partTypeId = $sender->Parent->Parent->DataKeys[$sender->Parent->ItemIndex];
    		$selectedPartType = Factory::service("PartType")->getPartType($partTypeId);

    		$aliasPatterns = Factory::service("Lu_PartType_PartInstanceAliasPattern")->getMandatoryUniquePatternsForPtPiat($selectedPartType,null,null,null);
    		foreach ($aliasPatterns as $alias)
    		{
    			$aliasTypeIds[] = $alias->getPartInstanceAliasType()->getId();
    		}
    		$contractIds = $item->SearchContractText->getRelatedContractIds();
    		foreach($contractIds as $contractId)
    		{
    			$contractObj = Factory::service("Contract")->get($contractId);
    			$cgObj = $contractObj->getContractGroup();
    			$client = $cgObj->getClient();
    			if (!in_array($client->getId(), $clientIds))
    				$clientIds[] = $client->getId();
    		}
    		if(count($aliasTypeIds)>0)
    		{
	    		if(in_array(PartInstanceAliasType::ID_CLIENT_ASSET_NUMBER,$aliasTypeIds) && count($clientIds)>1)
	    		{
	    			$error .= "Client Asset Number can NOT be shared between contracts!\nPlease split the part types into two!";
	    		}
    		}
    	}

    	$parttypeName = trim($item->newPartTypeName->Text);
    	$parttypesDescription = trim($item->newPartTypeDescription->Text);

     	if(strlen($parttypeName) < 5)
    	{
	    	$this->setErrorMessage("Name must consist of at least 5 characters. ");
	    	return false;
    	}
     	else if(strlen($parttypesDescription) < 5)
    	{
	    	$this->setErrorMessage("Description must consist of at least 5 characters.\n");
	    	return false;
    	}
     	$unitPrice = StringUtils::getValueFromCurrency(trim($item->unitPrice->Text));
     	if($unitPrice === '' || preg_match('/^\d+(\.\d{1,4})?$/', $unitPrice) === 0)
    	{
	    	$this->setErrorMessage("Please provide a valid number for Unit Price, like: 1,234.56 or $1,234.56");
	    	return false;
    	}

    	$owner = null;
    	$ownerName = $item->newPartTypeOwner->Text;
    	$ownerId = $item->ownerClient->Value;
    	if ($ownerId =='')
    	{
    		$error .= "Invalid Owner Client";
    	}
    	else
    	{
    		$owner = Factory::service("Client")->getClient($ownerId);
    		if ((! $owner instanceof Client)||(! isset($owner)))
    		{
    			$error .= "Invalid Owner Client";
    		}
    	}

    	if($owner instanceof Client)
    	{
	    	$newSerialised = $item->newSerialized->Checked ? false : true;
	    	$byteClientIds = Factory::service("Client")->getClientIdsWithNameLike('byte');
	    	if (in_array($owner->getId(), $byteClientIds )) //is a bytecraft owned part
	    	{
	    		if ($newSerialised) //is serialised
	    		{
	    			if (!$item->newDMChk->Checked)
	    			{
	    				$error .= "You must depreciate a SERIALISED part that is owned by Bytecraft...";
	    			}
	    		}
	    	}
    	}


    	$checked = $item->SearchContractText->contractGroupCheck->getChecked();
    	if($checked == true)
    	{
    		$cg = $item->SearchContractText->contractGroupList->getSelectedValue();
    		$cg_object = Factory::service("ContractGroup")->get($cg);

    		$contractGroupArray = array();
    		$contractIds = $item->SearchContractText->getRelatedContractIds();

    		foreach($contractIds as $contractId)
    		{
    			$contractObj = Factory::service("Contract")->get($contractId);
    			$contractGrp = $contractObj->getContractGroup()->getID();
    			array_push($contractGroupArray,$contractGrp);
    		}
    		if (!in_array($cg,$contractGroupArray))
	    		$error .= "At least one Contract must be selected for a selected Contract Group";
    	}

    	if($error != '')
    	{
    		if($this->AddPanel->Visible==false)
    		{
    			$item->validateEdit->value = false;
    		}
    		else
    		{
    			$item->validateAdd->value = false;
    		}
    		$this->setErrorMessage($error);
    		return false;
    	}
    	else
    	{
    		if($this->AddPanel->Visible==false)
    		{
    			$item->validateEdit->value = true;
    		}
    		else
    		{
    			$item->validateAdd->value = true;
    		}
    		return true;
    	}
    }

    /**
     * Owner Client Selected
     *
     * @param unknown_type $value
     */
    public function ownerClientSelected($value)
    {
    	$item = $this->getOwnerItem();
		$item->ownerClient->Value = $value;
		$text = strtolower($item->newPartTypeOwner->Text);
		if (strpos($text,'bytecraft') !== false)
		{
			if (!$item->newSerialized->Checked)
				$item->newDMChk->Checked = true;
		}
		else if ($this->AddPanel->Visible == true)
			$item->newDMChk->Checked = false;

		$this->Page->jsLbl->Text = '<script type="text/javascript">toggleDepList(\'' . $item->newDMChk->getClientId() . '\', false);</script>';
    }

    /**
     * Owner Client Suggest
     *
     * @param unknown_type $text
     * @return unknown
     */
    public function ownerClientSuggest($text)
    {
		$item = $this->getOwnerItem();
		$item->ownerClient->Value = '';
    	$item->newPartTypeOwner->value = null;
		$result = Factory::service("Client")->find($text, false, 1, 20, 'RLIKE');
		return $result;
    }

    /**
     * Save
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function save($sender,$param)
    {
    	$saved=false;
    	if($this->AddPanel->Visible == true)
    	{
    		$entity = $this->createNewEntity();
    		$params = $this;
    		$newObject = true;
    	}
    	else
    	{
    		$params = $param->Item;
    		$entity = $this->lookupEntity($this->DataList->DataKeys[$params->ItemIndex]);
    	}
    	try
    	{
    		$focusObject = $this->focusObject->Value;
    		$focusObjectArgument = $this->focusObjectArgument->Value;
    		if($focusObject == "")
    			$focusObject = null;
    		else
    			$focusObject = $this->getFocusEntity($focusObject,$focusObjectArgument);

    		$this->setEntity($entity,$params,$focusObject);
    		$this->saveEntity($entity);

    		if($this->AddPanel->Visible == true)
    		{
    			$this->AddPanel->Visible = false;
    			$partType = Factory::service("PartType")->getPartType($entity->getId());

    			if (($partType instanceof PartType) && ($partType->getSerialised()==true))
				{

      				$this->jsLbl->setText("<script type='text/javascript'>if (confirm('Would you like to add Part Alias patterns for the newly created Part Type?')) {
              		window.open('/compulsorypartinstancealiastype/" . $partType->getId() . "','');}</script>");
              	}
    		}
    		else
    			$this->DataList->EditItemIndex = -1;

    		$this->resetFields($params);
			$saved=true;
    	}
    	catch (Exception $e)
    	{
    		$this->onLoad("reload");
    		$this->setErrorMessage($e->getMessage());

    	}
    	if ($saved==true)
    		$this->dataLoad();
    }


    /**
     * validatePartTypeAliases
     * @param String $errors
     */
    private function validatePartTypeAliases($params)
    {
    	/// Check if a parttypealiastype is added more than once with allowMultiple set as 0 * MRAHMAN
		$uniqueArray = array();
		$errors = "";

		for($i = 1; $i <= 10; $i++)
		{
			$aliasTypeID = $params->{"type$i"}->getSelectedValue();
			$ptatObject = Factory::service("PartTypeAliasType")->get($aliasTypeID);
			$aliasValue = "";

			if($ptatObject instanceOf PartTypeAliasType)
			{
				if ($ptatObject->getValueType() != StringUtils::VALUE_TYPE_BOOL)
					$aliasValue = $params->{"alias$i"}->Text;
				else
				{
					$aliasValue = 1;
				}
			}

			if(trim($aliasValue) != "")
			{
				if(!isset($uniqueArray["$aliasTypeID"]))
				{
					$uniqueArray["$aliasTypeID"] = 1;
				}
				else
				{
					$uniqueArray["$aliasTypeID"] = $uniqueArray["$aliasTypeID"] + 1;
				}
			}

		}

		foreach($uniqueArray as $key => $value)
		{
			if($value > 1)
			{
				$ptatObject = Factory::service("PartTypeAliasType")->get($key);
				if($ptatObject instanceof PartTypeAliasType)
				{
					if($ptatObject->getAllowMultiple() == 0)
					{
						$errors .= "Part Type Alias Type [".$ptatObject->getName()."] Cannot Be Added Multiple Times <br/>";
					}
				}
			}
		}
		return $errors;
    }


    /**
     * Set Entity
     *
     * @param unknown_type $object
     * @param unknown_type $params
     * @param unknown_type $focusObject
     */
    protected function setEntity(&$object,$params,&$focusObject = null)
    {
    	$this->setErrorMessage("");
    	$this->setInfoMessage("");
    	$isNewObject = $object->getId() ? false : true;

    	$object->setName(StringUtils::cleanString($params->newPartTypeName->Text, false));
		$object->setDescription(StringUtils::cleanString($params->newPartTypeDescription->Text, false));
		$object->setModel('');
		$object->setMake('');
		$object->setVersion('');
		$object->setUnitPrice(StringUtils::getValueFromCurrency(trim($params->unitPrice->Text)));

       	//Get Object of Selected Contract Group - For contractgroupid in Parttypes table
    	$cg = $params->SearchContractText->contractGroupList->getSelectedValue();
    	$cg_object = Factory::service("ContractGroup")->get($cg);

    	//Check for the ContractGroup check box -> if checked add contractgroupid to Parttype table
    	$contractGroupArray = array();
    	$contractIds = $params->SearchContractText->getRelatedContractIds();
    	foreach($contractIds as $contractId)
		{
			$contractObj = Factory::service("Contract")->get($contractId);
			$contractGrp = $contractObj->getContractGroup()->getID();
			array_push($contractGroupArray,$contractGrp);
		}

		//Include the Contract Group if the check box is checked
    	$checked = $params->SearchContractText->contractGroupCheck->getChecked();
		if($checked == true)
		{
			if($cg_object != NULL)
				$object->setContractGroup($cg_object);
		}
		else
		{
			$object->setContractGroup(NULL);
		}

		$object->setRepairable($params->repairable->Checked);
		if($this->AddPanel->Visible==false)
			$object->setReturnable($params->returnable->Checked);
		else
			$object->setReturnable($params->returnable_add->Checked);

		$oldSerialised = $object->getSerialised();
		$newSerialised = $params->newSerialized->Checked ? false : true;

		//owner Client
		$owner = Factory::service("Client")->get($params->newPartTypeOwner->getSelectedValue());
		$object->setOwnerClient($owner);

		//manufacturer
		$manufacturer = Factory::service("Company")->get($params->newPartTypeManufacturer->getSelectedValue());
		if($manufacturer instanceof Company)
	    	$object->setManufacturer($manufacturer);

		//depreciation method
		$dm = null;
		$oldDm = $object->getDepreciationMethod();
		$byteClientIds = Factory::service("Client")->getClientIdsWithNameLike('byte');
		if (in_array($owner->getId(), $byteClientIds )) //is a bytecraft owned part
		{
			if ($newSerialised) //is serialised
			{
				if ($params->newDMChk->Checked)
					$dmId = $params->newDM->getSelectedValue();

				$dm = Factory::service("DepreciationMethod")->get($dmId);
			}
			else
			{
				//checking here to see if RTB...
			    $isRTB = false;
			    if (in_array(73,$contractIds))
					$isRTB = true;

				if ($isRTB && $params->newDMChk->Checked) //is RTB and depreciation is checked
				{
					$dmId = $params->newDM->getSelectedValue();
					$dm = Factory::service("DepreciationMethod")->get($dmId);
				}
			}
		}
		$object->setDepreciationMethod($dm);

		//check if we're changing the depreciation method
		DepreciationService::handleDepreciationToggle($object, $oldDm, $dm);
		//kitType
		if($params->kitCheck->Checked == true)
		{
			$oldKitType = "";

			$kitTypeId = $params->kitList->getSelectedValue();
			$kitType = Factory::service("KitType")->get($kitTypeId);
			$oldKitType = $object->getKitType();

			if($kitType != $oldKitType)
				$object->setKitType($kitType);
		}
		//Label List

		if($params->tempList->getselectedValue()!= '')
		{
			$labelListIds = $params->tempList->getSelectedValue();
			$sqlArray =array();
			$sqlArray[] = "('$labelListIds','".$object->getId()."',NOW(),'".Core::getUser()->getId()."')";

			$sql="delete from label_parttype where parttypeId = '".$object->getId()."'";
			Dao::execSql($sql);

			$sql = "insert into label_parttype (`labelId`,`parttypeId`,`created`,`createdById`) values ".implode(",",$sqlArray)."";
			Dao::execSql($sql);
		}

		try
		{
			//Dao::beginTransaction();
			if (!$isNewObject && $oldSerialised != $newSerialised)
				Factory::service("PartType")->changeSerialiseFlag($object,$newSerialised,"Changing Flag from ".($oldSerialised ? "" : "Non-")."serialised' to '".($newSerialised ? "" : "Non-")."serialised'");
			else
				$object->setSerialised($newSerialised);

			//save the PartType
			Factory::service("PartType")->savePartType($object);

			if (in_array($owner->getId(), $byteClientIds)) //is a bytecraft owned part
			{
				DepreciationService::handleSerialisedConversion($object, $oldSerialised);
			}

			//contracts
			$sqlArray =array();
			foreach($contractIds as $contractId)
			{
				if (!$object->getSerialised()) //if part type is NON serialised, it will force the compulsory to be nothing
					$aliasTypeIds = '';
				$sqlArray[] = "('".$object->getId()."','$contractId',NOW(),'".Core::getUser()->getId()."')";
			}
			if(count($sqlArray)>0)
			{
				$sql="delete from contract_parttype where parttypeId = '".$object->getId()."'";
				Dao::execSql($sql);

				$sql="insert into contract_parttype(`partTypeId`,`contractId`,`created`,`createdById`) values ".implode(",",$sqlArray);
				Dao::execSql($sql);
			}

			//suppliers
			$controlIds = array();
			foreach($params->newPartTypeSuppliers->getItems() as $item)
			{
				$controlIds[] = $item->getValue();
			}
			$suppliers = $object->getSuppliers();
			$this->saveManyToMany($object,$controlIds,$suppliers,'addSupplier','removeSupplier',Factory::service("Company"),'get');

			//part type group
			$controlIds=array();
			foreach($params->newPartTypeGroups->getItems() as $allpartTypeGroup)
			{
				$controlIds[]=$allpartTypeGroup->getValue();
			}
			$partTypeGroups = $object->getPartTypeGroups();
			$this->saveManyToMany($object,$controlIds,$partTypeGroups,'addPartTypeGroup','removePartTypeGroup',Factory::service("PartType"),'getPartTypeGroup');

			if ($object instanceof PartType && $isNewObject)
			{
				//there is no partcode
				if (trim($object->getAlias())=="")
				{
					$partTypeAlias = new PartTypeAlias();
					$partTypeAlias->setPartType($object);
					$partTypeAlias->setPartTypeAliasType(Factory::service("PartType")->getPartTypeAliasType(1));
					$partTypeAlias->setAlias($params->newPartTypeCode->Text);
					Factory::service("PartType")->savePartTypeAlias($partTypeAlias);
				}

				$bcp = trim($params->newBarcode->Text);
				if ($bcp != '' && !$object->getSerialised() && trim($object->getAlias(2)) == '')
				{
					$partTypeAlias = new PartTypeAlias();
					$partTypeAlias->setPartType($object);
					$partTypeAlias->setPartTypeAliasType(Factory::service("PartType")->getPartTypeAliasType(2));
					$partTypeAlias->setAlias($params->newBarcode->Text);
					Factory::service("PartType")->savePartTypeAlias($partTypeAlias);
				}
			}

			$message = ($isNewObject ? "New ": "");
			$message .= "Part Type {$object->getAlias()} - {$object->getName()} has saved successfully!";
			if(!$isNewObject && !$object->getSerialised())
			{
				if ($oldSerialised == 1 && $params->newSerialized->Checked) //only show the message if it has been changed from serialised to non-serialised
				$message .= "<br /><div style='color:red;'>All compulsory part instance alias types have been <u>removed</u>, as it is now a <u>non-serialised</u> part!</div>";
			}


			$error1 = $this->validatePartTypeAliases($params);

			if($error1 != "")
			{
				$error1 .= "!!! No Part Type Alias Type Information For This Part Type Have Been Changed !!!";
				$this->setErrorMessage($error1);
			}
			else
			{
				$aliasId = 0;
				$error = "";
				for($i=1; $i <= 10; $i++)
				{
					try
					{
						$aliasType = $params->{"type$i"}->getSelectedValue();

						$partTypeAliasType = Factory::service("PartTypeAliasType")->get($aliasType);

						$valueType = "";
						if($partTypeAliasType instanceOf PartTypeAliasType)
						{
							$valueType = $partTypeAliasType->getValueType();
						}

						if($valueType != StringUtils::VALUE_TYPE_BOOL)
						{
							$alias = trim($params->{"alias$i"}->Text);
						}
						else
						{
							if ($params->{"alias".$i."Chk"}->Checked == true)
							{
								$alias = true;
							}
							else
							{
								$alias = false;
							}
						}

						try
						{
							$aliasId = $params->{"aliasId$i"}->Value;
						}
						catch (Exception $e){
						}

						if($aliasId)
						{

							//UPDATE
							$partTypeAlias = Factory::service("PartType")->getPartTypeAlias($aliasId);
							if ($partTypeAlias instanceof PartTypeAlias)
							{

								if(Factory::service("PartType")->checkPartTypeAliasesForDuplicate($object->getId(),$aliasType,$alias,$aliasId))
								{
									$error .= "Duplicate found for Alias: " . $alias . ", Failed to update!<br />";
								}
								else
								{
									if(!Factory::service("PartType")->checkPartTypeAliasesForDuplicate($object->getId(),$aliasType,$alias))
									{
										$partTypeAlias->setPartType($object);
										$partTypeAlias->setPartTypeAliasType(Factory::service("PartType")->getPartTypeAliasType($aliasType));

										$partTypeAlias->setAlias($alias);
										Factory::service("PartType")->savePartTypeAlias($partTypeAlias);
									}
								}
							}
						}
						else
						{
							if (($alias != "" && $valueType === StringUtils::VALUE_TYPE_STRING)||($valueType === StringUtils::VALUE_TYPE_BOOL && ($alias==true || $alias==false)))
							{
								//ADD
								$partTypeAlias = new PartTypeAlias();

								if(Factory::service("PartType")->checkPartTypeAliasesForDuplicate($object->getId(),$aliasType,$alias))
								{
									$error .= "Duplicate found for Alias: " . $alias . ", Failed to add!<br />";
								}
								else
								{
									$partTypeAlias->setPartType($object);
									$partTypeAlias->setPartTypeAliasType(Factory::service("PartType")->getPartTypeAliasType($aliasType));
									$partTypeAlias->setAlias($alias);
									Factory::service("PartType")->savePartTypeAlias($partTypeAlias);
								}
							}
						}

						if($error)
						{
							$this->setErrorMessage($error);
						}

					}
					catch (Exception $e)
					{
						$this->setErrorMessage($e->getMessage() . "<br />");
					}
				}

			}

			if($isNewObject){
				$this->Page->jsLbl->Text = '<script type="text/javascript">hideAllAliases();</script>';
			}

			$this->setInfoMessage($message);

			//Dao::commitTransaction();
		}
		catch (Exception $e)
		{
			$this->setErrorMessage("The Part Type was not saved due to the following error(s);<br /><br />" . $e->getMessage());

			//Dao::rollbackTransaction();
		}
    }

    /**
     * Populate Add
     *
     */
    protected function populateAdd()
    {
    	$this->initialiseAliasCombos($this);
    	$this->newPartTypeCode->Text = $this->newPartTypeName->Text = $this->newPartTypeDescription->Text = "";
    	$this->bindDropDownList($this->newPartTypeSuppliers,array());
    	$this->bindDropDownList($this->newPartTypeGroups,array());
    	$this->newSerialized->Checked = $this->repairable->Checked = false;
    	$this->returnable_add->Checked = true;
    	$this->SearchContractText->clear();

    	$this->newDMChk->Checked = false;
    	$this->bindDropDownList($this->newDM, Factory::service("DepreciationMethod")->findAll());

    	//Kittype
    	$this->kitCheck->Checked = false;
    	$this->bindDropDownList($this->kitList, Factory::service("KitType")->findAll());

		//owner Client
    	$this->newPartTypeOwner->setSelectedValue(null);

		$companies=Factory::service("Company")->findAll();
		$this->bindDropDownList($this->newPartTypeManufacturer,$companies);
		$result = Factory::service("Sequence")->findByCriteria("name like 'Partcode'");
		if(count($result)>0)
			$this->newPartTypeCode->Text=Factory::service("Sequence")->getNextNumberAsBarcode($result[0]);

		$this->SearchContractText->Enabled=true;
		$this->ListingPanel->Visible = false;
		$this->SearchContractText->contractGroupList->Enabled = false;
		//$this->SearchContractText->compulsoryAliasTypeList->Enabled=true;

		if(!UserAccountService::isSystemAdmin()) //disable for all except sys admin
		{	$this->tempList->visible=false;
		$this->LabelText->visible=false;
		$this->LabelText1->visible=false;}

		$this->unitPrice->Text = '';

		//Label List
		$sql = "SELECT id,text FROM label";
		$results = Dao::getResultsNative($sql);
		$result =array();
		foreach ($results as $row)
		{
			$result[]= array("id"=>$row[0], "name"=>$row[1]);
		}
		$this->tempList->dataSource = $result;
		$this->tempList->DataBind();

    }

    /**
     * Initialise Alias Combos
     *
     * @param unknown_type $Item
     * @param unknown_type $mode
     */
	private function initialiseAliasCombos($Item = false, $mode = "")
	{
		if($mode == "")
		{
			$data = $this->getPartTypeAliasTypeList();
		}
		else if($mode == "EDIT")
		{
			$data = $this->getPartTypeAliasTypeList("EDIT");
		}

		$Item->type1->DataSource = $data;
	    $Item->type1->dataBind();

        //$defaultVals = array(6,8,3,4,9,3,3,3,3,3);
		for ($i=1; $i<=10; $i++)
		{
			$Item->{"type$i"}->DataSource = $data;
			$Item->{"type$i"}->DataBind();
			$Item->{"alias$i"}->setText('');

			//$index = $Item->{"type$i"}->getItems()->findIndexByValue($defaultVals[$i-1]);
			//if ($index > 0)
			//{
			//	$Item->{"type$i"}->setSelectedValue($defaultVals[$i-1]);
			//}
		}
		$this->Page->jsLbl->Text = '<script type="text/javascript">hideAllAliases();</script>';
	}

	/**
	 * Populate Edit
	 *
	 * @param unknown_type $editItem
	 */
    protected function populateEdit($editItem)
    {
    	$contractGroup = "";
    	$partType = $editItem->getData();
    	$this->partTypeIdHidden->Value = $partType->getId();

        $editItem->newPartTypeCode->Text = $partType->getAlias();
        $editItem->newPartTypeName->Text = trim($partType->getName());
        $editItem->newPartTypeDescription->Text = trim($partType->getDescription());
        $editItem->newBarcode->Text = $partType->getAlias(2);
        $editItem->newSerialized->Checked=(!$partType->getSerialised());
        $editItem->repairable->Checked=($partType->getRepairable());
        $editItem->returnable->Checked=($partType->getReturnable());
        $editItem->returnable->Enabled = false;
        $editItem->unitPrice->Text = StringUtils::getCurrency($partType->getUnitPrice());

        //external code
        $externalCode = $editItem->getData()->getExternalCode();
        $editItem->SearchContractText->partTypeExtCode->Value = $editItem->getData()->getExternalCode();
        $editItem->SearchContractText->aliasPane->style='display:block';
		//owner company
		$owner = $partType->getOwnerClient();
		if ($owner instanceof Client)
		{
    		$editItem->newPartTypeOwner->setSelectedValue($owner);
    		$editItem->ownerClient->Value = $owner->getId();
		}

		//contractGroup
		$parttypeContractGroup = $partType->getContractGroup();

		if($parttypeContractGroup != NULL || $parttypeContractGroup != "")
			$contractGroup = $parttypeContractGroup->getID();

  	  	if(!UserAccountService::isSystemAdmin()) //disable for all except sys admin
		{
			$editItem->tempList->visible=false;
			$editItem->LabelText->visible=false;
			$editItem->LabelText1->visible=false;
			$editItem->unitPrice->Enabled = false;
		}

		if($contractGroup != "")
		{
			$this->SearchContractText->contractGroupCheck->setChecked(true);
			$this->SearchContractText->contractGroupList->Enabled = true;
		}
		//Label List
		$sql = "SELECT id,text FROM label";
		$results = Dao::getResultsNative($sql);
		$result =array();
		foreach ($results as $row)
		{
			$result[]= array("id"=>$row[0], "name"=>$row[1]);
		}
		$editItem->tempList->dataSource = $result;
		$editItem->tempList->DataBind();

		$sql1= "Select * from label_parttype where parttypeId  = ".$partType->getId();
		$results1 = Dao::getResultsNative($sql);

		$sql= "Select labelId from label_parttype where parttypeId  = ".$partType->getId();
		$results = Dao::getResultsNative($sql);

		$temp = array();
		$count = 0;

		foreach($results1 as $ca)
        {
              foreach($results as $cg)
              {
                  $temp[]=$cg[0];
                  $count++;
              }
              $editItem->tempList->setSelectedValues($temp);
        }

        //manufacturer
		$companies=Factory::service("Company")->findAll();
		$this->bindDropDownList($editItem->newPartTypeManufacturer,$companies,$partType->getManufacturer());

		//suppliers
		$this->bindDropDownList($editItem->newPartTypeSuppliers,$partType->getSuppliers());

		//Part Type Groups
		$this->bindDropDownList($editItem->newPartTypeGroups,$partType->getPartTypeGroups());

		//contracts
		$sql="select contractId from contract_parttype where parttypeId = ".$partType->getId();
		$data = array();
		foreach(Dao::getResultsNative($sql) as $row)
    	{
    		$data[] =  $row[0];
    	}

    	if ($externalCode != '')
    	{
    	    if ($contractGroup != '')
    	    {
                $contractGroupObject = Factory::service("ContractGroup")->get($contractGroup);
                $clientId = $contractGroupObject->getClient()->getId();
                $editItem->SearchContractText->loadData($data,$partType,$contractGroup,$clientId);
        	}
        	else
        	{
        	    foreach($data as $contractId)
        	    {
        	        $contractGroupObject = Factory::service("ContractGroup")->getContract($contractId)->getContractGroup();
        	        $clientId = $contractGroupObject->getClient()->getId();
        	    }
        	    $editItem->SearchContractText->loadData($data,$partType,$contractGroup,$clientId);
        	}

    	}
    	else
    	{
            $editItem->SearchContractText->loadData($data,$partType,$contractGroup);
    	}
		//Depreciation Method
		$dm = $partType->getDepreciationMethod();
		if (is_null($dm))
		{
			$editItem->newDMChk->Checked = false;
			$editItem->newDM->Style = 'display:none;';
			$this->bindDropDownList($editItem->newDM, Factory::service("DepreciationMethod")->findAll());
		}
		else
		{
			$editItem->newDMChk->Checked = true;
			$editItem->newDM->Style = '';
			$this->bindDropDownList($editItem->newDM, Factory::service("DepreciationMethod")->findAll(), $dm);
		}

		//disable if there are instances
		if (DepreciationService::partTypeHasDepreciationInstances($partType))
		{
			$editItem->newDMChk->Enabled = false;
			$editItem->newDM->Enabled = false;
		}

		//allow if system admin
		if (UserAccountService::isSystemAdmin())
		{
			$editItem->newDMChk->Enabled = true;
			$editItem->newDM->Enabled = true;
			$editItem->returnable->Enabled = true;
		}
		//if (!DepreciationService::partTypeHasDepreciationInstances($partType) && UserAccountService::isSystemAdmin()) //has no instances, and is sysadmin

		//Kittype
		$kitType = $partType->getKitType();
		if(is_null($kitType))
		{
			$editItem->kitCheck->Checked = false;
			$editItem->kitList->Style='display:none;';
			$this->bindDropDownList($editItem->kitList,Factory::service("KitType")->findAll());
		}
		else
		{
			$editItem->kitCheck->Checked = true;
			$editItem->kitList->Style='';
			$this->bindDropDownList($editItem->kitList,Factory::service("KitType")->findAll(),$kitType);
		}
		$editItem->kitCheck->Enabled = false;
		$editItem->kitList->Enabled = false;

    	if (UserAccountService::isSystemAdmin())
		{
			//Cant change or
			if($this->getInstanceQuantity($partType->getId())==0)
			{
				$editItem->kitCheck->Enabled = true;
				$editItem->kitList->Enabled = true;
			}
		}
		else
		{
			$editItem->newSerialized->Enabled = false; //disable serialised toggle if not sysadmin
		}

        $editItem->newPartTypeCode->focus();
        $partTypeAlias = Factory::service("PartType")->getPartTypeAliasesForPartTypeEditable($partType->getId());
        $partTypeAliasCount = sizeof($partTypeAlias);
   		$i = 1;


   		$canDelete = Session::checkRoleFeatures(array('menu_all','menu_all','feature_PartTypeAlias_deactivate'));
        foreach($partTypeAlias as $partTypeAlias)
    	{
    		$lueaoId = $partTypeAlias->getPartTypeAliasType()->getLu_entityAccessOption()->getId();
    		if($i<=10)
    		{
    			$populatedData = "";
    			$tempchk = "alias".$i."Chk";
    			$tempalias = "alias" . $i;
	   			$tempaliasid = "aliasId" . $i;
	     		$temptype = "type" . $i;
	     		$tempcreated = "created" . $i;
	     		$tempupdated = "updated" . $i;
	     		$tempdelete = "delete" . $i;
	     		/// Check the part type alias type from the part type alias and verify whether the partTypeAliasType has the accessMode id as 3 or 2 * MRAHMAN
	     		if($lueaoId == 3 || ($lueaoId == 2 && UserAccountService::isSystemAdmin() == false))
	     		{
	     			$populatedData = $this->getPartTypeAliasTypeList("EDIT");
	     		}
	     		else
	     		{
	     			$populatedData = $this->getPartTypeAliasTypeList();
	     		}

	     		$editItem->$temptype->DataSource = $populatedData;
	     		$editItem->$temptype->dataBind();
	     		$editItem->$tempalias->setText('');


	     		$index = $editItem->$temptype->getItems()->findIndexByValue($partTypeAlias->getPartTypeAliasType()->getId());
				$editItem->$temptype->setSelectedValue($partTypeAlias->getPartTypeAliasType()->getId());

				if ($partTypeAlias->getPartTypeAliasType()->getValueType()=== StringUtils::VALUE_TYPE_BOOL)
				{
				    $editItem->$tempalias->setAttribute("style", "display:none");
				    $editItem->$tempchk->setAttribute("style", "display:in-line");
				    if ($partTypeAlias->getAlias() == '1')
				        $editItem->$tempchk->Checked = true;
				    else
				        $editItem->$tempchk->Checked = false;
				}
				else
				{
				    $editItem->$tempalias->setText($partTypeAlias->getAlias());
				    $editItem->$tempalias->setAttribute("style", "display:in-line");
				    $editItem->$tempchk->setAttribute("style", "display:none");
				}
				if($lueaoId == 3 || ($lueaoId == 2 && UserAccountService::isSystemAdmin() == false))
				{
					$editItem->$temptype->Enabled = false;
					$editItem->$tempalias->Enabled = false;
				}



	     		$editItem->$tempaliasid->setValue($partTypeAlias->getId());
	      	  	$editItem->$tempcreated->setText($partTypeAlias->getCreatedBy()->getPerson()->getFirstName() . " " . $partTypeAlias->getCreatedBy()->getPerson()->getLastName());
	        	$editItem->$tempupdated->setText($partTypeAlias->getUpdatedBy()->getPerson()->getFirstName() . " " . $partTypeAlias->getUpdatedBy()->getPerson()->getLastName());



	        	if($canDelete)
	        	{
	        		$editItem->$tempdelete->SetVisible(true);
	        	}

	        	$i++;
    		}
    	}

    	$data = $this->getPartTypeAliasTypeList();
    	for($j = $i; $j <= 10; $j++)
    	{
    		$editItem->{"type$j"}->DataSource = $data;
			$editItem->{"type$j"}->DataBind();
			$editItem->{"alias$j"}->setText('');
			if ($data[$j]['valueType']=== StringUtils::VALUE_TYPE_BOOL)
			{
			    $editItem->{"alias$j"}->setAttribute("style", "display:none");
			    $editItem->{"alias".$j."Chk"}->setAttribute("style", "display:in-line");
			}
			else
			{
			    $editItem->{"alias$j"}->setAttribute("style", "display:in-line");
			    $editItem->{"alias".$j."Chk"}->setAttribute("style", "display:none");
			}
    	}



    	$editItem->PartTypeAliasLabel->Style='display:;';

     	$originalSize = $partTypeAliasCount;
		if($partTypeAliasCount > 10){
			$partTypeAliasCount  = 10;
			$editItem->aliasOverflowMessage->Style="display:;color:red;font-weight:bold;";
			$editItem->aliasOverflowMessage->setText("There are " . $originalSize . " Aliases for this Part type, You can only edit the first 10 here. Edit all <a href='/parttypealias/" . $partType->getId(). "' target='_blank'>here</a>");
		}

		$this->Page->jsLbl->Text = '<script type="text/javascript">setTimeout("showActiveAliases('  .$partTypeAliasCount . ');",1000);</script>';

    }

    /**
     * deleteAlias
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function deleteAlias($sender,$param)
    {

    	$editItem = $this->DataList->getEditItem();
    	$aliasIndex = trim($param->CommandParameter);
    	$tempchk = "alias".$aliasIndex."Chk";
    	$tempalias = "alias" . $aliasIndex;
	   	$tempaliasid = "aliasId" . $aliasIndex;
	    $tempcreated = "created" . $aliasIndex;
	    $tempupdated = "updated" . $aliasIndex;
    	$temptype = "type" . $aliasIndex;
    	$tempdelete = "delete" . $aliasIndex;
    	$temploader = "deleteAliasLoader" . $aliasIndex;

	    $aliasId = $editItem->$tempaliasid->Value;
		$partTypeAlias = Factory::service("PartTypeAlias")->get($aliasId);

		$isCheckBox = false;

		if($partTypeAlias instanceOf PartTypeAlias)
		{
			$partTypeAliasTyp = $partTypeAlias->getPartTypeAliasType();


			if($partTypeAliasTyp instanceOf PartTypeAliasType)
			{
				if ($partTypeAliasTyp->getValueType() == StringUtils::VALUE_TYPE_BOOL)
				{
					$isCheckBox = true;
				}
			}
		}

		try {

	  		Factory::service("PartType")->deletePartTypeAlias($partTypeAlias);
			if($isCheckBox)
			{
				$editItem->$tempchk->Visible = false;
			}
			else
			{
				$editItem->$tempalias->Visible = false;
			}

			$editItem->$temptype->setSelectedIndex(0);
			$editItem->$temptype->Visible = false;
			$editItem->$tempcreated->Visible = false;
			$editItem->$tempupdated->Visible = false;
			$editItem->$tempdelete->Visible = false;
			$editItem->$tempaliasid->Value = "";
			$editItem->$temploader->Visible = false;

		}
		catch(Exception $e)
		{
			$editItem->$temploader->Visible = false;

			$editItem->aliasOverflowMessage->Style="display:;color:red;font-weight:bold;";
			$editItem->aliasOverflowMessage->setText("Failed to delete alias! " . $e->getMessage());
		}
    }




    /**
     * On Suggest Supplier
     *
     * @param unknown_type $text
     * @return unknown
     */
    public function onSuggestSupplier($text)
    {
    	$data = Factory::service("Company")->findByCriteria("name like '%$text%'",array(),false,1,50,array("Company.name"=>"asc"));
    	return $data;
    }

    /**
     * Handle Selected Supplier
     *
     * @param unknown_type $id
     */
    public function handleSelectedSupplier($id)
    {
    	$company = Factory::service("Company")->get($id);
    	if(!$company instanceof Company)
    		return;

    	$newItem = new TListItem($company->getName(),$company->getId());
    	$item = $this;
   		if($this->AddPanel->Visible == false)
   			$item =$this->DataList->getEditItem();

    	$item->newPartTypeSuppliers->getItems()->add($newItem);
    	$item->SearchSupplierText->Text="";
    }

    /**
     * On Suggest PartType Group
     *
     * @param unknown_type $text
     * @return unknown
     */
    public function onSuggestPartTypeGroup($text)
    {
    	$data = Factory::service("PartTypeGroup")->findByCriteria("name like '%$text%'",array(),false,1,50,array("PartTypeGroup.name"=>"asc"));
    	return $data;
    }

    /**
     * Handle Selected PartType Group
     *
     * @param unknown_type $id
     */
    public function handleSelectedPartTypeGroup($id)
    {
    	$partTypeGroup = Factory::service("PartTypeGroup")->get($id);
    	if(!$partTypeGroup instanceof PartTypeGroup)
    		return;

    	$item = $this;
   		if($this->AddPanel->Visible == false)
   			$item =$this->DataList->getEditItem();
    	$newItem = new TListItem($partTypeGroup->getName(),$partTypeGroup->getId());
    	$item->newPartTypeGroups->getItems()->add($newItem);
    	$item->SearchPartTypeGroupText->Text="";
    }

    /**
     * Remove Selected Item
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function removeSelectedItem($sender,$param)
    {
    	$listId = trim($param->CommandParameter);
    	$item = $this;
   		if($this->AddPanel->Visible == false)
   			$item =$this->DataList->getEditItem();
   		if($item->$listId instanceof TActiveListBox)
   		{
   			$index =$item->$listId->getSelectedIndex();
   			$item->$listId->getItems()->removeAt($index);
   		}
    }



	/**
	 * Show/hide the barcode panel in the part Type panel
	 *
	 * @param $param
	 */
	public function toggleNewSerializedPartPanel($sender,$param)
	{
		$partTypeId = 0;
		$item = $this;
   		if ($this->AddPanel->Visible == false)
   		{
   			$item = $this->DataList->getEditItem();
			$partTypeId = $sender->Parent->Parent->DataKeys[$sender->Parent->ItemIndex];
   		}

		$item->newBarcode->Text="";
		if($sender->Checked)
		{
			$sql="select alias from parttypealias where partTypeId = $partTypeId and partTypeAliasTypeId = 2 and (alias like 'BP%' or alias like 'BCP%') order by id desc";
    		$res = Dao::getResultsNative($sql);

    		//if we haven't had this one before. then create BP
    		if(count($res)==0)
    		{
				$result = Factory::service("Sequence")->findByCriteria("id=2");
				if(count($result)>0)
					$item->newBarcode->Text = Factory::service("Sequence")->getNextNumberAsBarcode($result[0]);
    		}
    		else
    		{
    			$item->newBarcode->Text = $res[0][0];
    		}
		}
	}

 	/**
     * This function is used to just get deactivated user
     *
     * @param $partTypeId
     */
    public function getCreatedBy($partTypeId)
    {
    	$daoReport = new DaoReportQuery("PartType");
    	$daoReport->column("(select ua.personId from useraccount ua where ua.id = pt.createdById)","personId");
    	$daoReport->where("id=?",array($partTypeId));
    	$ids= $daoReport->execute(false);
    	if(count($ids)==0)
    		return;

    	return Factory::service("Person")->get($ids[0][0])->getFullName();
    }

 	/**
     * This function is used to just get deactivated user
     *
     * @param $partTypeId
     */
    public function getUpdatedBy($partTypeId)
    {
    	$daoReport = new DaoReportQuery("PartType");
    	$daoReport->column("(select ua.personId from useraccount ua where ua.id = pt.updatedById)","personId");
    	$daoReport->where("id=?",array($partTypeId));
    	$ids= $daoReport->execute(false);
    	if(count($ids)==0)
    		return;

    	return Factory::service("Person")->get($ids[0][0])->getFullName();
    }

    /**
     * Get Instance Quantity
     *
     * @param unknown_type $partTypeId
     * @param unknown_type $returnNumber
     * @return unknown
     */
    public function getInstanceQuantity($partTypeId, $returnNumber = false)
    {
    	//this function is called twice from the .page so to avoid the extra call do below
    	if ($this->instanceQty != null && $returnNumber == true)
    	{
    		$qty = $this->instanceQty;
    		$this->instanceQty = null;

    		//return a 0 instead of a ''
    		if ($qty == ' ')
    			$qty = 0;

    		return $qty;
    	}
    	try
    	{
    		$sql = "SELECT SUM(pi.quantity)
	    			FROM partinstance pi
	    			INNER JOIN warehouse ware ON (ware.id=pi.warehouseId AND ware.active=1 AND ware.ignorestockcount!=1)
	    			WHERE pi.active=1 AND pi.partTypeId=" . $partTypeId;

	    	$result = Dao::getResultsNative($sql);
    		if (count($result) == 0)
    		{
    			$qty = $returnNumber == false ? ' ' : 0;
    			$this->instanceQty = $qty;
    			return $qty;
    		}
    		else if (!isset($result[0][0]) || $result[0][0]==null)
    		{
    			$qty = $returnNumber == false ? ' ' : 0;
    			$this->instanceQty = $qty;
    			return $qty;
    		}
    		else
    		{
    			$qty = $result[0][0];
    			$this->instanceQty = $qty;
    			return $qty;
    		}
    	}
    	catch(Exception $e)
    	{
    		return "Error: ".$e->getMessage();
    	}
    }

    /**
     * OutPut To Excel
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function outputToExcel($sender, $param)
    {
    	// 10/12/2010 - now we grab all aliases out
        $aliasTypes = array();
        $aliasTypeIds = array();
        $q = new DaoReportQuery("PartTypeAliasType");
        $q->column("ptat.id");
        $q->column("ptat.name");
        $q->where("ptat.active=1");
        $q->orderBy("ptat.id");
        $res = $q->execute(false, PDO::FETCH_ASSOC);

        foreach ($res as $row)
        {
        	$aliasTypeIds[] = $row['id'];
        	$aliasTypes[$row['id']] = $row['name'];
        }

       	$searchArray = $this->buildSearchArray();
    	$result = $this->actualDbSearch($searchArray,true,null, null);

    	$results = $result[0];
		$errorMsg = $result[1];


    	//This is for output to excel, which requires all the data....
    	$totalSize = sizeof($results);
    	if($totalSize <= 0 )
    	{
    		$this->setErrorMessage($errorMsg);
    		$this->dataLoad();
    	}
    	else
    	{
	    	$columnHeaderArray = array("Part Code",
										"Name",
				    					"Groups",
				    					"Serialised",
				    					"Repairable",
				    					"Returnable",
				    					"Unit Price",
				    					"Qty",
				    					"Active"	);
	    	foreach ($aliasTypes as $id => $name)
	    	{
	    		// do not include "partcode" in here
	    		if ($id != 1)
	    			$columnHeaderArray[] = $name;
	    	}

	    	if(isset($results))
	    	{
		    	$allPtIds = array(0);
				foreach ($results as $row)
				{
					$allPtIds[] = $row->getId();
				}

				// grab all active parttypealiases for those parts in the result
		    	$q = new DaoReportQuery("PartTypeAlias");
		    	$q->column("pta.id");
		    	$q->column("pta.partTypeId");
		    	$q->column("pta.partTypeAliasTypeId");
		    	$q->column("pta.alias");
		    	$q->where("pta.active=1");
		    	$q->where("pta.partTypeId IN (".join(",", $allPtIds).")");
		    	$q->where("pta.partTypeAliasTypeId IN (".join(",", array_merge(array(0), $aliasTypeIds)).")");
		    	$aliasRes = $q->execute(false, PDO::FETCH_ASSOC);
		    	$aliasesByPartType = array();

		    	// now group them by parttypeid
		    	foreach ($aliasRes as $aliasRow)
		    	{
		    		if (empty($aliasesByPartType[$aliasRow['partTypeId']]))
						$aliasesByPartType[$aliasRow['partTypeId']] = array();
					$aliasesByPartType[$aliasRow['partTypeId']][] = $aliasRow;
		    	}

		    	// for each parttype returned by the result
		    	$columnDataArray = array();
				foreach ($results as $row)
				{
					$partCode = '';
					$ptGroupNames = '';
					$otherAliases = array();

					// grab all parttypealiases under this parttype
					$onePartAliases = array();
					if (!empty($aliasesByPartType[$row->getId()]))
					{
						$tmpArr = $aliasesByPartType[$row->getId()];
						foreach ($tmpArr as $tmpRow)
						{
							if (empty($onePartAliases[$tmpRow['partTypeAliasTypeId']]))
								$onePartAliases[$tmpRow['partTypeAliasTypeId']] = array();
							$onePartAliases[$tmpRow['partTypeAliasTypeId']][] = $tmpRow['alias'];
						}
					}

					// then group/sort them by parttypealiastypeid
					$aliasesInOrder = array();
					foreach ($aliasTypeIds as $atid)
					{
						// if this is partcode, put it into partcode, otherwise, to our "bucket"
						if ($atid == 1)
						{
							if (!empty($onePartAliases[$atid]))
								$partCode = join(",", $onePartAliases[$atid]);
							else
								$partCode = '';
						}
						else
						{
							if (!empty($onePartAliases[$atid]))
								$aliasesInOrder[] = join(",", $onePartAliases[$atid]);
							else
								$aliasesInOrder[] = '';
						}
					}

					$ptGroups = $row->getPartTypeGroups();
					if (!empty($ptGroups) && is_array($ptGroups))
						$ptGroupNames = join(',', $ptGroups);

					$isSerialised = ($row->getSerialised() ? "Y" : "N");
					$isActive = ($row->getActive() ? "Y" : "N");
					$qty = $this->getInstanceQuantity($row->getId());

					$isRepairable = ($row->getRepairable() ? "Y" : "N");
					$isReturnable = ($row->getReturnable() ? "Y" : "N");

					$tmpRow = array($partCode, $row->getName(), $ptGroupNames, $isSerialised,$isRepairable, $isReturnable,"$".$row->getUnitPrice(), $qty, $isActive);
					// then append these extra aliases into the row
					$tmpRow = array_merge($tmpRow, $aliasesInOrder);
					array_push($columnDataArray, $tmpRow);
				}

				$fileName = "Part Type List";
			    $this->toCSV($fileName, $fileName, $fileName, $columnHeaderArray, $columnDataArray);
	    	}


    	}

    }

    /**
     * Build Search Array
     *
     * @return unknown
     */
    private function buildSearchArray()
    {
    	$aliasType = $this->SearchAliasType->getSelectedValue();
    	$aliasText = trim($this->SearchAliasText->Text);

    	if ($aliasType == 0 || $aliasText == '')
    	{
    		$aliasType = null;
    		$aliasText = '';
    	}
    	$searchArray = array(
    							"partCode" => trim($this->SearchPartCode->Text),
    							"supplierCode" => trim($this->SearchSupplierCode->Text),
    							"partName" => trim($this->SearchName->Text),
    							"partDescription" => trim($this->SearchDescription->Text),
    							"partTypeGroup" => trim($this->SearchPartTypeGroup->Text),
    							"manufacturer" => trim($this->SearchManufacturer->Text),
    							"owner" => trim($this->owner->getSelectedValue()),
    							"make" => '',
    							"model" => '',
    							"contract" => trim($this->SearchContract->Text),
    							"version" => trim($this->SearchVersion->Text),
    							"supplier" => trim($this->SearchSupplier->Text),
    							"active" => trim($this->SearchActive->Text),
    							"serialised" => trim($this->Serialised->Text),
    							"returnable" => trim($this->Returnable->Text),
    							"partTypeAliasType" => trim($aliasType),
    							"partTypeAliasText" => trim($aliasText)
    						);
    	return $searchArray;
    }

    /**
     * Actual DB Search
     *
     * @param unknown_type $searchArray
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    private function actualDbSearch($searchArray,$outputToExcel=false, $pageNumber=null, $pageSize=null)
    {
    	$dao = new DaoReportQuery("PartType");
    	$addJoin = "LEFT JOIN parttypealias pta ON (pt.id=pta.partTypeId AND pta.active=1) ";
    	$dao->setAdditionalJoin($addJoin);
    	$dao->column("pt.id");
    	$SearchActive =$this->SearchActive->getSelectedValue();
    	$Serialised = $this->Serialised->getSelectedValue();
    	$Returnable = $this->Returnable->getSelectedValue();
    	$kitTypeSelected = $this->selectkitlist->getSelectedValue();

    	$where = "";
    	if(isset($SearchActive) && $SearchActive>"" && $SearchActive != "All")
    	{
	    	$where .= "pt.active=$SearchActive  ";
    	}
    	else
	    	$where .= "pt.active in (0,1)  ";

    	if(isset($Serialised) && $Serialised>"" && $Serialised != "All")
    	{
    		$where .= "AND pt.serialised=$Serialised  ";
    	}

    	if(isset($Returnable) && $Returnable>"" && $Returnable != "All")
    	{
    		$where .= "AND pt.returnable=$Returnable  ";
    	}

    	if(isset($kitTypeSelected) && $kitTypeSelected>"" && $kitTypeSelected != "All")
    	{
	    	$where .= "and pt.kitTypeId=$kitTypeSelected";
    	}

    	if($searchArray["partCode"]!="")
    		$where .=" AND pt.id in (select pta.partTypeId from parttypealias pta where pta.alias ='".$searchArray["partCode"]."' AND pta.partTypeAliasTypeId = 1 )";
    	if($searchArray["supplierCode"]!="")
    		$where .=" AND pt.id in (select pta.partTypeId from parttypealias pta where pta.alias like '%".$searchArray["supplierCode"]."%' AND pta.partTypeAliasTypeId = 3 )";
    	if($searchArray["partName"]!="")
    	{
    		//JT - Requested by Tom Rose, commented out to implement multi word search where
    		//'ARI PRO' becomes '%ARI% %PRO%' instead of '%ARI PRO%'
    		//$word = trim($searchArray["partName"]);
    		//$where .= " AND (pt.name like '%$word%')";

    		$wordArray = explode(' ', trim($searchArray["partName"]));
    		$where .= " AND (pt.name LIKE '%" . implode("%' AND pt.name LIKE '%",$wordArray) . "%') ";
    	}

    	if($searchArray["owner"]!="")
    	{
    		$where .= " AND (pt.ownerclientid='{$searchArray["owner"]}')";
    	}
    	if($searchArray["partDescription"]!="")
    	{
    		$word = trim($searchArray["partDescription"]);
    		$where .= " AND (pt.description like '%$word%')";
    	}
    	if($searchArray["partTypeGroup"]!="")
    		$where .=" AND pt.id in (select x.partTypeId from parttype_parttypegroup x where x.parttypegroupId in (select ptg.id from parttypegroup ptg where ptg.name like '".$searchArray["partTypeGroup"]."' ))";

    	if($searchArray["version"]!="")
    		$where .=" AND (pt.version like '".$searchArray["version"]."' OR pt.id in( select pta.partTypeId from parttypealias pta where pta.alias  like '".$searchArray["version"]."' AND pta.partTypeAliasTypeId = 8 ) )";

    	if($searchArray["manufacturer"]!="")
    		$where .=" AND pt.manufacturerId in (select man.id from company man where man.name like '".$searchArray["manufacturer"]."' )";

    	if($searchArray["contract"]!="")
    		$where .=" AND pt.id in (select x.parttypeId from contract_parttype x where x.contractId in( select c.id from contract c where c.contractName like '".$searchArray["contract"]."' ))";

    	if($searchArray["supplier"]!="")
    		$where .=" AND pt.id in (select x.parttypeId from company_parttype x where x.companyId in( select com.id from company com where com.name like '".$searchArray["supplier"]."' ))";

    	if(!empty($searchArray["partTypeAliasType"]) && is_numeric($searchArray["partTypeAliasType"]) && $searchArray["partTypeAliasText"] != '')
    	{
    		$dao->where("pta.alias LIKE '%".$this->escapeString($searchArray["partTypeAliasText"])."%'");
    		$dao->where("pta.partTypeAliasTypeId='".$searchArray["partTypeAliasType"]."'");
    	}

    	$dao->where($where);
    	$result = $dao->execute(false);
    	$errorMsg='';
    	if(count($result)>0 && sizeof($result)>"")
    	{
    		if($outputToExcel && count($result) > intval($this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'outputToExcelLimit')))
    		{
				$errorMsg = "Too much data to export to Excel (".number_format(count($result))." records found). Please narrow down the search.";
				$partTypes=array();
    		}
    		else
    		{
		    	$partTypeIds = array();
		    	foreach($result as $r)
		    	{
		    		$partTypeIds[] = $r[0];
		    	}
		    	$inc_inactive = ($SearchActive!=1 ? true : false);
		    	$partTypes = Factory::service("PartType")->findByCriteria("pt.id in (".implode(",",$partTypeIds).")",array(),$inc_inactive,$pageNumber,$pageSize);
    		}
    	}
    	else
    	{
    		$partTypes=array();
			$errorMsg = 'No Part Type(s) as per search criteria.';
    	}

		$this->totalRows = Dao::getTotalRows();
		return array($partTypes,$errorMsg);

    }

    /**
     * Escape String
     *
     * @param unknown_type $string
     * @return unknown
     */
	private function escapeString($string)
	{
		if (!get_magic_quotes_gpc())
		{
			$string = addslashes($string);
		}
		return $string;
	}

	/**
	 * Get Value Type
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function getValueType($sender,$param)
	{
	    $editItem = $this->DataList->getEditItem();
	    $id = $sender->getSelectedValue();
	    $rowId = substr($sender->getID(),strpos($sender->getID(),"e")+1);

	    $chkParam = "alias".$rowId."Chk";
	    $txtParam = "alias".$rowId;

	    $editItem->$chkParam->setAttribute("style", "display:none");
	    $editItem->$txtParam->setAttribute("style", "display:none");
	    $ptat = Factory::service("PartTypeAliasType")->get($id);
	    if ($ptat instanceof PartTypeAliasType)
	    {
	        $ptatValueType = $ptat->getValueType();

	        if ($ptatValueType === StringUtils::VALUE_TYPE_BOOL)
	        {
	            $editItem->$chkParam->setStyle("display:in-line");
	            $editItem->$txtParam->setStyle("display:none");
	        }
	        else if ($ptatValueType == "string")
	        {
	            $editItem->$txtParam->setStyle("display:in-line");
	            $editItem->$chkParam->setStyle("display:none");
	        }
	    }
	}

}
?>
