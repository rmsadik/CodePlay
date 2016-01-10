<?php

class PartTypeGroupMinimumLevelController extends CRUDPage
{
	public $barcodes;

	protected $totalRows = 0;

	public function __construct()
	{
		parent::__construct();
		$this->menuContext = "logistics_storeageLocationMiniumLevel";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_storeageLocationMiniumLevel";
		$this->barcodes = array();
		$this->focusOnSearch = false;
	}

	public function onLoad($param)
    {
       	parent::onLoad($param);

	    if(!$this->IsPostBack || $param == "reload")
        {
			$this->DataList->EditItemIndex = -1;
			$this->dataLoad();

        }
		$warehouse = Factory::service("Warehouse")->getWarehouse($this->Request['id']);
		$this->setInfoMessage("Set Group MSL for ".Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse));
    }

    protected function loadMatchList($name="")
    {
    	$pageNumber = $this->MSLDataList->CurrentPageIndex + 1;
    	$pageSize = $this->MSLDataList->pageSize;

    	$query = new DaoReportQuery("PartTypeGroup");
    	$query->column('ptg.id');
    	$query->column('ptg.name');
    	$query->page($pageNumber,$pageSize);
    	$query->orderBy('ptg.name',DaoReportQuery::ASC);

   		$query->where('ptg.active = 1 and (ptg.id not in (SELECT partTypeGroupId FROM parttypegroupminimumlevel p where p.active = 1 and p.warehouseId = ?))',array($this->Request['id']));

   		if($name != "")
   			$query->where('ptg.name like ?',array($this->Request['id'],'%'.$name.'%'));

   		$results = $query->execute(true);

   		$this->MSLDataList->VirtualItemCount = $query->TotalRows;
    	$this->MSLPaginationPanel->visible = $query->TotalRows > $pageSize;

    	$this->MSLDataList->DataSource = $results;
    	$this->MSLDataList->DataBind();
    }

    public function MSLPageChanged($sender, $param)
    {
    	$this->MSLDataList->EditItemIndex = -1;
      	$this->MSLDataList->CurrentPageIndex = $param->NewPageIndex;
      	$this->dataLoad();
    }

    protected function howBigWasThatQuery()
    {
    	return $this->totalRows;
    }

    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$query = new DaoReportQuery("PartTypeGroupMinimumLevel");
    	$query->column('ptgml.id');
    	$query->column('ptg.name');
    	$query->column('ptgml.quantity');
    	$query->where('ptgml.warehouseid = ? and ptgml.active = 1',array($this->Request['id']));
    	$query->innerJoin('PartTypeGroupMinimumLevel.partTypeGroup','ptg','ptg.active = 1');
    	$query->page($pageNumber,$pageSize);
    	$query->orderBy('ptg.name',DaoReportQuery::ASC);
    	$results = $query->execute(true);
       	$this->totalRows = $query->TotalRows;

    	$this->loadMatchList();

    	return  $results;
    }

    public function updateStock($sender,$param)
    {
    	$id = $param->CommandParameter;
    	$msl = Factory::service("PartTypeMinimumLevel")->get($id);
    	$text = intval($sender->parent->quantity->Text);

    	if($text < 1)
    	{
    		$msl->setActive(0);
    	} else {
    		$msl->setQuantity($text);
    	}

    	Factory::service("PartTypeMinimumLevel")->save($msl);
    	$text = $this->SearchString->Value;
    	$this->dataLoad();
    	$this->loadMatchList($text);
    }

    protected function searchEntity($searchString,&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$query = new DaoReportQuery("PartTypeGroupMinimumLevel");
    	$query->column('ptgml.id');
    	$query->column('ptg.name');
    	$query->column('ptgml.quantity');
    	$query->where('ptgml.warehouseid = ? and ptg.name like ? and ptgml.active = 1',array($this->Request['id'],'%'.$searchString.'%'));
    	$query->innerJoin('PartTypeGroupMinimumLevel.partTypeGroup','ptg','ptg.active = 1');
    	$query->orderBy('ptg.name',DaoReportQuery::ASC);
    	$query->page($pageNumber,$pageSize);
    	$results = $query->execute(true);

    	$this->totalRows = $query->TotalRows;

    	$this->loadMatchList($searchString);

    	return $results;
    }

    public function addMSL($sender,$param)
    {
    	$id = $this->Request['id'];
    	$warehouse = Factory::service("Warehouse")->get($id);

    	foreach($this->MSLDataList->items as $item)
    	{
    		$quantity = intval($item->quantity->text);
    		if($quantity > 0)
    		{
    			$id = $this->MSLDataList->DataKeys[$item->itemindex];
    			$partTypeGroup = Factory::service("PartTypeGroup")->get($id);

    			$piml = new PartTypeGroupMinimumLevel();
    			$piml->setPartTypeGroup($partTypeGroup);
    			$piml->setQuantity($quantity);
    			$piml->setWarehouse($warehouse);
    			Factory::service("PartTypeGroup")->save($piml);
    		}
    	}

    	$text = $this->SearchString->Value;
    	$this->dataLoad();
    	$this->loadMatchList($text);
    }

    public function updateMSL($sender,$param)
    {
    	$parent = $sender->getParent()->getParent();
    	$quantityValue = intval($parent->quantity->Text);
    	$piml = Factory::service("PartTypeGroupMinimumLevel")->get($param->CommandParameter);
    	if($quantityValue < 1)
    	{
    		$piml->setActive(0);
    	} else {
    		$piml->setQuantity($quantityValue);
    	}

    	Factory::service("PartTypeGroupMinimumLevel")->save($piml);
    	$text = $this->SearchString->Value;
    	$this->dataLoad();
    	$this->loadMatchList($text);
    }

    public function getStyle($index)
    {
    	if($index % 2 == 0)
    		return 'DataListItem';
    	else
    		return 'DataListAlterItem';
    }
}
?>