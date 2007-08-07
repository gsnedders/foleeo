<?php

/**
 * HTTP Response Parser
 *
 * @package HTTP
 */
class HTTP_Response_Parser
{
	/**
	 * HTTP Version
	 *
	 * @var string
	 */
	public $http_version = '';

	/**
	 * Status code
	 *
	 * @var string
	 */
	public $status_code = '';

	/**
	 * Reason phrase
	 *
	 * @var string
	 */
	public $reason = '';

	/**
	 * Key/value pairs of the headers
	 *
	 * @var array
	 */
	public $headers = array();

	/**
	 * Body of the response
	 *
	 * @var string
	 */
	public $body = '';
	
	/**
	 * Current state of the state machine
	 *
	 * @var string
	 */
	private $state = 'start';

	/**
	 * Input data
	 *
	 * @var string
	 */
	private $data = '';

	/**
	 * Input data length (to avoid calling strlen() everytime this is needed)
	 *
	 * @var int
	 */
	private $data_length = 0;

	/**
	 * Current position of the pointer
	 *
	 * @var int
	 */
	private $position = 0;

	/**
	 * Name of the hedaer currently being parsed
	 *
	 * @var string
	 */
	private $name = '';

	/**
	 * Value of the hedaer currently being parsed
	 *
	 * @var string
	 */
	private $value = '';

	/**
	 * Create an instance of the class with the input data
	 *
	 * @param string $data Input data
	 */
	public function __construct($data)
	{
		$this->data = $data;
		$this->data_length = strlen($this->data);
	}

	/**
	 * Parse the input data
	 *
	 * @return bool true on success, false on failure
	 */
	public function parse()
	{
		while ($this->state && $this->state != 'emit' && $this->has_data())
		{
			$state = $this->state;
			$this->$state();
		}
		$this->data = '';
		if ($this->state == 'emit')
		{
			return true;
		}
		else
		{
			$this->http_version = '';
			$this->status_code = '';
			$this->reason = '';
			$this->headers = array();
			$this->body = '';
			return false;
		}
	}

	/**
	 * Check whether there is data beyond the pointer
	 *
	 * @return bool true if there is further data, false if not
	 */
	private function has_data()
	{
		return (bool) ($this->position < $this->data_length);
	}

	/**
	 * See if the next character is LWS
	 *
	 * @return bool true if the next character is LWS, false if not
	 */
	private function is_linear_whitespace()
	{
		return (bool) (strspn($this->data, "\x09\x20", $this->position, 1)
			|| ($this->data[$this->position] === "\x0A" && strspn($this->data, "\x09\x20", $this->position + 1, 1)));
	}
	
	private function start()
	{
		if (strpos($this->data, "\x0A") !== false
			&& preg_match('/^HTTP\/([0-9]+\.[0-9]+)[\x09\x20]+([0-9]+)(?:[\x09\x20]+([^\x0A]*))?\x0A/i', $this->data, $match))
		{
			$this->http_version = (float) $match[1];
			$this->status_code = (int) $match[2];
			$this->reason = rtrim($match[3], "\x0D");
			$this->state = 'new_line';
			$this->position += strlen($match[0]);
		}
		else
		{
			$this->state = false;
		}
	}
	
	private function new_line()
	{
		$this->value = trim($this->value, "\x0D\x20");
		if ($this->name !== '' && $this->value !== '')
		{
			if (isset($this->headers[$this->name]))
			{
				$this->headers[$this->name] .= ', ' . $this->value;
			}
			else
			{
				$this->headers[$this->name] = $this->value;
			}
		}
		$this->name = '';
		$this->value = '';
		switch (true)
		{
			case substr($this->data[$this->position], 0, 2) === "\x0D\x0A":
				$this->position++;
			
			case $this->data[$this->position] === "\x0A":
				$this->position++;
				$this->state = 'body';
				break;
			
			default:
				$this->state = 'name';
		}
	}
	
	private function name()
	{
		$len = strcspn($this->data, "\x0A:", $this->position);
		if (isset($this->data[$this->position + $len]))
		{
			if ($this->data[$this->position + $len] === "\x0A")
			{
				$this->position += $len;
				$this->state = 'new_line';
			}
			else
			{
				$this->name = substr($this->data, $this->position, $len);
				$this->position += $len + 1;
				$this->state = 'value';
			}
		}
		else
		{
			$this->state = false;
		}
	}

	/**
	 * Parse LWS, replacing consecutive characters with a single space
	 */
	private function linear_whitespace()
	{
		do
		{
			if (substr($this->data, $this->position, 2) == "\x0D\x0A")
			{
				$this->position += 2;
			}
			elseif ($this->data[$this->position] === "\x0A")
			{
				$this->position++;
			}
			$this->position += strspn($this->data, "\x09\x20", $this->position);
		} while ($this->has_data() && $this->is_linear_whitespace());
		$this->value .= "\x20";
	}
	
	private function value()
	{
		if ($this->is_linear_whitespace())
		{
			$this->linear_whitespace();
		}
		else
		{
			switch ($this->data[$this->position])
			{
				case '"':
					$this->position++;
					$this->state = 'quote';
					break;
				
				case "\x0A":
					$this->position++;
					$this->state = 'new_line';
					break;
				
				default:
					$this->state = 'value_char';
					break;
			}
		}
	}
	
	private function value_char()
	{
		$len = strcspn($this->data, "\x09\x20\x0A\"", $this->position);
		$this->value .= substr($this->data, $this->position, $len);
		$this->position += $len;
		$this->state = 'value';
	}
	
	private function quote()
	{
		if ($this->is_linear_whitespace())
		{
			$this->linear_whitespace();
		}
		else
		{
			switch ($this->data[$this->position])
			{
				case '"':
					$this->position++;
					$this->state = 'value';
					break;
				
				case "\x0A":
					$this->position++;
					$this->state = 'new_line';
					break;
				
				case '\\':
					$this->position++;
					$this->state = 'quote_escaped';
					break;
				
				default:
					$this->state = 'quote_char';
					break;
			}
		}
	}
	
	private function quote_char()
	{
		$len = strcspn($this->data, "\x09\x20\x0A\"\\", $this->position);
		$this->value .= substr($this->data, $this->position, $len);
		$this->position += $len;
		$this->state = 'value';
	}
	
	private function quote_escaped()
	{
		$this->value .= $this->data[$this->position];
		$this->position++;
		$this->state = 'quote';
	}
	
	private function body()
	{
		$this->body = substr($this->data, $this->position);
		$this->state = 'emit';
	}
}

?>