<?php
class HYExcelWorkBook
{
	private $sheets = array();
	public $styles = array();
	
	private $header = '<?xml version="1.0" encoding="UTF-8"?>
<Workbook xmlns="urn:schemas-microsoft-com:office:spreadsheet" 
	xmlns:x="urn:schemas-microsoft-com:office:excel" 
	xmlns:ss="urn:schemas-microsoft-com:office:spreadsheet" 
	xmlns:html="http://www.w3.org/TR/REC-html40">';
	
	private $footer ="</Workbook>";
	
	public function __construct()
	{
		$defaultStyle = new HYExcelStyle("Default","Normal");
		$defaultStyle->setAlignment("Left","Bottom",true);
		$this->styles[] = $defaultStyle;
	}
	
	public function addWorkSheet(HYExcelWorkSheet $sheet)
	{
		$this->sheets[] = $sheet;
		//preventing duplicated HYExcelStyles
		$styleIds = array();
		foreach($sheet->styles as $style)
		{
			if($style instanceof HYExcelStyle)
				$this->setStyle($style);
		}
	}
	
	public function __toString()
	{
		$return = $this->header;
		if(count($this->styles)>0)
		{
			//preventing duplicated HYExcelStyles
			$styleIds = array();
			 	
			$return .="\t<Styles>\n";
			foreach($this->styles as $style)
			{
				if(!in_array($style->getId(),$styleIds))
				{
					$return .=$style->__toString();
					$styleIds[] = $style->getId();
				}
			}
			$return .="\t</Styles>\n";
		}
		foreach($this->sheets as $sheet)
		{
			$return .= $sheet->__toString();
		}
		$return .=$this->footer;
		return $return;
	}
	
	public function __toStringHeader()
	{
		$return = $this->header;
		if(count($this->styles)>0)
		{
			//preventing duplicated HYExcelStyles
			$styleIds = array();
			 	
			$return .="\t<Styles>\n";
			foreach($this->styles as $style)
			{
				if(!in_array($style->getId(),$styleIds))
				{
					$return .=$style->__toString();
					$styleIds[] = $style->getId();
				}
			}
			$return .= "\t</Styles>\n";
		}
		return $return;
	}
	
	public function __toStringFooter()
	{
		$return = $this->footer;
		return $return;
	}
	
	public function addStyle(HYExcelStyle $style)
	{
		$this->styles[] = $style;
	}
	
	public function getStyles()
	{
		return $this->styles;
	}
	
	public function setStyle(HYExcelStyle $style)
	{
		//preventing duplicated HYExcelStyles
		$styleIds = array();
		foreach($this->styles as $s)
		{
			$styleIds[] = $s->getId();
		}
		if(!in_array($style->getId(),$styleIds))
			$this->styles[] = $style;
	}
}

?>