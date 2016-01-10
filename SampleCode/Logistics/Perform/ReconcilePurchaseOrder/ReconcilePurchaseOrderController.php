<?php
/**
 * Reconcile Purchase Order Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class ReconcilePurchaseOrderController extends CRUDPage
{
	protected $uniqueAliasTypeForPartcode;

	protected $querySize;

	private $aliasTypes;

	private $readonly;

	public function __construct()
	{
		parent::__construct();
		//$this->menuContext = 'registerparts';
		$this->openFirst = true;
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_reconcilepurchaseorder,menu_staging";
		$this->querySize = 0;
	}

	public function onPreInit($param)
	{
		$this->getPage()->setMasterClass("Application.layouts.LogisticsLayout");
	    $this->menuContext = 'reconcilepo';
	}

	public function onLoad($param)
	{
		parent::onLoad($param);
		if((!$this->IsPostBack && !$this->IsCallBack) || $param == "reload")
		{
			//to decide whether or not to show the purchase order auto-complete
			$isBytePerson = Factory::service("Person")->getIsBytecraftPerson(Session::getPerson());
			if ($isBytePerson)
			{
				$this->Page->purchaseOrderRow->Style = '';
			}
			$this->poNo->focus();
			$this->DataList->EditItemIndex = -1;
			$this->dataLoad();
		}

		$this->readonly = false;
		if(Factory::service("UserPreference")->getOption(Core::getUser(),'readonly_reconcilepurchaseorder') or Session::checkRoleFeatures(array('feature_readonly_reconcilepurchaseorder'))){
			$this->readonly = true;
		}
	}

	public function doReset($resetPo = true)
	{
		$this->setErrorMessage('');
		$this->setInfoMessage('');

	}

	public function addNew($sender, $param){
		$this->hiddenDeliveryId->Value = 0;
		$poId = $this->hiddenPoId->Value;
		$deliveryId = $this->hiddenDeliveryId->Value;
		$this->Page->getPoPartsInformation($poId,$deliveryId);
	}

	public function updateDelivery($sender, $param){
		$poId = $this->hiddenPoId->Value;
		$deliveryId = $this->hiddenDeliveryId->Value;
		$this->Page->getPoPartsInformation($poId,$deliveryId);
	}

	private function getTotalReconciledQtyForPartId($partId){
		$totalReconciledQuantity = 0;
		$sql = "SELECT SUM(reconciledQty) AS countQty FROM purchaseorderreconcileparts WHERE purchaseOrderPartId = " . $partId;

		$result = Dao::getResultsNative($sql);
    	if(count($result)>0){
    		foreach ($result as $row)
	   		{
    			$totalReconciledQuantity = $row[0];
	   		}
    	}
    	return $totalReconciledQuantity;
	}

	private function getTotalReconciledQtyForDeliveryIdPartId($deliveryId,$partId){
		$totalReconciledQuantity = 0;
		$sql = "SELECT SUM(reconciledQty) AS countQty FROM purchaseorderreconcileparts  WHERE purchaseOrderDeliveryId = " . $deliveryId . " AND purchaseOrderPartId = " . $partId;
		$result = Dao::getResultsNative($sql);
    	if(count($result)>0){
    		foreach ($result as $row)
	   		{
    			$totalReconciledQuantity = $row[0];
	   		}
    	}
    	return $totalReconciledQuantity;
	}


	public function saveDelivery($sender, $param){

		$error = "";
		$decodedReconcileData = json_decode(($this->hiddenPoReconcileData->Value));

		$poId = $this->hiddenPoId->Value;
		$hiddenDeliveryId = $this->hiddenDeliveryId->Value;
		$deliveryDocketNumber =  urldecode(trim($decodedReconcileData->DeliveryDocketNumber));
		$deliveryNotes =  urldecode(trim($decodedReconcileData->DeliveryNotes));
		$forwardedTo =  urldecode(trim($decodedReconcileData->ForwardedTo));

		if(!$poId){
			$error .= "Purchase Order Number is required!<br>";
		}
		if(!$deliveryDocketNumber){
			$error .= "Delivery Docket Number is required!<br>";
		}
		if(!$forwardedTo){
			$error .= "Forwarded To is required!<br>";
		}

		$foundNumericQuantity = false;
		$decodedReconcileDataParts = $decodedReconcileData->ReconciledParts;
		foreach($decodedReconcileDataParts as $p){
			if(is_numeric($p->ReconciledQuantity)){
				$foundNumericQuantity = true;
			}
		}
		if(!$foundNumericQuantity){
			$error .= "You must enter a Reconciled Quantity as a number!<br>";
		}

		if(!$error){
			foreach($decodedReconcileDataParts as $p){


				$partId = $p->PartId;
				$reconciledQuantity = $p->ReconciledQuantity;

    			$totalReconciledQuantity = $this->getTotalReconciledQtyForPartId($partId);

				if(is_numeric($reconciledQuantity) and $reconciledQuantity>0){
					$poPart = Factory::service("PurchaseOrderPart")->get($partId);
					if ($poPart instanceof PurchaseOrderPart)
					{

						$partName = "";
						$partCode = $this->getPartCodeFromPartType($poPart->getPartType());
						$partName = $this->getPurchaseOrderPartName($poPart);


						if($hiddenDeliveryId){
							//UPDATE

							$totalReconciledQuantityForDelivery = $this->getTotalReconciledQtyForDeliveryIdPartId($hiddenDeliveryId,$partId);
							$reconciledQuantityDiff = $reconciledQuantity - $totalReconciledQuantityForDelivery;


							if($poPart->getReconciledQty() == $poPart->getQty()){
								if($reconciledQuantityDiff > 0){
									$error .= "You may not reconcile more than " . ($poPart->getReconciledQty()) . " for " . $partName . "<br>";
								}
							}elseif(($reconciledQuantityDiff+$totalReconciledQuantity) > $poPart->getQty()){
								$error .= "You may not reconcile more than " . ($poPart->getQty() - $poPart->getReconciledQty()) . " more for " . $partName . "<br>";
							}


						}else{

							if(($poPart->getReconciledQty() == $poPart->getQty()) and $reconciledQuantity>0){
								$error .= "All " . $poPart->getQty() . " parts for " . $partName . " have been reconciled!<br>";
							}elseif(($reconciledQuantity+$totalReconciledQuantity) > $poPart->getQty()){
								$error .= "You may not reconcile more than " . ($poPart->getQty() - $poPart->getReconciledQty()) . " more for " . $partName . "<br>";
							}

						}

					}
				}
			}
		}


		if(!$hiddenDeliveryId){
			$poDelQuery = Factory::service("PurchaseOrderDelivery")->findByCriteria("docketNumber = '$deliveryDocketNumber'");
			if(count($poDelQuery)>=1){
				$error .= "Delivery Docket $deliveryDocketNumber already exists!<br>";
			}
		}




		if(!$error){
			try{
				$isUpdate = false;
				//UPDATE CODE
				if($hiddenDeliveryId){
					$isUpdate = true;
					$poDel = Factory::service("PurchaseOrderDelivery")->get($hiddenDeliveryId);
				}else{
					$poDel = new PurchaseOrderDelivery();
				}
				//UPDATE CODE

				$po = Factory::service("PurchaseOrder")->get($poId);
				if ($po instanceof PurchaseOrder){
					$poDel->setPurchaseOrder($po);
				}
				$poDel->setDocketNumber($deliveryDocketNumber);
				$poDel->setForwardedTo($forwardedTo);
				$poDel->setNotes($deliveryNotes);
				$poDeliveryId = Dao::save($poDel);


				if($isUpdate){
					$poDeliveryId = $poDel->getId();
				}


				if($poDeliveryId){
					foreach($decodedReconcileDataParts as $p){

						$partId = $p->PartId;
						$reconciledQuantity = $p->ReconciledQuantity;
						$reconciledNotes = urldecode(trim($p->ReconciledNotes));

						if($isUpdate){

							//UPDATE CODE
							$poRecPartQuery = Factory::service("PurchaseOrderReconcileParts")->findByCriteria("purchaseOrderDeliveryId = $poDeliveryId AND purchaseOrderPartId = " . $partId);

							$poReconcileParts = null;
							if(count($poRecPartQuery)>=1){
								$poReconcileParts = $poRecPartQuery[0];
							}else{
								$poReconcileParts = new PurchaseOrderReconcileParts();
							}

							if(!$poReconcileParts->getReconciledQty()){
								if($reconciledQuantity==0){
									continue;
								}
							}
							//UPDATE CODE

						}else{
							if($reconciledQuantity==0){
								continue;
							}
							$poReconcileParts = new PurchaseOrderReconcileParts();
						}

						$poDel = Factory::service("PurchaseOrderDelivery")->get($poDeliveryId);
						if ($poDel instanceof PurchaseOrderDelivery)
						{
							$poReconcileParts->setPurchaseOrderDelivery($poDel);
						}

						$poPart = Factory::service("PurchaseOrderPart")->get($partId);
						if ($poPart instanceof PurchaseOrderPart)
						{
							$poReconcileParts->setPurchaseOrderPart($poPart);
						}

						$poReconcileParts->setReconciledQty($reconciledQuantity);
						$poReconcileParts->setNotes($reconciledNotes);

						Dao::save($poReconcileParts);

						if ($poPart instanceof PurchaseOrderPart)
						{
							$totalReconciledQuantity = $this->getTotalReconciledQtyForPartId($partId);
							$poPart->setReconciledQty($totalReconciledQuantity);
							Dao::save($poPart);
						}
					}

					$this->Page->getPoPartsInformation($poId);
					$this->hiddenDeliveryId->Value = "";
					if($isUpdate){
						$this->setInfoMessage('Successfully updated Delivery.');
					}else{
						$this->setInfoMessage('Successfully saved Delivery.');
					}
				}else{
					$this->setErrorMessage("Failed to save Delivery!");
				}

			}catch (Exception $e){
				$this->setErrorMessage("Error Occured : ".$e->getMessage());
				return;
			}

		}

		if($error){
			$this->setErrorMessage($error);
		}

	}

	private function getPartCodeFromPartType($partType ){
		$partCode = "";
		if ($partType instanceof PartType)
		{
			$partCode = $partType->getAlias(PartTypeAliasType::$partTypeAliasType_codeName);
		}
		//Debug::show($partCode);

		if ($partCode == DepreciationService::$unknownPartCode)
		{
			$partCode = '????????';
		}
		if ($partCode == DepreciationService::$genericPartCode)
		{
			$partCode = '';
		}
		return $partCode;
	}

	public function getPoPartsInformation($poId,$deliveryId = false)
	{
		$this->doReset(false);
		if(!$deliveryId){
			$this->hiddenDeliveryId->Value = 0;
		}
		if ($poId == -1)
		{
			//$this->Page->jsLbl->Text = '<script type="text/javascript">showModalBox();</script>';
		}
		else
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
								'<td style="width:16%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">Purchase Order No:</td>' .
								'<td style="width:28%  height:20px; background-color:white; padding-left: 5px;">' . $poNo . '</td>' .
								'<td style="width:10%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">Supplier:</td>' .
								'<td style="width:46%  height:20px; background-color:white; padding-left: 5px;">' . $supplier . '</td>' .
							'</tr>' .
							'<tr>' .
								'<td style="width:16%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">Requisition No:</td>' .
								'<td style="width:28%; height:20px; background-color:white; padding-left: 5px;">' . $reqNo . '</td>' .
								'<td style="width:10%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">Recipient:</td>' .
								'<td style="width:46%; height:20px; background-color:white; padding-left: 5px;">' . $recipient . '</td>' .
							'</tr>' .
							'<tr>' .
								'<td style="width:16%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">Contract/Quote No:</td>' .
								'<td style="width:28%; height:20px; background-color:white; padding-left: 5px;">' . $contractQuoteNo . '</td>' .
								'<td style="width:10%; height:20px; font-weight:bold; background-color:white; padding-left: 5px;">Notes:</td>' .
								'<td style="width:46%; height:20px; background-color:white; padding-left: 5px;">' . $notes . '</td>' .
							'</tr>' .
							'<tr>' .
								'<td colspan="4">';

				if (!empty($po['parts']))
				{
					$html .=		'<table width="100%" cellspacing="1" cellpadding="1" style="cursor:default;font-size:10px;">' .
										'<tr style="height:20px;">' .
											'<td class="tdHeadings" style="width:30px; text-align:center;" title="Purchase Order Quantity">Qty.</td>' .
											'<td class="tdHeadings" style="width:30px; text-align:center;" title="Reconciled Quantity">Rec.</td>' .
											'<td class="tdHeadings" style="width:30px; text-align:center;" title="Registered Quantity">Reg.</td>' .
											'<td class="tdHeadings" style="width:30px; text-align:center;" title="Serialised Part?">Ser?</td>' .
											'<td class="tdHeadings" style="width:70px; text-align:center;"" title="BSuite PartCode">Part Code</td>' .
											'<td class="tdHeadings">Part Name</td>' .
										'</tr>';

					//get update info
					$deliveryDocketNumber = "";
					$deliveryNotes = "";
					$deliveryForwardedTo = "";

					if($deliveryId){
						$poDel = Factory::service("PurchaseOrderDelivery")->get($deliveryId);
						if ($poDel instanceof PurchaseOrderDelivery)
						{
							$deliveryDocketNumber = $poDel->getDocketNumber();
							$deliveryNotes = $poDel->getNotes();
							$deliveryForwardedTo =$poDel->getForwardedTo();
						}
					}


					$html2 = '<br><br><br><div class="clsBorderRed">
							<div class="row">
								<div style="float:left;font-size:1.4em;color:#ff0000;">New Delivery</div>
							</div>
							<div class="row">
								<div style="width:190px" class="divHeadings" title="Delivery Docket/Invoice No.">
									<label for="DeliveryDocketNumber">Delivery/Invoice No.</label>
								</div>
								<div title="Forwarded to." style="width:190px;" class="divHeadings">
									<label for="ForwardedTo">Forwarded to.</label>
								</div>
								<div title="Notes regarding this delivery." style="width:380px;" class="divHeadings">
									<label for="DeliveryNotes">Notes</label>
								</div>
							</div>
								<div class="row">
									<div class="divText" style="width:190px">
										<input type="text" value="' . $deliveryDocketNumber . '" name="DeliveryDocketNumber" maxlength="30"  id="DeliveryDocketNumber">
									</div>
									<div class="divText" style="width:190px">
										<input type="text" value="' . $deliveryForwardedTo . '" name="ForwardedTo" maxlength="100"  id="ForwardedTo">
									</div>
									<div class="divText" style="width:380px;">
										<textarea name="DeliveryNotes" rows="3" cols="43" id="DeliveryNotes">' . $deliveryNotes . '</textarea>
									</div>
								</div>
								<div class="row">
									<div style="width:260px;" class="divHeadings">Part Name</div>
									<div style="width:70px;" class="divHeadings">Part Code</div>
									<div title="Enter Reconciled Quantity" style="width:50px;" class="divHeadings">Rec. Qty</div>
									<div title="Notes regarding this Part." style="width:380px;" class="divHeadings">Notes</div>
								</div>';

						//Dao::$Debug = true;
						$htmlHistory = '';
						$poDelQuery = Factory::service("PurchaseOrderDelivery")->findByCriteria("purchaseOrderId = " . $poId, array(),false,null,null,array("PurchaseOrderDelivery.created" =>'desc'));
						//Dao::$Debug = false;

						if(count($poDelQuery)>=1){
							$htmlHistory = '<br><br><div style=";font-size:1.4em;color:#7C7C7C;text-decoration:underline">History</div>';

							foreach($poDelQuery as $rowDeliverys)
							{
								$recBy = $rowDeliverys->getCreatedBy()->getPerson();
								$recDate = new HydraDate($rowDeliverys->getCreated());
								$recDate->setTimeZone("Australia/Melbourne");

								$htmlHistory .= '<br>' .
									 '<div class="clsBorder">' .
										'<div class="row">' .
											'<div style="width:126px;" class="divHeadings" title="Date Delivered.">' .
												'<label>Date Delivered.</label>' .
											'</div>' .
											'<div style="width:126px;" class="divHeadings" title="Delivery Docket/Invoice No.">' .
												'<label>Delivery/Invoice No.</label>' .
											'</div>' .
											'<div style="width:126px;" class="divHeadings" title="Forwarded to.">' .
												'<label>Forwarded to.</label>' .
											'</div>' .
											'<div title="Reconciled Quantity." style="width:140px;" class="divHeadings">' .
												'<label>Rec. By</label>' .
											'</div>' .
											'<div style="width:240px;" class="divHeadings" title="Notes.">' .
												'<label>Notes.</label>' .
											'</div>' .
										'</div>' .
										'<div class="row">' .
											'<div style="width:126px;" class="divText">' .
												'<label>' .  $rowDeliverys->getCreated() . '&nbsp;</label>' .
											'</div>' .
											'<div style="width:126px;" class="divText">' .
												'<label>' . $rowDeliverys->getDocketNumber() . '&nbsp;</label>' .
											'</div>' .
											'<div style="width:126px;" class="divText">' .
												'<label>' . $rowDeliverys->getForwardedTo() . '&nbsp;</label>' .
											'</div>' .
											'<div style="width:140px;" class="divText">' .
												'<label>' . $recBy . '<br /><span style="font-size:10px;">' . $recDate . '<br />(Australia/Melbourne)</span></label>' .
											'</div>' .
											'<div style="width:240px;" class="divText">' .
												'<label>' . $rowDeliverys->getNotes() . '&nbsp;</label>' .
											'</div>' .
								        '</div>';

								$htmlHistory .=	'<div class="row">' .
										'<div title="Part Name."  style="width:200px;" class="divHeadings">' .
											'<label>Part Name.</label>' .
										'</div>' .
										'<div title="Part Code."  style="width:80px;" class="divHeadings">' .
											'<label>Part Code.</label>' .
										'</div>' .
										'<div title="Reconciled Quantity."  style="width:80px;" class="divHeadings">' .
											'<label>Rec. Qty</label>' .
										'</div>' .
										'<div title="Notes."  style="width:400px;" class="divHeadings">' .
											'<label>Notes.</label>' .
										'</div>' .
									'</div>';

								$poRecPartQuery = Factory::service("PurchaseOrderReconcileParts")->findByCriteria("purchaseOrderDeliveryId = " . $rowDeliverys->getId());

								foreach ($poRecPartQuery as $row)
		   						{
		   							$partName = "";
		   							$partCode = "";

	   								$poPart = Factory::service("PurchaseOrderPart")->get($row->getPurchaseOrderPart()->getId());
									if ($poPart instanceof PurchaseOrderPart)
									{
										$partName = $this->getPurchaseOrderPartName($poPart);
										$partType = $poPart->getPartType();
										$partCode = $this->getPartCodeFromPartType($partType);
									}

									if($row->getReconciledQty()!=0)
									{
										$htmlHistory .=	'<div class="row">' .
												'<div style="width:200px;" class="divText">' .
													'<label>' . $partName . '&nbsp;</label>' .
												'</div>' .
												'<div style="width:80px;" class="divText">' .
													'<label>' . $partCode . '&nbsp;</label>' .
												'</div>' .
												'<div style="width:80px;" class="divText">' .
													'<label>' . $row->getReconciledQty() . '&nbsp;</label>' .
												'</div>' .
												'<div style="width:400px" class="divText">' .
													'<label>' . $row->getNotes() . '&nbsp;</label>' .
												'</div>' .
											'</div>';
									}
		   						}

		   						if (!$this->readonly){

									$htmlHistory .= '<div class="buttonPanel"><input type="button" value="Edit" onClick="populateEdit(' . $poId . ',' . $rowDeliverys->getId() . ')"></div>';
									$htmlHistory .= '</div>';
		   						}

							}
						}





					$poAllPartIds = "";
					foreach ($po['parts'] as $poPart)
					{
						$color = 'black';
						$checked = '';
						$radioDisabled = '';
						$img = '';

						if ($poPart->getId() == $this->Page->hiddenPoPartsId->Value)
						{
							$checked = ' checked=true ';
						}

						$partType = $poPart->getPartType();
						if ($partType instanceof PartType)
						{
							$serialised = $partType->getSerialised();
							$partCode = $partType->getAlias(PartTypeAliasType::$partTypeAliasType_codeName);
							$partName = $partType->getName();
						}

						$partStr = $partCode . ' : ' . htmlentities($partName);

						$qty = $poPart->getQty();
						$recQty = $poPart->getReconciledQty();
						$regQty = $poPart->getRegisteredQty();
						$qtyCheck = $qty . '_' . $recQty . '_' . $regQty;

						if ($regQty >= $qty)
						{
							$color = 'grey';
							//$radioDisabled = 'disabled';
						}

						if ($serialised)
						$img = '<img src="/themes/images/small_yes.gif">';

						$usingInactivePartCode = false;
						if ($partCode == DepreciationService::$unknownPartCode)
						{
							$color = 'red';
							$partCode = '????????';
							$partName = $poPart->getPartDescription();
							//$radioDisabled = 'disabled';
							$usingInactivePartCode = true;
						}

						if ($partCode == DepreciationService::$genericPartCode)
						{
							$partCode = '';
							$color = 'grey';
							$partName = $poPart->getPartDescription();
							//$radioDisabled = 'disabled';
							$usingInactivePartCode = true;
						}

						//display a message to logistics saying that the parttype is inactive
						if ($partType->getActive() == 0 && $usingInactivePartCode == false)
						{
							//$radioDisabled = 'disabled';
							$partName .= '<br /><span style="color:red;font-weight:bold;">This part has been deactivated, please contact Purchasing</span>';
						}

						$poPartId = $poPart->getId();
						//$radioEvents = ' onClick="poPartRadioClicked(' .$poPart->getId() . ',\'' . $partStr . '\',' . $poId . ',' . $poPartId . ',\'' . $qtyCheck . '\')"';
                        $radioEvents = '';
						$html .=		'<tr style="color:' . $color . ';">' .
											'<td style="height:20px; font-weight:bold; background-color:white; width:30px; text-align:center;">' . $qty . '</td>' .
											'<td style="height:20px; font-weight:bold; background-color:white; width:30px; text-align:center;">' . $recQty . '</td>' .
											'<td style="height:20px; font-weight:bold; background-color:white; width:30px; text-align:center;">' . $regQty . '</td>' .
											'<td style="height:20px; font-weight:bold; background-color:white; width:30px; text-align:center;">' . $img . '</td>' .
											'<td style="height:20px; font-weight:bold; background-color:white; width:70px; text-align:center;">' . $partCode . '</td>' .
											'<td style="height:20px; padding-left:5px; background-color:white;">' . $partName . '</td>' .
										'</tr>';



						//get update info
						$reconciledQty = "";
						$reconciledNotes = "";
						if($deliveryId){
							$poRecPartQuery = Factory::service("PurchaseOrderReconcileParts")->findByCriteria("purchaseOrderDeliveryId = $deliveryId AND purchaseOrderPartId = " . $poPartId);
							if(count($poRecPartQuery)>=1){
								$poReconcileParts = $poRecPartQuery[0];
								if ($poReconcileParts instanceof PurchaseOrderReconcileParts)
								{
									$reconciledQty = $poReconcileParts->getReconciledQty();
									$reconciledNotes = $poReconcileParts->getNotes();
								}
							}
						}

						$disable = '';
						if(!$deliveryId){
							if($recQty >= $qty){
								$disable = ' disabled ';
							}
						}

						if($disable == ' disabled '){
							$reconciledNotes = "Part has been fully reconciled.";
						}

						$html2 .='<div class="row">
							<div class="divText" style="width:260px;">' . $partName . '</div>
							<div class="divText" style="width:70px;">' . $partCode . '</div>
							<div class="divText" style="width:50px;"><input ' . $disable . ' type="text" value="' . $reconciledQty . '" name="reconcile_' . $poPartId.'" size="4" id="reconcile_' . $poPartId.'"></div>
							<div class="divText" style="width:380px;"><textarea  ' . $disable . ' name="notes_' . $poPartId.'" rows="3" cols="43" id="notes_' . $poPartId.'">' . $reconciledNotes . '</textarea></div>
						</div>';

						$poAllPartIds .= $poPartId . ",";
					}

					$html .= 		'</table>';

					$add_new = "";
					if($deliveryId){
						$buttonValue = "Update";
						if (!$this->readonly){
							$add_new = "<input type='button' onClick='add_new()' value='Add New'>";
						}
					}else{
						$buttonValue = "Save";
					}


					if($this->readonly)
						Debug::show('fddddddddddddddddddd');


					if (!$this->readonly){
						$html2 .='<div class="buttonPanel"><input id="SaveButton" type="button" value="' . $buttonValue . '" name="SaveButton" onClick="saveDelivery(\'' . $poId . '\',\'' . $poAllPartIds . '\')">' . $add_new;
						$html2 .='</div>';
					}


					$html2 .='</div>';
				}
				else
				{
					$html .= 		'<span style="color:red; font-weight:bold;">Unable to retrieve parts from the Purchase Order.</span>';
				}

				$html .= 		'</td>' .
							'</tr>' .
						'</table>';


			}
			else
			{
				$html = '<span style="font-size:10px; color:red; font-weight:bold;">Unable to retrieve PO information...</span>';
			}
			$this->poPartsLbl->Text = $html . $html2 . $htmlHistory . '<br />';
		}
	}

	private function getPurchaseOrderPartName($poPart){
		$partName = "";
		$partType = $poPart->getPartType();
		if ($partType instanceof PartType)
		{
			$partCode = $partType->getAlias(PartTypeAliasType::$partTypeAliasType_codeName);
		}


		if($partCode){
			if ($partCode == DepreciationService::$unknownPartCode)
			{
				$partName = $poPart->getPartDescription();
			}

			if ($partCode == DepreciationService::$genericPartCode)
			{
				$partName = $poPart->getPartDescription();
			}
		}

		if(!$partName){
			$partName = $poPart->getPartType()->getName();
		}
		return $partName;
	}


}
?>