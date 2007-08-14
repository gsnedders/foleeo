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
			if (stripos($method, 'date_') === 0 && ($returned = call_user_func(array(&$this, $method))) !== false)
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
	protected function date_asctime()
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
	
	private function remove_rfc2822_comments($string)
	{
		$string = (string) $string;
		$position = 0;
		$length = strlen($string);
		$depth = 0;
		
		$output = '';
		
		while ($position < $length && ($pos = strpos($string, '(', $position)) !== false)
		{
			$output .= substr($string, $position, $pos - $position);
			$position = $pos + 1;
			if ($string[$pos - 1] !== '\\')
			{
				$depth++;
				while ($depth && $position < $length)
				{
					$position += strcspn($string, '()', $position);
					if ($string[$position - 1] === '\\')
					{
						$position++;
						continue;
					}
					elseif (isset($string[$position]))
					{
						switch ($string[$position])
						{
							case '(':
								$depth++;
								break;
							
							case ')':
								$depth--;
								break;
						}
						$position++;
					}
					else
					{
						break;
					}
				}
			}
			else
			{
				$output .= '(';
			}
		}
		$output .= substr($string, $position);
		
		return $output;
	}
	
	protected function date_rfc2822()
	{
		static $pcre;
		if (!$pcre)
		{
			$wsp = '[\x09\x20]';
			$fws = '(?:' . $wsp . '+|' . $wsp . '*(?:\x0D\x0A' . $wsp . '+)+)';
			$optional_fws = $fws . '?';
			$day_name = '(' . implode(array_keys($this->day), '|') . ')';
			$month = '(' . implode(array_keys($this->month), '|') . ')';
			$day = '([0-9]{1,2})';
			$hour = $minute = $second = '([0-9]{2})';
			$year = '([0-9]{2,4})';
			$num_zone = '([+\-])([0-9]{2})([0-9]{2})';
			$character_zone = '([A-Z]{1,5})';
			$zone = '(' . $num_zone . '|' . $character_zone . ')';
			$pcre = '/(?:' . $optional_fws . $day_name . $optional_fws . ',)?' . $optional_fws . $day . $fws . $month . $fws . $year . $fws . $hour . $optional_fws . ':' . $optional_fws . $minute . '(?:' . $optional_fws . ':' . $optional_fws . $second . ')?' . $fws . $zone . '/i';
		}
		if (preg_match($pcre, $this->remove_rfc2822_comments($this->date), $match))
		{
			static $timezones = array('EST' => -18000, 'EDT' => -14400, 'CST' => -21600, 'CDT' => -18000, 'MST' => -25200, 'MDT' => -21600, 'PST' => -28800, 'PDT' => -25200);
			$month = $this->month[strtolower($match[3])];
			
			// Numeric timezone
			if ($match[9] !== '')
			{
				$timezone = $match[10] * 3600;
				$timezone += $match[11] * 60;
				if ($match[9] === '-')
				{
					$timezone = 0 - $timezone;
				}
			}
			// Character timezone
			elseif (isset($timezones[strtoupper($match[12])]))
			{
				$timezone = $timezones[strtoupper($match[12])];
			}
			// Assume everything else to be -0000
			else
			{
				$timezone = 0;
			}
			
			return gmmktime($match[5], $match[6], $match[7], $month, $match[2], $match[4]) - $timezone;
		}
		else
		{
			return false;
		}
	}
}

?>