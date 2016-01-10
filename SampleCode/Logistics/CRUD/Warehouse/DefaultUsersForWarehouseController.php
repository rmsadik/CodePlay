<?php
/**
 * Default Users For Warehouse Controller
 *
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 */
class DefaultUsersForWarehouseController extends CRUDPage
{
	/**
	 * @var warehouseId
	 */
	private $warehouseId;

	/**
	 * @var totalUsers
	 */
	public $totalUsers;

	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->menuContext="logistics_storageLocationAliasType";
		$this->roleLocks = "pages_all,pages_logistics";
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
       	parent::onLoad($param);
       	$this->warehouseId = $this->Request['id'];
       	$wh = Factory::service("Warehouse")->getWarehouse($this->warehouseId);

       	$dropdown = array("55" => "Default Warehouse",
       					  "69" => "Default Workshop",
       					  "70" => "Default Mobile Warehouse",
       					  "79" => "Options for Default Warehouse",
       					  "80" => "Options for Default Workshop");

       	$totalCount = 0;
       	foreach ($dropdown as $key => $value)
       	{
       		$r = Factory::service("Warehouse")->getAllUsersWithDefaultOption($this->warehouseId, $key);
			$r = $this->_getWarehousePreferenceCount($this->warehouseId, $key);
    		$dropdown[$key] = $dropdown[$key] . ' (' . count($r) . ')';

    		$totalCount += count($r);
       	}
       	$this->warehouseLabel->Text = ' ' . Factory::service("Warehouse")->getWarehouseBreadCrumbs($wh, true, ' / ') . '<br /><br />Total: ' . $totalCount;

       	$this->optionList->DataSource = $dropdown;
     	$this->optionList->dataBind();

     	if(!$this->IsPostBack)
        {
	     	$this->optionList->SelectedIndex = 0;
        }

		$this->AddPanel->Visible = false;
		$this->DataList->EditItemIndex = -1;
		$this->dataLoad();
    }

    private function _getWarehousePreferenceCount($whId, $luupId)
    {
    	//count of default warehouse users
    	$sql = "SELECT ua.id, p.id, CONCAT(p.firstname,' ',p.lastname)
		    	FROM useraccount ua
		    	INNER JOIN userpreference up ON ua.id=up.useraccountid AND up.active=1 AND up.lu_userpreferenceid=$luupId AND (up.value='$whId' OR up.value LIKE '$whId,%' OR up.value LIKE '%,$whId,%' OR up.value LIKE '%,$whId')
		    	INNER JOIN person p ON p.id=ua.personid AND p.active=1
		    	WHERE ua.active=1";
    	return Dao::getResultsNative($sql);
    }

    /**
     * Get all of Entity
     *
     * @param unknown_type $focusObject
     * @param unknown_type $pageNumber
     * @param unknown_type $pageSize
     * @return unknown
     */
    protected function getAllOfEntity(&$focusObject = null,$pageNumber=null,$pageSize=null)
    {
    	$uaIds = array();
    	$res = $this->_getWarehousePreferenceCount($this->warehouseId, $this->optionList->Text);
    	if (empty($res))
    		return array();

    	$data = array();
    	$wh = Factory::service("Warehouse")->get($this->warehouseId);
    	if (!$wh instanceof Warehouse)
    	{
    		$this->setErrorMessage('Invalid Warehouse: ' . $this->warehouseId);
    		return array();
    	}

    	foreach ($res as $r)
    		$data[] = array($r[1], $r[2], $wh->getName());

    	$this->totalUsers = count($data);

    	return $data;
    }

    /**
     * Redirect To Personal page
     *
     * @param unknown_type $sender
     * @param unknown_type $param
     */
	public function redirectToPersonnelPage($sender, $param)
    {
   		$this->response->Redirect("/useraccountrole/person/{$sender->getText()}/");
    }
}

?>