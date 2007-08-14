<?php

abstract class Base_Parse_Date
{
	protected $date;
	
	protected $day = array(
		'mon' => 1,
		'monday' => 1,
		'tue' => 2,
		'tuesday' => 2,
		'wed' => 3,
		'wednesday' => 3,
		'thu' => 4,
		'thursday' => 4,
		'fri' => 5,
		'friday' => 5,
		'sat' => 6,
		'saturday' => 6,
		'sun' => 7,
		'sunday' => 7,
	);
	
	protected $month = array(
		'jan' => 1,
		'january' => 1,
		'feb' => 2,
		'february' => 2,
		'mar' => 3,
		'march' => 3,
		'apr' => 4,
		'april' => 4,
		'may' => 5,
		// No long form of May
		'jun' => 6,
		'june' => 6,
		'jul' => 7,
		'july' => 7,
		'aug' => 8,
		'august' => 8,
		'sep' => 9,
		'september' => 8,
		'oct' => 10,
		'october' => 10,
		'nov' => 11,
		'november' => 11,
		'dec' => 12,
		'december' => 12,
	);
	
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
	protected function asctime()
	{
		static $pcre;
		if (!$pcre)
		{
			$space = '[\x09\x20]+';
			$wday_name = '(' . implode(array_keys($this->day), '|') . ')';
			$mon_name = '(' . implode(array_keys($this->month), '|') . ')';
			$day = '([0-2][0-9]|3[01])';
			$hour = '([01][0-9]|2[0-3])';
			$min = '([0-5][0-9])';
			$sec = $min;
			$year = '([0-9]{4})';
			$pcre = '/^' . $wday_name . $space . $mon_name . $space . $day . $space . $hour . ':' . $min . ':' . $sec . $space . $year . '$/i';
		}
		if (preg_match($pcre, $this->date, $match))
		{
			$month = $this->month[strtolower($match[2])];
			return gmmktime($match[4], $match[5], $match[6], $month, $match[3], $match[7]);
		}
		else
		{
			return false;
		}
	}
}

$parser = new Parse_Date('Sun Sep 16 01:03:52 1973');
var_dump($parser->parse());

?>