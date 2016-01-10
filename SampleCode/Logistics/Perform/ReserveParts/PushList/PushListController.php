<?php
/**
 * Facility Request Push List Controller Page
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class PushListController extends HydraPage
{
	/**
	 * constructor
	 */
	public function __construct()
	{
		parent::__construct();

		$this->roleLocks = "pages_all,page_facilityRequestPushList"; 
	}
	
	/**
	 * (non-PHPdoc)
	 * @see TPage::onPreInit()
	 */
	public function onPreInit($param)
	{
		parent::onPreInit($param);
	}
	
	/**
	 * (non-PHPdoc)
	 * @see TControl::onInit()
	 */
	public function onInit($param)
	{
		parent::onInit($param);
		$scriptManager = $this->getPage()->getClientScript();
	
		$scriptManager->registerStyleSheetFile('extCss', '/common/ext-4.1.1/resources/css/ext-all-scoped.css');
		$scriptManager->registerHeadScriptFile('extJs', '/common/ext-4.1.1/ext-all.js');
	}
	
	/**
	 * (non-PHPdoc)
	 * @see HydraPage::onLoad()
	 */
	public function onLoad($param)
	{
		parent::onLoad($param);
	}
	
	/**
	 * To init the list
	 *
	 * @param TCallback $sender The sender of the call back
	 * @param Mixed     $param  The parameters sent along with this event
	 */
	public function initPushList($sender, $param)
	{
		$result = $errors = array();		
		try 
		{
			if (isset($this->Request["warehouseId"]) && trim($this->Request["warehouseId"]) > 0)
			{
				$fromWh = Factory::service("Warehouse")->getWarehouse(trim($this->Request["warehouseId"]));
				if (!$fromWh instanceof Warehouse)
				{
					throw new Exception("Invalid From Warehouse: " . trim($this->Request["warehouseId"]));
				}
			}
			else
			{
				throw new Exception("Missing Warehouse from Request");
			}
				
			$availPushList = $currPushList = $currentWhIds = array();
			
			$res = Factory::service("WarehouseRelationship")->findByCriteria('fromWarehouseId=? AND type=?', array($fromWh->getId(), WarehouseRelationship::TYPE_FRLIST));
			foreach ($res as $whRel)
			{
				$wh = $whRel->getToWarehouse();
				$currentWhIds[] = $wh->getId();
				
				$checked = '';
				if ($whRel->getSendEmail())
				{
					$checked = 'checked';
				}
				$chk = '<input type="checkbox" value="' . $wh->getId() . '" ' . $checked . ' class="chk" />';
				
				$bread = Factory::service("Warehouse")->getWarehouseBreadcrumbs($wh, true, '/');
				$currPushList[] = array('id' => $wh->getId(), 
										'email' => $chk,
										'bread' => ltrim($bread, 'Bytrecraft/')); //remove 'Bytecraft/' from the start for real-estate
			}
			
			$canPushToList = WarehouseLogic::getFacilityRequestPushListWarehouses();
			foreach ($canPushToList as $push)
			{
				if (!in_array($push[0], $currentWhIds))
				{
					$bread = Factory::service("Warehouse")->getWarehouseBreadcrumbs(Factory::service("Warehouse")->getWarehouse($push[0]), true, '/');
					$availPushList[] = array('id' => $push[0], 
											 'email' => '<input type="checkbox" value="' . $push[0] . '" class="chk" />',
											 'bread' => ltrim($bread, 'Bytrecraft/')); //remove 'Bytecraft/' from the start for real-estate
				}
			}
			
			$fromWhBread = Factory::service("Warehouse")->getWarehouseBreadcrumbs($fromWh, true, '/');
			$this->headerLbl->Text .= ' for ' . $fromWhBread;
			
			$result['push'] = array('fromWhBread' => $fromWhBread, 
									'availPushList'=> $availPushList, 
									'currPushList' => $currPushList);				
		} 
		catch (Exception $ex) 
		{
			$errors[] = $ex->getMessage();
		}
		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}

	/**
	 * To save the push list
	 *
	 * @param TCallback $sender The sender of the call back
	 * @param Mixed     $param  The parameters sent along with this event
	 */
	public function savePushList($sender, $param)
	{
		$result = $errors = array();
		try
		{
			if (isset($this->Request["warehouseId"]) && trim($this->Request["warehouseId"]) > 0)
			{
				$fromWh = Factory::service("Warehouse")->getWarehouse(trim($this->Request["warehouseId"]));
				if (!$fromWh instanceof Warehouse)
				{
					throw new Exception("Invalid From Warehouse: " . trim($this->Request["warehouseId"]));
				}
			}
			else
			{
				throw new Exception("Missing Warehouse from Request");
			}
			
			$params = json_decode($param->CallbackParameter->params, true);
			
			//first delete all the old ones
			Factory::service("WarehouseRelationship")->deleteWhRelationship($fromWh, null);
			
			//now save the new ones
			foreach ($params['whRel'] as $whRel)
			{
				$toWh = Factory::service("Warehouse")->getWarehouse($whRel['id']);
				Factory::service("WarehouseRelationship")->createWhRelationship($fromWh, $toWh, WarehouseRelationship::TYPE_FRLIST, $whRel['email']);
			}
		}
		catch (Exception $ex)
		{
			$errors[] = $ex->getMessage();
		}
		$param->ResponseData = Core::getJSONResponse($result, $errors);
	}
}


?>