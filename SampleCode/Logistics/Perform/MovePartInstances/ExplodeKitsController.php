<?php
/**
 * Explode Kits Page 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class ExplodeKitsController extends CRUDPage
{
	/**
	 * Constructor
	 *
	 */	
	public function __construct()
	{
		parent::__construct();
		$this->roleLocks = "pages_all,page_logistics_explodeKits";
	}
	
	/**
	 * On Pre Initialization
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
		$str = explode('/',$_SERVER['REQUEST_URI']);
		if ($str[1] == 'staging')
		{
			$this->getPage()->setMasterClass("Application.layouts.StagingLayout");
			$this->roleLocks = "pages_all,page_logistics_explodeKits,menu_staging";
			$this->menuContext = 'staging/explodekits';
		}
		else
		{
			$this->getPage()->setMasterClass("Application.layouts.LogisticsLayout");
			$this->roleLocks = "pages_all,page_logistics_explodeKits";
			$this->menuContext = 'explodekits';
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
       	$this->explodeAllBtn->Enabled = false;
       	$this->outer->Display="None";
		$this->inner->Display="None";
    }
    
    /**
     * Checks if the partinstance has a kittype, if so adds it to the explode kit list 
     * and if not checks whether the parttype has a kittype. If it does then the kit
     * gets added to the explode kit list otherwise displays an error message.
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function attemptToExplode($sender,$param)
    {
    	$kitBarcode = $this->kitBarcode->Text;
    	$tobeExploded = unserialize($this->tobeExploded->getValue());
    	$partInstanceArray = Factory::service("PartInstance")->searchPartInstancesBySerialNo($kitBarcode);
    	if(count($partInstanceArray)<=0)
        {
        	$this->setErrorMessage("No such kit found in the system.");
        	$this->updateDataList($tobeExploded);
        	return;
        }
    	else
    	{
	    	if($partInstanceArray[0] instanceof PartInstance)
	    	{
	    		$partInstanceKitType = $partInstanceArray[0]->getKitType();
	    		if($partInstanceKitType instanceof KitType)
	    		{
			    	$autoExplode = $partInstanceKitType->getAutoExplode();
			    	if($autoExplode != 1)
			    	{
			    		$this->updateDataList($tobeExploded);
			    		$this->outer->Display="Hidden";
						$this->inner->Display="Dynamic";
			    	}
			    	else
			    	{
			    		$this->outer->Display="None";
						$this->inner->Display="None";
			    		$this->addToList();
			    	}
	    		}
	    		else
	    		{
	    			$partType = $partInstanceArray[0]->getPartType();
	    			$partTypeKitType = $partType->getKitType();
	    			if($partTypeKitType instanceof KitType)
	    			{
	    				$partTypeAutoExplode = $partTypeKitType->getAutoExplode();
			    		if($partTypeAutoExplode != 1)
				    	{
				    		$this->updateDataList($tobeExploded);
				    		$this->outer->Display="Hidden";
							$this->inner->Display="Dynamic";
				    	}
				    	else
				    	{
				    		$this->outer->Display="None";
							$this->inner->Display="None";
				    		$this->addToList();
				    	}
	    			}
	    			else
	    			{
	    				$this->setErrorMessage("No such kit found in the system.");
    	    			$this->updateDataList($tobeExploded);
       		 			return;
	    			}
        		}
	    	}
    	}
    }
    
    /**
     * Return Page
     *
     */
	public function returnPage()
    {
    	$this->outer->Display="None";
		$this->inner->Display="None";
    	$tobeExploded = unserialize($this->tobeExploded->getValue());
    	$this->updateDataList($tobeExploded);
    }
    
    /**
     * Add to List
     *
     */
    public function addToList()
    {
        $kitBarcode = $this->kitBarcode->Text;
        $tobeExploded = unserialize($this->tobeExploded->getValue());
        if (empty($kitBarcode))
        {
        	$this->updateDataList($tobeExploded);
        	return;
        }
        	
        $q = new DaoReportQuery("PartInstance");
        $q->column("pi.id");
        $q->column("pia.alias");
        $q->where("pia.alias = '$kitBarcode'");
        $q->where("pia.partInstanceAliasTypeId = 1");
        $q->setAdditionalJoin("INNER JOIN partinstancealias pia ON pi.id=pia.partInstanceId INNER JOIN parttype pt ON pt.id = pi.parttypeid ");
        $result = $q->execute(false);
        
        if (empty($result))
        {
        	$this->setErrorMessage("No such kit found in the system.");
        	$this->updateDataList($tobeExploded);
        	return;
        }

        $pi = $result[0];
        $partInstance = Factory::service("PartInstance")->get($pi[0]);
		$facilityRequest = $partInstance->getFacilityRequest();

		if ($facilityRequest instanceof FacilityRequest)
    		$this->setInfoMessage("There is a facility request against $pi[1]. Ignore this message if you wish to proceed.");

    	if ($partInstance->getWarehouse() instanceof Warehouse)
    	{
	    	$transiteNotes = Factory::service("TransitNote")->findByCriteria("tn.transitNoteLocationId=?",array($partInstance->getWarehouse()->getId()));
	    	if(count($transiteNotes)>0)
	    	{
				$this->setErrorMessage("$pi[1] is on a transitNote (".$transiteNotes[0]."), you can't explode it!");
        		$this->updateDataList($tobeExploded);
        		return;
	    	}
    	}
    	
    	$parent = $partInstance->getDirectParent();
    	if( $parent instanceof PartInstance)
    	{
    		$this->setErrorMessage("Part is within another part ($parent). Please explode the parent first.");
        	$this->updateDataList($tobeExploded);
        	return;
    	}
    		
        $tobeExploded[$pi[0]] = $pi;
        $this->updateDataList($tobeExploded);
        $this->kitBarcode->setText('');
    }
    
    /**
     * Remove from the Explode Kit List
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function cancelFromList($sender, $param)
    {
    	$partInstanceId = $this->DataList->DataKeys[$sender->Parent->ItemIndex];
        $tobeExploded = unserialize($this->tobeExploded->getValue());
        $newTobe = array();
        foreach ($tobeExploded as $key => $keep)
        {
        	if ($key != $partInstanceId)
        		$newTobe[$key] = $keep;
        }
        $this->updateDataList($newTobe);
    }
    
    /**
     * Each kit in the Explode List is exploded
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function explodeKits($sender, $param)
    {
    	$tobeExploded = unserialize($this->tobeExploded->getValue());
    	foreach ($tobeExploded as $piArr)
    	{
    		$pi = Factory::service("PartInstance")->getPartInstance($piArr[0]);
    		$warehouse = $pi->getWarehouse();
    		$kitTypeId = $piArr[4];
    		
    		if($kitTypeId == 2)
				$cMsg = "Auto Exploding kit on Install: exploded from ";
			elseif($kitTypeId == 3)
				$cMsg = "Auto-Exploding kit on Tech: exploded from ";
			elseif($kitTypeId == 4)
				$cMsg = "Auto-Exploding kit on Reconcile: exploded from ";
			elseif($kitTypeId == 1)
				$cMsg = "Non-Exploding Kit: exploded from ";
		
    		Factory::service("PartInstance")->explodeKits(null, $pi, $warehouse, $cMsg);
    	}
    	$this->setInfoMessage("All kits have been exploded.");
    	$this->updateDataList(array());
    }
    
    /**
     * Update List that needs to be Exploded
     *
     * @param array $arr
     */
    private function updateDataList($arr)
    {
    	$this->explodeAllBtn->Enabled = false;
    	$piIds = array();
    	$newArr = array();
    	
    	if (!empty($arr))
    		$piIds = array_keys($arr);
    	
    	if (!empty($piIds))
    	{
	    	$q = new DaoReportQuery("PartInstance");
	        $q->column("pi.id");
	        $q->column("pia.alias");
	        $q->column("(SELECT pt.name FROM parttype pt WHERE pt.id=pi.partTypeId)");
	        $q->column("pi.warehouseId");
	        $q->where("pi.id IN (".join(',', $piIds).")");
	        $q->where("pia.partInstanceAliasTypeId = 1");
			$q->setAdditionalJoin("INNER JOIN partinstancealias pia ON pi.id=pia.partInstanceId INNER JOIN parttype pt ON pt.id = pi.parttypeid ");
	        $result = $q->execute(false);
	        
	        foreach ($result as $row)
	        {
	        	$row[3] = Factory::service("Warehouse")->getWarehouseBreadCrumbs(Factory::service("Warehouse")->getWarehouse($row[3]));
	        	$partInstance = Factory::service("PartInstance")->getPartInstance($row[0]);
	        	$partInstanceKitType = $partInstance->getKitType();
	        	
	        	if($partInstanceKitType instanceof KitType)
	        		$row[4] = $partInstanceKitType->getId();
	        	else
	        	{
					$partTypeKitType = $partInstance->getPartType()->getKitType();
					if($partTypeKitType instanceof KitType)
	        			$row[4] = $partTypeKitType->getId();
	        	}
	        	$newArr[$row[0]] = $row;
	        }
    	}
        $this->tobeExploded->setValue(serialize($newArr));
        $this->DataList->DataSource = $newArr;
        $this->DataList->dataBind();
        
        if (count($newArr) > 0)
        	$this->explodeAllBtn->Enabled = true;
    }
}
?>