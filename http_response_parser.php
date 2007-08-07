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
	 * @var float
	 */
	public $http_version = 0.0;

	/**
	 * Status code
	 *
	 * @var int
	 */
	public $status_code = 0;

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
	private $state = 'http_version';

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
		while ($this->state && $this->state !== 'emit' && $this->has_data())
		{
			$state = $this->state;
			$this->$state();
		}
		$this->data = '';
		if ($this->state === 'emit')
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
		return (bool) ($this->data[$this->position] === "\x09"
			|| $this->data[$this->position] === "\x20"
			|| ($this->data[$this->position] === "\x0A"
				&& isset($this->data[$this->position + 1])
				&& ($this->data[$this->position + 1] === "\x09" || $this->data[$this->position + 1] === "\x20")));
	}
	
	/**
	 * Parse the HTTP version
	 */
	private function http_version()
	{
		if (strpos($this->data, "\x0A") !== false && stripos($this->data, 'HTTP/') === 0)
		{
			$len = strspn($this->data, '0123456789.', 5);
			$this->http_version = substr($this->data, 5, $len);
			$this->position += 5 + $len;
			if (substr_count($this->http_version, '.') <= 1)
			{
				$this->http_version = (float) $this->http_version;
				$this->position += strspn($this->data, "\x09\x20", $this->position);
				$this->state = 'status';
			}
			else
			{
				$this->state = false;
			}
		}
		else
		{
			$this->state = false;
		}
	}
	
	/**
	 * Parse the status code
	 */
	private function status()
	{
		if ($len = strspn($this->data, '0123456789', $this->position))
		{
			$this->status_code = (int) substr($this->data, $this->position, $len);
			$this->position += $len;
			$this->state = 'reason';
		}
		else
		{
			$this->state = false;
		}
	}
	
	/**
	 * Parse the reason phrase
	 */
	private function reason()
	{
		$len = strcspn($this->data, "\x0A", $this->position);
		$this->reason = trim(substr($this->data, $this->position, $len), "\x09\x0D\x20");
		$this->position += $len + 1;
		$this->state = 'new_line';
	}
	
	/**
	 * Deal with a new line, shifting data around as needed
	 */
	private function new_line()
	{
		$this->value = trim($this->value, "\x0D\x20");
		if ($this->name !== '' && $this->value !== '')
		{
			$this->name = strtolower($this->name);
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
		if (substr($this->data[$this->position], 0, 2) === "\x0D\x0A")
		{
			$this->position += 2;
			$this->state = 'body';
		}
		elseif ($this->data[$this->position] === "\x0A")
		{
			$this->position++;
			$this->state = 'body';
		}
		else
		{
			$this->state = 'name';
		}
	}
	
	/**
	 * Parse a header name
	 */
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
	 * Parse LWS, replacing consecutive LWS characters with a single space
	 */
	private function linear_whitespace()
	{
		do
		{
			if (substr($this->data, $this->position, 2) === "\x0D\x0A")
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
	
	/**
	 * See what state to move to while within non-quoted header values
	 */
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
	
	/**
	 * Parse a header value while outside quotes
	 */
	private function value_char()
	{
		$len = strcspn($this->data, "\x09\x20\x0A\"", $this->position);
		$this->value .= substr($this->data, $this->position, $len);
		$this->position += $len;
		$this->state = 'value';
	}
	
	/**
	 * See what state to move to while within quoted header values
	 */
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
	
	/**
	 * Parse a header value while within quotes
	 */
	private function quote_char()
	{
		$len = strcspn($this->data, "\x09\x20\x0A\"\\", $this->position);
		$this->value .= substr($this->data, $this->position, $len);
		$this->position += $len;
		$this->state = 'value';
	}
	
	/**
	 * Parse an escaped character within quotes
	 */
	private function quote_escaped()
	{
		$this->value .= $this->data[$this->position];
		$this->position++;
		$this->state = 'quote';
	}
	
	/**
	 * Parse the body
	 */
	private function body()
	{
		$this->body = substr($this->data, $this->position);
		$this->state = 'emit';
	}
}

?>