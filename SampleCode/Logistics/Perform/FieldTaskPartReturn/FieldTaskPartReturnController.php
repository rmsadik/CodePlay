<?php
/**
 * Register Part Instance Page - Create Part Instance Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class FieldTaskPartReturnController extends HydraPage
{
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

		$this->roleLocks = "pages_all,pages_logistics,menu_staging";
	}
	
	/**
	 * (non-PHPdoc)
	 * @see TPage::onPreInit()
	 */
	public function onPreInit($param)
	{
		parent::onPreInit($param);
		$this->getPage()->getClientScript()->registerScriptFile('bsuitebarcodeJs', $this->publishFilePath(Prado::getApplication()->getBasePath() . '/../common/bsuiteJS/barcode/barcode.js'));
		
		$this->menuContext = 'fieldtaskpartreturn';
		
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
		parent::onLoad($param);
		
		//first hit of the page
		if(!$this->IsPostBack && !$this->IsCallBack)
		{
			$defaultWarehouse = Factory::service("Warehouse")->getDefaultWarehouse(Core::getUser());
			if ($defaultWarehouse instanceof Warehouse)
			{
				if(isset($_SESSION[get_class($this) . '_selectedWarehouseId']) && trim($_SESSION[get_class($this) . '_selectedWarehouseId']) !== '') 
				{
					$this->warehouseId->Value = trim($_SESSION[get_class($this) . '_selectedWarehouseId']);
				} 
				else 
				{
					$this->warehouseId->Value = Factory::service("Warehouse")->getWarehouseIdBreadCrumbs($defaultWarehouse);
				}
			}
		}
	}
	
	/**
	 * When searching for field task
	 *
	 * @param TCallback $sender The sender of the call back
	 * @param Mixed     $param  The parameters sent along with this event
	 */
	public function searchFieldTask($sender, $param)
	{
		$result = $errors = array();
		try 
		{
			if(!isset($param->CallbackParameter->txtTaskNo) || ($txtTaskNo = trim($param->CallbackParameter->txtTaskNo)) === '')
			{
				throw new Exception("Invalid request data!");
			}
			
			//get the params for the page
			$dhc = Factory::service("DontHardcode")->getParamValueForParamName('FieldTaskPartReturn', true);
			if (empty($dhc))
			{
				throw new Exception('<br />Unable to load required params, please contact BSuiteHelp...');
			}
			
			$statusInfo = array();
			foreach ($dhc['workTypes'] as $wt)
			{
				$statusInfo[$wt['id']] = $wt["statusInfo"];
			}

			//get the field task
			$ft = Factory::service("FieldTask")->findByCriteria("(ft.id=? OR ft.clientFieldTaskNumber=?) AND ft.workTypeId IN (" . implode(',', array_keys($statusInfo)) . ")", array($txtTaskNo, $txtTaskNo));
			if (count($ft) == 0)
			{
				throw new Exception("No Field Tasks found...");
			}
			else if (count($ft) > 1)
			{
				throw new Exception("Multiple Field Tasks found...");
			}
			
			if (!$ft[0] instanceof FieldTask)
			{
				throw new Exception("Invalid Field Task...");
			}
			
			$ft = $ft[0];
			$ftId = $ft->getId();
			$status = $ft->getStatus();
			$wtId = $ft->getWorkType()->getId();
			
			$result['ft'] = array(	'id'=> $ftId,
								  	'status' => $status, 					//the current field task status
									'statusInfo' => $statusInfo[$wtId],		//the status info for page logic
									'nextStatuses' => array());				//the next statuses we can progress to (including current)
				
			if (!in_array($status, array($statusInfo[$wtId]["start"], $statusInfo[$wtId]["scan"])))
			{
				$result['ft']['errMsg'] = "The task (" . $ftId . ") is in '" . $status . "', expecting '" . $statusInfo[$wtId]["start"] . "'"; 
			}	
			else
			{
				$nextStatuses = Factory::service("WFProcess")->getWaitingFieldTaskStatus($ft);
				foreach ($nextStatuses as $s)
				{
					$result['ft']['nextStatuses'][] = $s; 
				}
			}
			
			//check here if we will need to update the FieldTaskProperty later
			foreach ($dhc['workTypes'] as $wt)
			{
				if ($wt['id'] == $wtId)
				{
					if (array_key_exists('requiresTaskProgressionForRA', $wt))
					{
						$result['ft']['updateFieldTaskPropertyOnFinish'] = true;
					}
				}
			}
		} 
		catch (Exception $ex) 
		{
			$errors[] = $ex->getMessage();
		}
		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}
	
	/**
	 * Update task status
	 *
	 * @param TCallback $sender The sender of the call back
	 * @param Mixed     $param  The parameters sent along with this event
	 */
	public function updateTaskStatus($sender, $param)
	{
		$result = $errors = array();
		$result['ft'] = array();
		
		try 
		{
			
			if (!isset($param->CallbackParameter->ftId) || ($txtTaskNo = trim($param->CallbackParameter->ftId)) === '' ||
				!isset($param->CallbackParameter->status) || ($txtTaskNo = trim($param->CallbackParameter->status)) === '')
			{
				throw new Exception("Invalid request data!");
			}
				
			$ft = Factory::service("FieldTask")->getFieldTask(trim($param->CallbackParameter->ftId));
			if (!$ft instanceof FieldTask)
			{
				throw new Exception("Invalid Field Task...");
			}
			
			$msg = '';
			$ftId = $ft->getId();
			$status = $ft->getStatus();
			$newStatus = trim($param->CallbackParameter->status);
			
			try
			{
				if ($status == $newStatus) //make sure we're not already in the status
				{
					throw new Exception("The task (" . $ftId . ") is already in '" . $status . "', nothing to do...");
				}
				
				Factory::service("WFProcess")->updateStatus($ft, $newStatus);				
				$ft = Factory::service("FieldTask")->getFieldTask($ftId);
				
				//check here if we are to update the notes of the task
				if (isset($param->CallbackParameter->taskNotes) && ($taskNotes = trim($param->CallbackParameter->taskNotes)) !== '')
				{
					$now = new HydraDate();
					
					$address = $ft->getAddress();
					if ($address instanceof Address)
					{
						$tz = $address->getTimezone();
						$now->setTimeZone($tz);
					}
					$ft->setAdditionalNotes($ft->getAdditionalNotes() . " [!" . Core::getUser()->getPerson() .  " - " . $now .  " - " . $taskNotes . "!]");
					$ft = Factory::service("FieldTask")->saveAndReturnFieldTask($ft);
					
					//check here if we have failed, and need to update log part instance move
					if (isset($param->CallbackParameter->failed) && $param->CallbackParameter->failed === true)
					{
						$pi = Factory::service("PartInstance")->getPartInstance($param->CallbackParameter->piId);
						if ($pi instanceof PartInstance)
						{
							Factory::service("PartInstance")->movePartInstanceToWarehouse($pi, $pi->getQuantity(), $pi->getWarehouse(), false, null, "Failed BOM TEST" . $this->_getCommentPostFix($ft), false);
						}
					}
				}
				
				//check here if we are to update the field task property
				if (isset($param->CallbackParameter->updateFtpPiId) && $param->CallbackParameter->updateFtpPiId !== false)
				{
					$ftp = new FieldTaskProperty();
					$ftp->setFieldTask($ft);
					$ftp->setIsEditable(false);
					$ftp->setName(FieldTaskPropertyService::RECEIVED_PI_ID_NAME);
					$ftp->setDescription(strtoupper(FieldTaskPropertyService::RECEIVED_PI_ID_NAME));
					$ftp->setValue($param->CallbackParameter->updateFtpPiId);
					
					Factory::service("FieldTaskProperty")->save($ftp);		
					$msg = 'Successfully added Field Task Property: ' . FieldTaskPropertyService::RECEIVED_PI_ID_NAME;
					
					$pi = Factory::service("PartInstance")->getPartInstance($param->CallbackParameter->updateFtpPiId);
					if ($pi instanceof PartInstance)
					{
						$alias = new PartInstanceAlias();
						$alias->setAlias('Received part instance linked to task #' . $ft->getId());
						$alias->setPartInstance($pi);
						$alias->setPartInstanceAliasType(Factory::service("PartInstanceAliasType")->get(PartInstanceAliasType::ID_HOT_MESSAGE));
						Factory::service("PartInstanceAlias")->save($alias);
						$msg .= "\nSuccessfully added Part Instance Hot Message: " . $alias->getAlias();
					}
					
				}
			}
			catch (Exception $ex)
			{
				$result['ft']['errMsg'] = $ex->getMessage();
			}
			
			$result['ft']['id'] = $ftId;
			$result['ft']['status'] = $ft->getStatus();
			$result['ft']['nextStatuses'] = array();
			$result['ft']['alertMsg'] = $msg;
				
			$nextStatuses = Factory::service("WFProcess")->getWaitingFieldTaskStatus($ft);
			foreach ($nextStatuses as $s)
			{
				$result['ft']['nextStatuses'][] = $s; 
			}
		} 
		catch (Exception $ex) 
		{
			$errors[] = $ex->getMessage();
		}
		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}
	
	/**
	 * Find Returned Part
	 *
	 * @param TCallback $sender The sender of the call back
	 * @param Mixed     $param  The parameters sent along with this event
	 */
	public function searchReturnedPart($sender, $param)
	{
		$result = $errors = array();
		
		try 
		{
			if (!isset($param->CallbackParameter->ftId) || ($txtTaskNo = trim($param->CallbackParameter->ftId)) === '' ||
				!isset($param->CallbackParameter->txtSerialNo) || ($txtTaskNo = trim($param->CallbackParameter->txtSerialNo)) === '')
			{
				throw new Exception("Invalid request data!");
			}

			$serialNo = trim($param->CallbackParameter->txtSerialNo);
			$pi = Factory::service("PartInstance")->searchPartInstancesByPartInstanceAlias($serialNo, array(1,9), true);
			if (count($pi) == 0)
			{
				throw new Exception("Unable to find a matching part instance for serial no (" . $serialNo . ")");
			}
			else if (count($pi) > 1)
			{
				throw new Exception("Multiple part instances found for serial no (" . $serialNo . ")");
			}
			
			$pi = $pi[0];
			$pt = $pi->getPartType();
			$ptId = $pt->getId();
			$pc = $pt->getAlias(1);
			$ptMatch = false;
			
			//here we are doing a pure part type match, nothing to do with FieldTask
			if (isset($param->CallbackParameter->matchPartTypeId))
			{
				$result['pi'] = array('id'=> $pi->getId(),
									  'serialNo' => $serialNo,
									  'ptInfo' => $pc . " ::: " . $pt->getName());
				
				if ($ptId !== trim($param->CallbackParameter->matchPartTypeId)) //part type doesn't match
				{
					$result['pi']['errMsg'] = "The scanned part type (" . $pc . ") does not match...";
				}
			}
			else
			{
				$ft = Factory::service("FieldTask")->getFieldTask(trim($param->CallbackParameter->ftId));
				if (!$ft instanceof FieldTask)
				{
					throw new Exception("Invalid Field Task...");
				}
				$ftId = $ft->getId();
				$wlIds = array();
	
				//first try to look in the worklog for a REQUIRED part
				$q = new DaoReportQuery("PartTypeAction");
				$q->column("ptaction.partTypeId");
				$q->setAdditionalJoin("INNER JOIN worklog wl ON wl.id=ptaction.worklogid AND wl.active=1
									   INNER JOIN fieldtask ft ON ft.id=wl.fieldtaskid AND ft.active=1 AND ft.id=" . $ftId);
				$q->where("ptaction.active=1 AND ptaction.actiontypeid=" . ActionType::ID_REQUIRED);
				$res = $q->execute(false);
				foreach ($res as $r)
				{
					if ($ptId == $r[0])
					{
						$ptMatch = true;
						break;
					}
				}
				
				//look at the fieldtask part type instead
				if ($ptMatch === false)
				{
					$ftPt = $ft->getPartType();
					if ($ftPt instanceof PartType)
					{
						if ($ftPt->getId() == $ptId)
						{
							$ptMatch = true;
						}
					}
				}
				
				$errMsg = false;
				$recipe = array(); 	//to return the recipe
				
				//whether or not the parts in parts is complete to match the BOM
				$bomMatch = true;
				
				//if we still don't have a match, then we fail here
				if ($ptMatch === false)
				{
					$errMsg = "The part type (" . $pc . ") does NOT match the field task information for serial no (" . $serialNo . ")";
				}
				else 
				{
					//first lets try to find a BOM for the part type
					$bom = $pt->getBillOfMaterials();
					$bomReqd = array();
					foreach ($bom as $b)
					{
						$reqPt = $b->getRequiredPartType();
						if ($reqPt instanceof PartType)
						{
							$bomReqd[$reqPt->getId()] = $b->getQuantity();
// 							$recipe[] = array(	'ptId' => $reqPt->getId(), 
// 												'qty' => $b->getQuantity(), 
// 												'pc' => $reqPt->getAlias(),
// 												'name' => $reqPt->getName(),
// 												'hp' => ($reqPt->getAlias(18) == null ? false : true),
// 												'serialised' => (bool)$reqPt->getSerialised());
						}
					}
					
					//lets look for child parts, and see if the BOM is missing anything
					if (empty($recipe))
					{
						$res = $this->_getChildPartsForPartInstance($pi);						
						foreach ($res as $r)
						{
							$recipe[] = array(	'piId' => $r['piId'],
												'ptId' => $r['ptId'],
												'qty' => $r['qty'], 
												'pc' => $r['pc'],
												'name' => $r['name'],
												'hp' => ($r['hp'] == null ? false : true),
												'serialised' => (bool)$r['serialised']);
							
							//see if we match the BOM
							if (!empty($bomReqd) && $bomMatch == true)
							{
								if (array_key_exists($r['ptId'], $bomReqd))
								{
									//we are missing parts from the BOM
									if ($bomReqd[$r['ptId']] > $r['qty'])
									{
										$bomMatch = false;
									}
								}
							}
						}
					}
				}
				
				$result['ft'] = array(	'id'=> $ftId,
									  	'parentInfo' => array($pi->getId() => $pt->getAlias() . ' ::: ' . $pt->getName()),
									  	'parentMatch' => $ptMatch,
									  	'bomMatch' => $bomMatch,
										'recipe' => $recipe);
				
				if ($errMsg !== false)
				{
					$result['ft']['errMsg'] = $errMsg;
				}
			}
		} 
		catch (Exception $ex) 
		{
			$errors[] = $ex->getMessage();
		}
		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}
	
	/**
	 * Gets the required child part infor for a given Part Instance
	 * @param PartInstance $pi
	 * @return array()
	 */
	private function _getChildPartsForPartInstance(PartInstance $pi)
	{
		$q = new DaoReportQuery("PartInstance");
		$q->column("pi.id", "piId");
		$q->column("pt.id", "ptId");
		$q->column("pt.serialised", "serialised");
		$q->column("pt.name", "name");
		$q->column("COUNT(pi.id)", "qty");
		$q->column("pta.alias", "pc");
		$q->column("ptaHp.alias", "hp");
		$q->setAdditionalJoin( "INNER JOIN parttype pt ON pt.id=pi.parttypeid AND pt.active=1
								INNER JOIN parttypealias pta ON pta.parttypeid=pt.id AND pta.active=1 AND pta.parttypealiastypeid=" . PartTypeAliasType::ID_PARTCODE . "
								LEFT JOIN parttypealias ptaHp ON ptaHp.parttypeid=pt.id AND ptaHp.active=1 AND ptaHp.parttypealiastypeid=" . PartTypeAliasType::ID_HIGH_PRIORITY_PART);
		$q->where("pi.active=1 AND pi.parentid=" . $pi->getId());
		$q->groupBy("pt.id");
		$q->orderBy("pt.serialised", DaoReportQuery::DESC);
		return $q->execute(false, PDO::FETCH_ASSOC);
	}
	
	/**
	 * Balance Parts
	 *
	 * @param TCallback $sender The sender of the call back
	 * @param Mixed     $param  The parameters sent along with this event
	 */
	public function balanceParts($sender, $param)
	{
		$result = $errors = array();
		
		try 
		{
			if (!isset($param->CallbackParameter->whId) || ($txtTaskNo = trim($param->CallbackParameter->whId)) === '' ||
				!isset($param->CallbackParameter->ftId) || ($txtTaskNo = trim($param->CallbackParameter->ftId)) === '' ||
				!isset($param->CallbackParameter->recipe) || ($txtTaskNo = trim($param->CallbackParameter->recipe)) === '')
			{
				throw new Exception("Invalid request data!");
			}
			
			$pi = Factory::service("PartInstance")->getPartInstance($param->CallbackParameter->piId);
			if (!$pi instanceof PartInstance)
			{
				throw new Exception("Invalid part instance...");
			}
			
			$whIdBread = explode('/', $param->CallbackParameter->whId);
			$wh = Factory::service("Warehouse")->getWarehouse(end($whIdBread));
			if (!$wh instanceof Warehouse)
			{
				throw new Exception("Invalid destination warehouse...");
			}
			
			$ft = Factory::service("FieldTask")->getFieldTask($param->CallbackParameter->ftId);
			if (!$ft instanceof FieldTask)
			{
				throw new Exception("Invalid field task...");
			}
			
			$site = $ft->getSite();
			if (!$site instanceof Site)
			{
				throw new Exception("Invalid field task site...");
			}

			$targetWh = $site->getWarehouses();
			if (count($targetWh) > 0)				//we're linked to a site
			{
				$targetWh = $targetWh[0];
			}
			else
			{
				$targetWh = Factory::service("Warehouse")->getSiteWarehouse();
			}
			if ($targetWh->getParts_allow() == 0)
			{
				throw new Exception("Site Warehouse does not allow parts...");
			}
			
			if ($wh->getParts_allow() == 0)
			{
				throw new Exception("Warehouse does not allow parts...");
			}
			
			$recipe = json_decode($param->CallbackParameter->recipe, true);
			if (!is_array($recipe))
			{
				throw new Exception("Invalid parts list...");
			}
			
			$errMsg = false;
			$moveToSite = $moveToParent = $changeStatus = array();
			
			//go through and see what was returned
			foreach ($recipe as $r)
			{
				$piId = $r['piId'];										//the original piId from the kit
				$ptId = $r['ptId'];										//the original ptId from the kit
				$serialised = $r['serialised'];							//serialised?
				
				if ($serialised)
				{
					$foundStatusId = $r['found']['statusId'];		//the status of the returned part
					$foundPiId = $r['found']['piId'];				//the returned piId
					
					if ($piId != $foundPiId)							//a different serialised part was returned
					{
						$moveToSite[] = $piId;							//move the original part to site, as it was used
						$moveToParent[$foundPiId] = $foundStatusId;		//move the returned part into the kit and set the status
					}
					else
					{
						$changeStatus[$piId] = $foundStatusId;			//update the status of the returned part
					}
				}
				else
				{
					
				}
			}
			$commentPostFix = $this->_getCommentPostFix($ft);
			//move all the parts to site
			foreach ($moveToSite as $piId)
			{
				$pInst = Factory::service("PartInstance")->getPartInstance($piId);
				if ($pInst instanceof PartInstance)
				{
					$pInst = Factory::service("PartInstance")->movePartInstanceToWarehouse($pInst, $pInst->getQuantity(), $targetWh, false, null, "Moved to site via BOM TEST" . $commentPostFix, false, $site);
				}
			} 
			$piAlias = $pi->getAlias(PartInstanceAliasType::ID_SERIAL_NO);
			
			//put all parts into the parent
			foreach ($moveToParent as $piId => $statusId)
			{
				$pInst = Factory::service("PartInstance")->getPartInstance($piId);
				if ($pInst instanceof PartInstance)
				{
					$status = Factory::service("PartInstanceStatus")->get($statusId);
					if ($status instanceof PartInstanceStatus)
					{
						$pInst->setPartInstanceStatus($status);
						Factory::service("PartInstance")->savePartInstance($pInst);
						$pInst = Factory::service("PartInstance")->getPartInstance($piId);
						
					}
					$comment = new PartInstanceAlias();
					$comment->setPartInstanceAliasType(Factory::service("PartInstanceAliasType")->get(PartInstanceAliasType::ID_COMMENTS));
					$comment->setPartInstance($pInst);
					$comment->setAlias("Installed in parent (" . $piAlias . ") via BOM TEST" . $commentPostFix);
					Dao::save($comment);
					
					$pInst = Factory::service("PartInstance")->installPartInstance($pi, $pInst);
				}
			}
			
			//update statuses
			foreach ($changeStatus as $piId => $statusId)
			{
				$pInst = Factory::service("PartInstance")->getPartInstance($piId);
				if ($pInst instanceof PartInstance) 
				{
					$status = Factory::service("PartInstanceStatus")->get($statusId);
					if ($status instanceof PartInstanceStatus)
					{
						$pInst->setPartInstanceStatus($status);
						Factory::service("PartInstance")->savePartInstance($pInst);
						$pInst = Factory::service("PartInstance")->getPartInstance($piId);
					}
					$comment = new PartInstanceAlias();
					$comment->setPartInstanceAliasType(Factory::service("PartInstanceAliasType")->get(PartInstanceAliasType::ID_COMMENTS));
					$comment->setPartInstance($pInst);
					$comment->setAlias("Status updated via BOM TEST" . $commentPostFix);
					Dao::save($comment);
				}
			}
			
			//now we need to move the parent to the location selected in the tree
			$pi = Factory::service("PartInstance")->getPartInstance($pi->getId());
			if ($pi instanceof PartInstance)
			{
				$pi = Factory::service("PartInstance")->movePartInstanceToWarehouse($pi, $pi->getQuantity(), $wh, false, null, "Moved via successful BOM TEST" . $commentPostFix);
			}
			
			//add the success comments
			$now = new HydraDate();
				
			$address = $ft->getAddress();
			if ($address instanceof Address)
			{
				$tz = $address->getTimezone();
				$now->setTimeZone($tz);
			}
			
			$taskNotes = 'Returned part (' . $piAlias . ') passed the BOM TEST and was moved to (' . Factory::service("Warehouse")->getWarehouseBreadCrumbs($wh, true, '/') . ')';
			$ft->setAdditionalNotes($ft->getAdditionalNotes() . " [!" . Core::getUser()->getPerson() .  " - " . $now .  " - " . $taskNotes . "!]");
			$ft = Factory::service("FieldTask")->saveAndReturnFieldTask($ft);
			
			if ($errMsg !== false)
			{
				$result['ft']['errMsg'] = $errMsg;
			}
			
			$result['ft']['id'] = $ft->getId();
			
		} 
		catch (Exception $ex) 
		{
			$errors[] = $ex->getMessage();
		}
		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}
	
	/**
	 * Returns a string based on fieldtask numbers
	 * @param FieldTask $ft
	 * @return string
	 */
	private function _getCommentPostFix(FieldTask $ft)
	{
		$str = ": (Byte# " . $ft->getId();
		if ($ft->getClientFieldTaskNumber() != '')
			$str .= " / Client# " . $ft->getClientFieldTaskNumber();
		
		return $str . ")";
	}
	
	public function getDefaultWarehouseId()
	{
		$defaultWarehouseId = Factory::service("UserPreference")->getOption(Core::getUser(),'defaultWarehouse');
		$defaultWarehouse = Factory::service("Warehouse")->getWarehouse($defaultWarehouseId);
		
		if ($defaultWarehouse instanceof Warehouse)
		{
			return $defaultWarehouse->getId();
		}
		return null;		
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
	
}


?>