<?php

abstract class Base_Parse_Date
{
	private $date;
	
	public function __construct($date)
	{
		$this->date = $date;
	}
	
	public function parse()
	{
		$methods = array_diff(get_class_methods($this), get_class_methods(__CLASS__));
		
		foreach ($call_methods as $method)
		{
			if (($returned = call_user_func(array(&$this, $method))) !== false)
			{
				return $returned;
			}
		}
		
		return false;
	}
}

?>