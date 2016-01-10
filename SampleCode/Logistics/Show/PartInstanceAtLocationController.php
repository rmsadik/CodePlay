<?php
ini_set("max_execution_time", 600);
ini_set("memory_limit", "200M");
/**
 * PartInstance At Location Controller
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class PartInstanceAtLocationController extends CRUDPage
{
	/**
	 * @var unknown_type
	 */
	public $totalcount;

	/**
	 * @var unknown_type
	 */
	private $fileName;

	/**
	 * @var unknown_type
	 */
	private $subwarehouseIds = array();

	/**
	 * @var unknown_type
	 */
	private $subwarehouseNames = array();

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->allowOutPutToExcel = true;
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_stock";
	}

	/**
	 * On Pre Initialization
	 *
	 * @param unknown_type $param
	 */
	public function onPreInit($param)
	{
		$str = explode('/',$_SERVER['REQUEST_URI']);
		$this->menuContext = "stocktake";
		if(isset($this->Request['siteid']))
		{
			$this->getPage()->setMasterClass("Application.layouts.NoExtJs.DefaultLayout");
		}
		else if ($str[1] == 'staging')
		{
			$this->getPage()->setMasterClass("Application.layouts.NoExtJs.StagingLayout");
			$this->roleLocks = "pages_all,pages_logistics,page_logistics_stock,menu_staging";
			$this->menuContext = 'staging/consignment';
		}
		else
			$this->getPage()->setMasterClass("Application.layouts.NoExtJs.LogisticsLayout");
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
    	parent::onLoad($param);
		$this->fileName = "Stock";
		$this->jsLbl->Text = "";
	    if(!$this->IsPostBack || $param == "reload")
        {
        	$defaultWarehouse = Factory::service("Warehouse")->getDefaultWarehouse(Core::getUser());
        	try
        	{
        		//if AGENT
        		if (strpos(strtolower(Core::getRole()->getName()), "agent") !== false)
        		{
        			//check they have a valid Default Warehouse
        			WarehouseLogic::checkValidDefaultWarehouse($defaultWarehouse);

        		}
        		else
        		{
        			//check they have a valid Default Warehouse, we want to let through if no filters and no default warehouse
        			$errMsg = WarehouseLogic::checkValidDefaultWarehouse($defaultWarehouse, false);
	        		if ($errMsg !== true)
	        		{
        				$this->StocktakeLocationButton->Enabled = false;
        				$this->stocktakeErrorLbl->Text = $errMsg;
	        		}
        		}
        		//if they have View Warehouse Filters check if the Default Warehouse exists under or equal
	        	WarehouseLogic::checkDefaultWarehouseWithinViewWarehouseFilter($defaultWarehouse);
        	}
        	catch (Exception $e)
        	{
        		$this->setErrorMessage($e->getMessage());
        		$this->Page->MainContent->Enabled = false;
        		$this->whTree->Visible = false;
        		return;
        	}

        	$this->ListingPanel->findControl('OutputToExcelTable')->findControl('OutputToExcelRow')->findControl('OutputToExcelCell')->findControl('OutputToExcelButton')->Visible=false;

        	$this->getInitialTreePath($defaultWarehouse);

        	$this->DataList->pageSize = $this->resultsPerPageList->numResults->getSelectedValue();

			$this->aliasTypes->DataSource = Factory::service("PartInstanceAliasType")->findAll();
        	$this->aliasTypes->DataBind();
        }
        else
        {
        	$this->MoreOptionLabel->setStyle("display:block;");
	        $this->LessOptionLabel->setStyle("display:none;");
	        $this->SearchRefinePanel->setStyle("display:none; border: 1px solid black; padding: 3px; overflow: visible;");
        }
    }

    private function getInitialTreePath($defaultWarehouse)
    {
    	if(isset($this->Request['siteid']))
    	{
    		$site = $this->getFocusEntity($this->Request['siteid'],"Site");

    		$this->SearchStockLocationButton->Visible=false;
    		$this->StocktakeLocationButton->Visible=false;
    		$this->WarehouseLabel->Visible=false;
    		$this->whTree->Visible = false;
    		$this->FilterForSite->setViewState('value', $site->getId(), null);
    		$this->FilterForSite->Text = $site->getSiteCode(). " - ".$site->getCommonName();
    		$this->DataList->EditItemIndex = -1;
    		$this->dataLoad();
    	}
    	else if(isset($this->Request['id']))
    	{
    		$warehouse = Factory::service("Warehouse")->getWarehouse($this->Request['id']);
    		$this->whTree->whIdPath->Value = $warehouse->getId();
    		$this->DataList->EditItemIndex = -1;
    		$this->dataLoad();
    	}
    	else
    	{
    		if ($defaultWarehouse instanceOf Warehouse)
    		{
    			$this->whTree->whIdPath->Value = $defaultWarehouse->getId();
    		}
    	}
    }

    /**
     * Get Focus Entity
     *
     * @param unknown_type $id
     * @param unknown_type $type
     * @return unknown
     */
    protected function getFocusEntity($id,$type="")
    {
    	if(trim($type)!="")
    	{
	    	return Factory::service(ucfirst($type))->get($id);
    	}
    }

    /**
     * Get All of Entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	if(isset($this->Request['siteid']))
    	{
    		$countSql = "DISTINCT pi.id";
		    $selectStatement = "
		    					if(pi.quantity>1,concat('<b>',pi.quantity,'</b>'),pi.quantity) as Qty,
					    		pta.alias as Partcode,
					    		if(pt.serialised=1,
					    			(	SELECT GROUP_CONCAT(pia.alias SEPARATOR '<br/>')
										FROM partinstancealias pia
										WHERE pia.partInstanceAliasTypeId = 1
										AND pia.partInstanceId = pi.id
										AND pia.active = 1),
					    			concat('<b>',pta1.alias,'</b>')) as Barcode,
					    		pis.name as Status,
					    		pt.name as Decsription,
					    		'" . str_replace("'", "\'", $this->FilterForSite->Text) . "' as Location,
					    		'27' as warehouseID
					    		";
		    $sql = "select {select} from partinstance pi
		    		left join partinstancestatus pis on (pis.id = pi.partInstanceStatusId)
		    		inner join parttype pt on (pt.id = pi.partTypeId)
		    		left join partinstancealias pia on (pia.partInstanceId = pi.id and pia.active = 1 and pia.partInstanceAliasTypeId = 1)
		    		left join parttypealias pta on (pta.active = 1 and pta.partTypeAliasTypeId = 1 and pt.id = pta.partTypeId)
		    		left join parttypealias pta1 on (pta1.active = 1 and pta1.partTypeAliasTypeId = 2 and pt.id = pta1.partTypeId)
		    		where pi.active = 1
		    		and pi.siteId = ".trim($this->Request['siteid'])."
		    		GROUP BY pi.id
		    		ORDER BY pta.alias";
	    	$res = Dao::getResultsNative(str_replace("{select}",$selectStatement,$sql));

	    	if(count($res)>0 && sizeof($res)>"")
	    		$this->DataList->pageSize = count($res);

	    	return $res;
    	}

    	$this->ListingPanel->findControl('OutputToExcelTable')->findControl('OutputToExcelRow')->findControl('OutputToExcelCell')->findControl('OutputToExcelButton')->Visible=false;
    	$this->StockHeaderPanel->Visible=false;
    	$extraLabel="";
	    $warehouseName = "";

		$temp="";
	    // 1/11/2010 - added functionality to do group by part type, then by location, then by status
	    // 22/11/2010 - added functionality to do group by part type, then by status
	    $toGroupByCodeStatusLocation = $this->DoGroupResultsByCodeStatusLocation->Checked;
	    $toGroupByCodeStatus = $this->DoGroupResultsByCodeStatus->Checked;

	    $warehouseNameSql = "(SELECT GROUP_CONCAT(w7.name ORDER BY w7.position asc SEPARATOR '/') FROM warehouse w7 WHERE w7.active=1 AND ware.position LIKE concat(w7.position,'%') AND w7.active=1)";
	    if ($toGroupByCodeStatusLocation)
	    {
		    $countSql = "DISTINCT (CONCAT(pt.id, '-', ware.id, '-', pis.id))";
		    $selectStatement = "
			     				SUM(pi.quantity) AS sumqty1,
					    		pta.alias,
					    		if(pt.serialised=1, 'serialised', pta1.alias),
					    		pis.name,
					    		pt.name,
					    		'' as Location,
					    		pi.warehouseid,
					    		wc.id as warehouseCategoryId,
		    					'' as ptId,
		    					pta.alias as Partcode,
		    					pt.serialised as serialised

					    		";
		    $joinStatement = "";
		    $additionalWhereStatement = "";
		    $groupByStatement = " GROUP BY pt.id, ware.id, pis.id ";
		    $orderByStatement = " ORDER BY ware.position, pta.alias, pis.name";
	    }
	    else if ($toGroupByCodeStatus)
	    {
		    $countSql = "DISTINCT (CONCAT(pt.id, '-', pis.id))";
		    $selectStatement = "
			     				SUM(pi.quantity) AS sumqty2,
					    		pta.alias,
					    		if(pt.serialised=1, 'serialised', pta1.alias),
					    		pis.name,
					    		pt.name,
					    		'' AS name,
					    		'',
					    		wc.id as warehouseCategoryId,
		    					'' as ptId,
		    					pta.alias as Partcode,
		    					pt.serialised as serialised
					    		";
		    $joinStatement = "";
		    $additionalWhereStatement = "";
		    $groupByStatement = " GROUP BY pt.id, pis.id ";
		    $orderByStatement = " ORDER BY pta.alias, pis.name";
	    }
	    else
	    {
		    $countSql = "DISTINCT pi.id";
		    $selectStatement = "
		    					if(pi.quantity>1,concat('<b>',pi.quantity,'</b>'),pi.quantity) as Qty,
					    		pta.alias as Partcode,
					    		if(pt.serialised=1,
					    			(	SELECT GROUP_CONCAT(pia.alias SEPARATOR '<br/>')
										FROM partinstancealias pia
										WHERE pia.partInstanceAliasTypeId = 1
										AND pia.partInstanceId = pi.id
										AND pia.active = 1),
					    			concat('<b>',pta1.alias,'</b>')) as Barcode,
					    		pis.name as Status,
					    		pt.name as Decsription,
					    		if(pi.siteId is null,
					    		'',
					    		concat('[',ware.name,'] ',commonName)
					    		) as Location,
		    					ware.id as warehouseID ,
		    					wc.id as warehouseCategoryId,
					    		pt.id as ptId,
		    					pta.alias as Partcode,
		    					pt.serialised as serialised
					    		";
		    $joinStatement = "";
		    $additionalWhereStatement = "";
		    $groupByStatement = " GROUP BY pi.id";
		    $orderByStatement = " ORDER BY ware.position, pta.alias";
	    }


		//check that alias exists before running querys
	    if($this->alias->Text){
    		$sqlTest = " select id from partinstancealias pia where pia.partInstanceAliasTypeId = " . $this->aliasTypes->getSelectedValue() . " and pia.active = 1 ";
			$sqlTest .= " and pia.alias like '" . $this->alias->Text . "'";
			if(count(Dao::getResultsNative($sqlTest))==0)
			{
				$this->totalcount = 0;
				$this->StockHeader->Text =  "<div style='font-size:0.9em; font-weight:normal; width:100%; '>
		    					LAST STOCKTAKE DATE: <br/>
								NEXT STOCKTAKE DATE: <br/>
		    				</div>";;
	    		$this->RecordHeader->Text = "Total quantity found 0.";
	    		$this->StockHeaderPanel->Visible=true;
	    		return;
			}
    	}

    	$pageSize = $this->resultsPerPageList->numResults->getSelectedValue();
	    $sql = $this->getSql($selectStatement,$extraLabel,$warehouseName,$pageNumber,$pageSize,$joinStatement, $additionalWhereStatement, false, $groupByStatement, $orderByStatement);
// 		echo $sql;
	    $res = Dao::getResultsNative($sql);
	    if ($toGroupByCodeStatus)
	    {

	    }
	    else
	    {
			$temp = array();
		    foreach($res as $row)
		    {
		    	if (($row[5]<='') && ($row[6]>''))
		    	{
		    		if(isset($this->subwarehouseNames[$row[6]]['full'])){
			    		if($this->ShowLocName->Checked)
	    					$row[5] = $this->subwarehouseNames[$row[6]]['full'];
	    				else
	    					$row[5] = $this->subwarehouseNames[$row[6]]['name'];

		    		}else{
		    			$warehouse = Factory::service("Warehouse")->getWarehouse($row[6]);
		    			if($warehouse instanceOf Warehouse){
	       					$this->subwarehouseNames[$row[6]]['full']= Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,true,"/");
		    				$this->subwarehouseNames[$row[6]]['name']= $warehouse->getName();
		    			}else{
		    				$this->subwarehouseNames[$row[6]]['full'] = "";
		    				$this->subwarehouseNames[$row[6]]['name'] = "";
		    			}

		    			if($this->ShowLocName->Checked)
	    					$row[5] = $this->subwarehouseNames[$row[6]]['full'];
	    				else
	    					$row[5] = $this->subwarehouseNames[$row[6]]['name'];
		    		}

		    	}
				if(intval($row[7]) == WarehouseCategory::ID_RESTRICTED_AREA)
				{
					$row[5] = "<span style='color:red'>(Restricted area)</span> "	. $row[5];
				}

				if(!$toGroupByCodeStatusLocation && !$toGroupByCodeStatus)//if group by partcode, part status , location
				{
					//if BS/BP is deactivated
					$deactivatedBarcode='';
					if(empty($row[2]) || is_null($row[2]))
					{
						if($warehouse instanceOf Warehouse)
						{
							$piId = Factory::service('PartInstance')->findByCriteria('pi.parttypeid=? and pi.warehouseid = ?',array($row[8],$warehouse->getId()));
							if(count($piId)>0 && $piId[0] instanceof PartInstance)
							{
								if($row[10] == 1)
								{
									$aliasType = PartInstanceAliasType::ID_SERIAL_NO;
									$deactivatedBarcode = Factory::service('PartInstance')->searchPartInstanceAliaseByPartInstanceAndAliasType($piId[0]->getId(), $aliasType,true);
									if(count($deactivatedBarcode)>0 && $deactivatedBarcode[0] instanceof PartInstanceAlias)
										$deactivatedBarcode = $deactivatedBarcode[0]->getAlias();
								}
								else
								{
									$aliasType = PartTypeAliasType::ID_BP;
									$bp = Factory::service('PartTypeAlias')->findByCriteria('pta.parttypeid=? and pta.parttypealiastypeid=? and pta.active=0',array($row[8],$aliasType),true);
									if(count($bp)>0 && $bp[0] instanceof PartTypeAlias)
										$deactivatedBarcode = $bp[0]->getAlias();
								}
							}

						}
					}

					if(($row[0]>1 && $row[10] == 1) || ((!is_array($deactivatedBarcode) && trim($deactivatedBarcode)) == "") || (is_array($deactivatedBarcode)))//if serialized and quantity >1 || ignore looking for deactive alias
						$row[8] = "";
					else
						$row[8] = "<b><span style='color:red'>".$deactivatedBarcode."<br/>(De-active)</span></b> ";
				}


				$temp[] = $row;
		    }
		    $res = $temp;
	    }
	    if ($toGroupByCodeStatus || $toGroupByCodeStatusLocation)
	    {
			$result = Dao::getResultsNative($this->getSql("SUM(pi.quantity)",$temp,$warehouseName,null,null,$joinStatement, $additionalWhereStatement, false, "", $orderByStatement));
	    	$qtyCount = 0;
	    	foreach ($result as $row)
	    		$qtyCount += $row[0];

	    	$this->totalcount=count($result)>0 ? count($result) : 0;
    		$this->RecordHeader->Text = "Total quantity found ".$qtyCount.".";
	    }
	    else
	    {
			$result = Dao::getResultsNative($this->getSql("SUM(pi.quantity)",$temp,$warehouseName,null,null,$joinStatement, $additionalWhereStatement, false, $groupByStatement, $orderByStatement));
			$qtyCount = 0;
	    	foreach ($result as $row)
	    		$qtyCount += $row[0];

	    	$this->totalcount=count($result)>0 ? count($result) : 0;
    		$this->RecordHeader->Text = "Total quantity found ".$qtyCount.".";
	    }

	    if($this->totalcount>0)
	   		$this->ListingPanel->findControl('OutputToExcelTable')->findControl('OutputToExcelRow')->findControl('OutputToExcelCell')->findControl('OutputToExcelButton')->Visible=true;

		$maximumRowsForExcel = $this->getMaximumRowsForExcel();
    	if($this->totalcount > $maximumRowsForExcel)
    	{
    		$this->RecordHeader->Text = $this->RecordHeader->Text . " <span style='color:red'>To excel is disabled, you may only export " . $maximumRowsForExcel . " rows to excel.";
    		$this->ListingPanel->findControl('OutputToExcelTable')->findControl('OutputToExcelRow')->findControl('OutputToExcelCell')->findControl('OutputToExcelButton')->Visible=false;
    	}

	   	$this->StockHeaderPanel->Visible=true;
    	$this->StockPanel->Visible=true;
    	$this->StockHeader->Text = "View stock for " . $warehouseName . $extraLabel;

    	//see whether we want to show the regenerate button or not
    	$this->Page->regenBtn->Style = 'display:none';
    	$ids = explode('/',$this->whTree->whIdPath->Value);
    	$sql = "SELECT COUNT(id) FROM logstocktake WHERE active=0 AND warehouseId=" . end($ids);
    	$oldStocktakeCount = Dao::getSingleResultNative($sql);
    	if ($oldStocktakeCount[0] > 0)
    		$this->Page->regenBtn->Style = '';


    	return $res;
    }

    /**
     * Results Per Page Changed
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function resultsPerPageChanged($sender, $param)
    {
    	$this->AddPanel->Visible = false;
    	$this->DataList->EditItemIndex = -1;
    	$this->DataList->pageSize = $param->NewPageResults;
    	$this->dataLoad();
    }

    /**
     * Get Sql
     *
     * @param unknown_type $select
     * @param unknown_type $extraLabel
     * @param unknown_type $warehouseName
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @param unknown_type $join
     * @param unknown_type $where
     * @param unknown_type $runSqlOnly
     * @param unknown_type $groupBy
     * @param unknown_type $orderBy
     * @return unknown
     */
    private function getSql($select,&$extraLabel,&$warehouseName,$pageNumber=null,$pageSize=null,$join="",$where="",$runSqlOnly=false,$groupBy="",$orderBy="")
    {
    	$restrictedWarehouse = Factory::service('Useraccountfilter')->findByCriteria('uaf.filterid =13 and uaf.roleid=? and uaf.useraccountid=?',array(core::getRole()->getId(),core::getUser()->getId()));
    	$rWarePositions = array();
    	if(count($restrictedWarehouse)>0)
    	{
    		$restrictedWarehouseIds = $restrictedWarehouse[0]->getFilterValue();
	    	$allowedRestrictedWarehouses = Factory::service('Warehouse')->findByCriteria("id in(".$restrictedWarehouseIds.")");
			if(!empty($allowedRestrictedWarehouses) && count($allowedRestrictedWarehouses)>0)
			{
				foreach($allowedRestrictedWarehouses as $allowedRestrictedWarehouse)
					$rWarePositions[] =$allowedRestrictedWarehouse->getPosition();
			}
    	}
    	$this->whTree->Visible = true;
    	$this->StocktakeLocationButton->Enabled=true;
    	$this->WarehouseLabel->Visible = true;
    	$siteId = 0;
    	$forSiteId = $this->FilterForSite->getSelectedValue();
    	if (!empty($forSiteId) && is_numeric($forSiteId) && $forSiteId > 0)
    	{
    		$warehouseName = $this->FilterForSite->getText();
    		$siteId = $forSiteId;
	    	$this->whTree->Visible = false;
	    	$this->StocktakeLocationButton->Enabled=false;
	    	$this->WarehouseLabel->Visible=false;
    	}
    	else
    	{
	    	$ids = explode('/',$this->whTree->whIdPath->Value);
		    $warehouseId = end($ids);
		    $warehouseParent = Factory::service("Warehouse")->getWarehouse($warehouseId);
		    $warehouse = $warehouseParent;

    		$maxChildrenWarehousesForOutput = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'maxChildrenWarehousesForOutput',true);
	    	if(!is_numeric($maxChildrenWarehousesForOutput))
	    	{
	    		$maxChildrenWarehousesForOutput = 10000;
	    	}

	    	$excludeWhIds = array();
	    	$ignoreWhIds = array();
	    	$excludeWhPositions = array();
	    	$includeWhPositions = array();

    	 	$childrenWarehouseCount = Factory::service("Warehouse")->getWarehouseChildrenCount($warehouse);
		    if ($childrenWarehouseCount > $maxChildrenWarehousesForOutput)
		    {
		    	if(!$this->alias->Text)
		    	{
		    		$this->subwarehouseIds[$warehouseId] = -1;
		    		$this->setErrorMessage("The maximum of " . $maxChildrenWarehousesForOutput . " children warehouses has exceeded. <br>Please choose a lower level, Thank you.");
		    	}
		    }
		    else
		    {
		    	$this->subwarehouseIds[$warehouseId] = $warehouseId;
			    //if we want to see all part instance in all sub trees
			    if ($this->ShowSubs->Checked)
			    {
			    	if($runSqlOnly==false)
			    		$extraLabel = " and sublocations";

					$sql = "SELECT id, ignorestockcount,ignorestocktake,position FROM warehouse ware WHERE ware.active=1 AND ware.position LIKE '".$warehouse->getPosition()."%'";
					$result = Dao::getResultsNative($sql, array(), PDO::FETCH_ASSOC);
				    foreach ($result as $row)
				    {
				    	$this->subwarehouseIds[$row['id']] = $row['id'];

				    	if ($row['ignorestockcount'] == 1 || $row['ignorestocktake'] == 1)
				    	{
					    	if ($row['ignorestockcount'] == 1)
				    			$ignoreWhIds['ignoreSC'][] = $row['id'];
					    	if ($row['ignorestocktake'] == 1)
				    			$ignoreWhIds['ignoreST'][] = $row['id'];

				    		$excludeWhPositions[] = $row['position'];
				    		$excludeWhIds[] = $row['id'];
				    	}
				    	else if ($row['ignorestockcount'] == 0)
				    		$includeWhPositions[] = $row['position'];
	       			}
			    }
		   }
 		   $js = '<a href="#" id="showDetailsLink" onclick="showDetails();" style="display:none;"> Show Details </a>';
 		   $js .= '<a href="#" id="hideDetailsLink" onclick="hideDetails();" > Hide Details </a>';
		   $ignoreTable ="";
		   $ignoreTable .= $js;
		   $stockCountImage="<img alt='Ignore In Stock Count' title='This location is set to Ignore in Stock Count' src='../../../themes/images/icons/info-badge.png'/>";
		   $stockTakeImage="<img alt='Ignore In Stocktake' title='This location is set to Ignore in Stocktake' src='../../../themes/images/icons/excl-badge.png'/>";

		   $ignoreTable .= "<div id='ignoreWarhouseInfo' style='font-size:0.9em; font-weight:normal;' ><table class='ignoreWarhouseInfoTable' border='1'>";
		   $ignoreTable .= "<tr><th><b>Warehouse</b></th><th><b>Ignore SC</b></th><th><b>Ignore ST</b></th></tr>";
			if (count($excludeWhIds) > 0)
			{
				$extraLabel .= "<div style='width:100%; '>
									<h3>These warehouses are excluded from stocktake and/or stock count :</h3><br>";
				foreach ($excludeWhIds as $whId)
				{
					$wh = Factory::service("Warehouse")->getWarehouse($whId);
					if ($wh instanceOf Warehouse)
					{
						$sc=$st="";
						if(isset($ignoreWhIds['ignoreSC']) && in_array($whId,$ignoreWhIds['ignoreSC']))
							$sc=$stockCountImage;
						if(isset($ignoreWhIds['ignoreST']) && in_array($whId,$ignoreWhIds['ignoreST']))
							$st=$stockTakeImage;
						$ignoreTable .= "<tr style='font-size:1.0em;'><td>" . Factory::service("Warehouse")->getWarehouseBreadCrumbs($wh) . "</td><td>".$sc."</td><td>".$st."</td></tr>";
					}
				}
			   	$ignoreTable .= "<tr><td colspan='3'>&nbsp;&nbsp;&nbsp;".$stockCountImage." - Ignore Stock Count&nbsp;&nbsp;&nbsp;&nbsp;".$stockTakeImage." - Ignore StockTake &nbsp;</td></tr>";
			   	$ignoreTable .= "</table></div>";
				$extraLabel .= "</div><br>";
			$extraLabel .= $ignoreTable;
			}
			$extraLabel .= "<div style='font-size:0.9em; font-weight:normal; width:100%; '>
		    					LAST STOCKTAKE DATE: ".Factory::service("Warehouse")->getLastStocktakeDate($warehouseParent)."<br/>
								NEXT STOCKTAKE DATE: ".Factory::service("Warehouse")->getNextStocktakeDate($warehouseParent)."<br/>
		    				</div>";
    	}

    	$contractId = 0;
    	$forContractId = $this->FilterToContract->getSelectedValue();
    	if (!empty($forContractId) && is_numeric($forContractId) && $forContractId > 0)
    	{
    		$contractId = $forContractId;
    		if($runSqlOnly==false)
    			$extraLabel .= "<br /><span style='font-size:0.9em; font-weight:normal;'>For Contract: ".$this->FilterToContract->Text.'</span>';
    	}

    	$partTypeId = 0;
    	$forPartTypeId = $this->FilterForPartType->getSelectedValue();
    	if (!empty($forPartTypeId) && is_numeric($forPartTypeId) && $forPartTypeId > 0)
    	{
    		$partTypeId = $forPartTypeId;
    		if($runSqlOnly==false)
    			$extraLabel .= " <br /><span style='font-size:0.9em; font-weight:normal;'>For Part: ".$this->FilterForPartType->Text.'</span>';
    	}


    	// NOTE 4/11/2010 - there's a potential that a part instance could have multiple active partinstancealiases
    	// so for that, we'll need to put a group by partinstance.id, if $groupBy is empty
    	// if $groupBy has been supplied by the user, then it's up to them to handle the result of the aggregate functions
    	if (empty($groupBy))
    		$groupBy = " GROUP BY pi.id ";

    	$sql = "SELECT $select
	    		FROM partinstance pi
	    		INNER JOIN parttype pt ON (pi.partTypeId = pt.id AND pt.active=1) ";

    	if($contractId > 0){
	    	$sql .= " LEFT JOIN contract_parttype cpt ON (cpt.partTypeId=pt.id) ";
    	}
    	$sql .= " left join partinstancestatus pis on (pi.partInstanceStatusId = pis.id)
	    		left join warehouse ware on (pi.warehouseId = ware.id)
	    		left join site s on (s.id = pi.siteId)
	    		left join parttypealias pta on (pta.partTypeAliasTypeId = 1 and pta.partTypeId = pt.id and pta.active = 1)
	    		left join parttypealias pta1 on (pta1.partTypeAliasTypeId = 2 and pta1.partTypeId = pt.id and pta1.active = 1)
	    		left join warehousecategory wc on (ware.warehouseCategoryId = wc.id and wc.active=1) ";



    	//taking care of from facility of last move
    	$fromFacilityId = $this->FilterForFacility->getSelectedValue();
    	if($fromFacilityId)
    	{
    		if($runSqlOnly==false)
    			$extraLabel .= " <br /><span style='font-size:0.9em; font-weight:normal;'>For Part: ".$this->FilterForPartType->Text.'</span>';

    		$sql .= " inner join logpartinstancemove lpmFacilityLastMoved on lpmFacilityLastMoved.partInstanceId = pi.id and lpmFacilityLastMoved.fromFacilityId = $fromFacilityId
    		and lpmFacilityLastMoved.warehouseId in  (".implode(",",$this->subwarehouseIds).") and lpmFacilityLastMoved.id = (select max(lpm2.id) from logpartinstancemove lpm2 where lpm2.partInstanceId = pi.id) " ;
    	}


    	if($this->alias->Text)
    	{
    		$sql .= " left join partinstancealias pia on (pia.partInstanceAliasTypeId = " . $this->aliasTypes->getSelectedValue() . " and pia.partInstanceId = pi.id and pia.active = 1) ";
    	}

	    $sql .= $join;

	    $sql .=	" where pi.active = 1 and pi.quantity>0 ";

    	if (!UserAccountService::isSystemAdmin())//restricted warehouse
    	{
    		$sql .= " and (ware.warehousecategoryid != 18 ";
    		if(count($rWarePositions)>0 && sizeof($rWarePositions)>'')
    		{
    			$rsql=array();
    			foreach($rWarePositions as $key => $rWarePosition)
    			{
    				if($key == 0)
    					$rsql[] = "ware.position like('".$rWarePosition."%')";
    			}

    			$reSql="";
    			if(count($rsql)>0 && sizeof($rsql)>'')
    				$sql .= " OR ".implode(' OR ', $rsql);
    		}
    		$sql .= " ) ";
    	}

    	//ignore stock count
    	if ($this->ShowIgnoreStockcount->Checked)
    	{
    		if(count($includeWhPositions) > 0 && sizeof($includeWhPositions)>'')
    		{
    			$sql .= " and (";
    			$inPosSql = array();
    			foreach ($includeWhPositions as $includeWhPosition)
    				$inPosSql[] =	"ware.position like('".$includeWhPosition."%')";
    	
    			if(count($inPosSql)>0 && sizeof($inPosSql)>'')
    				$sql .= implode(' or ', $inPosSql);
    	
    			$sql .= " )";
    		}
    	}
    	 
	    //and (wa.alias is null or wa.alias = 0)
	    if($this->alias->Text)
	    {
	    	$sql .= " and pia.alias like '" . $this->alias->Text . "'";
	    }
	    else
	    {
	    	$sql .= (count(array_keys($this->subwarehouseIds))>0 ? " and pi.warehouseId in (".implode(",",$this->subwarehouseIds).") " : "");
	    }

    	$sql .= ($forSiteId > 0 ? " and pi.siteId=$forSiteId" : "") .
    			($contractId>0 ? " and cpt.contractId=$contractId" : "") .
    			($partTypeId>0 ? " and pt.id=$partTypeId" : "") .
    			$where .
    			$groupBy .
    			$orderBy;

	    if ($pageNumber!=null)
	    {
	    	$sql.=" limit ".($pageNumber-1)*$pageSize.",$pageSize";
	    }
    	$todayDate = new HydraDate("now");
		$this->fileName = "Stock " . $warehouseName . " " . date_format($todayDate->getDateTime(), 'Ymd');
    	return $sql;
    }

    /**
     * How Big Was The Query
     *
     * @return unknown
     */
	protected function howBigWasThatQuery()
    {
    	return $this->totalcount;
    }

    /**
     * Pick Facility
     *
     * @param unknown_type $searchText
     * @return unknown
     */
    public function pickFacility($searchText)
    {
    	$sql = "select
    				f.id,
    				concat(warea.alias,' - ', ware.name) 'facility'
    			from warehouse ware
    				inner join facility f on (f.id = ware.facilityId and f.active = 1)
    				left join warehousealias warea on (warea.warehouseAliasTypeId = 2 and warea.warehouseId=ware.id and warea.active = 1)
    			where
    				ware.active = 1
    				and concat(warea.alias,' - ', ware.name) like '%$searchText%'
    			order by facility";
    	$res = Dao::getResultsNative($sql);
    	$partTypeArr = array();
    	foreach ($res as $row)
    		$partTypeArr[$row[0]] = array("id" => $row[0], "facility" => $row[1]);

    	return $partTypeArr;
    }

    /**
     * Pick PartType
     *
     * @param unknown_type $searchText
     * @return unknown
     */
	public function pickPartType($searchText)
    {
    	$sql = "select
    				  pt.id,
    				  concat(pta.alias,' - ', pt.name) 'parttype'
    			from parttype pt
    				  left join parttypealias pta on (pta.partTypeId = pt.id and pta.partTypeAliasTypeId = 1 and pta.active = 1)
    			where
    				  pt.active = 1
    				  and concat(pta.alias,' - ', pt.description) like '%$searchText%'
    			order by parttype";
    	$res = Dao::getResultsNative($sql);
    	$partTypeArr = array();
    	foreach ($res as $row)
    		$partTypeArr[$row[0]] = array("id" => $row[0], "parttype" => $row[1]);

    	return $partTypeArr;
    }

    /**
     * Get Maximum Rows For Excel
     *
     * @return unknown
     */
    private function getMaximumRowsForExcel()
    {

    	$maxRowsForOutput = $this->dontHardcodeService->searchDontHardcodeByParamNameAndFilterName(__CLASS__,'maxRowsForExcelOutput',true);

    	if(!is_numeric($maxRowsForOutput))
    	{
    		$maxRowsForOutput = 15000;
    	}

    	return $maxRowsForOutput;
    }

    /**
     * Output To Excel
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
    public function outputToExcel($sender, $param)
    {
    	$a = "";
    	$b = "";
    	$sql="";
    	$orderByStatement = "";

   	 	$contractId = 0;
    	$forContractId = $this->FilterToContract->getSelectedValue();
    	if (!empty($forContractId) && is_numeric($forContractId) && $forContractId > 0)
    	{
    		$contractId = $forContractId;
    	}

    	$toGroupByCodeStatusLocation = $this->DoGroupResultsByCodeStatusLocation->Checked;
    	$toGroupByCodeStatus = $this->DoGroupResultsByCodeStatus->Checked;
    	$warehouseNameSql = "(SELECT GROUP_CONCAT(w7.name ORDER BY w7.position ASC SEPARATOR '/') FROM warehouse w7 WHERE w7.active=1 AND ware.position LIKE CONCAT(w7.position,'%') AND w7.active=1)";
	    if ($toGroupByCodeStatusLocation)
	    {
	    	$columnHeaderArray = array("Qty",
										"Part Code",
				    					"Part Type",
										"Serial No",
				    					"User Contract",
				    					"Status",
				    					"Part Name",
				    					"FRU Number",
				    					"Location");


	    	$selectStatement = " SUM(pi.quantity) AS sumqty3,
					    		 pta.alias,
					    		 pt.name AS name,
					    		 if(pt.serialised=1, '', pta1.alias),
					    		 '' AS contractName,
					    		 pis.name,
					    		 pt.name,
					    		 '' as `fru`,
					    		 ware.id
					    		 ";

	    	$joinStatement = " ";
	    	$groupByStatement = " GROUP BY pt.id, ware.id, pis.id";
	    	$orderByStatement = " ORDER BY ware.position, pta.alias, pis.name";
	    }
	    else if ($toGroupByCodeStatus)
	    {
	    	$columnHeaderArray = array("Qty",
										"Part Code",
				    					"Part Type",
										"Serial No",
				    					"User Contract",
				    					"Status",
				    					"Part Name",
				    					"FRU Number");

	    	$selectStatement = "SUM(pi.quantity) AS sumqty4,
					    		pta.alias,
					    		pt.name AS name,
					    		if(pt.serialised=1, '', pta1.alias),
					    		'' AS contractName,
					    		pis.name,
					    		pt.name,
					    		(select group_concat(pta.alias) from parttypealias pta where pta.partTypeId = pt.id and pta.active = 1 and pta.partTypeAliasTypeId = 10) `fru`
					    		";
	    	$joinStatement = " ";
	    	$groupByStatement = " GROUP BY pt.id, pis.id";
	    	$orderByStatement = " ORDER BY pta.alias, pis.name";

	    }
	    else
	    {
	    	$columnHeaderArray = array("Qty",
										"Part Code",
										"Serial No",
				    					"Owner Client",
				    					"User Contract",
				    					"Status",
				    					"Group Description",
				    					"Part Name",
				    					"FRU Number",
				    					"Current Location",
				    					"Current Location Category",
				    					"Previous Location",
				    					"Last Movement Date",
				    					"Last Moved By",
				    					"Manufacturer No",
				    					"Client Asset No",
				    					"Purchase Order No",
				    					"Warranty Details",
				    					"Supplier Asset No",
								    	"Box Label",
								    	"LAST TRANSIT NOTE DESTINATION",
								    	"Current Location State",
	    								"Registed Date (UTC) ");

	    	$selectStatement = "pi.quantity,
					    		pta.alias,
					    		if(pt.serialised=1,

					    			 (	SELECT GROUP_CONCAT(DISTINCT pia.alias SEPARATOR ', ')
					    				FROM partinstancealias pia
					    				WHERE pia.partInstanceAliasTypeId = 1
					    				AND pia.partInstanceId = pi.id
					    				AND pia.active = 1),
				    				 pta1.alias),
					    		cl.clientname,
					    		GROUP_CONCAT(DISTINCT con.contractName SEPARATOR ', '),
					    		pis.name,
					    		pg.name,
					    		pt.name,
					    		(select group_concat(pta.alias) from parttypealias pta where pta.partTypeId = pt.id and pta.active = 1 and pta.partTypeAliasTypeId = 10) `fru`,
					    		ware.id,
	    						wc1.name,
					    		SUBSTRING_INDEX(GROUP_CONCAT(DISTINCT ware2.id ORDER BY lpm.created DESC), ',', 1),
					    		IF(MAX(lpm.id) IS NOT NULL, (SELECT lastlogpim.created FROM logpartinstancemove lastlogpim WHERE lastlogpim.id=MAX(lpm.id)), ''),
					    		IF(MAX(lpm.id) IS NOT NULL, (SELECT CONCAT(p.firstName, ' ', p.lastName) lastMovedBy FROM logpartinstancemove lastlogpim INNER JOIN useraccount ua ON lastlogpim.createdById=ua.id INNER JOIN person p ON ua.personId=p.id WHERE lastlogpim.id=MAX(lpm.id)), ''),
					    		pia2.alias as Manuf,
					    		pia3.alias as ClientNo,
					    		pia4.alias as PO,
					    		pia5.alias as Warranty,
					    		pia6.alias as SupplierAssetNo,
					    		pia7.alias as BoxLabel,
					    		(SELECT xxw.name FROM logpartsintransitnote xxl inner join transitnote xxt on xxt.id = xxl.transitnoteid inner join warehouse xxw on xxt.destinationId = xxw.id where xxl.partInstanceId = pi.id order by xxt.created desc limit 1) 'LAST TRANSIT NOTE DESTINATION',
  					 			st.name as State,
  					 			pi.created
					    		";

	    	$joinStatement = " left join parttype_parttypegroup ptpg on ptpg.parttypeid=pt.id
			    	           left join parttypegroup pg on ptpg.parttypegroupid=pg.id
			    	           left join partinstancealias pia2 on (pia2.partinstanceid=pi.id and pia2.partinstancealiastypeid=6 and pia2.active=1)
			    	           left join partinstancealias pia3 on (pia3.partinstanceid=pi.id and pia3.partinstancealiastypeid=8 and pia3.active=1)
			    	           left join partinstancealias pia4 on (pia4.partinstanceid=pi.id and pia4.partinstancealiastypeid=10 and pia4.active=1)
			    	           left join partinstancealias pia5 on (pia5.partinstanceid=pi.id and pia5.partinstancealiastypeid=7 and pia5.active=1)
			    	           left join partinstancealias pia6 on (pia6.partinstanceid=pi.id and pia6.partinstancealiastypeid=12 and pia6.active=1)
			    	           left join partinstancealias pia7 on (pia7.partinstanceid=pi.id and pia7.partinstancealiastypeid=9 and pia7.active=1)
			    	           left join logpartinstancemove lpm on lpm.partinstanceid=pi.id and lpm.warehouseid=pi.warehouseid
			    	           left join warehouse ware2 on lpm.fromwarehouseid=ware2.id
			    	           left join transitnote tn on lpm.fromwarehouseid = tn.transitNoteLocationId
			    	           left join warehouse ware3 on tn.sourceid=ware3.id
	    					   left join warehousecategory wc1 on wc1.id = ware.warehousecategoryid
			    	           left join client cl on (cl.id = pt.ownerclientId) ";
	    					   if(!$contractId){
			    	          		$joinStatement .= " left join contract_parttype cpt ON (cpt.partTypeId=pt.id) ";
	    					   }
			    	           $joinStatement .= " left join contract con on (con.id = cpt.contractId)
							   left join facility f on ware.facilityid=f.id
							   left join address a on f.addressid=a.id
							   left join state st on st.id=a.stateid
			    	           left join site s2 on lpm.fromsiteid=s2.id ";
	    	$groupByStatement = " GROUP BY pi.id desc";


	    }
    	$whereStatement = "";

    	$runSqlOnly = true;
    	$pageNumber = null;
    	$pageSize = null;

    	$sql = $this->getSql($selectStatement,$a, $b, $pageNumber ,$pageSize, $joinStatement, $whereStatement, $runSqlOnly, $groupByStatement, $orderByStatement);
    	$result = Dao::getResultsNative($sql);

    	//This is for output to excel, which requires all the data....
    	$totalSize = sizeof($result);

    	if($totalSize <= 0 )
    		$this->setErrorMessage("Can't Output To Excel, as There is No Data.");
    	else
    		$allData = $result;


    	if(isset($allData))
    	{
	    	$columnDataArray = array();
			foreach ($allData as $row)
			{
				$newRow = array();
				$i =0;
				foreach($row as $r)
				{
					if ($toGroupByCodeStatusLocation)
	    			{
	    				if($i==8)
							$newRow[] = $this->getFullWHPath($r);
						else
							$newRow[] = $r;
	    			}else{
						if($i==9||$i==11)
							$newRow[] = $this->getFullWHPath($r);
						else
							$newRow[] = $r;
	    			}
					$i++;
				}
				array_push($columnDataArray, $newRow);
			}
			$this->fileName = preg_replace("/\//", ".", $this->fileName);
		    $this->toExcel($this->fileName, "", "", $columnHeaderArray, $columnDataArray);
    	}
    }

    /**
     * Call Submit
     *
     * @param unknown_type $sender
     */
	public function callSubmit($sender)
    {
		$ids = explode('/',$this->whTree->whIdPath->Value);
	    $wh = Factory::service("Warehouse")->getWarehouse(end($ids));
	    if (!$wh instanceof Warehouse)
	    {
    		$this->setErrorMessage("Invalid Warehouse. Please try again.");
    		$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('mb.hide()'));
    		return;
	    }

    	if($sender->ID == "StocktakeLocationButton")
    	{
    		$defaultWarehouse = Factory::service("Warehouse")->getDefaultWarehouse(Core::getUser());
    		$errMsg = WarehouseLogic::checkValidDefaultWarehouse($defaultWarehouse, false);
    		if ($errMsg !== true)
    		{
    			$this->setErrorMessage("Unable to perform Stocktake.<br />" . $errMsg);
    			$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('mb.hide()'));
    			return;
    		}

    		//only check for filters/default warehouses etc if non bytecraft
    		if (!UserAccountService::isSystemAdmin() && Factory::service("Role")->hasFeature(Core::getRole(), "menu_logistics") == false)
    		{
    			if (WarehouseLogic::checkWarehouseWithinOrEqualToParentWarehouses(array($defaultWarehouse->getId()), $wh->getPosition()) === false)
	    		{
	    			$this->setErrorMessage("Unable to perform Stocktake.<br />Stocktake location [" . $wh->getName() . "] is NOT within [" . $defaultWarehouse->getName() . "]");
	    			$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('mb.hide()'));
	    			return;
	    		}
    		}
    		$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array("location.href = '/stocktake/stock/" . $wh->getId() . "/'"));
    		//$this->response->Redirect('/stocktake/stock/'.$wh->getId().'/');
    	}
    	else
    	{
    		$viewWhIds = FilterService::getFilterArray(FilterService::$VIEW_WAREHOUSE_FILTER_ID);
    		if (count($viewWhIds) > 0)
    		{
	    		if (WarehouseLogic::checkWarehouseWithinOrEqualToParentWarehouses($viewWhIds, $wh->getPosition()) === false)
	    		{
	    			$this->setErrorMessage("Unable to perform Stock count.<br />Warehouse [" . $wh->getName() . "] is NOT within the View Warehouse Filter(s)");
	    			$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('mb.hide()'));

	    			$this->getInitialTreePath(Factory::service("Warehouse")->getDefaultWarehouse(Core::getUser()));

	    			$this->StockPanel->Visible = false;
	    			$this->StockHeaderPanel->Visible = false;
	    			$this->ListingPanel->findControl('OutputToExcelTable')->findControl('OutputToExcelRow')->findControl('OutputToExcelCell')->findControl('OutputToExcelButton')->Visible = false;
	    			return;
	    		}
    		}
    		$this->search(null, null);
    	}
    }

    /**
     * Get Full WH Path
     *
     * @param unknown_type $id
     * @return unknown
     */
    private function getFullWHPath($id)
    {
    	$warehouse = Factory::service("Warehouse")->getWarehouse($id);
    	if($warehouse instanceof Warehouse ){
    		return Factory::service("Warehouse")->getWarehouseBreadCrumbs($warehouse,TRUE,"/");
    	}else{
    		return $id;
    	}
    }

    public function onTreeLoadError($errMsg)
    {
    	//$this->setErrorMessage($errMsg);
    	$this->jsLbl->Text = JavascriptLogic::getScriptTagWithContent(array('onTreeLoadError()'));
    }

    private function getLostStocktakeData($logStocktakeId)
    {
    	$arr = array();
    	//get parts lost
    	$sql = "SELECT ls.partInstanceId,
						pt.id as partTypeId,
						pis.id as partInstanceStatusId,
						pi.quantity ,
						pta.alias `partcode`,
						pt.name `parttype`,
						pis.name `status`,
						pt.serialised,
						GROUP_CONCAT(piaBS.alias) `bs`,
						GROUP_CONCAT(piaBX.alias) `bx`,
						GROUP_CONCAT(ptaBP.alias) `bp`
	    		 FROM logstocktakelost ls
    				INNER JOIN partinstance pi ON (pi.id = ls.partInstanceId)
	    		 	INNER JOIN parttype pt ON (pt.id = pi.partTypeId)
					INNER JOIN parttypealias pta ON (pta.partTypeId=pt.id AND pta.active=1 AND pta.partTypeAliasTypeId=" . PartTypeAliasType::ID_PARTCODE . ")
					INNER JOIN partinstancestatus pis ON pis.id = pi.partInstanceStatusId
					LEFT JOIN partinstancealias piaBS ON piaBS.partInstanceId=pi.id AND piaBS.partInstanceAliasTypeId=" . PartInstanceAliasType::ID_SERIAL_NO . " AND piaBS.active=1
					LEFT JOIN partinstancealias piaBX ON piaBX.partInstanceId=pi.id AND piaBX.partInstanceAliasTypeId=" . PartInstanceAliasType::ID_BOX_LABEL . " AND piaBX.active=1
					LEFT JOIN parttypealias ptaBP ON ptaBP.partTypeId=pt.id AND ptaBP.active=1 and ptaBP.partTypeAliasTypeId= " . PartTypeAliasType::ID_BP . "
    			WHERE ls.stocktakeLogId = $logStocktakeId
    			GROUP BY ls.partInstanceId
    			ORDER BY ls.partInstanceId";
    	$results = Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC);
    	foreach($results as $row)
    	{
    		$arr[] = $row;
    	}

    	//get parts quantity changed
    	$sql = "SELECT ls.partInstanceId,
						pt.id as partTypeId,
						pis.id as partInstanceStatusId,
						ls.changeInQuantity quantity,
						pta.alias `partcode`,
						pt.name `parttype`,
						pis.name `status`,
						pt.serialised,
						GROUP_CONCAT(piaBS.alias) `bs`,
						GROUP_CONCAT(piaBX.alias) `bx`,
						GROUP_CONCAT(ptaBP.alias) `bp`
	    		 FROM logstocktakequantitychange ls
    				INNER JOIN partinstance pi ON (pi.id = ls.partInstanceId)
	    		 	INNER JOIN parttype pt ON (pt.id = pi.partTypeId)
					INNER JOIN parttypealias pta ON (pta.partTypeId=pt.id AND pta.active=1 AND pta.partTypeAliasTypeId=" . PartTypeAliasType::ID_PARTCODE . ")
					INNER JOIN partinstancestatus pis ON pis.id = pi.partInstanceStatusId
					LEFT JOIN partinstancealias piaBS ON piaBS.partInstanceId=pi.id AND piaBS.partInstanceAliasTypeId=" . PartInstanceAliasType::ID_SERIAL_NO . " AND piaBS.active=1
					LEFT JOIN partinstancealias piaBX ON piaBX.partInstanceId=pi.id AND piaBX.partInstanceAliasTypeId=" . PartInstanceAliasType::ID_BOX_LABEL . " AND piaBX.active=1
					LEFT JOIN parttypealias ptaBP ON ptaBP.partTypeId=pt.id AND ptaBP.active=1 and ptaBP.partTypeAliasTypeId= " . PartTypeAliasType::ID_BP . "
    			WHERE ls.stocktakeLogId = $logStocktakeId
    			GROUP BY ls.partInstanceId
    			ORDER BY ls.partInstanceId";

   	    $results = Dao::getResultsNative($sql,array(),PDO::FETCH_ASSOC);
   	    foreach($results as $row)
   	    {
   	    	$row['quantity'] = -$row['quantity']; //the quantity is in the wrong sign
   	    	$arr[] = $row;
   	    }
   	    return $arr;
    }

    public function regenerateLastStockTakeEmail()
    {
    	$ids = explode('/',$this->whTree->whIdPath->Value);
    	$warehouseId = end($ids);
    	$warehouse = Factory::service("Warehouse")->get($warehouseId);

    	$logStockTakes = Factory::service("LogStocktake")->findByCriteria("warehouseid = ?", array($warehouseId), true, 1, 2, array('logstocktake.updated' => 'desc'));

    	$lastLogStocktakeId = '';
    	$lastLogStocktake = null;

    	foreach($logStockTakes as $logStocktake)
    	{
    		if($logStocktake->getActive() == 0)
    		{
    			$lastLogStocktakeId = $logStocktake->getId();
    			$lastLogStocktake = $logStocktake;
    			break;
    		}
    	}

    	if($lastLogStocktake instanceOf LogStocktake && $warehouse instanceOf Warehouse)
    	{
    		$resultsExcel = array('partsFound' => array(), 'partsLost' => array(),'partsGain' => array());
    		$results = Factory::service("StockTake")->getStocktakeData($lastLogStocktakeId);

    		$i = 1;
    		foreach ($results as $row)
    		{
    			$piId = $row['partInstanceId'];
    			if ($piId == null)
    			{
    				$piId = 'BP_' . $i;
    				$i++;
    			}
    			Factory::service("StockTake")->populateStocktakeToExcelArray($resultsExcel['partsFound'], $piId, $row['partcode'], $row['parttype'], $row['status'], $row['quantity'], Factory::service("StockTake")->getBarcodeExcelEmail($row), $row['serialised']);
    		}

    	  	Factory::service("StockTake")->genLostGain($resultsExcel, $lastLogStocktakeId);

    	  	Factory::service("StockTake")->sendResultToExcelAndSendEmail($resultsExcel, $warehouse, $lastLogStocktake);

    	  	$this->jsLbl->Text = "<script type=\"text/javascript\">sentEmail();</script>";
    	}

    }
}

?>
