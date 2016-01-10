<?php
/**
 * Compatible PartType Controller
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 *
 */
class CompatiblePartTypeController extends CRUDPage
{
	/**
	 * @var partTypeAliasTypeId
	 */
	public $partTypeAliasTypeId = 10;
	/**
	 * @var ableToEditItem
	 */
	public $ableToEditItem;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = 'compatiablePartType';
		$this->roleLocks = "pages_all,page_logistics_compatibleParts";
		$this->partTypeAliasTypeId = 10;
		$this->ableToEditItem = Session::checkRoleFeatures(array('pages_all','feature_logistics_editCompatibleParts'));
	}

	/**
	 * OnLaod
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
       	parent::onLoad($param);
       	if(!$this->IsPostBack || $param == "reload")
        {
			$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
			$this->bindAliasType($this->searchAliasType);
			$this->searchAliasType->setSelectedValue('partcode');
			$this->dataLoad();
        }
    }

    /**
     * Bind AliasType
     *
     * @param unknown_type $list
     */
    public function bindAliasType(&$list)
    {
		$aliasArray=array();
		$aliasArray[]=array('id'=>0,'name' => 'Please select');
		$aliasArray[]=array('id'=>'partcode','name' => 'Part Code');
		$aliasArray[]=array('id'=>'partname','name' => 'Part Name');
		$ptat = Factory::service("PartTypeAliasType")->findByCriteria("active=1",array(),false, null, 30, array('parttypealiastype.name' => 'asc'));

		foreach($ptat as $pta)
			$aliasArray[]=array('id'=>$pta->getId(),'name' =>$pta->getName());

		$list->DataSource =$aliasArray;
    	$list->DataBind();
    }

    /**
     * Create New Entity
     *
     * @return unknown
     */
    protected function createNewEntity()
    {
    	return new Lu_PartCompatibility();
    }

    /**
     * Lookup Entity
     *
     * @param unknown_type $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
    	return Factory::service("Lu_PartCompatibility")->get($id);
    }

    /**
     * Set Entity
     *
     * @param unknown_type $object
     * @param unknown_type $params
     * @param unknown_type $focusObject
     */
    protected function setEntity(&$object, $params, &$focusObject=null)
    {
    	$source = Factory::service("PartType")->get($params->sourcePartType->getSelectedValue());
    	$target = Factory::service("PartType")->get($params->targetPartType->getSelectedValue());

        $msg = "Compatiblity updated!";
        if($object->getId()===null)
	        $msg = "New compatiblity created!";

    	if(!$source instanceof PartType)
    		throw new Exception("Invalid Source PartType!");

    	if(!$target instanceof PartType)
    		throw new Exception("Invalid Compatible PartType!");

    	if($this->checkExsitence($source,$target,$object->getId()) > 0)
    		throw new Exception("Compatiblity Exists! Source PartType ('".$source->getAlias().": ".$source->getName()."') to Compatible Part Type('".$target->getAlias().": ".$target->getName()."') ");

	    try
	    {
	    	$object->setSourcePart($source);
	        $object->setCompatiblePart($target);
	    	$object->setBiDirectional($params->boiDirections->Checked);

			Factory::service("Lu_PartCompatibility")->save($object);
	        $this->setInfoMessage($msg);
	    }
	    catch(Exception $e)
	    {
	    	$this->setErrorMessage($e->getMessage());
	    }
    }

    /**
     * Check Existence
     *
     * @param unknown_type $sourcePart
     * @param unknown_type $targetPart
     * @return unknown
     */
    private function checkExsitence($sourcePart,$targetPart,$id)
    {
    	$sql = "select distinct id from lu_partcompatibility where active = 1 and sourcePartId = ".$sourcePart->getId()." and compatiblePartId = ".$targetPart->getId();
    	if(!is_null($id) && $id >'')
    		$sql .= " and id !=".$id;

    	return count(Dao::getResultsNative($sql));
    }

    /**
     * To Perform Search
     *
     * @return unknown
     */
 	protected function toPerformSearch()
    {
    	$aliasTypeId = $this->searchAliasType->getSelectedValue();
    	return $aliasTypeId=="";
    }

    /**
     * Get All Of Entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject=null, $pageNumber=null, $pageSize=null)
    {
    	return null;
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
    protected function searchEntity($searchString, &$focusObject = null, $pageNumber=null, $pageSize=null)
    {
    	$aliasTypeId = $this->searchAliasType->getSelectedValue();
    	if($aliasTypeId>0)
    		$where = " and pta.partTypeAliasTypeId = ".$aliasTypeId;
    	else if($aliasTypeId == 'partcode')
    		$where = " and pta.partTypeAliasTypeId = ".PartTypeAliasType::ID_PARTCODE;
    	else
    		$where = " and pta.partTypeAliasTypeId is not null ";

    	$searchString = trim($searchString);
    	if($aliasTypeId == 'partname')
    		$data = Factory::service("Lu_PartCompatibility")->findByCriteria("sourcePartId in (select distinct pt.id from parttype pt where pt.active = 1 ".($searchString=="" ? "" : " and ucase(pt.name) like ucase('%$searchString%')")." )", array(),false, $pageNumber, $pageSize);
    	else
    		$data = Factory::service("Lu_PartCompatibility")->findByCriteria("sourcePartId in (select distinct pta.partTypeId from parttypealias pta where pta.active = 1 ".($searchString=="" ? "" : " and ucase(pta.alias)=ucase('$searchString')")." $where)", array(),false, $pageNumber, $pageSize);

    	if(count($data)==0)
    		$this->setErrorMessage("No Data Found!");

    	return $data;
    }

    /**
     * Populate Add
     *
     */
 	protected function populateAdd()
    {
    	$aliasTypeId = $this->searchAliasType->getSelectedValue();
    	$this->bindAliasType($this->aliasType);
		$this->aliasType->setSelectedValue($aliasTypeId);

		//source part type
    	$this->sourcePartType->Text="";
    	$this->sourcePartType->setViewState("value","",null);

    	//target part type
    	$this->targetPartType->Text="";
    	$this->targetPartType->setViewState("value","",null);

    	$this->sourcePartType->focus();
    }

    /**
     * Populate Edit
     *
     * @param unknown_type $editItem
     */
    protected function populateEdit($editItem)
    {
    	$aliasTypeId = $this->searchAliasType->getSelectedValue();
    	$this->bindAliasType($editItem->aliasType);
    	$editItem->aliasType->setSelectedValue($aliasTypeId);

    	$source = $editItem->getData()->getSourcePart();

    	$editItem->sourcePartType->Text=$source->getAlias().": ".$source->getName();
    	$editItem->sourcePartType->setViewState("value",$source->getId(),null);

    	$target = $editItem->getData()->getCompatiblePart();
    	$editItem->targetPartType->Text=$target->getAlias().": ".$target->getName();
    	$editItem->targetPartType->setViewState("value",$target->getId(),null);
    	$editItem->Updated->Text 	= $editItem->getData()->getUpdated();
    	$editItem->UpdatedById->Text = $editItem->getData()->getUpdatedBy()->getPerson();

    	if($editItem->getData()->getBiDirectional()==1)
    	{
    		$this->boiDirections->Checked=true;
    		$editItem->directionBtn->setImageUrl('/themes/images/bio-arrow.png');
    	}
    	else
    	{
    		$this->boiDirections->Checked=false;
    		$editItem->directionBtn->setImageUrl('/themes/images/arrow.png');
    	}

    	$editItem->sourcePartType->focus();
    }

    /**
     * Search Parttype
     *
     * @param unknown_type $text
     * @return unknown
     */
    public function searchPartType($text)
    {
    	$text = trim($text);
    	$aliasTypeId = $this->aliasType->getSelectedValue();
    	if($aliasTypeId == 'partname'|| $aliasTypeId == 'partcode')
    	{
	    	$sql = "select distinct pt.id, concat(pta.alias,' : ',pt.name) `name`
					from parttype pt
					inner join parttypealias pta on (pta.partTypeId=pt.id and pta.active =1 and pta.partTypeAliasTypeId = ".PartTypeAliasType::ID_PARTCODE.")
					where pt.active = 1 ";

	    		if($aliasTypeId == 'partname')
					$sql .="and ucase(pt.name) like ucase('%$text%')";
	    		else
					$sql .="and ucase(pta.alias) = ucase('$text')";

				$sql .= "	limit 50";
    	}
    	else
    	{
	    	$sql = "select distinct pt.id, concat(pta.alias,' : ',pt.name) `name`
					from parttype pt ";
	    	if($aliasTypeId>0)
				$sql .= " inner join parttypealias pta on (pta.partTypeId=pt.id and pta.active =1 and pta.partTypeAliasTypeId=$aliasTypeId) ";
	    	else
				$sql .= " inner join parttypealias pta on (pta.partTypeId=pt.id and pta.active =1 and pta.partTypeAliasTypeId is not null) ";

			$sql .=	" where pt.active = 1
					and ucase(pta.alias) = ucase('$text')
					limit 50";
    	}

		$data = Dao::getResultsNative($sql);
		return $data;
    }

    /**
     * Get Alias Table
     *
     * @param PartType $partType
     * @return unknown
     */
    public function getAliasTable(PartType $partType)
    {
    	$html = "<table width='100%'style='font-size:11px;'>";
    		$html .="<tr><td>&nbsp;</td></tr>";
 				foreach(Factory::service("PartTypeAliasType")->findAll() as $aliasType)
	    		{
	    			$alias = trim($partType->getAlias($aliasType->getId()));
	    			if($alias=="")
	    				continue;
	    				$html .="<tr>";
		    			$html .="<td width='40%'>";
		    				$html .=$aliasType->getName();
		    			$html .="</td>";
		    			$html .="<td width='40%' style='border-bottom:1px #000000 solid;'>";
		    				$html .=" : ".$partType->getAlias($aliasType->getId());
		    			$html .="</td>";
	    				$html .="</tr>";
	    		}
    		$html .="<tr><td>&nbsp;</td></tr>";
    	$html .= "</table>";

    	return $html;
    }

    /**
     * Delete
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function delete($sender, $param)
    {
    	$this->setInfoMessage("");
    	$this->setErrorMessage("");
    	$id = trim($param->CommandParameter);
    	$lu_com = Factory::service("Lu_PartCompatibility")->get($id);
    	if(!$lu_com instanceof Lu_PartCompatibility )
    		return;

    	$lu_com->setActive(false);
    	Factory::service("Lu_PartCompatibility")->save($lu_com);
    	$this->setInfoMessage("Deleted Successfully!");
    	$this->dataLoad();
    }
}

?>