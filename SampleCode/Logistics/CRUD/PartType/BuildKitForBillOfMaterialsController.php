<?php
/**
 * Build Kit For Bill of Materials Controller
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class BuildKitForBillOfMaterialsController extends CRUDPage
{
	/**
	 * @var defaultWarehouse
	 */
	private $defaultWarehouse=null;

	/**
	 * @var totalSubParts
	 */
	public $totalSubParts;

	/**
	 * @var bomPartsCount
	 */
	public $bomPartsCount;

	/**
	 * @var bomPartsQty
	 */
	private $bomPartsQty = array();

	/**
	 * @var kitPartsQty
	 */
	private $kitPartsQty = array();

	/**
	 * @var partGroupQty
	 */
	private $partGroupQty = array();

	/**
	 * @var partsInPartGroup
	 */
	private $partsInPartGroup = array();

	/**
	 * @var bomGroupParts
	 */
	private $bomGroupParts = array();

	/**
	 * @var deactiveCheck
	 */
	private $deactiveCheck = 0;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext="buildkits";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_buildKits";
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
       	$this->disableFields();
       	$this->ToLocationPanel->setAttribute("style", "display:none");
       	$this->partLocationLabel->setAttribute("style", "display:none");
       	if(!$this->IsPostBack || $param == "reload")
        {
        	$this->newPartPanel->Visible = false;
       		$this->existingPartPanel->Visible = false;
        	$this->resetPageFields();
			$this->DataList->EditItemIndex = -1;
		}

        $this->setDefaultWarehouse(Core::getUser());

    }

    /**
     * Search PartType
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
    public function searchPartType($sender, $param)
    {
   		$newKit = $this->newKit->getChecked();
   		//----- Reset Kit Selection -----
   		$this->newKit->Checked = 0;
		$this->oldKit->Checked = 0;
		$this->bomGroupParts = array();

		if($newKit == true)
   		{
   			if($this->selectedPartType->getSelectedValue() != "")
   			{
   				$partTypeId = $this->Page->selectedPartType->getSelectedValue();
   				$selectedPartType = Factory::service("PartType")->getPartType($partTypeId);
				if($selectedPartType instanceof PartType)
				{
					$partTypeAliasList = Factory::service("PartType")->searchPartTypeAliaseByPartTypeAliasTypeForPartType($partTypeId,1);
   					$partCode = $partTypeAliasList[0]->getAlias();
   					$partKitType = $selectedPartType->getKitType();
					if($partKitType instanceof KitType)
					{
						$this->mainPanel->Visible = true;
						$this->mainPanel->GroupingText = "New Kit";
						$this->BillOfMaterialsLabel->Text = "Bill of Materials for " . $selectedPartType->getName() . " ( " . $partCode . " ) <input type='hidden' id='bomPartTypeId' value='" . $selectedPartType->getId() . "'/> ";
						$this->BillOfMaterialsLabel->Visible = false;
						$this->getBomParts($selectedPartType);

						$this->installPanel->Visible = true;
						$this->KitType->Visible = true;
						$this->kitTypeLabel->Visible = true;
						$this->keepPartCheck->Visible = true;
    					$this->keepPartTypeLabel->Visible = true;

						$this->KitType->Text = $partKitType->getName();
						$this->newPanel->Visible = true;

						$this->hideFieldsOnInstallPanel();
						$this->toBeInstalledPartInstances_parentPartTypeId->Value = $partTypeId;
						$this->ToInstance1->Text = "";
						$this->ToInstance1->focus();

						$this->partTypeLabel->Text = $selectedPartType->getName() . " ( " . $partCode . " )";
						$this->newPartPanel->Visible = false;
						//$this->warehouseid->Value = "";
					}
				}
			}
			else
			{
				$this->selectedPartType->focus();
	   			return $this->errorOnPartSelectPanel("Please Select a Valid Part Type.");
			}
   		}
   		else
   		{
	   		$barcode = trim($this->partBCS->Text);
	   		if(empty($barcode))
	   		{
	   			$this->resetExistingPartFields();
				return $this->errorOnPartSelectPanel("Please enter a Valid BCS");
	   		}

	   		//search on BX label as well as serial no
	   		$partInstanceArray = Factory::service("PartInstance")->searchPartInstancesByPartInstanceAlias($barcode, array(PartInstanceAliasType::ID_SERIAL_NO, PartInstanceAliasType::ID_BOX_LABEL), true);
			if (empty($partInstanceArray))
			{
				$this->resetExistingPartFields();
				return $this->errorOnPartSelectPanel("No parts found for '$barcode'!");
			}
			else
			{
				if (count($partInstanceArray) < 1 && $partInstanceArray[0]->getQuantity()!= 1)
				{
					$this->resetExistingPartFields();
					return $this->errorOnPartSelectPanel("Multiple parts found.");
				}
				else
				{
					// get parttypeId for the provided bcs and check if the parttype is of type kit
			    	if($partInstanceArray[0] instanceof PartInstance)
		    		{
		    			$partInstance = $partInstanceArray[0];
		    			$partInstanceId = $partInstance->getId();
			    		$partKitType = $partInstance->getPartType()->getKitType();
			    		$partType = $partInstance->getPartType();

			    		//move this down here as is a better way of testing for serialised.
			    		if ($partType->getSerialised() == 0)
			    		{
			    			$this->resetExistingPartFields();
			    			return $this->errorOnPartSelectPanel("Must be a serialised part.");
			    		}

			    		if($partKitType instanceof KitType)
		    			{
		    				$partInstanceSerialNo = $this->getSerialNo($partInstanceId);
		    				$partTypeAliasList = Factory::service("PartType")->searchPartTypeAliaseByPartTypeAliasTypeForPartType($partType->getId(),1);
   							$partCode = $partTypeAliasList[0]->getAlias();

							if($partType instanceof PartType)
							{
								$this->mainPanel->Visible = true;
								$this->mainPanel->GroupingText = "Existing Kit";

								$this->BillOfMaterialsLabel->Text = "Bill of Materials for " . $partType->getName() . " ( " . $partCode . " ) <input type='hidden' id='bomPartTypeId' value='" . $partType->getId() . "'/>";
								$this->BillOfMaterialsLabel->Visible = false;
								$this->bomPartsQty = $this->getBomPartsQuantity($partType->getId());

								$this->toBeInstalledPartInstances_parentId->Value = $partInstanceId;
								$this->toBeInstalledPartInstances_parentPartTypeId->Value = $partType->getId();

								//check if the kittype of partinstance and parttype is same, if not update partinstance kittype to the parttype kittype.
								$this->checkAndUpdatePartInstanceKittype($partType,$partInstance);

								$this->ListBOMPanel->Visible = true;
							    $this->getPartsWithin($partInstance);
							    $this->loadDataList($this->kitDataList,$this->withinParts,$this->kitHeaderLabel,"Parts in Kit - ". $partType->getName() . " ( " . $partCode . " ):<input type='hidden' id='kitPartInstanceId' value='" . $partInstance->getId() . "'/><br/>");

							    $this->getBomParts($partType);

								$this->installPanel->Visible = true;
							    $this->ToInstance->Text = $partInstanceSerialNo;
							    $this->parentBcs->Value = $partInstanceSerialNo;

								$this->KitType->Text = $partKitType->getName();

								$this->KitType->Visible = true;
								$this->kitTypeLabel->Visible = true;


					          	$this->BillOfMaterialsLabel->Visible = true;
    							$this->DataList->Visible = true;
    							$this->kitPanel->Visible = true;

    							$this->oldPanel->Visible = true;
    							$this->newPanel->Visible = false;
    							$this->SearchPartsPanel->SearchInstance->focus();

    							$this->partTypeLabel->Text = $partType->getName() . " ( " . $partCode . " )";

    							$this->existingPartPanel->Visible = false;
    							$this->keepPartCheck->Visible = false;
    							$this->keepPartTypeLabel->Visible = false;

    							$this->ToLocationPanel->Visible = false;

    							$this->locationPanel->Visible = true;

    							//get warehouse crumbs
    							$partWarehouse = $partInstance->getWarehouse();
    							$warehouseCrumbs = Factory::service("Warehouse")->getWarehouseBreadCrumbs($partWarehouse,true);
								$this->partLocation->Text = $warehouseCrumbs;
							}
		    			}
			    		else
			    		{
			    			$this->resetExistingPartFields();
			    			return $this->errorOnPartSelectPanel("Selected Part is not Kit. Please select one with a kit type.");
			    		}
		    		}
				}
	    	}
   		}
    }

    /**
     * Reset Existing Part Fields
     *
     */
    private function resetExistingPartFields()
    {
    	$this->partBCS->Text = "";
		$this->partBCS->focus();
    }

    public function getChildPartsHtml($parentPiId)
    {
    	$html = '';

    	$daoQuery = new DaoReportQuery("PartInstance");
    	$daoQuery->column("id");
    	$daoQuery->where("pi.active=1 AND pi.parentid=" . $parentPiId);
    	$results = $daoQuery->execute(false);
    	if (!empty($results))
    	{
    		$parentPi = Factory::service("PartInstance")->get($parentPiId);
    		$parentPt = $parentPi->getPartType();
    		if ($parentPt->getSerialised())
    			$serial = $parentPi->getAlias();
    		else
    			$serial = $parentPt->getAlias(PartTypeAliasType::ID_BP);

    		$html .= '  <a href="javascript:void(0)" onclick="togglePartsWithin(this, ' . $parentPiId . ');" >View Parts Within ' . $serial . '</a>
	    				<div style="padding-left:20px;display:none;" id="childParts_' . $parentPiId . '">
				    		<table width="100%" class="DataList">
					    		<thead>
						    		<tr>
							    		<th width="15%">Serial Number</td>
							    		<th width="15%">Part Code</td>
							    		<th width="25%">Part Description</td>
							    		<th width="5%">Qty</td>
							    		<th width="*">Location</td>
						    		</tr>
					    		</thead>';

	    	foreach ($results as $row)
	    	{
	    		$pi = Factory::service("PartInstance")->get($row[0]);
	    		$pt = $pi->getPartType();
	    		if ($pt->getSerialised())
	    			$serial = $pi->getAlias();
	    		else
	    			$serial = $pt->getAlias(PartTypeAliasType::ID_BP);

	    		$piActiveFlag = (intval($pi->getActive()) === 1 ? '' : ' <img src="../../../themes/images/warning.png" title="This Part Instance is Deactivated." />');
	    		$alterPT = null;
	    		if(intval($pt->getActive()) === 0 && count($alertPTAs = Factory::service('PartTypeAlias')->findByCriteria('partTypeAliasTypeId = ? and alias = ?', array(PartTypeAliasType::ID_PREVIOUS_PARTCODE, trim($pt->getAlias())), false, 1, 1, array('PartTypeAlias.id' => 'desc'))) > 0 )
	    			$alterPT = $alertPTAs[0]->getPartType();
	    		$ptActiveFlag = (intval($pt->getActive()) === 1 ? '' : ' <img src="../../../themes/images/' . ($alterPT instanceof PartType ? 'red.PNG' : 'warning.png') . '" title="This PartType is Deactivated. ' . ($alterPT instanceof PartType ? 'Please use this PartType ' . $alterPT->getAlias() . ' instead.' : '') . '" />');
    			$html .= '<tr>
								<td width="15%">' . $serial . $piActiveFlag . '</td>
								<td width="15%">' . $pt->getAlias() . $ptActiveFlag . '</td>
								<td width="25%">' . $pt->getName() . '</td>
								<td width="5%">' . $pi->getQuantity() . '</td>
								<td width="*">' . $pi->getRootWarehouse() . '</td>
							</tr>';
	    		$html .= $this->getChildPartsHtml($pi->getId());
	    	}
	    	$html .= 		'</table>
	    				</div>';

    	}

    	if ($html == '')
    		return '';

    	return '<tr><td colspan="6">' . $html . '</td></tr>';
    }

   	/**
   	 * Gets a list of all parts and their quantities within a kit.
   	 *
   	 * @param PartInstance $partInstance
   	 */
	private function getPartsWithin(PartInstance $partInstance)
	{
		$array = array();
		$partTypeQtyArray = array();
		//$piIds = Factory::service("PartInstance")->getPartInstanceChildrenIds($partInstance);
		$kitType = $partInstance->getKitType();
		$this->kitPartsQty = array();
		$this->withinParts->Value = "";
		$this->partsInKit->Value = "";
		$pgArray = unserialize($this->partGroupsInBom->Value);

		$daoQuery = new DaoReportQuery("PartInstance");
		$daoQuery->column("id");
		$daoQuery->where("pi.parentid=" . $partInstance->getId());
		$results = $daoQuery->execute(false);
		$piIds = array();
		foreach ($results as $row)
			$piIds[] = $row[0];

		if(count($piIds)>0)
		{
			$daoQuery = new DaoReportQuery("PartInstance");
			$daoQuery->column("id");
			$daoQuery->column("quantity");
			$daoQuery->column("parttypeid");
			$daoQuery->where("pi.id in(".implode(",",$piIds).")");
			$results = $daoQuery->execute(false);

			foreach($results as $r)
			{
				$array[$r[0]] = $r[1];
				if(isset($this->kitPartsQty[$r[2]]))
					$this->kitPartsQty[$r[2]] = $this->kitPartsQty[$r[2]] + $r[1];
				else
					$this->kitPartsQty[$r[2]] = $r[1];
			}
			$this->totalSubParts = count($array);
			$this->withinParts->Value = serialize($array);
			$this->partsInKit->Value = serialize($this->kitPartsQty);
		}
		else
		{
			$kitActive = $partInstance->getActive();
			$this->hideDeactivateKitPanel();

			//display the deactivate kit pop-up if no parts found in kit
			if($kitActive == 1)
			{
				$parentId=$partInstance->getId();
				$this->kitId->Value = $parentId;

				if($kitType instanceof KitType)
				{
					$this->outer->Display="Hidden";
					$this->inner->Display="Dynamic";
				}
			}
			else
				$this->setErrorMessage("Kit '$partInstance' has been deactivated");
		}
	}

	/**
	 * Loads the parts within kit list into a DataList.
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
   		$pgArray = unserialize($this->partGroupsInBom->Value);
   		if($partInstanceIds!=false)
   		{
   			foreach($partInstanceIds as $partInstanceId=>$qty)
   			{
   				$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
   				if($partInstance instanceof PartInstance)
   				{
	   				$partTypeId = $partInstance->getPartType()->getId();

   					if($partInstance->getPartType()->getSerialised()==1)
   					{
   						$bxlabel = $partInstance->getAlias(PartInstanceAliasType::ID_BOX_LABEL);

   						$barcode = $partInstance->getAlias();
   						if($bxlabel)
   						{
   							$barcode .= " (" . 	$bxlabel . ")";
   						}
   					}
   					else
   					{
   						$bpAliasList = Factory::service("PartType")->searchPartTypeAliaseByPartTypeAliasTypeForPartType($partTypeId,2);
   						$barcode = count($bpAliasList)==0 ? "-" : $bpAliasList[0]->getAlias();
   					}

	   				$partcodes = Factory::service("PartType")->searchPartTypeAliaseByPartTypeAliasTypeForPartType($partTypeId,1);
	   				$partcode = count($partcodes)==0 ? "" : $partcodes[0]->getAlias();
	   				$alterPT = null;
	   				if(intval($partInstance->getPartType()->getActive()) === 0 && count($alertPTAs = Factory::service('PartTypeAlias')->findByCriteria('partTypeAliasTypeId = ? and alias = ?', array(PartTypeAliasType::ID_PREVIOUS_PARTCODE, trim($partcode)), false, 1, 1, array('PartTypeAlias.id' => 'desc'))) > 0 )
	   					$alterPT = $alertPTAs[0]->getPartType();
   					$partInstances[]= array(
	   									"partInstance"=>$partInstance,
	   									"qty"=>$qty,
	   									"barcode"=>$barcode,
	   									"partcode"=>$partcode,
	   									"id"=>$partInstance->getId(),
	   									"piActiveFlag"=>(intval($partInstance->getActive()) === 1 ? '' : '<img src="../../../themes/images/warning.png" title="This Part Instance is Deactivated." />'),
	   									"ptActiveFlag"=>(intval($partInstance->getPartType()->getActive()) === 1 ? '' : '<img src="../../../themes/images/' . ($alterPT instanceof PartType ? 'red.PNG' : 'warning.png') . '" title="This PartType is Deactivated. ' . ($alterPT instanceof PartType ? 'Please use this PartType ' . $alterPT->getAlias() . ' instead.' : '') . '" />')
	   									);


	   				$ptGroupList = $partInstance->getPartType()->getPartTypeGroups();
   					if(count($ptGroupList)>0 && $this->partGroupsInBom->Value != "")
					{
						foreach($ptGroupList as $pg)
						{
							if(in_array($pg->getId(),$pgArray))
							{
								if(!in_array($partInstanceId,$this->bomGroupParts))
								{
									if(isset($this->partGroupQty[$pg->getId()]))
									{
										if($this->partGroupQty[$pg->getId()] + $qty <= $this->bomPartsQty[$pg->getId()])
										{
											$this->partGroupQty[$pg->getId()] = $this->partGroupQty[$pg->getId()] + $qty;
											$this->bomGroupParts[] = $partInstanceId;
										}
									}
									else
									{
										if($qty <= $this->bomPartsQty[$pg->getId()])
										{
											$this->partGroupQty[$pg->getId()] = $qty;
											$this->bomGroupParts[] = $partInstanceId;
										}
									}
								}
							}
						}
					}
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
	 * Gets a list of all requied Part types and their quantities in BOM for the selected part type.
	 * It also display a small image at the end of each required part row. This image represents
	 * if the correct quantity of the required parts have been added to the kit. Green meams correct
	 * quantity of parts has been added. Orange - only some parts of the required parttype has been added.
	 * Red - No parts of the required parttype has been added.
	 *
	 * @param PartType $partType
	 */
	private function getBomParts(PartType $partType, $repeatPart=0)
	{
		$partTypeId = $partType->getId();
		$partTypeArray = array();
		$partsInPartGroup = array();
		$check = 0;

		$sql = "SELECT bom.id,
					bom.requiredparttypeid,
					bom.quantity,
					bom.comments,
					bom.requiredparttypegroupid
				FROM billofmaterials bom
				inner join parttype pt on (pt.id = bom.requiredparttypeid and pt.active = 1)
				WHERE bom.parttypeid = $partTypeId
				AND bom.active = 1";

		$results = dao::getResultsNative($sql);
		if(count($results)>0)
		{
			$this->bomPanel->Visible = true;
			$this->BillOfMaterialsLabel->Visible = true;

			foreach($results as $r)
			{
				$requiredPart = Factory::service("PartType")->getPartType($r[1]);
				$bomid = $r[0];
				$qty = $r[2];
				$comments = $r[3];
				$requiredPartGroupId = $r[4];


				if($requiredPart instanceof PartType)
				{
					$partAliasArray = Factory::service("PartType")->getPartTypeAliasByTypeForPartType($r[1],1);
					$partAlias = $partAliasArray[0];

					$partCode = $partAlias->getAlias();
					$partName = $partCode . " : " . $requiredPart->getName();
					$pId = $r[1];
					$isPartType = 1;
				}
				else
				{
					$ptGroup = Factory::service("PartTypeGroup")->get($requiredPartGroupId);

					if($ptGroup instanceof PartTypeGroup)
					{
						$partName = $ptGroup->getName();
						$pId = $r[4];
						$isPartType = 0;
					}
				}

				if($repeatPart == 0)
				{
					if($this->toBeInstalledPartInstances_parentId->Value != "")
						$check = $this->comparePartsInKitWithBom($pId,$isPartType);
					else
						$check = 0;
				}
				else
					$check = 0;

				$partTypeArray[]= array(
	   									"qty"=>$qty,
	   									"comments"=>$comments,
	   									"requiredPartName"=>$partName,
	   									"id"=>$bomid,
										"check"=>$check
	   									);
			}

   			$partTypeArray = array_reverse($partTypeArray);
   			$this->bomPartsCount = count($partTypeArray);

   			$this->DataList->DataSource = $partTypeArray;
	   		$this->DataList->DataBind();
		}
	}

	/**
	 * Checks if the requiredparttype from BOM is already in the kit or if it has been added to installing list. If it is and if the
	 * quantity matches then return 1, if only few parts of the required parttype has been added then it returns 2,
	 * and if no parts of the requied parttype has been added then it returns 0.
	 *
	 * @param unknown $requiredPartTypeId
	 * @return int
	 */
	private function comparePartsInKitWithBom($requiredPartId,$isPartType)
    {
    	$bomPartQty = 0;
    	$kitPartQty = 0;
    	$partGroupQty = 0;
    	$partGroupIdArray = array();
    	$kitPartInstanceId = $this->toBeInstalledPartInstances_parentId->Value;
    	$kitPartInstance = Factory::service("PartInstance")->getPartInstance($kitPartInstanceId);
    	$childParts = Factory::service("PartInstance")->getChilrenForPartInstance($kitPartInstance);

    	if(isset($this->bomPartsQty[$requiredPartId]))
    		$bomPartQty = $this->bomPartsQty[$requiredPartId];
    	if(isset($this->kitPartsQty[$requiredPartId]))
    		$kitPartQty = $this->kitPartsQty[$requiredPartId];
    	if(isset($this->partGroupQty[$requiredPartId]))
    		$partGroupQty = $this->partGroupQty[$requiredPartId];

    	$installListArray = unserialize($this->toBeInstalledPartInstances->Value);
    	$installList = unserialize($this->installPartList->Value);

    	// Check if required part has already been added to kit.
    	// If so check if the kit part quantity >= the bom part quantity.

    	if(count($childParts)>0)
    	{
			foreach($childParts as $child)
			{
				if($isPartType == 1)
				{
					$childPartTypeId = $child->getPartType()->getId();
					if($childPartTypeId == $requiredPartId)
					{
						if($kitPartQty >= $bomPartQty)
							return 1;
						else
						{
							//return 2;

							//------- Check if the required part has been added to Install Part List --------
					    	// If so check if the install part quantity >= the bom part quantity.
							if(count($installList)>0 && $installList != false)
					    	{
					    		foreach($installList as $pId =>$q)
					    		{
					    			if($pId == $requiredPartId)
					    			{
					    				$qty = $q + $kitPartQty;
					    				if($qty >= $bomPartQty)
						    				return 1;
					    			}
					    		}
					    	}
				    		return 2;
						}
					}
				}
				else
				{   // if requied parttypegroup in list
					$childPartTypeGroupList = $child->getPartType()->getPartTypeGroups();
					$partGroupIdArray = $this->getPartTypeGroupList($childPartTypeGroupList);

					if(in_array($requiredPartId,$partGroupIdArray) && $partGroupQty != 0)
	    			{
	    				if($partGroupQty >= $bomPartQty)
	    					return 1;
	    				else
	    				{
	    					//return 2;
	    					//------- Check if the required part has been added to Install Part List --------
					    	// If so check if the install part quantity >= the bom part quantity.
	    					if(count($installListArray)>0 && $installListArray != false)
	    					{
		    					foreach($installListArray as $piId=>$q)
				    			{
				    				$installPart = Factory::service("PartInstance")->getPartInstance($piId);
				    				$installPartGroupList = $installPart->getPartType()->getPartTypeGroups();
				    				$partGroupIdArray = $this->getPartTypeGroupList($installPartGroupList);

				    				if(in_array($requiredPartId,$partGroupIdArray) && $partGroupQty != 0)
				    				{
				    					if($partGroupQty >= $bomPartQty)
				    					{
				    						return 1;
				    					}
				    				}
				    			}
	    					}
    						return 2;
	    				}
	    			}
				}
			}
    	}

    	//------- Check if the required part has been added to Install Part List --------
    	// If so check if the install part quantity >= the bom part quantity.

    	if(count($installList)>0 && $installList != false)
    	{
    		if($isPartType == 1)
    		{
    			foreach($installList as $pId =>$q)
    			{
    				if($pId == $requiredPartId)
    				{
    					if($q >= $bomPartQty)
	    					return 1;
	    				else
	    					return 2;
    				}
    			}
    		}
    	}

    	if(count($installListArray)>0 && $installListArray != false)
    	{
    		foreach($installListArray as $piId=>$q)
    		{
    			$installPart = Factory::service("PartInstance")->getPartInstance($piId);
    			$installPartGroupList = $installPart->getPartType()->getPartTypeGroups();
    			$partGroupIdArray = $this->getPartTypeGroupList($installPartGroupList);

    			if(in_array($requiredPartId,$partGroupIdArray) && $partGroupQty != 0)
    			{
    				if($partGroupQty >= $bomPartQty)
    					return 1;
    				else
    					return 2;
    			}
    		}
    	}
    	return 0;
    }

    /**
     * Returns an array of all requiedparttype and their quantity from a BOM for the
     * selected parttype.
     *
     * @param unknown_type $partTypeId
     * @return array
     */
	private function getBomPartsQuantity($partTypeId)
	{
		$array = array();
		$partGroupsInBom = array();

		$sql = "SELECT requiredparttypeid,
						quantity,
						requiredparttypegroupid
				FROM billofmaterials
				WHERE parttypeid = $partTypeId
				AND active = 1";

		$results = dao::getResultsNative($sql);
		if(count($results)>0)
		{
			foreach($results as $r)
			{
				if($r[0] != NULL)
					$array[$r[0]]=$r[1];
				else
				{
					$array[$r[2]]=$r[1];

					if(!in_array($r[2],$partGroupsInBom))
						$partGroupsInBom[] = $r[2];
				}
			}
		}
		$this->partGroupsInBom->Value = serialize($partGroupsInBom);
		return $array;
	}

	/**
	 * Kit Selection - New kit or existing kit
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $params
	 */
	public function kitSelection($sender,$params)
    {
    	$newKit = $this->newKit->getChecked();
    	if($newKit == true)
    	{
    		$this->newPartPanel->Visible = true;
    		$this->existingPartPanel->Visible = false;
    		$this->kitCheck->Value = 1;
    		$this->installPanel->Visible = false;
    		$this->errorPanel->Visible = false;
    		$this->selectedPartType->Text = "";
    		$this->selectedPartType->focus();

    		$this->selectLocationLabel->Visible = true;
    		$this->MovePartTechnicianWrapper->Visible = true;

    		$this->MovePartTreeLocation->Checked = false;
    		$this->MovePartDefaultLocation->Checked = false;
    		$this->warehouseid->Value = "";

    		$this->locationPanel->Visible = false;
    		$this->ToLocationPanel->Visible = true;
    		$this->selectedWarehouseId->Value = "";
    	}
    	else
    	{
    		$this->newPartPanel->Visible = false;
    		$this->existingPartPanel->Visible = true;
    		$this->kitCheck->Value = 0;
    		$this->installPanel->Visible = false;
    		$this->errorPanel->Visible = false;
    		$this->partBCS->focus();

    		$this->selectLocationLabel->Visible = false;
    		$this->MovePartTechnicianWrapper->Visible = false;

    		$this->locationPanel->Visible = true;
    		$this->ToLocationPanel->Visible = false;
    		$this->selectedWarehouseId->Value = "";
    	}
    	$this->kitPanel->Visible = false;
    	$this->bomPanel->Visible = false;
    	$this->resetPageFields();

    }

    /**
     * Disable Fields
     *
     */
    private function disableFields()
    {
       	if($this->toBeInstalledPartInstances_parentPartTypeId->Value == "")
       		$this->installPanel->Visible = false;
       	else
       	{
       		$this->installPanel->Visible = true;
       	}

       	$this->outer1->Display="None";
		$this->inner1->Display="None";
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
       	$this->partBCS->Text = "";

       	$this->disableInstallPanel();

       	$this->toBeInstalledPartInstances->Value="";
       	$this->toBeInstalledPartInstances_parent->Value="";
       	$this->toBeInstalledPartInstances_parentId->Value="";
       	$this->toBeInstalledPartInstances_parentPartTypeId->Value="";
       	$this->withinParts->Value="";
       	$this->partsInKit->Value = "";

       	//$this->searchResult_multipleFound_Panel->Visible=false;
       	$this->DataList->DataSource = array();
       	$this->DataList->DataBind();

       	$this->kitTypeLabel->Visible = true;
       	$this->KitType->Visible = true;

       	$this->SearchPartsPanel->showPanel();


       	$this->partGroupsInBom->Value= "";
       	$this->mainPanel->Visible = false;
       	$this->installButton->Display = "None";

       	$this->boxLabelPanel->Visible = false;
       	$this->printBoxLabelBox->Checked = false;
       	$this->installPartList->Value = "";

       	$this->totalErrorsWhileInstalling->Value = "";
    	$this->errorsWhileInstalling->Value = "";
    	$this->keepPartCheck->Checked = false;
	}

	/**
	 * Disable Install Panel
	 *
	 */
	private function disableInstallPanel()
	{
		$this->FromCandidateDataList_label->Text = "";
       	$this->FromCandidateDataList->DataSource = array();
       	$this->FromCandidateDataList->DataBind();
	}

	/**
	 * OnError
	 *
	 * @param unknown_type $errorMessage
	 * @return unknown
	 */
	private function onError($errorMessage)
	{
		$this->setErrorMessage($errorMessage);
		$this->loadPartsList();
		$this->disableFields();
		$this->hideDeactivateKitPanel();
		if($this->toBeInstalledPartInstances_parentId->Value == "")
		{
			$this->ToInstance1->Text = "";
			$this->ToInstance1->focus();
		}
		else
		{
			$this->SearchPartsPanel->SearchInstance->Text = "";
			$this->SearchPartsPanel->SearchInstance->focus();
		}
		return false;
	}

	/**
	 * Error On Part SelectPanel
	 *
	 * @param unknown_type $errorMessage
	 * @return unknown
	 */
	private function errorOnPartSelectPanel($errorMessage)
	{
		$this->setErrorMessage($errorMessage);
		$this->loadPartsList();
		$this->disableFields();
		return false;
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
    	$selectedLocationId = "";
    	if($this->selectedWarehouseId->Value == "")
    	{
	    	if($this->MovePartDefaultLocation->Checked == true || ($this->MovePartDefaultLocation->Checked == false && $this->MovePartTreeLocation->Checked == false))
	    	{
	    		$defaultPartLocation = "";
	    	}
	    	else
	    	{
	    		$defaultPartLocation = $this->warehouseid->Value;
	    	}
    	}

    	$alias = trim($this->ToInstance1->Text);
       	$foundKitWarehouse = 0;
       	$destinationWarehouse = "";

		$this->SearchPartsPanel->hidePanel();

        $partType = Factory::service("PartType")->getPartType($this->toBeInstalledPartInstances_parentPartTypeId->Value);
        $partKitType = $partType->getKitType();
        if($this->selectedWarehouseId->Value != "")
        {
        	$selectedWarehouseIds = explode('/', $this->selectedWarehouseId->Value);
			$selectedWarehouseId = end($selectedWarehouseIds);
        	$destinationWarehouse = Factory::service("Warehouse")->getWarehouse($selectedWarehouseId);
        }
        else
        {
	        if($defaultPartLocation == "")
	        {
		        $warehouse = $this->defaultWarehouse;
				if($warehouse->getParts_allow() == 0)
				{
					$sql = "SELECT ware.id
							FROM warehouse ware
							LEFT JOIN warehousealias wa ON (wa.active = 1 AND wa.warehouseId = ware.id
																		  AND wa.warehouseAliasTypeId = 1)
							WHERE ware.active = 1
							AND wa.alias = 'kitsinbuild'
							AND ware.parentId = ".$warehouse->getId();

				    $results = Dao::getResultsNative($sql);
					if(count($results)<=0)
					{
						$kitsInBuildWarehouse = new Warehouse();
						$kitsInBuildWarehouse->setName("Kits in Build");
						$kitsInBuildWarehouse->setParts_allow(true);
						$kitsInBuildWarehouse->setMoveable(false);
						$kitsInBuildWarehouse->setFacility(null);

						$kitsInBuildWarehouse->setWarehouseCategory(Factory::service("WarehouseCategory")->getWarehouseCategoryForNode());
						Factory::service("Warehouse")->addWarehouse($warehouse,$kitsInBuildWarehouse,UserAccountService::isSystemAdmin());

						$currentUserAccountId = Core::getUser()->getId();
						$sql = "insert into warehousealias (`alias`,`warehouseAliasTypeId`,`warehouseId`,`created`,`createdById`,`updated`,`updatedById`)
								value('kitsinbuild',1,".$kitsInBuildWarehouse->getId().",NOW(),$currentUserAccountId,NOW(),$currentUserAccountId)";
						Dao::execSql($sql);

						$destinationWarehouse = $kitsInBuildWarehouse;
					}
					else
						$destinationWarehouse = Factory::service("Warehouse")->getWarehouse($results[0][0]);
				}
				else
					$destinationWarehouse = $warehouse;

				$warehouseCrumbs = Factory::service("Warehouse")->getWarehouseBreadCrumbs($destinationWarehouse,true);
				$this->partLocation->Text = $warehouseCrumbs;
	        }
	        else
	        {
	        	$selectedWarehouseIds = explode('/', $this->warehouseid->Value);
				$selectedWarehouseId = end($selectedWarehouseIds);

				$query = new DaoReportQuery("Warehouse");
				$query->column("parts_allow");
				$query->where("id=$selectedWarehouseId");

				$result = $query->execute();

				$destinationWarehouse = Factory::service("Warehouse")->getWarehouse($selectedWarehouseId);
				$warehouseCrumbs = Factory::service("Warehouse")->getWarehouseBreadCrumbs($destinationWarehouse,true);
				$this->partLocation->Text = $warehouseCrumbs;

				if ($result[0][0] == "1")
				{
					$this->partLocation->Visible = true;
					$this->partLocationLabel->setAttribute("style", "display:block");
					$this->ToLocationPanel->setAttribute("style", "display:none");
				}
				else
				{
					$this->loadPartsList();
					$this->ToLocationPanel->setAttribute("style", "display:block");
					$this->MovePartTreeLocation->Checked = true;
					$this->partLocationLabel->setAttribute("style", "display:block");
					$this->MovePartDefaultLocation->Checked = false;

					return $this->setErrorMessage("Selected Warehouse '$warehouseCrumbs' doesn't allow parts.");
				}
	        }
        }

        if($destinationWarehouse != "")
        {
	       	$status = Factory::service("PartInstanceStatus")->get(1);
	       	if($alias == "")
	       	{
	       		$this->kitPanel->Visible = false;
	       		$this->getBomParts($partType,1);
	       		$this->ToInstance1->Text = "";
				$this->ToInstance1->focus();
	       		return $this->setErrorMessage("Please Enter a valid Serialised Part",1);
	       	}

	       	if(strstr($alias,"BCS")===false && strstr($alias,"BS")===false&& strstr($alias,"BX")===false&& strstr($alias,"BOX")===false)
	       	{
	       		$this->kitPanel->Visible = false;
	       		$this->getBomParts($partType,1);
	       		$this->ToInstance1->Text = "";
				$this->ToInstance1->focus();
	       		return $this->setErrorMessage("Must be a serialised part.",1);
	       	}
			try{BarcodeService::validateBarcode($alias, array(BarcodeService::BARCODE_REGEX_CHK_REGISTRABLE, BarcodeService::BARCODE_REGEX_CHK_PART_INSTANCE));}
			catch(Exception $ex)
			{
				$this->kitPanel->Visible = false;
	       		$this->getBomParts($partType,1);
	       		$this->ToInstance1->Text = "";
				$this->ToInstance1->focus();
				return $this->setErrorMessage("Invalid BS Number",1);
			}

	       	$sql="SELECT id
	       		  FROM partinstancealias
	       		  WHERE alias ='".$alias."'
	       		  AND active = 1";

	       	$result = Dao::getResultsNative($sql);
	       	if(count($result)>0)
	       	{
	       		$this->kitPanel->Visible = false;
	       		$this->getBomParts($partType,1);
	       		$this->ToInstance1->Text = "";
				$this->ToInstance1->focus();
	       		return $this->setErrorMessage("Serial Number ($alias) Exists Already (PI ID={$result[0][0]})!");
	       	}
	       	else
	       	{
	       		if ($partType->getSerialised())
	       		{
	       			// creating new part instance
					$partInstance = new PartInstance();
					$partInstance->setPartType($partType);
					$partInstance->setPartInstanceStatus($status);

					$partInstance->setWarehouse($destinationWarehouse);
					$partInstance->setKitType($partKitType);
					$partInstance->setQuantity(1);
					Factory::service("PartInstance")->save($partInstance);
					Factory::service("PartInstance")->makeRootPartInstance($partInstance);

					$partInstanceAlias = new PartInstanceAlias();
					$partInstanceAlias->setPartInstance($partInstance);
					$partInstanceAlias->setAlias($alias);

					$partInstanceAlias->setPartInstanceAliasType(Factory::service("PartInstanceAliasType")->get(1));
					Factory::service("PartInstanceAlias")->save($partInstanceAlias);
					$comments = "Part Instance Created on Fly using Build Kits";

					try
					{
						Factory::service("PartInstance")->movePartInstanceToWarehouse($partInstance, $partInstance->getQuantity(), $destinationWarehouse, false, null, $comments, false, null, false);
						$infoMsg = "Part registered successfully.";
					}
	       			catch(Exception $e)
					{
						$this->kitPanel->Visible = false;
	       				$this->getBomParts($partType,1);
	       				$this->ToInstance1->Text = "";
						$this->ToInstance1->focus();
						return $this->setErrorMessage($e->getMessage()."<br/>");
					}

					//$this->warehouseid->Value = "";
					$this->selectLocationLabel->Visible = false;
					$this->MovePartTechnicianWrapper->Visible = false;

					//print box label
					$boxLabelMsg = "";
					if($this->printBoxLabelBox->Checked==1)
						$boxLabelMsg = $this->printBoxLabel($alias,$partInstance);

					$this->setInfoMessage($infoMsg . $boxLabelMsg);
					$this->SearchPartsPanel->SearchInstance->focus();

					$this->SearchPartsPanel->showPanel();

					$this->toBeInstalledPartInstances_parentId->Value = $partInstance->getId();
					$this->toBeInstalledPartInstances_parentPartTypeId->Value = $partInstance->getPartType()->getId();
					$this->loadPartsList();

					$this->oldPanel->Visible = true;
					$this->newPanel->Visible = false;
					$this->ToInstance->Text = $alias;
					$this->locationPanel->Visible = true;
					if($this->selectedWarehouseId->Value == "")
						$this->selectedWarehouseId->Value = $this->warehouseid->Value;

	       		}
	       	}
        }
    }

    /**
     * Hide Deactivate Kit Panel
     *
     */
    private function hideDeactivateKitPanel()
    {
    	$this->outer->Display="None";
		$this->inner->Display="None";
    }

    /**
     * Maybe Non Serialised Code
     *
     * @param unknown_type $code
     * @return unknown
     */
	private function maybeNonSerialisedCode($code)
    {
    	if (preg_match("/^\s*\d{7}\s*$/", $code) == 1 ||
       		preg_match("/^\s*BCP\d{8}\s*$/i", $code) == 1 ||
       		preg_match("/^\s*BP\d{8}\w\s*$/i", $code) == 1)
			return true;
		return false;
    }

    /**
     * Set Default Warehouse
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
			$this->setErrorMessage($person->getFullName().' requires a default warehouse.');
			return;
		}
		$this->defaultWarehouse = $defaultWarehouse;
		$this->setErrorMessage('');
	}

	/**
	 * Returns the serialno of the selected partinstance.
	 *
	 * @param unknown_type $partInstanceId
	 * @return unknown
	 */
	private function getSerialNo($partInstanceId)
	{
		$serialNo = "";
		$pia =  Factory::service("PartInstanceAlias")->findByCriteria("partinstanceid=$partInstanceId and partinstancealiastypeid = 1");
		if(count($pia)>0)
		{
			foreach($pia as $p)
			{
				return $p->getAlias();
			}
		}
	}

 	/**
 	 * Add selected partinstance to to the installing part list.
 	 * Checks if the selected partinstance is already in another part or if it is on a transitnote,
 	 * if so it cannot add the partinstance to the kit.
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
    		$this->setErrorMessage("There is a Facility Request against part ".$partInstance);
    	}

    	if($partInstance->getId() == $this->toBeInstalledPartInstances_parentId->Value)
    		return $this->onError("Can't install a part $partInstance to itself.");

    	if($partInstance->getWarehouse() instanceof Warehouse)
    	{
	    	$transiteNotes = Factory::service("TransitNote")->findByCriteria("tn.transitNoteLocationId=?",array($partInstance->getWarehouse()->getId()));
	    	if(count($transiteNotes)>0)
	    		return $this->onError("Part($partInstance) is on transit note ".$transiteNotes[0].". Transit note must be reconciled before installation.");
    	}

    	$parent  =$partInstance->getDirectParent();
    	if( $parent instanceof PartInstance)
    		return $this->onError("Part ($partInstance) is within another part ($parent). It cannot be installed independently.");

    	$array[$partInstance->getId()] = $qty;
    	$hiddenField->Value = serialize($array);

    	//$this->searchResult_multipleFound_Panel->Visible = false;
    	$this->SearchPartsPanel->SearchInstance->Text = "";
    	//$this->loadPartsList(); ------- hima not sure
    }

    /**
     * Loads and displays BOM DataList, Kit DataList if kit already exists and
     * install part panel
     *
     */
    private function loadPartsList()
    {
    	$partInstanceId = $this->toBeInstalledPartInstances_parentId->Value;
    	$selectedPartTypeId = $this->toBeInstalledPartInstances_parentPartTypeId->Value;
    	$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
    	$selectedPartType = Factory::service("PartType")->getPartType($selectedPartTypeId);
    	$this->bomGroupParts = array();
    	$this->partGroupQty = array();

    	if($selectedPartType instanceof PartType)
    	{
    		$this->bomPartsQty = $this->getBomPartsQuantity($selectedPartTypeId);
    		if($this->kitCheck->Value == 0 || $partInstance instanceof PartInstance)
    		{
    			$this->getPartsWithin($partInstance);
    			$pCode = Factory::service("PartType")->getPartTypeAliasByTypeForPartType($selectedPartTypeId,1);
				$this->loadDataList($this->kitDataList,$this->withinParts,$this->kitHeaderLabel,"Parts in Kit - ". $selectedPartType->getName() . " ( " . $pCode[0] . " ):<br/>");
				$this->kitPanel->Visible = true;
    		}
    		// Load installing part list
			$this->loadDataList($this->FromCandidateDataList,$this->toBeInstalledPartInstances,$this->FromCandidateDataList_label,"Parts to be installed:");
			$this->updateToBeInstalledPartsList();
    		$this->getBomParts($selectedPartType);

			$this->installPanel->Visible = true;
			if(	$this->kitCheck->Value == 0)
				$this->ToInstance->Text = $this->parentBcs->Value;

			if($this->toBeInstalledPartInstances->Value!="")
	   		{
	   			$this->installButton->Text="Add Part(s) to Kit &raquo;";
	   			$this->installButton->Display="Dynamic";
	   		}
	   		else
	   			$this->installButton->Display="None";

	   		$this->bomPartsQuantity->Value = serialize($this->bomPartsQty);
	   		$this->kitPartsQuantity->Value = serialize($this->kitPartsQty);
	   		$this->partGroupQuantity->Value = serialize($this->partGroupQty);
    	}
    	if($this->toBeInstalledPartInstances->Value != "" || $this->ToInstance1->Text != "" || $this->SearchPartsPanel->PartsAvailablePanel->Visible == true)
    	{
    		if($this->deactiveCheck == 1)
    		{
    			$this->outer->Display="Hidden";
				$this->inner->Display="Dynamic";
    		}
    		else
    		{
    			$this->outer->Display="None";
				$this->inner->Display="None";
    		}
    		$this->deactiveCheck = 0;
    	}

		$this->loadErrorList();

    }

    /**
     * Load Error List
     *
     */
    private function loadErrorList()
    {
    	if($this->totalErrorsWhileInstalling->Value != 0)
    	{
    		$this->errorPanel->Visible = true;
    		$errArray = unserialize($this->errorsWhileInstalling->Value);
    		$errList = array();
    		foreach($errArray as $pInstanceId => $err)
    		{
    			$partCode = "";
				$barCode = "";

				$pInstance = Factory::service("PartInstance")->getPartInstance($pInstanceId);
				$partType = $pInstance->getPartType();

				if($partType instanceof PartType)
				{
					$isSerializedPartType = $partType->getSerialised();
					$partTypeAlias = Factory::service("PartType")->getPartTypeAliasByTypeForPartType($partType->getId(),1);

					if($partTypeAlias[0] instanceof PartTypeAlias)
					{
						$partCode = $partTypeAlias[0]->getAlias();
					}

					if($isSerializedPartType == 1)
					{
						$partInstanceAlias = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($pInstanceId, 1);

						if($partInstanceAlias[0] instanceof PartInstanceAlias)
							$barCode = $partInstanceAlias[0]->getAlias();
					}
				}
				$errList[]= array(
   									"barcode"=>$barCode,
   									"partcode"=>$partCode,
   									"id"=>$pInstanceId,
									"error"=>$err
   									);
    		}
   			$this->ErrorDataList->DataSource = $errList;
   			$this->ErrorDataList->DataBind();
    	}
    	else
   			$this->errorPanel->Visible = false;
    }

    /**
     * Update To Be InstalledParts List
     *
     */
    private function updateToBeInstalledPartsList()
    {
    	$installPartsList = unserialize($this->toBeInstalledPartInstances->Value);
    	$installArray = array();
    	if($installPartsList != false)
    	{
	    	foreach($installPartsList as $pId=>$q)
	    	{
	    		$partInstance = Factory::service("PartInstance")->getPartInstance($pId);
	    		$partTypeId = $partInstance->getPartType()->getId();

	    		if(isset($installArray[$partTypeId]))
	    			$installArray[$partTypeId] = $installArray[$partTypeId] + $q;
	    		else
	    			$installArray[$partTypeId] = $q;

	    	}
			$this->installPartList->Value = serialize($installArray);
    	}
    	else
    		$this->installPartList->Value = "";

    }

	/**
	 * Returns count of the total number of parts to be installed.
	 *
	 * @return int
	 */
    public function getTotalPartsToBeInstalled()
    {
    	$array = unserialize($this->toBeInstalledPartInstances->Value);
    	return count($array);
    }

	/**
	 * Remove Part from Installing List.
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
    public function removeFromInstallingList($param)
    {
    	$partInstanceId = $param->CommandParameter;
    	$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
   		if($partInstance instanceof PartInstance)
   		{
   			$this->removePartInstanceFromList($partInstance,$this->toBeInstalledPartInstances);
   			$this->SearchPartsPanel->SearchInstance->focus();
   		}
   		$this->hideDeactivateKitPanel();
    }



	/**
	 * Removes the selected partInstance from the intalling part list.
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
    	{
    		$hiddenField->Value="";

    	}
    	else
    		$hiddenField->Value = serialize($array);

    	if($this->totalErrorsWhileInstalling->Value != 0)
    	{
    		$errArray = unserialize($this->errorsWhileInstalling->Value);
    		if(isset($errArray[$partInstance->getId()]))
    			unset($errArray[$partInstance->getId()]);

    		$this->totalErrorsWhileInstalling->Value = count($errArray);
    		$this->errorsWhileInstalling->Value = serialize($errArray);

    		$this->loadErrorList();
    	}
    	$this->loadPartsList();
    }


 	public function finishProcessing()
    {
    	$this->jsLbl->Text = "<script type=\"text/javascript\">finishProcessingBuildKits('" . $this->BuildKitsPartsMessage->Value . "','" . $this->BuildKitsPartsError->Value . "');</script>";
    }


	/**
	 * Finish Processing BuildKits Parts
	 */

    public function finishProcessingBuildKits()
    {
    	$successMessage = "";
    	$errorMessage = "";
    	$toPart = null;
    	$errList = null;
    	$partInstanceErrList = null;

    	$toPartsId = $this->toBeInstalledPartInstances_parentId->Value;
		$toPart =  Factory::service("PartInstance")->getPartInstance($toPartsId);

		if($this->BuildKitsPartInstanceErrList->Value)
		{
			$partInstanceErrList = unserialize($this->BuildKitsPartInstanceErrList->Value);
		}

    	if($this->BuildKitsErrList->Value)
		{
			$errList = unserialize($this->BuildKitsErrList->Value);
		}

    	if(count($errList)== 0)
    	{
			$successMessage .= "Selected parts succesfully installed in ". $toPart;
			if($this->keepPartCheck->Checked == false)
			{
				$this->resetPageFields();

				$this->bomPanel->Visible = false;
				$this->kitPanel->Visible = false;
				$this->installPanel->Visible = false;
			}
			else
			{
				$this->disableInstallPanel();
				$this->oldPanel->Visible = false;
				$this->newPanel->Visible = true;

				$this->SearchPartsPanel->SearchInstance->Text = "";
       			$this->ToInstance1->Text = "";

       			$this->toBeInstalledPartInstances->Value="";

       			$this->withinParts->Value="";
       			$this->partsInKit->Value = "";

		       	$this->SearchPartsPanel->hidePanel();

		       	$this->installButton->Display = "None";
		       	$this->installPartList->Value = "";

		       	$this->totalErrorsWhileInstalling->Value = "";
		    	$this->errorsWhileInstalling->Value = "";

		    	$this->toBeInstalledPartInstances->Value = "";
    			$this->installPartList->Value = "";

		    	$this->getBomParts($toPart->getPartType(),1);
				$this->bomPanel->Visible = true;
				$this->kitPanel->Visible = false;
				$this->installPanel->Visible = true;

				$this->locationPanel->Visible = true;
				$this->selectLocationLabel->Visible = false;
    			$this->MovePartTechnicianWrapper->Visible = false;
    			$this->ToLocationPanel->setAttribute("style", "display:none");
			}
    	}
    	else
    	{

    		$errorMessage .= "Error while installing parts. All Non-Installed parts are listed in 'Parts to be installed' list.";

    		$this->errorPanel->Visible = true;
    		$this->errorsWhileInstalling->Value = serialize($partInstanceErrList);
    		$this->ErrorDataList->DataSource = $errList;
   			$this->ErrorDataList->DataBind();
   			$this->totalErrorsWhileInstalling->Value = count($errList);

    		$installListArray = unserialize($this->toBeInstalledPartInstances->Value);
    		$this->loadPartsList();
    	}

		if($partInstanceErrList)
		{
			foreach($partInstanceErrList as $partInstanceId => $error)
			{
				$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
	    		if($partInstance instanceof PartInstance)
	    		{
	    			$errorMessage .= "<br />" . "Part: " . $partInstance->getAlias() . " Error: " .  $error;
	    		}
			}
		}



    	if($this->BuildKitsPartsError->Value)
    	{
    		$errorMessage .= "<br />" . $this->BuildKitsPartsError->Value;
    	}

    	if($errorMessage)
    	{
    		$this->setErrorMessage($errorMessage);
    	}

    	if($this->BuildKitsPartsMessage->Value)
    	{
    		$successMessage .= "<br />" . $this->BuildKitsPartsMessage->Value;
    	}

    	if($successMessage)
    	{
    		$this->setInfoMessage("<br />" . $successMessage);
    	}

    	$this->BuildKitsPartsMessage->Value = "";
    	$this->BuildKitsPartsError->Value = "";
    }




    /**
     * Process Build Kits
     */

    public function  processBuildKits()
    {

    	$toPart = null;
    	$toPartsId = $this->toBeInstalledPartInstances_parentId->Value;
		$toPart =  Factory::service("PartInstance")->getPartInstance($toPartsId);
		$partInstance = null;
    	//get all parts that are to be installed to $toPart
    	$fromParts =  unserialize($this->toBeInstalledPartInstances->Value);
    	$partInstanceErrList = array();


    	if($this->BuildKitsPartInstanceErrList->Value != "")
    	{
    		$partInstanceErrList = unserialize($this->BuildKitsPartInstanceErrList->Value);
    	}



        // get the correct PartType Kit Type
    	$kitType = $toPart->getPartType()->getKitType();

    	try {
	    	//install all parts

	    	foreach($fromParts as $partInstanceId => $qty)
	    	{

	    		if(isset($partInstanceErrList[$partInstanceId]))
		    		continue;


	    		$error = "";
	    		$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
	    		if(!$partInstance instanceof PartInstance)
	    			continue;

	    		if($error == "")
	    			$error = $this->performInstall($partInstance, $qty, $toPart, $kitType);


	    		//if there is no error when performInstall
				if($error=="" || $error == "NULL")
				{

					unset($fromParts[$partInstanceId]);
					if(count($fromParts)==0)
						$this->toBeInstalledPartInstances->Value = "";
					else
						$this->toBeInstalledPartInstances->Value = serialize($fromParts);


					if($partInstance->getPartType()->getSerialised())
					{
						$this->BuildKitsPartsMessage->Value .= "Successfully installed " . $partInstance->getAlias() . "<br>";
					}
					else
					{
						$this->BuildKitsPartsMessage->Value .= "Successfully installed " . $partInstance->getPartType()->getAlias(PartTypeAliasType::ID_BP) . "<br>";
					}

					if(count($fromParts) == 0)
			    	{
			    		$this->BuildKitsPartsMessage->Value .= "<br>Selected parts succesfully installed.";
			    		return array('stop' => true);
			    	}
				}
				else
				{
					$partCode = "";
					$barCode = "";
					$partType = $partInstance->getPartType();
					if($partType instanceof PartType)
					{
						$isSerializedPartType = $partType->getSerialised();
						$partTypeAlias = Factory::service("PartType")->getPartTypeAliasByTypeForPartType($partType->getId(),1);
						if($partTypeAlias[0] instanceof PartTypeAlias)
						{
							$partCode = $partTypeAlias[0]->getAlias();
						}

						if($isSerializedPartType == 1)
						{
							$partInstanceAlias = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($partInstanceId, 1);
							if($partInstanceAlias[0] instanceof PartInstanceAlias)
								$barCode = $partInstanceAlias[0]->getAlias();
						}
					}
					$partInstanceErrList = array();


	   				if($this->BuildKitsPartInstanceErrList->Value != "")
	   				{
	   					$partInstanceErrList = unserialize($this->BuildKitsPartInstanceErrList->Value);
	   				}
	   				if($this->BuildKitsErrList->Value != "")
	   				{
	   					$errList = unserialize($this->BuildKitsErrList->Value);
	   				}

	   				$errList[]= array(
	   									"barcode"=>$barCode,
	   									"partcode"=>$partCode,
	   									"id"=>$partInstance->getId(),
										"error"=>$error
	   									);


	   				$partInstanceErrList[$partInstanceId] = $error;

	   				$this->BuildKitsPartInstanceErrList->Value = serialize($partInstanceErrList);
	   				$this->BuildKitsErrList->Value = serialize($errList);

	   				if((count($fromParts) - count($partInstanceErrList)) == 0)
	   				{
	   					return array('stop' => true);
	   				}
				}
				return array('stop' => false);
	    	}

    	}
    	catch(Exception $e)
    	{
    		if($partInstance instanceOf PartInstance)
    		{
    			$this->BuildKitsPartsError->Value = "Part: " . $partInstance->getAlias() . " Error: " .  addslashes($e->getMessage());
    		}
    		else
    		{
    			$this->BuildKitsPartsError->Value = "Error: " .  addslashes($e->getMessage());
    		}

    		return array('stop' => true);
    	}

    	if(count($fromParts) == 0)
    	{
    		$this->BuildKitsPartsMessage->Value .= "<br>Selected parts succesfully installed.";
    		return array('stop' => true);
    	}
    	return array('stop' => false);
    }




















    /**
     * Attempt to Install
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
   	public function attemptToInstall($sender,$param)
    {
    	//get the part to install part to!!!!
    	$toPart = null;
    	$toPartsId = $this->toBeInstalledPartInstances_parentId->Value;
    	$errList = array();
    	$partInstanceErrList = array();
    	$error = "";

    	if($toPartsId == "")
    		$error .= "Enter part to install to.";
    	else
    		$toPart =  Factory::service("PartInstance")->getPartInstance($toPartsId);

    	if(!$toPart instanceof PartInstance)
    	{
    		$error .= "Invalid part ($toPart) to install to! Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!";
    	}

    	//get all parts that are to be installed to $toPart
    	$fromParts =  unserialize($this->toBeInstalledPartInstances->Value);

    	if($fromParts==false ||count($fromParts)<1)
    		$error .= "Enter part/s to install.<br>";

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
	    	return;
    	}
    	catch (Exception $e)
    	{
    		return $e->getMessage();
    	}
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
    	$partInstanceId = $this->kitDataList->DataKeys[$sender->Parent->ItemIndex];
    	$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
    	if(!$partInstance instanceof PartInstance)
    		return $this->onError("Invalid part. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."! ");

    	if($param != null)
			$itemIndex = $sender->Parent->ItemIndex;
		else
			$itemIndex = 0;

		$this->kitDataList->SelectedItemIndex = -1;
		$this->kitDataList->EditItemIndex = $itemIndex;
    	$this->loadPartsList();

    	$this->kitDataList->getEditItem()->removingPartInstance_SerialNo->Text=$partInstance;
    	$this->kitDataList->getEditItem()->removingPartInstance_Id->Value=$partInstanceId;
    	$this->kitDataList->getEditItem()->targetWarehouseId->Value="";
    }

    /**
     * Removes selected part from the kit.
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     * @return unknown
     */
 	public function removePart($sender, $param)
    {

    	$targetWarehouseIds = explode("/",$this->kitDataList->getEditItem()->targetWarehouseId->Value);
    	$targetWarehouseId = end($targetWarehouseIds);
    	$targetWarehouse = Factory::service("Warehouse")->getWarehouse($targetWarehouseId);

    	if(!$targetWarehouse instanceof Warehouse)
    		return $this->onError("Invalid Warehouse. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

    	$movingPartInstanceId= $this->kitDataList->getEditItem()->removingPartInstance_Id->Value;
    	$movingPartInstance = Factory::service("PartInstance")->getPartInstance($movingPartInstanceId);
    	$kitBs = $this->ToInstance->Text;
    	if(!$movingPartInstance instanceof PartInstance)
    		return $this->onError("Invalid part. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

    	$parentPartInstance = $movingPartInstance->getParent();
    	$movingPartInstance = Factory::service("PartInstance")->movePartInstanceToWarehouse($movingPartInstance, $movingPartInstance->getQuantity(), $targetWarehouse,true, $movingPartInstance->getPartInstanceStatus(), 'Removed from kit '.$kitBs);
    	Factory::service("PartInstance")->installPartInstance(null,$movingPartInstance);

    	$this->kitDataList->EditItemIndex = -1;
    	$this->setInfoMessage("Part successfully removed.");

    	if($this->checkIfLastPartInKit() > 0)
    		$this->deactiveCheck = 0;
    	else
    		$this->deactiveCheck = 1;

    	$this->loadPartsList();
    }

    /**
     * Check if Last Part in Kit
     *
     * @return unknown
     */
    private function checkIfLastPartInKit()
    {
    	$count = 0;
    	$parentPartInstance = Factory::service("PartInstance")->getPartInstance($this->toBeInstalledPartInstances_parentId->Value);
    	if($parentPartInstance instanceof PartInstance)
    	{
    		$childParts = Factory::service("PartInstance")->getChilrenForPartInstance($parentPartInstance);
    		if(count($childParts) != 0 && count($childParts) != "")
    			$count = count($childParts);
    	}
    	return $count;
    }



    /**
     * Gets Locations of all the Non-Serialized selected parts under the user default warehouse
     * and lists them in a dropdown list for the user	to select.
     *
     * @param unknown_type $results
     */
	private function bindPartInLocationList($results)
	{
    	$array = array();
    	foreach($results as $partInstance)
    	{
    		$warehouse = $partInstance->getWarehouse();
    		if(!($warehouse instanceof  Warehouse))
    			continue;

    		$site = $partInstance->getSite();
    		if($site instanceof Site && $warehouse->getId() == 27)
    			$warehouse = $site."($warehouse)";

    		$partInstanceDescription = $partInstance->getPartType()." : ";
    		foreach($partInstance->getPartInstanceAlias() as $alias)
    		{
    			if(in_array($alias->getPartInstanceAliasType()->getId(),array(1,2,3,4,6)))
    				$partInstanceDescription .= $alias;
    		}
    		$ftId = PartInstanceLogic::getFieldtaskIdByPartInstance($partInstance);//only  for pre-allocated
    		$array[] = array("id"=>$partInstance->getId(),"name"=> $partInstanceDescription." | ".$warehouse." | ".$partInstance->getPartInstanceStatus().$ftId." | ".$partInstance->getQuantity());
    	}
    	usort($array, create_function('$a, $b', 'return strcmp($a["name"], $b["name"]);'));
    	if(count($array)==0)
    		$array[] = array("id"=>"","name"=>"No parts found!");
    	$this->partResultList->DataSource = $array;
    	$this->partResultList->DataBind();
	}

 	/**
 	 * Checks if the quantity of selected part entered by the user exists in the system.
 	 * If so, adds the selected number of parts to the Installing Parts List.
 	 *
 	 * @param unknown_type $sender
 	 * @param unknown_type $param
 	 * @return unknown
 	 */
	public function addPart()
    {
    	$partInstanceId = $this->SearchPartsPanel->partInstanceId->Value;
    	$qty = $this->SearchPartsPanel->partInstanceQuantity->Value;

   		$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
   		if($partInstance instanceof PartInstance)
   		{
   			if($qty>$partInstance->getQuantity())
   			{
   				return $this->onError("Not enough parts. $qty selected, but only ".$partInstance->getQuantity()." available.");
   			}

   			$return = $this->addPartInstanceToList($partInstance,$qty,$this->toBeInstalledPartInstances);

			$this->SearchPartsPanel->SearchInstance->setText('');

	    	if($return !== false)
	    	{
	    		$this->setInfoMessage("Part successfully added.");
	    		$this->SearchPartsPanel->SearchInstance->focus();
	    	}
   		}
   		$this->loadPartsList();
    }

    /**
     * Check If the Kit is complete or not. If Kit incomplete display message showing
     * that the kit is not complete and asking the user if they wish to proceed or not.
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function checkKitComplete($sender,$param)
    {
    	$bomPartsQty = unserialize($this->bomPartsQuantity->Value);
    	$kitPartsQty = unserialize($this->kitPartsQuantity->Value);
    	$partGroupQty = unserialize($this->partGroupQuantity->Value);
    	$partGroupsInBom = unserialize($this->partGroupsInBom->Value);
    	$installPartsQty = unserialize($this->installPartList->Value);
    	$bomParts = $this->getBomPartsQuantity($this->toBeInstalledPartInstances_parentPartTypeId->Value);
    	$check = 1;
    	foreach($bomParts as $partId => $qty)
    	{
    		if(isset($installPartsQty[$partId]))
    		{
    			if($installPartsQty[$partId] < $qty)
    				$check = 0;
    		}
    		else
    		{
	    		if(isset($kitPartsQty[$partId]))
	    		{
	    			if($kitPartsQty[$partId] < $qty)
	    				$check = 0;
	    		}
	    		else
	    		{
	    			if(isset($partGroupQty[$partId]))
					{
	    				if($partGroupQty[$partId] < $qty)
	    					$check = 0;
	    			}
	    			else
	    				$check = 0;
	    		}
    		}
    	}

    	if($check == 0)
    	{
    		$this->outer1->Display="Hidden";
    		$this->inner1->Display="Dynamic";
    		$this->loadPartsList();
    	}


    	if($check == 1)
    		$this->attemptToInstall($sender,$param);
    }

    /**
     * Return Page
     *
     */
    public function returnPage()
    {
    	$this->loadPartsList();
    	$this->outer1->Display="None";
		$this->inner1->Display="None";
    }

    /**
     * Deactivate Kit
     *
     */
	public function deactivateKit()
	{
		$serialNo = trim($this->ToInstance->Text);
		$kitPartInstance = Factory::service("PartInstance")->getPartInstance($this->toBeInstalledPartInstances_parentId->Value);

		$kitPartInstance->setActive(0);
		$kitPartInstance->setKitType(null);
		Factory::service("PartInstance")->save($kitPartInstance);

		$this->setInfoMessage("Kit with serialno '$serialNo'' has been successfully deactivated.");
		$this->outer->Display="None";
		$this->inner->Display="None";
		//$this->loadPartsList();
		$this->resetPageFields();
		$this->kitPanel->Visible = false;
		$this->installPanel->Visible = false;
		$this->bomPanel->Visible = false;
	}

	/**
	 * Keep kit Active
	 *
	 */
	public function keepKitActive()
	{
		$serialNo = trim($this->ToInstance->Text);
		$this->setErrorMessage("Kit with serialno '$serialNo' active - contains no parts");
		$this->deactiveCheck = 0;
		$this->loadPartsList();
		$this->outer->Display="None";
		$this->inner->Display="None";
		$this->SearchPartsPanel->SearchInstance->focus();
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
    	$movingPartInstanceId= $this->kitDataList->getEditItem()->removingPartInstance_Id->Value;
    	$movingPartInstance = Factory::service("PartInstance")->getPartInstance($movingPartInstanceId);
    	if(!$movingPartInstance instanceof PartInstance)
    		return $this->onError("Invalid part. Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

    	$parentPartInstance = $movingPartInstance->getParent();
    	$this->kitDataList->EditItemIndex = -1;

    	$this->loadPartsList();
    	//$this->showPartsWithin($parentPartInstance);
    }

    /**
     * Get PartType Group List
     *
     * @param unknown_type $partGroupList
     * @return unknown
     */
    private function getPartTypeGroupList($partGroupList)
    {
    	$groupArray = array();
    	foreach($partGroupList as $group)
    		$groupArray[] = $group->getId();

    	return $groupArray;
    }

    /**
     * Get PartType Group In Kit
     *
     * @param unknown_type $partsInKit
     * @return unknown
     */
    private function getPartTypeGroupsInKit($partsInKit)
    {
    	$groupArray = array();
    	foreach($partsInKit as $partId => $q)
    	{
    		$partTypeGroupsList = Factory::service("PartType")->getPartType($partId)->getPartTypeGroups();
			$gArray = $this->getPartTypeGroupList($partTypeGroupsList);
			array_merge($groupArray,$gArray);
    	}
    	return $groupArray;
    }

    /**
     * Hide Fields on Install Panel
     *
     */
    private function hideFieldsOnInstallPanel()
    {
    	$this->oldPanel->Visible = false;
    	$this->SearchPartsPanel->hidePanel();
		$this->installButton->Display = "None";
    }

    /**
     * Activate Lookup
     *
     */
    public function activateLookup()
    {
    	$this->LookupButton->Enabled = true;
    }

    /**
     * Print Box Label
     *
     * @param unknown_type $serialNo
     * @param unknown_type $partInstance
     * @return unknown
     */
    private function printBoxLabel($serialNo,$partInstance)
    {
		$msg = "";
    	$validSNCheck = true;

		try{BarcodeService::validateBarcode($serialNo, array(BarcodeService::BARCODE_REGEX_CHK_REGISTRABLE, BarcodeService::BARCODE_REGEX_CHK_PART_INSTANCE));}
		catch(Exception $ex)
		{
			$validSNCheck = false;
		}
		if($validSNCheck)
		{
			$partType = $partInstance->getPartType();
			$boxLabel = Factory::service("Sequence")->getNextNumberAsBarcode(Factory::service("Sequence")->get(SequenceService::ID_BX));

			try
			{
				$partInstanceAlias = new PartInstanceAlias();
				$partInstanceAlias->setPartInstance($partInstance);
				$partInstanceAlias->setAlias($boxLabel);

				$partInstanceAlias->setPartInstanceAliasType(Factory::service("PartInstanceAliasType")->get(9));
				Factory::service("PartInstanceAlias")->save($partInstanceAlias);
				$boxCode = Factory::service("Barcode")->printBoxLabel($partInstance,1,"Zebra-Text",false,$boxLabel,$serialNo);

				$msg .= "<br/>Box Label $boxCode printed Successfully.";
				//$this->setInfoMessage("Box Label $boxCode printed Successfully. <br/>");

				$this->boxLabelPanel->Visible = true;
				$this->boxLabel->Text = $boxCode;
				return $msg;
			}
			catch(Exception $e)
			{
				//$msg .= "<br/>Box Label Printing Error: ".$e->getMessage();
				$this->setErrorMessage("Box Label Printing Error: ".$e->getMessage()."<br/>");
			}
		}

    }

    /**
     * Get total Errors While Installing
     *
     * @return unknown
     */
    public function getTotalErrorsWhileInstalling()
    {
    	$errCount = $this->totalErrorsWhileInstalling->Value;
    	return $errCount;
    }

    /**
     * Update the kittype of partinstance to be the same as the kittype of parttype
     *
     * @param PartType $partType
     * @param PartInstance $partInstance
     */
    private function checkAndUpdatePartInstanceKittype($partType,$partInstance)
    {
    	$ptKitType = $partType->getKitType();
    	if($ptKitType instanceof KitType)
    	{
    	  	$piKitType = $partInstance->getKitType();
    	  	if($piKitType instanceof KitType)
    	  	{
    	  		if($piKitType->getId() != $ptKitType->getId())
    	  		{
	    	  		//update partInstance Kittype
	    	  		$partInstance->setKitType($ptKitType);
	    	  		Factory::service("PartInstance")->save($partInstance);
    	  		}
    	  		else
    	  			return;
    	  	}
    	  	else
    	  	{
    	  		$partInstance->setKitType($ptKitType);
    	  		Factory::service("PartInstance")->save($partInstance);
    	  	}
    	}
    }
    public function generatePickList($sender, $param)
    {
    	$results = $errors = array();
    	try
    	{
    		if(!isset($param->CallbackParameter->bomPartTypeId) || !($bomPartType = Factory::service('PartType')->get(trim($param->CallbackParameter->bomPartTypeId))) instanceof PartType)
    			throw new Exception('System Error: No bomPartTypeId passed in.');
    		if(!($defaultWarehouse = Factory::service('Warehouse')->getDefaultWarehouse(Core::getUser())) instanceof Warehouse)
    			throw new Exception('There is no Default Warehouse set for you yet.');
    		$kitPartInstance = null;
    		if(isset($param->CallbackParameter->kitPartInstanceId) && !($kitPartInstance = Factory::service('PartInstance')->get(trim($param->CallbackParameter->kitPartInstanceId))) instanceof PartInstance)
    			throw new Exception('System Error: Invalid kit part instance id passed in: ' . trim($param->CallbackParameter->kitPartInstanceId));
			//getting all the bill of material records for that parttype
    		$boms = Factory::service('BillOfMaterials')->findByCriteria('partTypeId = ?', array($bomPartType->getId()));
    		if(count($boms) === 0)
    			throw new Exception('There is nothing to generate pick list for, as there is no Required PartTypes in the BillOfMaterials for this PartType:' . $bomPartType->getAlias());
    		//check what we've got now
    		$gotPTs = array();
    		if($kitPartInstance instanceof PartInstance)
    		{
    			$sql = 'select pi.partTypeId, sum(pi.quantity) `gotQty` from partinstance pi where pi.active = 1 and pi.parentId = ? group by pi.partTypeId';
    			$result = Dao::getResultsNative($sql, array($kitPartInstance->getId()), PDO::FETCH_ASSOC);
				foreach($result as $row)
				{
					$gotPTs[$row['partTypeId']] = trim($row['gotQty']);
				}
    		}
			//translate the BillofMaterial's requiredPartType into IDs
    		$searchingPTs = array();
    		foreach($boms as $bom) {
    			if(!($requiredPartType = $bom->getRequiredPartType()) instanceof PartType || intval($requiredPartType->getActive()) !== 1)
    				continue;
    			$partTypeId = $requiredPartType->getId();
    			if(!array_key_exists($partTypeId, $gotPTs))
    				$searchingPTs[$partTypeId] = $bom->getQuantity();
    			else if($gotPTs[$partTypeId] < $bom->getQuantity())
    				$searchingPTs[$partTypeId] = ($bom->getQuantity() - $gotPTs[$partTypeId]);
    		}
    		if(count($searchingPTs) === 0)
    			throw new Exception('There is nothing to generate pick list for, as all the required parts have been installed into this Kit (SN:' . $kitPartInstance->getAlias() . ', PartCode:' . $bomPartType->getAlias() . ')');
    		//searching the warehouse locations for those parttype ids
    		$partLists = Factory::service('PartType')->getPickList(array_keys($searchingPTs), $defaultWarehouse);
    		if(count($partLists) === 0)
    			throw new Exception('There is nothing found under "' . $defaultWarehouse->getBreadCrumbs() . '" for this PartType:' . $bomPartType->getAlias());
			//output the result data
    		$timeZone  = Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
    		$now = new HydraDate('now', $timeZone);
			$results['data']= array(
				'BOMPartType' => array('id' => $bomPartType->getId(), 'name' => $bomPartType->getName(), 'partCode' => $bomPartType->getAlias())
				,'warehouse' => array('id' => $defaultWarehouse, 'breadCrumbs' => $defaultWarehouse->getBreadCrumbs())
				,'creator'  => array('id' => Core::getUser()->getId(), 'name' => Core::getUser()->getPerson()->getFullName())
				,'created'  => array('value' => trim($now), 'timeZone' => $timeZone)
			);
			$itemsInfo = array();
    		foreach($partLists as $partTypeId => $partList)
    		{
    			if(!($partType = Factory::service('PartType')->get(trim($partTypeId))) instanceof PartType)
    				continue;
    			$warehouse = null;
    			if(is_numeric($warehouseId = trim($partList['warehouseId'])))
    				$warehouse = Factory::service('Warehouse')->get($warehouseId);
    			$itemsInfo[] = array(
    				'partType' => array('id' => $partType->getId(), 'name' => $partType->getName(), 'partCode' => $partType->getAlias(), 'isSerialised' => ($serialised = (intval($partType->getSerialised()) === 1)), 'BP' => ($serialised !== true ? $partType->getAlias(PartTypeAliasType::ID_BP) : ''))
    				,'qty'     => array('avail' => trim($partList['qty']), 'need' => $searchingPTs[$partType->getId()])
    				,'warehouse'=> (!$warehouse instanceof Warehouse ? array() : array('id' => $warehouse->getId(), 'name' => $warehouse->getName(), 'breadCrumbs' => $warehouse->getBreadCrumbs()))
    			);
    		}
    		$results['data']['items'] = $itemsInfo;
    	}
    	catch (Exception $ex)
    	{
    		$errors[] = $ex->getMessage();
    	}
    	$param->ResponseData = Core::getJSONResponse($results, $errors);
    }

    /**
     * Get Default WarehouseId
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
}
