<?php
/**
 * PartInstance History Controller
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 * 
 */
class PartInstanceHistoryController extends CRUDPage 
{	
	/**
	 * @var unknown_type
	 */
	protected $querySize;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext = "partInstanceHistory";
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_partInstanceHistory";
		$this->querySize = 0;
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
	        if(count($this->DataList->DataSource) > 0) 
	        {
				$this->partInstance->Text = " for " . Factory::service("PartInstance")->get($this->Request['id']);
	        }
	        else {
				$this->partInstance->Text = "<font color=red> : No Movement of </font><font color=blue>" . Factory::service("PartInstance")->get($this->Request['id']).".</font>";
	        }
        }
    }

    /**
     * Create New Entity
     *
     * @return unknown
     */
	protected function createNewEntity()
    {
    	return null;
    }

    /**
     * Lookup Entity
     *
     * @param unknown_type $id
     * @return unknown
     */
    protected function lookupEntity($id)
    {
		return null;
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
    	$id = $this->focusObject->value;
    	
    	$partInstanceMoveReport = new DaoReportQuery('LogPartInstanceMove');
    	$partInstanceMoveReport->column('logpim.created','date');
		$partInstanceMoveReport->column($this->createUserAccountLookup("logpim.createdById"),'createdBy');
    	$partInstanceMoveReport->column("if(logpim.warehouseId!=fromWarehouseId,'Moved','Edited')",'text');
    	$partInstanceMoveReport->column('(	select concat(w.id,":::",w.name) from warehouse w 
    										where w.id = logpim.warehouseId)','name');
    	
    	$partInstanceMoveReport->column("pis.name",'status');
    	$partInstanceMoveReport->column("''",'additional');
    	$partInstanceMoveReport->column("logpim.comment",'comment');
    	$partInstanceMoveReport->column("90",'priority');
    	$partInstanceMoveReport->column('logpim.toSiteId','siteId');
    	$partInstanceMoveReport->setAdditionalJoin(" inner join partinstancestatus pis on logpim.partinstancestatusid=pis.id and pis.active=1");
    	$partInstanceMoveReport->where('logpim.partInstanceId = ? and logpim.active = 1',array($id));
    	$partInstanceMoveReport->orderBy('logpim.id');
    	
    	
    	$partInstanceMergeReport = new DaoReportQuery('LogPartInstanceMerge');
    	$partInstanceMergeReport->column('logpime.created','date');
    	$partInstanceMergeReport->column($this->createUserAccountLookup("logpime.createdById"),'createdBy');
    	$partInstanceMergeReport->column("if(logpime.mergeToId = '$id',concat('Merged From (ID=', logpime.mergeFromId, ')'),concat('Merged To (ID=', logpime.mergeToId, ')'))",'text');
    	$partInstanceMergeReport->column("''",'name');
    	 
    	$partInstanceMergeReport->column("''",'status');
    	$partInstanceMergeReport->column("''",'additional');
    	$partInstanceMergeReport->column("concat('Merged on ', piat.name, ': ', logpime.mergeContent, '<br />', logpime.comment)",'comment');
    	$partInstanceMergeReport->column("85",'priority');
    	$partInstanceMergeReport->column("''",'siteId');
    	$partInstanceMergeReport->leftJoin("LogPartInstanceMerge.mergeCriteria", 'piat');
    	$partInstanceMergeReport->where('(logpime.mergeFromId = ? or  logpime.mergeToId = ? ) and logpime.active = 1',array($id, $id));
    	$partInstanceMergeReport->orderBy('logpime.id');
    	
//     		var_dump($partInstanceMergeReport->generate());
// 			print "<br/><br/>";
//     		var_dump($id);
//     		die;
    	
    	$stocktakeLostReport = new DaoReportQuery('LogStocktakeLost',true);
    	$stocktakeLostReport->column('logst.created','date');
    	$stocktakeLostReport->column($this->createUserAccountLookup("logst.createdById"),'createdBy');
    	$stocktakeLostReport->column("'Lost in Stocktake'",'text');
    	//$stocktakeLostReport->column('(select w.name from warehouse w, stocktake s, logstocktakelost lstl where lstl.partInstanceId='.$id.' and lstl.stocktakelogid=s.id and s.warehouseId=w.id)','name');
    	$stocktakeLostReport->column('(select ware.name from partinstance pi 
    									left join warehouse ware on (ware.id = pi.warehouseId and ware.active = 1) 
    									where pi.id = '.$id.')','name');
    	
    	$stocktakeLostReport->column("''",'status');
    	$stocktakeLostReport->column("''",'additional');
    	$stocktakeLostReport->column("''",'comment');
    	$stocktakeLostReport->column("80",'priority');   	
    	$stocktakeLostReport->column("80",'blank');   	
    	$stocktakeLostReport->innerJoin('LogStocktakeLost.stocktakeLog','logst','logstl.partInstanceId = ? and logst.active = 1');
    	
		$stocktakeLostReport->where('logstl.active = 1',array($id));
		$stocktakeLostReport->orderBy('logstl.id');
		/*
			var_dump($stocktakeLostReport->generate());
			print "<br/><br/>";
			var_dump($id);
		*/

    	$stocktakeFoundReport = new DaoReportQuery('LogStocktakeFound',true);
    	$stocktakeFoundReport->column('logst.created','date');
    	$stocktakeFoundReport->column($this->createUserAccountLookup("logst.createdById"),'createdBy');
    	$stocktakeFoundReport->column("'Found in Stocktake'",'text');
    	//$stocktakeFoundReport->column('(select w.name from warehouse w, stocktake s, logstocktakefound lstf where lstf.partInstanceId='.$id.' and lstf.stocktakelogid=s.id and s.targetWarehouseId=w.id)','name');
    	$stocktakeFoundReport->column('(select w.name from warehouse w 
    									where w.id = logst.warehouseId)','name');
    	$stocktakeFoundReport->column("''",'status');
    	$stocktakeFoundReport->column("''",'additional');
    	$stocktakeFoundReport->column("''",'comment');
    	$stocktakeFoundReport->column("80",'priority');
    	$stocktakeFoundReport->column("80",'blank');
    	$stocktakeFoundReport->innerJoin('LogStocktakeFound.stocktakeLog','logst','logstf.partInstanceId = ? and logst.active = 1');
		$stocktakeFoundReport->where('logstf.active = 1',array($id));
		$stocktakeFoundReport->orderBy('logstf.id');		
		/*
			var_dump($stocktakeFoundReport->generate());
			print "<br/><br/>";
			var_dump($id);
		*/
	
    	$stocktakeQuantityReport = new DaoReportQuery('LogStocktakeQuantityChange',true);
    	$stocktakeQuantityReport->column('logst.created','date');
    	$stocktakeQuantityReport->column($this->createUserAccountLookup("logst.createdById"),'createdBy');
    	$stocktakeQuantityReport->column("'Stocktake Quantity Change'",'text');
    	//$stocktakeQuantityReport->column('(	select concat(w.id,":::",w.name) from warehouse w where w.id = logst.warehouseId)','name');
    	$stocktakeQuantityReport->column('(select w.name from warehouse w 
    										where w.id = logst.warehouseId)','name');
    	$stocktakeQuantityReport->column("''",'status');
    	$stocktakeQuantityReport->column("logstqc.changeInQuantity",'additional');
    	$stocktakeQuantityReport->column("''",'comment');
    	$stocktakeQuantityReport->column("70",'priority');
    	$stocktakeQuantityReport->column("70",'blank');
    	$stocktakeQuantityReport->innerJoin('LogStocktakeQuantityChange.stocktakeLog','logst','logstqc.partInstanceId = ? and logst.active = 1');
		$stocktakeQuantityReport->where('logstqc.active = 1',array($id));
		$stocktakeQuantityReport->orderBy('logstqc.id');
		/*
			var_dump($stocktakeQuantityReport->generate());
			print "<br/><br/>";
			var_dump($id);
		*/
				
		$actionReport = new DaoReportQuery('PartInstanceAction',true);
    	$actionReport->column('piaction.created','date');
    	$actionReport->column($this->createUserAccountLookup("piaction.createdById"),'createdBy');
    	$actionReport->column("at.name",'text');
    	$actionReport->column('s.commonName','name');
    	$actionReport->column("''",'status');
    	$actionReport->column("ft.id",'additional');
    	$actionReport->column("piaction.comments",'comment');
    	$actionReport->column("20",'priority');
    	$actionReport->column("20",'blank');
    	$actionReport->innerJoin('PartInstanceAction.workLog','wl','piaction.partInstanceId = ? and piaction.actiontypeId in (5,6) and wl.active = 1')->innerJoin('WorkLog.fieldTask','ft','ft.active = 1')->leftJoin('FieldTask.site','s','s.active = 1');
    	$actionReport->innerJoin('PartInstanceAction.actionType','at','at.active = 1');
		$actionReport->where('piaction.active = 1',array($id));
		$actionReport->orderBy('piaction.id');
		/*		
			var_dump($actionReport->generate());
			print "<br/><br/>";
			var_dump($id);
		*/
		
		$createdReport = new DaoReportQuery('PartInstance',true);
    	$createdReport->column('pi.created','date');
    	$createdReport->column($this->createUserAccountLookup("pi.createdById"),'createdBy');
    	$createdReport->column("'Created'",'text');
    	$createdReport->column("''",'name');
    	$createdReport->column("''",'status');
    	$createdReport->column("''",'additional');
    	$createdReport->column("''",'comment');
    	$createdReport->column("10",'priority');
    	$createdReport->column("10",'blank');  	
    	$createdReport->where('pi.id = ? and pi.active = 1',array($id));
    	$createdReport->orderBy('pi.id');
		/*
			var_dump($createdReport->generate());
			print "<br/><br/>";
			var_dump($id);
		*/
		
    	$workshopFixReport = new DaoReportQuery('LogFix',true);
    	$workshopFixReport->column('logfix.created','date');
    	$workshopFixReport->column($this->createUserAccountLookup("logfix.createdById"),'createdBy');
    	$workshopFixReport->column("'Workshop Fix'",'text');
    	$workshopFixReport->column($this->createUserAccountLookup("logfix.createdById"),'name');
    	$workshopFixReport->column("''",'status');
    	$workshopFixReport->column("fix.name",'additional');
    	$workshopFixReport->column("logfix.comment",'comment');
    	$workshopFixReport->column("10",'priority');
    	$workshopFixReport->column("10",'blank');

    	$workshopFixReport->innerJoin('Logfix.fix','fix');
    	$workshopFixReport->where('logfix.partInstanceId = ? ',array($id));
    	$workshopFixReport->orderBy('logfix.id');
		/*
			var_dump($workshopFixReport->generate());
			print "<br/><br/>";
			var_dump($id);
		*/
    	
    	$workshopDefectReport = new DaoReportQuery('LogDefect',true);
    	$workshopDefectReport->column('logdef.created','date');
    	$workshopDefectReport->column($this->createUserAccountLookup("logdef.createdById"),'createdBy');
    	$workshopDefectReport->column("'Workshop Defect'",'text');
    	$workshopDefectReport->column($this->createUserAccountLookup("logdef.createdById"),'name');
    	$workshopDefectReport->column("''",'status');
    	$workshopDefectReport->column("defect.name",'additional');
    	$workshopDefectReport->column("logdef.comment",'comment');
    	$workshopDefectReport->column("10",'priority');
    	$workshopDefectReport->column("10",'blank');
    	$workshopDefectReport->innerJoin('LogDefect.defect','defect');
    	$workshopDefectReport->where('logdef.partInstanceId = ? ',array($id));
    	$workshopDefectReport->orderBy('logdef.id');
    	
    	$unionReport = new DaoReportQuery();
    	$unionReport->union($partInstanceMoveReport);
    	$unionReport->union($partInstanceMergeReport);
    	$unionReport->union($stocktakeLostReport);
    	$unionReport->union($stocktakeFoundReport);
    	$unionReport->union($stocktakeQuantityReport);
    	$unionReport->union($actionReport);
    	$unionReport->union($createdReport);
    	$unionReport->union($workshopFixReport);
    	$unionReport->union($workshopDefectReport);
    	$unionReport->orderBy('date',DaoReportQuery::DESC);
    	$unionReport->orderBy('priority',DaoReportQuery::ASC);
    	$unionReport->page($pageNumber,$pageSize);    	
    	
    	$unionReport->unionAll = true;
    	$results = $unionReport->execute();
    	
    	$this->querySize = $unionReport->TotalRows;
    	$default_warehouse_timezone=Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
    
    	$actualResultsArray = array();
    	
    	for ($i=0; $i<count($results); $i++)
    	{
    		// As per discussion with Noel / Trevor.... decided to show melbourne timezone instead of UTC timezone....
    		/*$createdDateMelTimeZone = new HydraDate($row[0]);
    		$createdDateMelTimeZone->setTimeZone("Australia/Melbourne");*/
    			
    		//Changeing this to reflect the user's default Store Timezone
    		$createdDateLocalTime = new HydraDate($results[$i][0]);
    		$createdDateLocalTime->setTimeZone($default_warehouse_timezone);
    		
    		array_shift($results[$i]);
    		array_unshift($results[$i],$createdDateLocalTime);
    		
 			//START to display Sites name   		
    		if ($results[$i][3] != '')
    		{
	    		$whInfo = explode(':::', $results[$i][3]);
	    	
	    		$warehouseObject = Factory::service("Warehouse")->getWarehouse($whInfo[0]); 
	    		if(is_numeric($whInfo[0]))
	    		{
    				$warehouseRoot = Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouseObject,false,".");
	    		}	
	    		else
	    		{
    				$warehouseRoot = $results[$i][3];
	    		}			
	    		
	    		if ($whInfo[0] == Factory::service("Warehouse")->getSiteWarehouse()->getId()) //going to site
	    		{
		    		$destinationSite = Factory::service("Site")->getSite($results[$i][8]);
			    	if ($destinationSite instanceof Site && $destinationSite->getCommonName() != null)
			  		{
		  				$results[$i][3] = $warehouseRoot;
			  		}
			  		else 
			  			$results[$i][3] = $warehouseRoot;
	    		}
	    		else
	    		{
	    			$results[$i][3] = $warehouseRoot;
	    		}
    		}
 			//END to display Sites name
    		
    		array_push($actualResultsArray,$results[$i]);
    	}
    	array_multisort($actualResultsArray,SORT_DESC);    	
    	
    	return $actualResultsArray;
    }
    
    /**
     * make the hyperlinke to the merged from/into part instance's history from it's comments.
     * It's been called in the .page file.
     * 
     * @param string $comments
     * 
     * @return string
     */
    public function formatMergeLink($comments)
    {
    	if(trim($comments) === '')
    		return;
    	
    	if(preg_match('/^Merged (?P<id>.+(ID=\d+))/', $comments, $matches))
    	{
    		if(preg_match('/^(?P<sn>.+)(ID=(?P<id>\d+))/', $matches['id'], $ma))	
    		return str_replace($matches['id'], '<a style="font-weight:bold;" title="go this it\'s history" href="/parthistory/searchparttext/' . trim($ma['id']) . '" >' . $ma['sn'] .'</a>', $comments);
    	}
    	
    	return $comments;
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
     * Create Menu
     *
     * @param unknown_type $focusObject
     * @param unknown_type $focusArgument
     */
 	public function createMenu(&$focusObject=null,$focusArgument="")
    {
    }
    
    /**
     * Get Local Timezone
     *
     * @return unknown
     */
    public function getLocalTimeZone()
    {
    	return $default_warehouse_timezone=Factory::service("Warehouse")->getDefaultWarehouseTimeZone(Core::getUser());
    	
    }

}

?>