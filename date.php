<?php

abstract class Base_Parse_Date
{
	protected $date;
	
	public function __construct($date)
	{
		$this->date = $date;
	}
	
	public function parse()
	{
		if (extension_loaded('Reflection'))
		{
			$subclass_methods = $this->get_all_methods();
		}
		else
		{
			$subclass_methods = get_class_methods($this);
		}
		
		$methods = array_diff($subclass_methods, get_class_methods(__CLASS__));
		
		foreach ($methods as $method)
		{
			if (($returned = call_user_func(array(&$this, $method))) !== false)
			{
				return $returned;
			}
		}
		
		return false;
	}
	
	final private function get_all_methods()
	{
		$class = new ReflectionClass(get_class($this));
		$methods = $class->getMethods();
		$return = array();
		foreach ($methods as $method)
		{
			$return[] = $method->getName();
		}
		return $return;
	}
}

class Parse_Date extends Base_Parse_Date
{
	private $short_mon = array('Jan', 'Feb', 'Mar', 'Apr', 'May', 'Jun', 'Jul', 'Aug', 'Sep', 'Oct', 'Nov', 'Dec');
	
	protected function asctime()
	{
		static $pcre;
		if (!$pcre)
		{
			$space = '[\x09\x20]+';
			$wday_name = '(Mon|Tue|Wed|Thu|Fri|Sat|Sun)';
			$mon_name = '(' . implode($this->short_mon, '|') . ')';
			$day = '([0-2][0-9]|3[01])';
			$hour = '([01][0-9]|2[0-3])';
			$min = '([0-5][0-9])';
			$sec = $min;
			$year = '([0-9]{4})';
			$pcre = '/^' . $wday_name . $space . $mon_name . $space . $day . $space . $hour . ':' . $min . ':' . $sec . $space . $year . '$/i';
		}
		if (preg_match($pcre, $this->date, $match))
		{
			$mon = array_search($match[2], $this->short_mon) + 1;
			return gmmktime($match[4], $match[5], $match[6], $mon, $match[3], $match[7]);
		}
		else
		{
			return false;
		}
	}
}

?>