<?php
/**
 * Page for reregister part instance only for administration
 *
 * @package     Hydra-Web
 * @subpackage  Logistics Administration
 * @version     3.0
 * @author  	Dean McGowan <dmcgowan@bytecraft.com.au>
 */
class ReregisterPartInstancesAliasController extends CRUDPage 
{	
	/**
	 * @var querySize
	 */
	protected $querySize;

	/**
	 * Constructor 
	 * @return nothing
	 */
	public function __construct()
	{
		parent::__construct();
		$this->focusOnSearch = false;
		$this->menuContext = 'partInstanceAliasReRegister';
		$this->roleLocks = "pages_all,page_logistics_partInstanceAliasReRegister";
		$this->querySize = 0;
	}

	/**
	 * Event handler for onload
	 * @param  Array $param
	 * @return nothing
	 */
    public function onLoad($param)
    {
        parent::onLoad($param);
        if(!$this->IsPostBack || $param == "reload")
        {        
        	$this->AddPanel->Visible = false;
			$this->DataList->EditItemIndex = -1;
	        $this->dataLoad();
	        $this->BTBarcode->setAttribute( 'onkeydown', "doEnterBehavior(event,'ctl0_MainContent_SearchButton');" );
	        $this->BTBarcode->focus();
        }
    }

	/**
	 * Does nothing not sure why its here !!!
	 * @return Array
	 */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	return array();
    }

	/**
	 * Pass query text to local variable
	 * @return String
	 */ 
    protected function toPerformSearch()
    {
    	$this->SearchString->Value = $this->BTBarcode->Text;
    	return $this->BTBarcode->Text == "";
    }

	/**
	 * Get query size
	 * @return Double
	 */ 
    protected function howBigWasThatQuery()
    {
    	return $this->querySize;
    }

	 /**
	 * Event Handler for TDatalist OnItemCreated
	 * @param  String $searchString 
	 * @param  Pointer $focusObject
	 * @param  Int $pageNumber
	 * @param  Int $pageSize
	 * @return nothing
	 */ 
    public function itemCreated($sender, $param)
    {
	    $typesAdmin = Factory::service("PartInstanceAliasType")->findAll();
   		$types = Factory::service("PartInstanceAliasType")->findAll();
   		$types = array_splice($types, 1);
    	
    	$item=$param->Item;
		$results = $typesAdmin;
    	if($item->ItemType==='Item' || $item->ItemType==='AlternatingItem') 
    	{    		
	  		if (!UserAccountService::isSystemAdmin() && $item->data[2] != 1 ){ 
				$results = $types;
			}
	  		
	  		$item->pitype->DataSource= $results;
	    	$item->pitype->dataBind();

	  		if (!UserAccountService::isSystemAdmin() && $item->data[0] == '-1'){ 
				$results = $types;
		    }
	  		
	  		$item->addpitype->DataSource= $results;
       		$item->addpitype->dataBind();
    	}
    	
    }

	/**
	 * Sets alias to inactive
	 * @param  String $searchString
	 * @param  Pointer $focusObject
	 * @param  Int $pageNumber
	 * @param  Int $pageSize
	 * @return nothing
	 */
    protected function searchEntity($searchString,&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$isInActive = ($this->isInActive->Checked)?0:1;
    	$query = new DaoReportQuery("PartInstanceAlias");
    	$query->column("pia.partInstanceId","id");
    	$query->page(0,2);
    	$query->where("pia.partInstanceAliasTypeId = 1 and pia.active = ? and pia.alias = ?",array($isInActive,$searchString));
    	$results = $query->execute(false);
    	
    	if(sizeof($results) < 1 || sizeof($results) > 1)
    	{
    		$this->setErrorMessage("Part Not found");
    		return array();
    	}
    	
    	$partInstanceId = $results[0][0];
    	$query = new DaoReportQuery("PartInstanceAlias");
    	$query->column("pia.id","id");
    	$query->column("pia.alias","alias");
    	$query->column("pia.partInstanceAliasTypeId","type");
    	$query->column("pia.partInstanceId","parent");
    	$query->where("pia.active = 1 and pia.partInstanceId = ?", array($partInstanceId));
    	$query->orderBy('pia.partInstanceAliasTypeId',DaoReportQuery::ASC,true);
    	$results = $query->execute(false);

    	$results[] = array('-1','','1',$partInstanceId);
    	
    	$this->querySize = sizeof($results);
    	return $results;
    }
    
	/**
	 * Does nothing not sure why its here !!!
	 * @return nothing
	 */
    protected function resetScreen()
    {
    	
    }

	/**
	 * Display success message for part instance registration
	 * @param  Object $sender
	 * @param  Array $param
	 * @return nothing
	 */
	public function reregisterPartInstances($sender,$param)
	{
		$this->resetScreen();
		$this->setErrorMessage('');
    	$this->setInfoMessage('Part Instance sucessfully reregistered.');		
	}
	
	/**
	 * Sets alias to inactive
	 * @param  Object $sender
	 * @param  Array $param
	 * @return nothing
	 */
	public function DeleteAlias($sender,$param)
	{
		$pia = Factory::service("PartInstanceAlias")->get($param->CommandParameter);
		$pia->setActive(0);
		Factory::service("PartInstanceAlias")->save($pia);
		$this->dataLoad();
	}

	/**
	 * Update alias to details
	 * @param  Object $sender
	 * @param  Array $param
	 * @return nothing
	 */
	public function ChangeAlias($sender,$param)
	{
		$parent = $sender->getParent()->getParent()->getParent();
		$aliasText = trim($parent->alias->text);
		$aliasTypeId = $parent->pitype->getSelectedValue();
		if (empty($aliasText))
			$this->setErrorMessage("Alias can not be empty.");
		else
		{
			$pia = Factory::service("PartInstanceAlias")->get($param->CommandParameter);
			$piat = Factory::service("PartInstanceAliasType")->get($aliasTypeId);
			$pia->setAlias($aliasText);
			$pia->setPartInstanceAliasType($piat);
			Factory::service("PartInstanceAlias")->save($pia);
		}
		$this->dataLoad();
	}

	/**
	 * Add alias to for current part
	 * @param  Object $sender
	 * @param  Array $param
	 * @return nothing
	 */
	public function addAlias($sender,$param)
	{
		$parent = $sender->getParent()->getParent()->getParent();
		$aliasText = trim($parent->addalias->text);
		$aliasTypeId = $parent->addpitype->getSelectedValue();
		if (empty($aliasText))
			$this->setErrorMessage("Alias can not be empty.");
		else
		{
			$partInstance = Factory::service("PartInstance")->get($param->CommandParameter);	
			$piat = Factory::service("PartInstanceAliasType")->get($aliasTypeId);
			
			$pia = new PartInstanceAlias();
			$pia->setPartInstance($partInstance);
			$pia->setPartInstanceAliasType($piat);
			$pia->setAlias($aliasText);
			Factory::service("PartInstanceAlias")->save($pia);
		}	
		$this->dataLoad();		
	}

	/**
	 * Determine multiview active state for record
	 * @param  Array $data
	 * @return String 
	 */
	public function getView($data)
	{
		if($data[0] == '-1')
			return "2";

		if($data[2] == "1" && !UserAccountService::isSystemAdmin())
			return "1";		
		
		return "0";
	}
	
	/**
	 * Retrieve type as string from id
	 * @param  Integer $type
	 * @return String 
	 */
	public function getType($type)
	{
		return Factory::service("PartInstanceAliasType")->get($type);
	}
}

?>