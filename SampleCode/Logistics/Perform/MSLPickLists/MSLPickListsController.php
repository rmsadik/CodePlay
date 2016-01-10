<?php
ini_set("memory_limit", "150M");
ini_set("max_execution_time", 600);

/**
 * MSL PickList Controller
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 * 
 */
class MSLPickListsController extends HydraPage
{
	/**
	 * @var mslReport
	 */
	private $mslReport;

	/**
	 * @var breadcrumbs
	 */
	private $breadcrumbs;

	/**
	 * @var menuContext
	 */
	public $menuContext;
	
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		Dao::prepareNewConnection(Dao::DB_REPORT_SERVER); //reconnect to report server, if redirection is ON
		$this->roleLocks = "pages_all, page_MSLPickLists";
		$this->menuContext = 'generatemslpicklist';
		$this->mslReport = new MSLReport();
		$this->breadcrumbs = array();
	}

	/**
	 * On Initialization
	 *
	 * @param unknown_type $param
	 */
	public function onInit($param)
	{
		$debugFlag = new TDropDownList();
		$debugFlag->setID("debugFlag");
		$debugFlag->DataSource = array(array("Debug - No","No"),array("Debug - Yes","Yes")); 
        $debugFlag->dataBind(); 
		$this->MainContent->Controls[] = $debugFlag;
	}
	
	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
    public function onLoad($param)
    { 
        parent::onLoad($param);
    	$this->populateFields();
    }

    /**
     * Populate Fields
     *
     */
    public function populateFields()
    {
    	
    }
    
    /**
     * Run Picking Report
     *
     */
    public function runPickingReport()
    {
    	$debug = ($this->MainContent->findControl("debugFlag")->getSelectedValue() == 'No' ? false : true);
    	$bread = explode('/', $this->warehouseid->Value);
    	
    	$emailTo = trim($this->emailTo->Text);
    	if ($emailTo != '')
    	{
	    	$mslPickListReport = new MSLPickListReport();
	    	try
	    	{
	    		$success = $mslPickListReport->runPickingReport(end($bread), $emailTo, $debug);
	    		if ($success)
	    		{
	    			$this->jsLbl->Text = '<script type="text/javascript">alert("Please check the email address (' . $emailTo . ') for the pick lists. They may take up to a minute to arrive.");</script>';
	    		}
	    		else
	    		{
	    			$this->jsLbl->Text = '<script type="text/javascript">alert("There are no pick lists to generate for the selected Supplying Warehouse");</script>';
	    		}
	    	}
	    	catch (Exception $e)
	    	{
    			$this->jsLbl->Text = '<script type="text/javascript">alert("An ERROR has occured, please try again...\n\n' . $e->getMessage() . '");</script>';
	    	}
    	}
    	return;
    }
}

?>