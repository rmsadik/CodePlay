<?php
/**
 * Barcode checking Controller
 * 
 * @package	Hydra-Web
 * @subpackage	Controller-Page
 * @version	1.0
 * 
 */
class BarcodeCheckingController extends HydraPage 
{
	/**
	 * Constructor
	 *
	 */
	public function __construct()
	{
		parent::__construct();
		$this->roleLocks = "pages_all,page_barcodechecking";
	}
	
	/**
	 * On Load
	 *
	 * @param unknown_type $param
	 */
	public function onLoad($param)
    {
        parent::onLoad($param);
        if(!$this->IsPostBack && !$this->IsCallBack)
        {
        }
	}
	
	/**
	 * check BarCode
	 *
	 * @param unknown_type $sender
	 * @param unknown_type $param
	 */
	public function checkBarcode($sender,$param)
	{
		$this->resultPanel->Text="";
		try
		{
			$barcode = strtoupper(trim($this->barcode->Text));
			$errorMsg="";
			
			if(strlen($barcode)!=11)
				$errorMsg="barcode should be <b>ONLY 11</b> characters long! '$barcode' has <b>".strlen($barcode)."</b> characters!";
			else if(preg_match("/^[BCP|BCS](\d){8}$/",$barcode))
				$errorMsg="";
			else
			{
				$barcode_woCheck = substr($barcode,0,strlen($barcode)-1);
				$barcode_checkDigit = substr($barcode,strlen($barcode)-1);
				$checkDigitGenerator = new BarcodeCheckDigit();
				$checkDigitChar = $checkDigitGenerator->GenerateCheckCharacter($barcode_woCheck);
				if($barcode_checkDigit!=$checkDigitChar)	
					$errorMsg="the Check Sum should be '<b>$checkDigitChar</b>'(".$barcode_woCheck.$checkDigitChar."), but your input is '<b>$barcode_checkDigit</b>'(".$barcode_woCheck.$barcode_checkDigit.")!";
			}
			
			$html ="<div>";
				$html .="<fieldset style='padding:15px; margin: 10px;'><legend>Your input: <b>$barcode</b></legend>";
					$html .="<div>This barcode is <b style='font-size: 16px; color:".($errorMsg=="" ? "green;'>valid" : "red;'>invalid")."</b>!</div>";
					if($errorMsg!="")
						$html .="<div><b>Error:</b> $errorMsg</div>";
						
				$html .="</fieldset>";
			$html .="</div>";
			
			$this->resultPanel->Text=$html;
		}
		catch(Exception $ex)
		{
			$this->resultPanel->Text="<div style='font-weight:bold; color:red; padding:15px; margin: 15px; '>".$ex->getMessage()."</div>";
		}
	}
}
?>