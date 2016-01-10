<?php
/**
 * Tranist Note Search Page 
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 */
class FindTaskController extends HydraPage 
{
	/**
	 * @var JobNumberObsfucation
	 */
	private $fieldTaskNumberConveter;

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
		$this->menuContext = 'findtask';
		$this->roleLocks = "pages_all,pages_logistics,page_logistics_findTask";
		$this->fieldTaskNumberConveter = new JobNumberObsfucation();
	}

	/**
	 * OnLoad
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
        parent::onLoad($param);
	}
	
	/**
	 * On Init
	 *
	 * @param unknown_type $param
	 */
	public function onInit($param)
	{
		$javascriptLabel = new TActiveLabel();
		$javascriptLabel->setID("javascriptLabel");
		$this->MainContent->Controls[] =$javascriptLabel;
	}
	
	/**
	 * Find Task
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function findTask($sender,$param)
	{
		try
		{	
			$fieldTask = Factory::service("FieldTask")->getFieldTaskByClientFieldTaskNumber($this->TaskNumber->Text);
			if(count($fieldTask) < 1)
			{
				$temp = Factory::service("FieldTask")->getFieldTask($this->fieldTaskNumberConveter->decode($this->TaskNumber->Text));
				$fieldTask[0]=$temp;
			}
			
			if($fieldTask[0] != Null)
			{
				$fieldTaskNumber = $this->fieldTaskNumberConveter->encode($fieldTask[0]->getId());
				$this->MainContent->findControl("javascriptLabel")->Text = "<script type=\"text/javascript\">window.open('/viewfieldtasks/edit/".$fieldTaskNumber."');</script>";
			}
			else
				$this->setErrorMessage('No FieldTask as per task# ');
		}
		catch(Exception $e)
		{
			$this->setErrorMessage('No field task with that number');
		}
	}
}
?>