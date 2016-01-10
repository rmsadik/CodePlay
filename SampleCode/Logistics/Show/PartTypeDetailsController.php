<?php
/**
 * Part Type Details Page 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class PartTypeDetailsController extends CRUDPage 
{	
	/**
	 * @var querySize
	 */
	protected $querySize;
	
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->openFirst = true;
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_parttypedetails";
		$this->querySize = 0;
	}
	
	/**
	 * OnPreInit
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
		$str = explode('/',$_SERVER['REQUEST_URI']);
		if ($str[1] == 'staging')
		{
			$this->getPage()->setMasterClass("Application.layouts.StagingLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_parttypedetails,menu_staging";
			$this->menuContext = 'staging/parttypedetails';
		}
		else
		{
			$this->getPage()->setMasterClass("Application.layouts.LogisticsLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_parttypedetails";
			$this->menuContext = 'parttypedetails';
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
        if(!$this->IsPostBack || $param == "reload")
        {        
			$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
	        $this->dataLoad();
        }
    }

    /**
     * Get All Of Entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	return array();
    }
    
    /**
     * How Big Was That Query
     *
     * @return unknown
     */
    protected function howBigWasThatQuery()
    {
    	return $this->querySize;
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
    	$searchString= trim($searchString);
    	$query = new DaoReportQuery("PartTypeAlias",true);
    	$query->column("pt.id");
    	$query->column("pt.name");  	
    	$query->column("(SELECT GROUP_CONCAT(DISTINCT ptaxx.alias ORDER BY ptaxx.parttypealiastypeid SEPARATOR ', ') FROM parttypealias ptaxx where partTypeId = pt.id AND ptaxx.partTypeAliasTypeId = 1 GROUP BY pt.id)","Aliases");
    	$query->column("(SELECT GROUP_CONCAT(DISTINCT cxx.contractName ORDER BY cxx.id SEPARATOR ', ') FROM contract cxx INNER JOIN contract_parttype cpxx ON cpxx.contractId = cxx.id where cpxx.parttypeid = pt.id group by pt.id)","Contracts");
    	
		//contract filters
    	$filter = FilterService::getFilterArray(FilterService::$CONTRACT_FILTER_ID);
		if (count($filter)>0) $query->innerJoin("PartTypeAlias.partType",'pt','pt.active = 1')->innerJoin("PartType.contracts",'c','c.id IN (' . implode(",",$filter) . ')');
		else $query->innerJoin("PartTypeAlias.partType",'pt','pt.active = 1');    		
		//contract filters
		
    	//$query->innerJoin("PartTypeAlias.partType",'pt','pt.active = 1');    	
    	$query->groupBy('pt.id');
		$query->page($pageNumber,$pageSize);
		
		$where = "pta.active = 1 and ";
		$params = array();
		
		
    	switch($this->SearchPartType->getSelectedValue())
    	{
    		case 1:
    			$where .= "pta.alias like ? and pta.partTypeAliasTypeId = 1";
    			$params[] = '%'.$searchString.'%';
    			break;
    		case 2:
    			{
    				$array = array();
    				$lists = explode(' ',$searchString);
    				foreach($lists as $list)
    				{
    					$array[] = "pt.name like ?";
    					$params[] = '%'.$list.'%';
    				}
		    		$where .= implode(' and ',$array);
		    		break;
    			}
    		default:
    		case 0:
    			{
    				$array = array();
    				$lists = explode(' ',$searchString);
    				$where .= ' ((';
    				foreach($lists as $list)
    				{
    					$array[] = "pt.name like ?";
    					$params[] = '%'.$list.'%';
    				}
		    		$where .= implode(' and ',$array);
		    		$where .= ") OR (pta.alias like ?))";
		    		$params[] = '%'.$searchString.'%';
		    		break;    			
    			}	
    			break;    	
    	}
    	
    	$contractString = trim($this->ContractText->Text);
    	if($contractString != "")
    	{
    		$where .= ' and (pt.id in (SELECT cpt.parttypeid FROM contract c inner join worktype wt on wt.contractid = c.id inner join contract_parttype cpt on cpt.contractid = c.id where c.contractName like ?))';
    		$params[] = '%'.$contractString.'%';
    	}
   	
    	$query->where($where,$params);
    	$result = $query->execute(); 
		$this->querySize = $query->TotalRows;
		foreach($result as $row=>$value)
		{
			$partType = Factory::service("PartType")->get($result[$row][0]);
			$cg = $partType->getContractGroup();
			if(!empty($cg))
			{
				$cgn = $cg ->getGroupName();
				$result[$row][3] = $cgn . '<span style="font-weight:bold;"> (CG)</span>';
			}
		}
    	return $result;
        
    }
    
    /**
     * Implode Walk
     *
     * @param unknown_type $array
     * @param unknown_type $method
     * @param unknown_type $delimiter
     * @param unknown_type $params
     * @return unknown
     */
    protected function implodeWalk($array,$method,$delimiter,$params=array())
    {
    	$string = "";
		$first = true;
		foreach($array as $item)
		{
			if(!$first)
				$string .= $delimiter;
			else
				$first = false;
				
			$string .= call_user_func_array(array(&$item,$method),$params);
		}
		return $string;			    	
    }
    
    /**
     * Populate Edit
     *
     * @param unknown_type $editItem
     */
    protected function populateEdit($editItem)
    {
		$data = $editItem->getData();
		$id = $data[0];
		$partType = Factory::service("PartType")->get($id);
		
		$editItem->partTypeName->Text = $partType->getName();
		$editItem->partTypeDescription->Text = $partType->getDescription();
		$editItem->partTypeKitType->Text = $partType->getKitType();
		$editItem->partTypeMake->Text = $partType->getMake();
		$editItem->partTypeModel->Text = $partType->getModel();
		$editItem->partTypeVersion->Text = $partType->getVersion();
		 
		if ($partType->getRepairable() == 1)
		{
			$editItem->partTypeRepairable->setImageUrl("/themes/images/small_yes.gif");
		}
		else
		{
			$editItem->partTypeRepairable->setImageUrl("/themes/images/cross_48.gif");
		}
		
		$partTypeGroups = $partType->getPartTypeGroups();
		$editItem->partTypeGroups->Text = $this->implodeWalk($partTypeGroups,"getName",'<br/>');

		$cg = $partType->getContractGroup();
		if(!empty($cg))
		{
			$cga=array();
			$cga[]=$cg;
			$editItem->partTypeContracts->Text = $this->implodeWalk($cga,'getGroupName','<br/>'). '<span style="font-weight:bold;"> (CG)</span>';
		}
		else
	    {
	    	$contracts = $partType->getContracts();
	    	$editItem->partTypeContracts->Text = $this->implodeWalk($contracts,'getContractName','<br/>');
	    }		

		$editItem->partTypeManufacturer->Text = $partType->getManufacturer();
		
		$suppliers = $partType->getSuppliers();
		$editItem->partTypeSuppliers->Text = $this->implodeWalk($suppliers,'getName','<br/>');
		
		//getting part instance aliases, plus info
		$html = "";
		$patterns = Factory::service("Lu_PartType_PartInstanceAliasPattern")->getMandatoryUniquePatternsForPtPiat($partType,null,null,null);
		if (count($patterns) > 0)
		{
			$html .= "<ul style='list-style:disc inside; margin-left:5px'>";
			foreach ($patterns as $p)
			{
				$attributes = array();
				if ($p->getIsMandatory())
				{
					$attributes[] = 'MANDATORY';
				}
				if ($p->getIsUnique())
				{
					$attributes[] = 'UNIQUE';
				}
				if ($p->getSampleFormat() !== '')
				{
					$attributes[] = "SAMPLE: '" . $p->getSampleFormat() . "'";
				}
				$html .= "<li><b>" . $p->getPartInstanceAliasType()->getName() . "</b>";
				if (!empty($attributes))
				{
					$html .= " (" . implode(' <b>/</b> ', $attributes) . ")";
				}
				$html .= "</li>";
			}
			$html .= "</ul>";
		}
		$editItem->mandatoryFields->Text = $html;
		
    	$query = new DaoReportQuery("PartTypeAlias",true);
    	$query->column("ptat.name","Type");
    	$query->column("GROUP_CONCAT(DISTINCT pta.alias SEPARATOR ', ')","Alias");
		$query->leftJoin("PartTypeAlias.partTypeAliasType",'ptat');    	
    	$query->where('pta.active=1 and pta.partTypeId = ?',array($partType->getId()));
    	$query->groupBy('ptat.id');	
		$aliases = $query->execute();
		
		$aliasText = "";
		$first = true;
		foreach($aliases as $alias)
		{
			if(!$first)
			{
				$aliasText .= '<br/>';
			}
			else
			{
				$first = false;		
			}
				
			if(strrpos($alias[0],"Hot Message")===false)
			{	
				$aliasText .= $alias[0].': '.$alias[1];
			}
			else
			{
				$aliasText .= '<b style="color:orange">' . $alias[0].': <img src="../../../themes/images/red_flag_16.png"> '.$alias[1] . '</b>';	
			}
		} 
		$editItem->partTypeAlias->Text = $aliasText;		
    }
    
    /**
     * Get Style
     *
     * @param unknown_type $index
     * @return unknown
     */
    public function getStyle($index)
    {
    	if($index % 2 == 0)
    		return 'DataListItem';
    	else
    		return 'DataListAlterItem';
    }
}

?>