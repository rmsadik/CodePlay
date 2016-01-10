<?php
/**
 * Register Part Instance Page - Create Part Instance Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class CreatePartInstancesController extends HydraPage
{
	/**
	 * The max quantity that the user can register at one time
	 * @var int
	 */
	const MAX_QTY = 10000;
	/**
	 * Menu Context for the content
	 * @var string
	 */
	public $menuContext;

	/**
	 * constructor
	 */
	public function __construct()
	{
		parent::__construct();
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_partinstanceregister,menu_staging";
	}
	/**
	 * (non-PHPdoc)
	 * @see TPage::onPreInit()
	 */
	public function onPreInit($param)
	{
		parent::onPreInit($param);
		$this->getPage()->getClientScript()->registerScriptFile('bsuitebarcodeJs', $this->publishFilePath(Prado::getApplication()->getBasePath() . '/../common/bsuiteJS/barcode/barcode.js'));
		$this->menuContext = 'registerparts';
		if (isset($this->Request['loadmethod']) && $this->Request['loadmethod']=="loadfromiframe")
			$this->getPage()->setMasterClass("Application.layouts.PlainLayout");
		else if (isset($this->Request['loadmethod']) && $this->Request['loadmethod']=="staging")
			$this->getPage()->setMasterClass("Application.layouts.StagingLayout");
		else
			$this->getPage()->setMasterClass("Application.layouts.LogisticsLayout");
	}
	/**
	 * (non-PHPdoc)
	 * @see HydraPage::onLoad()
	 */
	public function onLoad($param)
	{
		//first hit of the page
		if(!$this->IsPostBack && !$this->IsCallBack)
		{
			$defaultWarehouse = Factory::service("Warehouse")->getDefaultWarehouse(Core::getUser());
			if ($defaultWarehouse instanceof Warehouse)
			{
				if(isset($_SESSION[get_class($this) . '_selectedWarehouseId']) && trim($_SESSION[get_class($this) . '_selectedWarehouseId']) !== '')
				{
					$this->warehouseid->Value = trim($_SESSION[get_class($this) . '_selectedWarehouseId']);
				}
				else
				{
					$this->warehouseid->Value = Factory::service("Warehouse")->getWarehouseIdBreadCrumbs($defaultWarehouse);
				}
			}

			//we're auto-populating the PT from the url
			$ptRegOnly = false;
			if (isset($this->Request['ptId']) && $this->Request['ptId'] != "")
			{
				$ptRegOnly = true;
				$this->regPtId->Value = $this->Request['ptId'];
			}

			//we're auto-populating the WH from the url
			if (isset($this->Request['whId']) && $this->Request['whId'] != "")
			{
				$wh = Factory::service("Warehouse")->get($this->Request['whId']);
				if ($wh instanceof Warehouse)
				{
					$this->warehouseid->Value = Factory::service("Warehouse")->getWarehouseIdBreadCrumbs($wh);
					$this->closeAfterSave->Value = true;
				}
			}

			//to decide whether or not to show the purchase order auto-complete
			if (Factory::service("Person")->getIsBytecraftPerson(Session::getPerson()) === false || $ptRegOnly === true)
			{
				$this->poRow->setStyle("display:none");
				$this->showPendingPartsPane->setStyle("display:none");
				$this->boxlabel->setStyle("display:none");
				$this->hasPoButton->Checked = false;
			}
			else
			{
				$this->hasPoButton->Checked = true;
				$this->partType->Enabled=false;
				$this->partType->setCssClass("disabled");
			}

			//we're getting the ptId from the url so disable the auto-complete
			if ($ptRegOnly)
			{
				$this->partType->Enabled = false;
				$this->partType->setCssClass("disabled");
			}
		}
	}
	/**
	 * showing the Purchase Order Information
	 *
	 * @param int $poId The purchase order id
	 */
	public function getPoPartsInformation($poId)
	{
		$po = Factory::service("PurchaseOrder")->getPurchaseOrderWithParts($poId);
		if (!empty($po))
		{
			$reqNo = $po['reqNo'];
			$poNo = $po['poNo'];
			$supplier = $po['supplier'];
			$recipient = $po['recipient'];
			$contractQuoteNo = $po['conQuoteNo'];
			$notes = $po['notes'];

				$html = '<table width="95%" cellspacing="1" cellpadding="1" style="background-color:#DDDDDD;font-size:10px;">' .
							'<tr>' .
								'<td style="width:22%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">PO No:</td>' .
								'<td style="width:28%  height:20px; background-color:white; padding-left: 5px;">' . $poNo . '</td>' .
								'<td style="width:10%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">Supplier:</td>' .
								'<td style="width:40%  height:20px; background-color:white; padding-left: 5px;">' . $supplier . '</td>' .
							'</tr>' .
							'<tr>' .
								'<td style="width:22%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">Req. No:</td>' .
								'<td style="width:28%; height:20px; background-color:white; padding-left: 5px;">' . $reqNo . '</td>' .
								'<td style="width:10%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">Recipient:</td>' .
								'<td style="width:40%; height:20px; background-color:white; padding-left: 5px;">' . $recipient . '</td>' .
							'</tr>' .
							'<tr>' .
								'<td style="width:22%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">Con./Quote No:</td>' .
								'<td style="width:28%; height:20px; background-color:white; padding-left: 5px;">' . $contractQuoteNo . '</td>' .
								'<td style="width:10%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">Notes:</td>' .
								'<td style="width:40%; height:20px; background-color:white; padding-left: 5px;">' . $notes . '</td>' .
							'</tr>' .
							'<tr>' .
								'<td colspan="4">';

					if (!empty($po['parts']))
					{
						$html .= '<table width="100%" cellspacing="1" cellpadding="1" style="cursor:default;font-size:10px;">';
						$html .= '<tr style="height:20px;">';
							$html .= '<td style="font-weight:bold; background-color:#7c7c7c; color:white; width:20px;">&nbsp;</td>';
							$html .= '<td style="font-weight:bold; background-color:#7c7c7c; color:white; width:30px; text-align:center;" title="Purchase Order Quantity">Qty</td>';
							$html .= '<td style="font-weight:bold; background-color:#7c7c7c; color:white; width:30px; text-align:center;" title="Reconciled Quantity">Rec.</td>';
							$html .= '<td style="font-weight:bold; background-color:#7c7c7c; color:white; width:30px; text-align:center;" title="Registered Quantity">Reg.</td>';
							$html .= '<td style="font-weight:bold; background-color:#7c7c7c; color:white; width:30px; text-align:center;" title="Serialised Part?">Ser?</td>';
							$html .= '<td style="font-weight:bold; background-color:#7c7c7c; color:white; width:70px; text-align:center;"" title="BSuite PartCode">Part Code</td>';
							$html .= '<td style="font-weight:bold; padding-left:5px; background-color:#7c7c7c; color:white;">Part Name</td>';
						$html .= '</tr>';
						foreach ($po['parts'] as $poPart)
						{
							$color = 'black';
							$checked = $radioDisabled  = $img ='';
							if ($poPart->getId() == $this->hiddenPoPartsId->Value)
								$checked = ' checked=true ';

							$partStr = '';
							if (($partType = $poPart->getPartType()) instanceof PartType)
							{
								$serialised = $partType->getSerialised();
								$partCode = $partType->getAlias(PartTypeAliasType::$partTypeAliasType_codeName);
								$partName = $partType->getName();
								$partStr = $partCode . ' : ' . htmlentities($partName);
							}

							$qty = $poPart->getQty();
							$recQty = $poPart->getReconciledQty();
							$regQty = $poPart->getRegisteredQty();
							$qtyCheck = $qty . '_' . $recQty . '_' . $regQty;

							if ($regQty >= $qty || trim($recQty) === '0')
							{
								$color = 'grey';
								$radioDisabled = 'disabled';
							}

							if ($serialised)
								$img = '<img src="/themes/images/small_yes.gif">';

							$usingInactivePartCode = false;
							if ($partCode == DepreciationService::$unknownPartCode)
							{
								$color = 'red';
								$partCode = "<span onmouseover=\"bsuiteJs.showTooltip(event, 'toolTip', 'This part is is NOT known to BSUITE,<br />contact Purchasing to change this!');\"><img src='/themes/images/error.gif' /> ????</span>";
								$partName = $poPart->getPartDescription();
								$radioDisabled = 'disabled';
								$usingInactivePartCode = true;
							}

							if ($partCode == DepreciationService::$genericPartCode)
							{
								$partCode = '';
								$color = 'grey';
								$partName = $poPart->getPartDescription();
								$radioDisabled = 'disabled';
								$usingInactivePartCode = true;
							}

							//display a message to logistics saying that the parttype is inactive
							if ($partType->getActive() == 0 && $usingInactivePartCode == false)
							{
								$radioDisabled = 'disabled';
								$partName .= '<br /><span style="color:red;font-weight:bold;">This part has been deactivated, please contact Purchasing</span>';
							}

							$poPartId = $poPart->getId();
							$radioEvents = ' onClick="pageJs.clearBeforeSelectPT();pageJs.requestData.po.id=' . $poId . ';pageJs.requestData.po.pId=' . $poPartId . ';pageJs.requestData.po.maxRegQty=' . ($recQty - $regQty) . ';pageJs.populateSelectedPT($(this).value);"';

							$html .= '<tr style="color:' . $color . ';">';
								$html .= '<td style="height:20px; font-weight:bold; background-color:white; width:20px; text-align:center;"><input ' . $checked . ' name="poPartsRadio" id="poPartRadio_' . $poPartId . '" type="radio" ' . $radioDisabled . $radioEvents . ' value="' . $partType->getId() . '"></td>';
								$html .= '<td style="height:20px; font-weight:bold; background-color:white; width:30px; text-align:center;">' . $qty . '</td>';
								$html .= '<td style="height:20px; font-weight:bold; background-color:white; width:30px; text-align:center;">' . $recQty . '</td>';
								$html .= '<td style="height:20px; font-weight:bold; background-color:white; width:30px; text-align:center;">' . $regQty . '</td>';
								$html .= '<td style="height:20px; font-weight:bold; background-color:white; width:30px; text-align:center;">' . $img . '</td>';
								$html .= '<td style="height:20px; font-weight:bold; background-color:white; width:70px; text-align:center;">' . $partCode . '</td>';
								$html .= '<td style="height:20px; padding-left:5px; background-color:white;">' . $partName . '</td>';
							$html .= '</tr>';
						}
						$html .= '</table>';
					}
					else
						$html .= '<span style="color:red; font-weight:bold;">Unable to retrieve parts from the Purchase Order.</span>';

					$html .= '</td>';
				$html .= '</tr>';
			$html .= '</table>';
		}
		else
			$html = '<span style="font-size:10px; color:red; font-weight:bold;">Unable to retrieve PO information...</span>';
		$this->poPartsLbl->Text = $html . '<br />';
	}
	/**
	 * generate the part instance alias type list
	 */
	public function generateAliasTypeList()
	{
		$excludedIds = array(Lu_EntityAccessOption::ID_SYSGEN);
		if (!UserAccountService::isSystemAdmin())
			$excludedIds[] = Lu_EntityAccessOption::ID_SYSADMINONLY;

		$query = "select piat.id, piat.name, piat.allowMultiple from partinstancealiastype piat INNER JOIN lu_entityaccessoption lueao ON (piat.lu_entityaccessoptionId = lueao.id and lueao.active = 1) where piat.active = 1 and lueao.id NOT IN (" . implode(",", $excludedIds) . ") order by piat.name";
		return "<option value='' allowmulti='1'>Alias Type ...</option>" . implode("", array_map(create_function('$a', 'return "<option value=\"".$a[0]."\" allowmulti=\"".$a[2]."\">$a[1]</option>";'), Dao::getResultsNative($query)));
	}
	/**
	 * When select a part type
	 *
	 * @param TCallback $sender The sender of the call back
	 * @param Mixed     $param  The parameters sent along with this event
	 */
	public function selectPT($sender, $param)
	{
		$result = $errors = array();
		try {
			if(!isset($param->CallbackParameter->partTypeId) || ($ptId = trim($param->CallbackParameter->partTypeId)) === '' || !($partType = Factory::service("PartType")->getPartType($ptId)) instanceof PartType)
				throw new Exception("Invalid part type provided!");

			//getting part type
			$kitTypeId = trim(($partTypeKitType = $partType->getKitType()) instanceof KitType ? $partTypeKitType->getId() : '');

			$result['partType'] = array('id'=> $partType->getId(),
							'name' => $partType->getName(),
							'kitTypeId' => $kitTypeId,
							'serialised' => ($partType->getSerialised() ? true : false),
							'bp' => $partType->getAlias(PartTypeAliasType::ID_BP),
							'partcode' => $partType->getAlias(PartTypeAliasType::ID_PARTCODE),
							'manAliasTypeIds' => array(),
							'manUniqueAliasTypeIds' => array(),
							'poAliasTypeIds' => array(),
							'extraAliasTypeIds' => array()
			);

			$piatList = Factory::service("Lu_PartType_PartInstanceAliasPattern")->getMandatoryUniquePatternsForPtPiat($partType,null,null,null);
			if (count($piatList)>0)
			{
				$manAliasIdString = '';
				$manUniqueAliasIdString = '';
				$nonManAliasIdString = '';
				foreach($piatList as $piatObj)
				{
					if ($piatObj->getIsUnique()==true)
					{
						if ($manUniqueAliasIdString === '')
							$manUniqueAliasIdString .= $piatObj->getPartInstanceAliasType()->getId();
						else
							$manUniqueAliasIdString .= ",".$piatObj->getPartInstanceAliasType()->getId();
					}
					if ($piatObj->getIsMandatory()==true)
					{
						if ($manAliasIdString === '')
							$manAliasIdString .= $piatObj->getPartInstanceAliasType()->getId();
						else
							$manAliasIdString .= ",".$piatObj->getPartInstanceAliasType()->getId();
					}
					else
					{
						if ($nonManAliasIdString === '')
							$nonManAliasIdString .= $piatObj->getPartInstanceAliasType()->getId();
						else
							$nonManAliasIdString .= ",".$piatObj->getPartInstanceAliasType()->getId();
					}

				}

				$result['partType']['manUniqueAliasTypeIds'] = explode(",",$manUniqueAliasIdString);
				$result['partType']['manAliasTypeIds'] = explode(",",$manAliasIdString);
				$result['partType']['extraAliasTypeIds'] = explode(",",$nonManAliasIdString);
			}

			//check here to see if we are registering against a PO
			if (isset($param->CallbackParameter->poId))
			{
				if ($param->CallbackParameter->poId !== null)
				{
					//add the extra mandatory part instance aliases (if any)
					$extraPartInstanceAliasTypes = Factory::service("PartType")->getContractMandatoryPartInstanceAliasType($partType);
					if ($extraPartInstanceAliasTypes !== null)
					{
						foreach ($extraPartInstanceAliasTypes as $piat)
						{
							$result['partType']['poAliasTypeIds'][] = $piat->getId();
						}
					}
				}
			}

			if($kitTypeId !== '') //if this is a kit type, then stop it
				return $param->ResponseData = Core::getJSONResponse($result, $errors);

			if($partType->getSerialised())
			{
				$option = array(BarcodeService::BARCODE_REGEX_CHK_REGISTRABLE);
				$option[] = ($partType->getSerialised() ? BarcodeService::BARCODE_REGEX_CHK_PART_INSTANCE : BarcodeService::BARCODE_REGEX_CHK_PART_TYPE);
				$result['partType']['barcodeValidator']= array (
					'regex' => BarcodeService::getBarcodeRegex($option, $partType, '^', '$'),
					'pattern' => BarcodeService::getValidBarcodePatterns($option, $partType),
				);
			}

			//getting contracts
			$result['contracts'] = array();
			foreach(($contracts = $partType->getContracts()) as $contract)
				$result['contracts'][] = array('id' => $contract->getId(), 'name' => $contract->getContractName());

			//get owner client
			$owner = $partType->getOwnerClient();
			$result['owner'] = $owner instanceof Client ? array('id' => $owner->getId(), 'name' => $owner->getClientName()) : array('id' => null, 'name' => null);

			//binding the statuses
			$result['statuses'] = array();
			$initalStatus = null;
			foreach ($contracts as $contract)
			{
				if(($initalStatus = Factory::service("PartInstanceStatus")->get(trim($contract->getPreference("initalStatusIdForSerialisedParts")))) instanceof PartInstanceStatus)
				{
					$result['statuses'][] = array('id' => $initalStatus->getId(), 'name' => $initalStatus->getName(), 'selected' => true);
					break;
				}
			}
			foreach (DropDownLogic::getPartInstanceStatusList(array(), $contracts) as $status)
			{
				$result['statuses'][] = array('id' => $status->getId(), 'name' => $status->getName(), 'selected' => (!$initalStatus instanceof PartInstanceStatus && $status->getId() == PartInstanceStatus::ID_PART_INSTANCE_STATUS_GOOD));
			}

			//getting pending parts list
			//$html = Factory::service("PartType")->checkPendingPartsStatus(array($partType), 'array');
			//$result['pendingPT'] = (is_array($html) ? $html : array());
			$html = Factory::service("PartType")->checkPendingPartsStatus(array($partType));
			$result['pendingPT'] = $html;
		} catch (Exception $ex) {
			$errors[] = $ex->getMessage();
		}
		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}
	/**
	 * Show Pattern
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function showPattern($sender,$param)
	{
		$result = $errors = array();
		try {
			if(!isset($param->CallbackParameter->partTypeId) || ($ptId = trim($param->CallbackParameter->partTypeId)) === '')
			throw new Exception("Invalid part type provided!");

			if(!isset($param->CallbackParameter->typeId) || (!$typeId = trim($param->CallbackParameter->typeId)))
			throw new Exception("Invalid alias type provided!");

			$partType = Factory::service("PartType")->getPartType($ptId);
			$piat = Factory::service("PartInstance")->getPartInstanceAliasType($typeId);
			$luPtPi = Factory::service("Lu_PartType_PartInstanceAliasPattern")->getMandatoryUniquePatternsForPtPiat($partType,$piat,null,null);
			if ((count($luPtPi)>0) && ($luPtPi[0] instanceof Lu_PartType_PartInstanceAliasPattern))
			{
				if ($luPtPi[0]->getPattern() !='')
				{
					$result['aliasPattern'] = $luPtPi[0]->getSampleFormat();
					//remove slashes
					$len = strlen($luPtPi[0]->getPattern())-2;
					$regex = substr($luPtPi[0]->getPattern(),1,$len);
					$result['regex'] = $regex;
					$result['format'] = $luPtPi[0]->getSampleFormat();
				}
				else
				{
					$result['aliasPattern'] = '';
					$result['regex'] = '';
					$result['format'] = $luPtPi[0]->getSampleFormat();
				}
			}
			else
			{
				$result['aliasPattern'] = '';
				$result['regex'] = '';
				$result['format'] = $luPtPi[0]->getSampleFormat();
			}
		} catch (Exception $ex) {
				$errors[] = $ex->getMessage();
		}
		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}
	/**
	 * checking part instance aliases
	 *
	 * @param TCallback $sender The sender of the call back
	 * @param Mixed     $param  The parameters sent along with this event
	 */
	public function checkSNExsiting($sender, $param)
	{
		$result = $errors = array();

		try {
			if(!isset($param->CallbackParameter->partTypeId) || ($ptId = trim($param->CallbackParameter->partTypeId)) === '' || !($partType = Factory::service("PartType")->getPartType($ptId)) instanceof PartType)
				throw new Exception("Invalid part type provided!");
			if(!isset($param->CallbackParameter->serialNo) || ($serialNo = trim($param->CallbackParameter->serialNo)) === '')
				throw new Exception("System Error: Invalid serialNo provided!");
			if(!isset($param->CallbackParameter->aliases))
				throw new Exception("System Error: Invalid aliases provided!");
			$scannedAliases = json_decode(json_encode($param->CallbackParameter->aliases), true);
			if(!isset($param->CallbackParameter->printBoxLabel) || ($printBoxLabel = $param->CallbackParameter->printBoxLabel) !== true)
				$printBoxLabel = false;

			//if this is a NON-serialised part, then don't check anything!
			if(!$partType->getSerialised()) {
				$param->ResponseData = Core::getJSONResponse($result, $errors);
				return;
			}

			//checking serial number unqiue
			$sql = "select partInstanceId from partinstancealias where alias = '$serialNo' and partInstanceAliasTypeId = " . PartInstanceAliasType::ID_SERIAL_NO;
			if(count(Dao::getResultsNative($sql)) > 0)
				throw new Exception("Serial Number(=$serialNo) is already used!");

			//checking mandatory aliases
			if (($manTypeIds = str_replace(" ", '', $partType->getMandatoryUniqueAliasIds())) !== '')
			{
				$manTypeArray = array();
				$manTypeIds = explode(",", $manTypeIds);
				foreach($scannedAliases as $typeId => $aliases)
				{
					if(!in_array(trim($typeId), $manTypeIds))
						continue;

					$manTypeArray[] = "(pia.partInstanceAliasTypeId = $typeId AND (pia.alias like '" . implode("' or pia.alias like '", $aliases) . "'))";
				}
				if (count($manTypeArray) > 0)
				{
					$sql = "select pit.name, pia.alias from partinstancealias pia inner join partinstancealiastype pit on (pit.id = pia.partInstanceAliasTypeId) inner join partinstance pi on (pi.partTypeId = " . $partType->getId() . " AND pi.id = pia.partInstanceId and pi.active = 1 and pia.active = 1)";
					$sql .= " where " . implode(" or ", $manTypeArray);

					if (count($result = Dao::getResultsNative($sql, array(), PDO::FETCH_ASSOC)) > 0 )
						throw new Exception("For the selected part type:\n\n" . implode("", array_map(create_function('$a', 'return "\n - ".$a["name"]."(=".$a["alias"].") is used already!";'), $result)));
				}
			}
			//print box label

			$this->setInfoMessage('');
			$this->setErrorMessage('');
			$printer = Factory::service("Barcode")->getPrinter(Core::getUser(),true);
			if($printer instanceof Printer && $printer->getActive() == 0)
			{
				$this->setErrorMessage("Printer linked to your profile has been deactivated. Please select a valid printer on your preferences page and save it.");
				throw new Exception("Printer linked to your profile has been deactivated. Please select a valid printer on your preferences page and save it.");
				return false;
			}

			if($printBoxLabel === true)
			{
				$boxLabel = Factory::service("Sequence")->getNextNumberAsBarcode(Factory::service("Sequence")->get(SequenceService::ID_BX));
				try
				{
					$fakePart = new PartInstance();
					$fakePart->setPartType($partType);

					Factory::service("Barcode")->printBoxLabel($fakePart, 1, "Zebra-Text", false, $boxLabel, $serialNo);
					if (!isset($scannedAliases[PartInstanceAliasType::ID_BOX_LABEL]))
						$scannedAliases[PartInstanceAliasType::ID_BOX_LABEL] = array();
					$scannedAliases[PartInstanceAliasType::ID_BOX_LABEL][] = $boxLabel;
				}
				catch(Exception $e)
				{
					throw new Exception("Box Label Printing Error: ".$e->getMessage());
				}
			}
			$result['aliases'] = $scannedAliases;

		} catch (Exception $ex) {
			$errors[] = $ex->getMessage();
		}

		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}
	/**
	 * checking warehouse
	 *
	 * @param TCallback $sender The sender of the call back
	 * @param Mixed     $param  The parameters sent along with this event
	 */
	public function checkWarehouse($sender, $param)
	{
		$result = $errors = array();
		try {
			if(!isset($param->CallbackParameter->warehouseId) || ($warehouseId = trim($param->CallbackParameter->warehouseId)) === '' )
				throw new Exception("System Error: Invalid warehouse provided!");

			$warehouseIds = explode("/", $warehouseId);
			$warehouseId = trim(end($warehouseIds));
			if(!($warehouse = Factory::service("Warehouse")->getWarehouse(end($warehouseIds))) instanceof Warehouse)
				throw new Exception("Invalid Warehouse Provided(ID=$warehouseId)!");
			if(!$warehouse->getParts_allow())
				throw new Exception("Selected Warehouse(=" . $warehouse->getBreadCrumbs() .") is NOT allow parts!");
			if($warehouse->getWarehouseCategory() instanceof WarehouseCategory && $warehouse->getWarehouseCategory()->getId() == WarehouseCategory::ID_TRANSITNOTE)
				throw new Exception("You can't register parts under a transite note(=" . $warehouse->getBreadCrumbs() .")!");
			//check access rights to the selected warehouse
			Factory::service("Warehouse")->checkAccessToWarehouse($warehouse);

			$result['warehouse'] = array("id" => $warehouse->getId(), 'name' => $warehouse->getName(), 'breadcrumbs' => $warehouse->getBreadCrumbs());
		} catch (Exception $ex) {
			$errors[] = $ex->getMessage();
		}
		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}
	/**
	 * registering part instance
	 *
	 * @param TCallback $sender The sender of the call back
	 * @param Mixed     $param  The parameters sent along with this event
	 */
	public function registerPI($sender, $param)
	{
		$result = $errors = array();
		try
		{
			$requestData = json_decode(json_encode($param->CallbackParameter), true);
			if (!isset($requestData['ptId']) || !(($partType = Factory::service("PartType")->getPartType(trim($requestData['ptId']))) instanceof PartType))
			{
				throw new Exception("System Error: Invalid Part type info.");
			}
			if (!isset($requestData['whId']) || !(($warehouse = Factory::service("Warehouse")->getWarehouse(trim($requestData['whId']))) instanceof Warehouse))
			{
				throw new Exception("System Error: Invalid Warehouse info.");
			}
			if (!isset($requestData['pis']) || !is_array($requestData['pis']) || count($requestData['pis']) === 0)
			{
				throw new Exception("System Error: Invalid Part Instance(s) info.");
			}
			if (!isset($requestData['po']))
			{
				throw new Exception("System Error: Invalid Purchase Order info.");
			}

			$poPart = $po = null;
			if (isset($requestData['po']['pId']) && ((!($poPart = Factory::service("PurchaseOrderPart")->get(trim($requestData['po']['pId']))) instanceof PurchaseOrderPart) || !(($po = $poPart->getPurchaseOrder()) instanceof PurchaseOrder)))
			{
				throw new Exception("System Error: Invalid Purcharse Order part(POPID = {$requestData['po']['pId']})!");
			}

			foreach($requestData['pis'] as $serialNo => $partInfo)
			{
				if(!isset($partInfo['qty']) || ($qty = trim($partInfo['qty'])) === '')
				{
					throw new Exception("Invalid QTY.");
				}
				if($poPart instanceof PurchaseOrderPart)
				{
					if ($qty > ($unregQty = ($poPart->getReconciledQty() - $poPart->getRegisteredQty())))
					{
						throw new Exception("You are attempting to register ($qty) items, but you can only register a maximum of ($unregQty) items for selected PT on PO (#" . $po->getPurchaseOrderNo() . ")!");
					}
				}
				if(!isset($partInfo['status']) || !isset($partInfo['status']['id']) || !(($status = Factory::service("PartInstanceStatus")->get(trim($partInfo['status']['id']))) instanceof PartInstanceStatus))
				{
					throw new Exception("Invalid Status.");
				}
				if(!isset($partInfo['aliases']) || !is_array($partInfo['aliases']))
				{
					throw new Exception("System Error:  Invalid aliases.");
				}


				//forming up the new part instance aliases
				$aliasArray = array();
				if($partType->getSerialised())
				{
					$aliasArray[] = $this->_getPIAliasObj(PartInstanceAliasType::ID_SERIAL_NO, $serialNo);
				}
				foreach($partInfo['aliases'] as $typeId => $aliases)
				{
					if(!is_array($aliases))
					{
						throw new Exception("System Error:  Invalid aliases content.");
					}
					foreach($aliases as $alias)
					{
						if(trim($alias) !== '')
						{
							$aliasArray[] = $this->_getPIAliasObj($typeId, $alias);
						}
					}
				}

				//do PO stuff here, add alias and generate an extra comment
				$extraRegComment = '';
				if ($po instanceof PurchaseOrder)
				{
					$poNo = trim($po->getPurchaseOrderNo());
					if($poNo != '')
					{
						$extraRegComment = " for PO (#:" . $poNo . ")";

						$aliasArray[] = $this->_getPIAliasObj(PartInstanceAliasType::ID_PURCHASE_ORDER_NUMBER, $poNo);
					}
				}

				//registering part instance
				try
				{
					Dao::beginTransaction();
					Factory::service("PartInstance")->registerPart($partType, $warehouse, $status, $qty, $aliasArray, "Registered Via Web" . $extraRegComment, null, true, false, $poPart);
					Dao::commitTransaction();
				}
				catch (Exception $ex)
				{
					Dao::rollbackTransaction();
					throw $ex;
				}
				$_SESSION[get_class($this) . '_selectedWarehouseId'] = trim($this->warehouseid->Value);
			}
		} catch (Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}
		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}
	/**
	 * getting a new part instance alias object base on the type and alias
	 *
	 * @param int    $piAliasTypeId The part instance alias type ID
	 * @param string $alias         The content of the part instance alias
	 *
	 * @throws Exception When the alias type ID is not valid
	 */
	private function _getPIAliasObj($piAliasTypeId, $alias)
	{
		if(!($piat = Factory::service("PartInstance")->getPartInstanceAliasType($piAliasTypeId)) instanceof PartInstanceAliasType)
			throw new Exception("Invalid partinstance alias type(ID=$piAliasTypeId)!");

		$pia = new PartInstanceAlias();
		$pia->setPartInstanceAliasType($piat);
		$pia->setAlias($alias);
		return $pia;
	}
}


?>
