<?php
/**
 * View Parts Within Controller Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version 1.0
 */
class ViewPartsWithinController extends CRUDPage
{
	/**
	 * @var querySize
	 */
	protected $querySize;

	/**
	 * @var partInstance
	 */
	private $partInstance;

	/**
	 * @var kitPartTypeQty
	 */
	private $kitPartTypeQty = array();

	/**
	 * @var kitPartTypeGroupQty
	 */
	private $kitPartTypeGroupQty = array();

	/**
	 * @var bomPartsCount
	 */
	public $bomPartsCount;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_partInstanceHistory";
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
    public function onLoad($param)
    {
        $partInstanceId = $this->Request['id'];
        $this->partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
        $this->showPartsWithin($partInstanceId);
        if(count($this->DataList->DataSource) > 0)
	    {
	    	$this->displayBomList($partInstanceId);
	    	$this->listLabel->Text = "View Parts in ";
			$this->partInstanceLabel->Text = " " . $this->partInstance;
	    }
    }

    /**
     * Show Parts Within
     *
     * @param unknown_type $partInstanceId
     * @return unknown
     */
    private function showPartsWithin($partInstanceId)
    {
    	$partInstance = Factory::service("PartInstance")->getPartInstance($partInstanceId);
    	$piAlias = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($partInstanceId, 1);
    	//TODO Added by Bharat Rayala on 07/03/2013 bug fix to make page run with out error. Please fix the issue afterwards...
    	if(!empty($piAlias))
    	{
    		$serialNo = $piAlias[0]->getAlias();
    		$partInstanceArray = array();
    		$partInstances =Factory::service("PartInstance")->searchPartInstancesBySerialNo($serialNo);
	    	if(count($partInstances)>1)
	    		return $this->onError("Multiple parts found for $serialNo! Please Contact ". Config::get("SupportHandling","Contact") ." on ". Config::get("SupportHandling","Phone") ." or ". Config::get("SupportHandling","Email") ."!");

    	}
    	$this->DataList->Visible = true;
    	$children = Factory::service("PartInstance")->getChilrenForPartInstance($this->partInstance,true);

    	$piIds = array();
		foreach($children as $pi)
		{
			$piIds[] = $pi->getId();
		}

		if(count($piIds)>0)
		{
			$daoQuery = new DaoReportQuery("PartInstance");
			$daoQuery->column("id");
			$daoQuery->column("quantity");
			$daoQuery->where("pi.id in(".implode(",",$piIds).")");
			$results = $daoQuery->execute(false);

			foreach($results as $r)
			{
				$id = $r[0];
				$qty = $r[1];

				$childPartInstance = Factory::service("PartInstance")->getPartInstance($r[0]);

	   			$partType = $childPartInstance->getPartType();
	   			if ($partType->getSerialised())
	   			{
					$barcodes = Factory::service("PartInstance")->searchPartInstanceAliaseByPartInstanceAndAliasType($r[0],1);
		   			$barcode = count($barcodes)==0 ? "-" : $barcodes[0]->getAlias();
	   			}
	   			else
	   			{
	   				$barcode = $partType->getAlias(PartTypeAliasType::ID_BP);
	   			}

	   			$partTypeId = $partType->getId();
				$partcodes = Factory::service("PartType")->searchPartTypeAliaseByPartTypeAliasTypeForPartType($partTypeId,1);
	   			$partcode = count($partcodes)==0 ? "" : $partcodes[0]->getAlias();

	   			$partDescription = $childPartInstance->getPartType()->getName();
	   			$warehouse = $childPartInstance->getRootWarehouse();

	   			$partInstanceArray[]= array(
	   									"partDescription"=>$partDescription,
	   									"qty"=>$qty,
	   									"barcode"=>$barcode,
	   									"partcode"=>$partcode,
	   									"warehouse"=>$warehouse,
	   									"id"=>$id
	   									);

	   			if(isset($this->kitPartTypeQty[$partTypeId]))
					$this->kitPartTypeQty[$partTypeId] = $this->kitPartTypeQty[$partTypeId] + $qty;
				else
					$this->kitPartTypeQty[$partTypeId] = $qty;

				$this->updatePartTypeGroupList($childPartInstance->getPartType(),$qty);

			}

			$this->totalParts->Value = count($partInstanceArray);
			$this->DataList->DataSource = $partInstanceArray;
   			$this->DataList->DataBind();
		}
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

    			$html .= '<tr>
								<td width="15%">' . $serial . '</td>
								<td width="15%">' . $pt->getAlias() . '</td>
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
     * Update PartType Group List
     *
     * @param PartType $partType
     * @param unknown_type $qty
     */
    private function updatePartTypeGroupList(PartType $partType,$qty)
    {
    	$partTypeGroups = $partType->getPartTypeGroups();
    	if(count($partTypeGroups)>0)
		{
			foreach($partTypeGroups as $partTypeGroup)
			{
				$pgId = $partTypeGroup->getId();

				if(isset($this->kitPartTypeGroupQty[$pgId]))
					$this->kitPartTypeGroupQty[$pgId] = $this->kitPartTypeGroupQty[$pgId] + $qty;
				else
					$this->kitPartTypeGroupQty[$pgId] = $qty;
			}
		}
    }

    /**
     * Display Bomb List
     *
     * @param unknown_type $partInstanceId
     */
    private function displayBomList($partInstanceId)
    {
    	$requiredPartTypeGroupId = "";
    	$requiredPartTypeId = "";
    	$partTypeId = $this->partInstance->getPartType()->getId();
    	$bomItems = Factory::service("BillOfMaterials")->findByCriteria("parttypeid=?",array($partTypeId));

    	if(count($bomItems)>0)
    	{
	    	foreach($bomItems as $bom)
	    	{
	    		$qty = $bom->getQuantity();
	    		$requiredPartType = $bom->getRequiredPartType();
	    		$requiredPartTypeGroup = $bom->getRequiredPartTypeGroup();
	    		$comments = $bom->getComments();
	    		$bomid = $bom->getId();

	    		if($requiredPartType instanceof PartType)
	    		{
	    			$requiredPartTypeId = $requiredPartType->getId();
	    			$partTypeAliasArray = Factory::service("PartType")->getPartTypeAliasByTypeForPartType($requiredPartTypeId,1);
					$partTypeAlias = $partTypeAliasArray[0];

					$partCode = $partTypeAlias->getAlias();
					$partName = $partCode . " : " . $requiredPartType->getName();

					$pId = $requiredPartTypeId;
					$isPartType = 1;
	    		}
	    		else
	    		{
	    			if($requiredPartTypeGroup instanceof PartTypeGroup)
					{
						$requiredPartTypeGroupId = $requiredPartTypeGroup->getId();
						$partName = $requiredPartTypeGroup->getName();
						$pId = $requiredPartTypeGroupId;
						$isPartType = 0;
					}
	    		}

				$check = $this->comparePartsInKitWithBom($pId,$isPartType,$partInstanceId,$qty);
				$bomItemArray[]= array(
		   								"qty"=>$qty,
		   								"comments"=>$comments,
		   								"requiredPartName"=>$partName,
		   								"id"=>$bomid,
										"check"=>$check
		   								);
	    	}

	    	$this->BomDataList->Visible = true;
	    	$bomItemArray = array_reverse($bomItemArray);
	   		$this->bomPartsCount = count($bomItemArray);

	   		$this->BomDataList->DataSource = $bomItemArray;
		   	$this->BomDataList->DataBind();

		   	$this->bomLabel->Visible = true;
		   	$this->bomLabel->Text = "Bill of Materials for: " . $this->partInstance;
    	}
    }

    /**
     * Compare Parts in Kits With BOM
     *
     * @param unknown_type $requiredPartId
     * @param unknown_type $isPartType
     * @param unknown_type $kitPartInstanceId
     * @param unknown_type $bomPartQty
     * @return unknown
     */
	private function comparePartsInKitWithBom($requiredPartId,$isPartType,$kitPartInstanceId,$bomPartQty)
    {
    	$kitPartTypeQty = 0;
    	$partTypeGroupQty = 0;
    	$partGroupIdArray = array();

    	$childParts = Factory::service("PartInstance")->getChilrenForPartInstance($this->partInstance);

    	// Check if required part in kit.
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
						if(isset($this->kitPartTypeQty[$requiredPartId]))
    						$kitPartTypeQty = $this->kitPartTypeQty[$requiredPartId];

    					if($kitPartTypeQty >= $bomPartQty)
							return 1;
						else
							return 2;
					}
				}
				else
				{   // if requied parttypegroup in list
					$childPartTypeGroupList = $child->getPartType()->getPartTypeGroups();

					if(isset($this->kitPartTypeGroupQty[$requiredPartId]))
					{
    					$partTypeGroupQty = $this->kitPartTypeGroupQty[$requiredPartId];
						if($partTypeGroupQty != 0)
	    				{
	    					if($partTypeGroupQty >= $bomPartQty)
	    						return 1;
	    					else
	    						return 2;
	    				}
	    			}
				}
			}
    	}
    	return 0;
    }
}

?>