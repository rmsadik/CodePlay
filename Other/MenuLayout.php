<?php

class MenuLayout extends TTemplateControl
{
	public function __construct()
	{
		parent::__construct();


	}

	protected function pageLock($page_names)
	{
		if($page_names == "")
		{
			$result=true;
			return $result;
		}

		if(strpos($page_names,',') !== false)
			$page_names = explode(',',$page_names);
		else
			$page_names = array($page_names);

		$role = Core::getRole();
		if($role == null)
			$this->redirectHome();

		return Session::checkRoleFeatures($page_names);

	}
}

?>