<?php
/**
 * Edit Part Instance Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class ReregisterPartInstancesController extends HydraPage
{
    /**
     * The menu heighlighter
     *
     * @var string
     */
    public $menuContext;
    /**
     * constructor
     */
    public function __construct()
    {
        parent::__construct();
        $this->menuContext = 'partInstanceReRegister';
        $this->roleLocks = "pages_all,pages_logistics,page_logistics_partInstanceReRegister, menu_staging";

    }

    /**
     * (non-PHPdoc)
     * @see TPage::onPreInit()
     */
    public function onPreInit($param)
    {
        parent::onPreInit($param);
        $layout = "Application.layouts.LogisticsLayout";
        if(trim($this->Request["btcode"]) !== "" || (isset($this->Request["errorMsg"]) && trim($this->Request["errorMsg"])!== ""))
            $layout = "Application.layouts.PlainLayout";
        else if (trim($this->Request["for"]) === "staging")
            $layout = "Application.layouts.StagingLayout";
        $this->getPage()->setMasterClass($layout);
    }

    /**
     * (non-PHPdoc)
     * @see HydraPage::onLoad()
     */
    public function onLoad($param)
    {
        parent::onLoad($param);
        if (!$this->IsPostBack)
        {
            $js = "$('searchbarcode').focus();";
            if(isset($this->Request['id']) && trim($this->Request["id"]) !== '')
            {
                $errorMsg = (isset($this->Request['errorMsg']) && trim($this->Request["errorMsg"]) !== '') ? trim($this->Request["errorMsg"]) : '';
                if(!($partInstance = Factory::service('PartInstance')->get(trim($this->Request["id"]))) instanceof PartInstance)
                {
                    $errorMsg .= ". Can't find part instance with ID: " . trim($this->Request["id"]) . "!";
                }
                else
                {
                    $js = "$('searchbarcode').value = '" . strtoupper($partInstance->getAlias(PartInstanceAliasType::ID_SERIAL_NO)) . "';";
                    $js .= "$('searchbarcode').disabled = true;";
                    $js .= "$('searchid').value = '" . trim($this->Request["id"]) . "';";
                    $js .= "$('searchBtn').disabled = true;";
                    $js .= "pageJs.getPI($('searchBtn'));";

                }
                $this->setErrorMessage($errorMsg);
            }
            else if(isset($this->Request["btcode"]) && trim($this->Request["btcode"]) !== '' )
            {
                $js = "$('searchbarcode').value = '" . strtoupper(trim($this->Request["btcode"])) . "';";
                $js .= "$('searchbarcode').disabled = true;";
                $js .= "$('searchBtn').disabled = true;";
                $js .= "pageJs.getPI($('searchBtn'));";
            }
            if (isset($this->Request['closeAfterSave']) && trim($this->Request["closeAfterSave"]) === 'true')
            {
            	$js .= "pageJs._setCloseAfterSave(true);";
            }

            $this->getPage()->getClientScript()->registerEndScript('editPIPageJs', $js);
        }
    }

    /**
     * Getting the part instance information
     *
     * @param TCallback          $sender The event sender
     * @param TCallbackParameter $param  The event params
     */
    public function getPI($sender, $param)
    {
    	$result = $errors = array();
        $countOfMerge =0;

        try
        {
            $barcode = isset($param->CallbackParameter->barcode) ? trim($param->CallbackParameter->barcode) : '';
            $piId = isset($param->CallbackParameter->piId) ? trim($param->CallbackParameter->piId) : '';

            $partInstances = array();
            if ($piId !== '' && ($partInstance = Factory::service("PartInstance")->get($piId)) instanceof PartInstance)
            	$partInstances[] = $partInstance;
            else if ($barcode !== '')
            {
            	$this->activeErrorLabel->Text = '';
            	$partInstances = Factory::service("PartInstance")->searchPartInstancesByPartInstanceAlias($barcode, array(  PartInstanceAliasType::ID_SERIAL_NO,
            																												PartInstanceAliasType::ID_CLIENT_ASSET_NUMBER,
            																												PartInstanceAliasType::ID_SUPPLIER_ASSET_NO,
            																												PartInstanceAliasType::ID_MANUFACTUR_SERIAL,
            																												PartInstanceAliasType::ID_BOX_LABEL), true,null,30,false,true);
            	if(isset($partInstances[0]))
            	{
            		$countOfMerge =  Factory::service("PartInstance")->countTimesPartInstanceMerged($partInstances[0]);
            	}
            }
       		if(count($partInstances) === 0)
                throw new Exception("No part instance found!");
            //TODO:: Hacking to display the HotMsg for a part type, when the part instance provided

            else if($countOfMerge > 0)
            {
	            $toMerge = Factory::service("LogPartInstanceMerge")->findByCriteria("mergeFromId = ?",array($partInstances[0]->getId()));

	                     foreach($toMerge as $logmerge)
	                     {
	                            if ($logmerge instanceof LogPartInstanceMerge)
	                            {
	                                  $piDetails = $logmerge->getMergeTo();


	                                  if($piDetails instanceof PartInstance)
	                                  {
	                                  		 $toPiAlias = $piDetails->getId();
	                                         $alias = $piDetails->getAlias();
	                                  }

	                            	if($alias !== null)
	                            	{
	                            		$this->activeErrorLabel->Text = "This part instance has been merged to <a target='_blank' href = /parthistory/searchparttext/" .  $toPiAlias .">" . $alias . "</a>" ;
	                            	}
	                            }
	                     }
            }
            else if(count($partInstances) === 1)
            {
            	$this->partType->loadPartTypeId($partInstances[0]->getPartType()->getId());
            }

            $piHolder = array();
            $result['piArray'] = array();
            foreach($partInstances as $pi)
            {
            	if(!in_array($pi->getId(), $piHolder))//get unique PI id to avoid duplicate conflict of same part.
            	{
	                Factory::service("PartInstance")->checkIfPartIsRestricted($pi);

	                //check if there are any open tasks
	                $openTasks = Factory::service('FieldTask')->getOpenTasksForPI($pi);
	                if (count($openTasks) > 0)
	                {
	                	$ftIds = array_map(create_function('$a', 'return $a->getId();'), $openTasks);
	                	throw new Exception(PartInstanceLogic::getOpenTasksMessageForPartInstance($pi, $ftIds));
	                }

	                $result['piArray'][] = $this->_getPIJsonArray($pi);
	                $piHolder[]=$pi->getId();
            	}
            }
        }
        catch(Exception $ex)
        {
            $errors[] = $ex->getMessage();
        }
        $param->ResponseData = Core::getJSONResponse($result, $errors);
    }

    /**
     * change Part Type information
     *
     * @param TCallback          $sender The event sender
     * @param TCallbackParameter $param  The event params
     */
    public function changePT($sender, $param)
    {
        $result = $errors = array();
        try
        {
            $piId = isset($param->CallbackParameter->piId) ? trim($param->CallbackParameter->piId) : '';
            if(!($partInstance = Factory::service("PartInstance")->get($piId)) instanceof PartInstance)
                throw new Exception("System Error: Invalid part instance(ID= " . $piId . ")!");

            $ptId = isset($param->CallbackParameter->ptId) ? trim($param->CallbackParameter->ptId) : '';
            if(!($partType = Factory::service("PartType")->get($ptId)) instanceof PartType)
                throw new Exception("System Error: Invalid part type(ID= " . $ptId . ")!");

            $result['piArray'] = array($this->_getPIJsonArray($partInstance, $partType));
        }
        catch(Exception $ex)
        {
            $errors[] = $ex->getMessage();
        }
        $param->ResponseData = Core::getJSONResponse($result, $errors);
    }

    /**
     * checking whether there are conflicts for unique aliases and new serial number
     *
     * @param TCallback          $sender The event sender
     * @param TCallbackParameter $param  The event params
     */
    public function chkConflict($sender, $param)
    {
        $result = $errors = array();
        try
        {
            //getting the part instance object
            $piId = isset($param->CallbackParameter->id) ? trim($param->CallbackParameter->id) : '';
            if(!($partInstance = Factory::service("PartInstance")->get($piId)) instanceof PartInstance)
                throw new Exception("System Error: Invalid part instance(ID= " . $piId . ")!");

           //check valid Barcode
            $newBarcode = isset($param->CallbackParameter->newbarcode) ? trim($param->CallbackParameter->newbarcode) : '';
            if(isset($newBarcode) && $newBarcode>'')
            {
          		if(!BarcodeService::validateBarcode($newBarcode, BarcodeService::BARCODE_REGEX_CHK_PART_INSTANCE))
          			throw new Exception('Invalid Barcode Provided');
            }

            //check newbarcode for BER status
            $checkBERedPi = Factory::service('PartInstance')->searchPartInstanceByBarcodeWithFilters($newBarcode);

			//handle deactivated BER
            if(!$checkBERedPi instanceof PartInstance)
            	$checkInActiveBERedPi = Factory::service('PartInstanceAlias')->findByCriteria("alias=? and partinstancealiastypeid=1",array($newBarcode),true);

            $berErrorMsg = 'New Serial Number: You cannot use BERed serial number.';
            if(isset($checkBERedPi)&&count($checkBERedPi)>0)
            {
            	if((int)$checkBERedPi[0]->getPartInstanceStatus()->getId() === PartInstanceStatus::ID_PART_INSTANCE_STATUS_BER)
	            	throw new Exception($berErrorMsg);
            }
            else if(isset($checkInActiveBERedPi)&&count($checkInActiveBERedPi)>0)
            {
            	if((int)$checkInActiveBERedPi[0]->getPartInstance()->getPartInstanceStatus()->getId() ===  PartInstanceStatus::ID_PART_INSTANCE_STATUS_BER)
            		throw new Exception($berErrorMsg);
            }

            //getting the part parttype object
            $ptId = (isset($param->CallbackParameter->parttype) && isset($param->CallbackParameter->parttype->id)) ? trim($param->CallbackParameter->parttype->id) : '';
            if(!($partType = Factory::service("PartType")->get($ptId)) instanceof PartType)
                throw new Exception("System Error: Invalid part type(ID= " . $ptId . ")!");

            //getting unique ids
            if(!isset($param->CallbackParameter->avialPIATs))
                throw new Exception("System Error: No part instance alias types passed in!");
            $avialPIATs = json_decode(json_encode($param->CallbackParameter->avialPIATs), true);
            $uniqueIds = array_filter(array_map(create_function('$a', 'return ($a["unique"] === true ? $a["id"] : null);'), array_values($avialPIATs)));

            //getting all the aliases
            if(!isset($param->CallbackParameter->aliases))
                throw new Exception("System Error: No part instance alias passed in!");
            $aliases = json_decode(json_encode($param->CallbackParameter->aliases), true);

            //getting the Maybe-conflict partinstance
            $conflictPIs = array();
            if(isset($aliases[PartInstanceAliasType::ID_SERIAL_NO]))
            {
                //find the conflicted serial number
                foreach($aliases[PartInstanceAliasType::ID_SERIAL_NO] as $alias)
                {
                    if((trim($alias['id']) === '') && (!isset($alias['deactivate']) || $alias['deactivate'] === false))
                    {
                        $partInstances = Factory::service("PartInstance")->searchPartInstanceByBarcodeWithFilters(trim($alias['alias']));
                        foreach($partInstances as $pi)
                        {
                            if($pi->getId() !== $partInstance->getId())
                            {
                                $conflictPIs[] = $pi;
                            }
                        }
                    }
                }
            }

            //getting all the unique aliases for this part instance
            $aliases[PartInstanceAliasType::ID_SERIAL_NO] = null;
            $aliases = array_filter($aliases);
            $uniqAlias = array(); //this is the aliases with the unique PartInstanceAliasType for this part instance
            foreach($aliases as $typeId => $aliasArray)
            {
                if(!in_array($typeId, $uniqueIds))
                    continue;
                foreach($aliasArray as $alias)
                {
                    if(!isset($uniqAlias[$typeId]))
                        $uniqAlias[$typeId] = array();
                    //getting the only one that user wants to keep
                    if((!isset($alias['deactivate']) || $alias['deactivate'] === false))
                        $uniqAlias[$typeId][] = trim($alias['alias']);
                }
            }
            $confictsPIATIds = array();
            $conficts = $this->_findConflicts($uniqAlias, $partInstance, $conflictPIs, $confictsPIATIds);
            $result['confictsPIATIds'] = $confictsPIATIds;
            $result['conficts'] = $conficts;
        }
        catch(Exception $ex)
        {
            $errors[] = $ex->getMessage();
        }
        $param->ResponseData = Core::getJSONResponse($result, $errors);
    }

    /**
     * saving the part instance
     *
     * @param TCallback          $sender The event sender
     * @param TCallbackParameter $param  The event params
     */
    public function savePI($sender, $param)
    {
        $result = $errors = array();
        try
        {
        	Dao::beginTransaction();

            //getting the part instance object
            $piId = isset($param->CallbackParameter->id) ? trim($param->CallbackParameter->id) : '';
            if(!($partInstance = Factory::service("PartInstance")->get($piId)) instanceof PartInstance)
               throw new Exception("System Error: Invalid part instance(ID= " . $piId . ")!");

            //getting the part parttype object
            $ptId = (isset($param->CallbackParameter->parttype) && isset($param->CallbackParameter->parttype->id)) ? trim($param->CallbackParameter->parttype->id) : '';
            if(!($partType = Factory::service("PartType")->get($ptId)) instanceof PartType)
                throw new Exception("System Error: Invalid part type(ID= " . $ptId . ")!");
            $partInstance->setPartType($partType);

            //getting the status object
            $stId = (isset($param->CallbackParameter->status) && isset($param->CallbackParameter->status->id)) ? trim($param->CallbackParameter->status->id) : '';
            if(!($status = Factory::service("PartInstanceStatus")->get($stId)) instanceof PartInstanceStatus)
                throw new Exception("System Error: Invalid status(ID= " . $stId . ")!");
            $partInstance->setPartInstanceStatus($status);

			//getting the quantity
           	$qty = $partInstance->getQuantity();
            if(isset($param->CallbackParameter->qty))
            	$qty = $param->CallbackParameter->qty;
            $partInstance->setQuantity($qty);

            //getting active
            $active = $partInstance->getActive();
            if(isset($param->CallbackParameter->active))
            {
            	$active = trim($param->CallbackParameter->active);
            	if($active === '1' || $active === true)
            		$active = true;
            	else
            		$active = false;
            }
            $partInstance->setActive($active);

            //getting the warehouse object
            if(!($warehouse = $partInstance->getWarehouse()) instanceof Warehouse)
                throw new Exception("System Error: Invalid Warehouse!");

            //getting change summary
            $editPIChgSummary = (isset($param->CallbackParameter->editPIChgSummary)) ? implode('; ', $param->CallbackParameter->editPIChgSummary) : '';

            try
            {
	            //Function to save the details
	            Factory::service('PartInstance')->save($partInstance);
            }
            catch(Exception $e)
            {
            	throw ($e);
            }

            //getting all the merging part instance(s)
            $mergeFromPIs = array();
            $mergeToPI = null;
            if(isset($param->CallbackParameter->conflictedPIIds))
            {
                $conflictedPIIds = json_decode(json_encode($param->CallbackParameter->conflictedPIIds), true);
                foreach($conflictedPIIds as $piId => $isSelected)
                {
                    if (!($pi = Factory::service('PartInstance')->get($piId)) instanceof PartInstance)
                        continue;
                    if($isSelected === true)
                        $mergeToPI = $pi;
                    else
                        $mergeFromPIs[] = $pi;
                }
                //deactivating the serial numbers for all the part instance
                Factory::service('PartInstanceAlias')->activatePIA(array_keys($conflictedPIIds), array(PartInstanceAliasType::ID_SERIAL_NO), false);
            }


            //getting all the aliases
            if(!isset($param->CallbackParameter->aliases))
               throw new Exception("System Error: No part instance alias passed in!");
            $aliasObjArray = array();
            foreach(json_decode(json_encode($param->CallbackParameter->aliases), true) as $typeId => $aliasArray)
            {
                $type = Factory::service('PartInstanceAliasType')->get(trim($typeId));
                if(!$type instanceof PartInstanceAliasType)
                    throw new Exception('System Error: Invalid part instance alias type with ID: ' . $typeId . '.');
                foreach($aliasArray as $alias)
                {
                    $aliasObj = ($id = trim($alias['id'])) === '' ? new PartInstanceAlias() : Factory::service('PartInstanceAlias')->get($id);
                    $aliasObj->setPartInstance($partInstance);
                    $aliasObj->setPartInstanceAliasType($type);
                    $aliasObj->setAlias(trim($alias['alias']));
                    $aliasObj->setActive((isset($alias['deactivate']) && $alias['deactivate'] === true) ? false : true);
                    $aliasObjArray[] = $aliasObj;
                    Factory::service('PartInstanceAlias')->save($aliasObj);
                }
            }

            $comments = "Edit via PI Edit Page: " . $editPIChgSummary;

            try
            {
            	//Function to create the logging for parts movement and parts returnable
            	Factory::service("PartInstance")->movePartInstanceToWarehouse($partInstance, $qty, $warehouse, false, $status, $comments, false, $partInstance->getSite(), false);
            }
            catch(Exception $e)
            {
            	throw ($e);
            }

            //start merging part instance(s)
            //doing the merging action
            foreach($mergeFromPIs as $pi)
            {
               $mergeToPI = Factory::service('PartInstance')->mergePartInstance($pi, $mergeToPI, 'Merged via PI Edit Page: ' . $editPIChgSummary);
            }
            Dao::commitTransaction();

            $finalPI = Factory::service('PartInstance')->get($mergeToPI instanceof PartInstance ? $mergeToPI->getId() : $partInstance->getId());
            $result['piArray'] = array($this->_getPIJsonArray($finalPI, $finalPI->getPartType()));
        }
        catch(Exception $ex)
        {
            Dao::rollbackTransaction();
            $errors[] = $ex->getMessage();
        }
        $param->ResponseData = Core::getJSONResponse($result, $errors);
    }

    /**
     * Getting all the conflicts array for JSON
     *
     * @param array        $uniqAliases      The array of unique aliases for the current part instance
     * @param PartInstance $currPI           The current part instance
     * @param array        $partInstances    The part instance objects that we are conflicting the serial number on
     * @param array        &$conflictPIATIds The summary of the conflicted part instance alias type ids
     *
     * @return multitype:
     */
    private function _findConflicts($uniqAliases, PartInstance $currPI, $partInstances = array(), &$conflictPIATIds = array())
    {
        $conflicts = array();
        foreach(Factory::service('PartInstanceAlias')->findConflictAliases($uniqAliases, $currPI) as $pia)
        {
            $pi = $pia->getPartInstance();
            $piatId = $pia->getPartInstanceAliasType()->getId();
            $conflictPIATIds[] = trim($piatId);
            $this->_getConflictPIInfo($conflicts, $pi, $piatId, $pia->getAlias());
        }
        foreach($partInstances as $pi)
        {
            $piatId = PartInstanceAliasType::ID_SERIAL_NO;
            $conflictPIATIds[] = trim($piatId);
            $this->_getConflictPIInfo($conflicts, $pi, $piatId, $pi->getAlias($piatId));
        }
        $conflictPIATIds = array_unique($conflictPIATIds);
        sort($conflictPIATIds);
        return $conflicts;
    }

    /**
     * Getting the conflicted part instance information for JSON
     *
     * @param array        $conflicts The conflict JSON array
     * @param PartInstance $pi        The partinstance object
     * @param int          $piatId    The id of the partinstance alias type
     * @param string       $alias     The alias of the partinstance alias
     *
     * @return ReregisterPartInstancesAliasController
     */
    private function _getConflictPIInfo(&$conflicts, PartInstance $pi, $piatId, $alias)
    {
        $piId = $pi->getId();
        if(!isset($conflicts[$piId]))
        {
            $pt = $pi->getPartType();
            $warehouse = $pi->getWarehouse();
            $conflicts[$piId] = array(
            	'pi'=> array('id'=> $pi->getId(),
            		'sn' => $pi->getAlias(PartInstanceAliasType::ID_SERIAL_NO),
            		'name' => $pt->getAlias(PartTypeAliasType::ID_PARTCODE) . ':' . $pt->getName(),
            		'parttype' => array('id' => $pt->getId(), 'name' => $pt->getAlias(PartTypeAliasType::ID_PARTCODE) . ':' . $pt->getName(), 'serialized' => (trim($pt->getSerialised()) === '1' ? true : false)),
            		'warehouse' => array('id' => $warehouse->getId(), 'name' => $warehouse->getName(), 'path' => $warehouse->getBreadCrumbs(true)),
            		'parent' => new stdClass(),
                ),
                'aliases' => array()
            );
            if(($parent = $pi->getParent()) instanceof PartInstance)
                $conflicts[$piId]['pi']['parent'] = array('id' => $parent->getId());
        }
        if(!isset($conflicts[$piId]['aliases'][$piatId]))
            $conflicts[$piId]['aliases'][$piatId] = array();
        $conflicts[$piId]['aliases'][$piatId][] = $alias;
        return $this;
    }

    /**
     * Getting the part instance information array for the json string
     *
     * @param PartInstance $pi The part instance object
     * @param PartType     $pt The part type object, when null - it will use part instance's part type
     *
     * @return Ambigous <multitype:NULL , multitype:multitype: number multitype:NULL  multitype:NULL string  multitype:NULL Ambigous <multitype:multitype:NULL, multitype:> string  >
     */
    private function _getPIJsonArray(PartInstance $pi, PartType $pt = null)
    {
        $piInfo = array();
        $piInfo['id'] = $pi->getId();
        $piInfo['qty'] = $pi->getQuantity();
        $piInfo['active'] = $pi->getActive();
        $piInfo['sn'] = $pi->getAlias(PartInstanceAliasType::ID_SERIAL_NO);

        $warehouse = $pi->getWarehouse();
        $piInfo['warehouse'] = array('name' => ($warehouse instanceof Warehouse ? $warehouse->getName() : ''), 'path' => ($warehouse instanceof Warehouse ? $warehouse->getBreadCrumbs(true) : ''));

        $partType = ($pt instanceof PartType ? $pt : $pi->getPartType());
        $piInfo['parttype'] = array('name' => $partType->getAlias(PartTypeAliasType::ID_PARTCODE) . ' : ' . $partType->getName(), 'id' => $partType->getId(), 'serialized' => (trim($partType->getSerialised()) === '1' ? true : false));

        $owner = $partType->getOwnerClient();
        $piInfo['owner'] = array('name' => ($owner instanceof Client ? $owner->getClientName() : ''), 'id' => ($owner instanceof Client ? $owner->getId() : ''));

        $piInfo['contracts'] = array();
        foreach($partType->getContracts() as $con)
            $piInfo['contracts'][] = array('name' => $con->getContractName(), 'id' => $con->getId());

        $status = $pi->getPartInstanceStatus();
        $piInfo['status'] = array('name' => $status->getName(), 'id' => $status->getId(), 'availStatuses' => $this->_getAvailPIStatuses($status, $partType->getContracts()));

        $piInfo['parent'] = new stdClass();
        if(($parent = $pi->getParent()) instanceof PartInstance)
            $piInfo['parent'] = array('id' => $parent->getId());

        $aliases = array();
        foreach($pi->getPartInstanceAlias() as $pia)
        {
            $typeId = $pia->getPartInstanceAliasType()->getId();
            if (!isset($aliases[$typeId]))
                $aliases[$typeId] = array();
            $aliases[$typeId][] = array('alias' => $pia->getAlias(), 'id' => $pia->getId(), 'typeid' => $pia->getPartInstanceAliasType()->getId());
        }
        $piInfo['aliases'] = count($aliases) === 0 ? null : $aliases;
        $piInfo['avialPIATs'] = $this->_getAvailPIATs($partType);

        //getting the valid format for the serial number
        $checkOptions = array(BarcodeService::BARCODE_REGEX_CHK_REGISTRABLE);
        $checkOptions[] = ($partType->getSerialised() ? BarcodeService::BARCODE_REGEX_CHK_PART_INSTANCE : BarcodeService::BARCODE_REGEX_CHK_PART_TYPE);
        $piInfo['snFormats'] = $this->_getAliasPattern(BarcodeService::getBarcodeRegex($checkOptions, $partType), implode(' | ', BarcodeService::getValidBarcodePatterns($checkOptions, $partType)));
        return $piInfo;
    }

    /**
     * Getting the json array for the available partinstance status
     *
     * @param PartInstanceStatus $currentPIS The current part instance status
     * @param array              $contracts  The contracts that we displaying
     *
     * @return multitype:multitype:NULL
     */
    private function _getAvailPIStatuses(PartInstanceStatus $currentPIS, array $contracts)
    {
        $array = array();
        $pisArr = DropDownLogic::getPartInstanceStatusList(array(), $contracts, $currentPIS);
        foreach($pisArr as $status)
        {
            $array[$status->getName()] =  array('name' => $status->getName(), 'id' => $status->getId());
        }
        ksort($array);
        return array_values($array);
    }

    /**
     * Getting the json array for the available partinstance alias types
     *
     * @param PartType $partType The parttype for the compulsory and unique flag
     *
     * @return multitype:
     */
    private function _getAvailPIATs(PartType $partType)
    {
        $array = array();
        $doneTypeIds = array(0);
        foreach(Factory::service('Lu_PartType_PartInstanceAliasPattern')->getMandatoryUniquePatternsForPtPiat($partType, null, null, null) as $patternObj)
        {
            $piat = $patternObj->getPartInstanceAliasType();
            $doneTypeIds[] = $piat->getId();
            $array[$piat->getId()] =  array('name' => $piat->getName(),
            	'id' => $piat->getId(),
            	'allowMulti' => (trim($piat->getAllowMultiple()) === '1' ? true : false),
            	'unique' => (trim($patternObj->getIsUnique()) === '1' ? true : false),
            	'mandatory' => (trim($patternObj->getIsMandatory()) === '1' ? true : false),
            	'access' => $piat->getLu_entityAccessOption()->getId(),
            	'pattern' => $this->_getAliasPattern(trim($patternObj->getPattern()), trim($patternObj->getSampleFormat()))
            );
        }
        $restIds = Factory::service('PartInstanceAliasType')->findByCriteria('id not in (' . implode(', ', $doneTypeIds). ')');
        foreach($restIds as $piat)
        {
            $array[$piat->getId()] =  array('name' => $piat->getName(),
            	'id' => $piat->getId(),
            	'allowMulti' => ($piat->getAllowMultiple() === 1 ? true : false),
            	'unique' => false,
            	'mandatory' => false,
            	'access' => $piat->getLu_entityAccessOption()->getId(),
            	'pattern' => $this->_getAliasPattern('', '')
            );
        }
        ksort($array);
        return $array;
    }

    /**
     * Getting the pattern array
     *
     * @param string $pattern      The pattern(regex) string
     * @param string $sampleFormat The sample format string
     *
     * @return multitype:unknown
     */
    private function _getAliasPattern($pattern, $sampleFormat)
    {
        return array('pattern' => $pattern, 'sampleformat' => $sampleFormat);
    }
}

?>
