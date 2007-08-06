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
			|| (substr($this->data, $this->position, 2) == "\r\n" && strspn($this->data, "\x09\x20", $this->position + 2, 1))
			|| (strspn($this->data, "\r\n", $this->position, 1) && strspn($this->data, "\x09\x20", $this->position + 1, 1)));
	}

	/**
	 * The starting state of the state machine, see if the data is a response or request
	 */
	private function start()
	{
		$this->state = 'http_version_response';
	}

	/**
	 * Parse an HTTP-version string
	 *
	 * @todo Replace PCRE
	 */
	private function http_version()
	{
		if (preg_match('/^HTTP\/([0-9]+\.[0-9]+)/i', substr($this->data, $this->position, strcspn($this->data, "\r\n", $this->position)), $match))
		{
			$this->position += strlen($match[0]);
			$this->http_version = $match[1];
			return true;
		}
		else
		{
			return false;
		}
	}

	/**
	 * Parse LWS, replacing consecutive characters with a single space
	 */
	private function linear_whitespace()
	{
		do
		{
			if (substr($this->data, $this->position, 2) == "\r\n")
			{
				$this->position += 2;
			}
			elseif (strspn($this->data, "\r\n", $this->position, 1))
			{
				$this->position++;
			}
			$this->position += strspn($this->data, "\x09\x20", $this->position);
		} while ($this->is_linear_whitespace());
		$this->value .= "\x20";
	}

	/**
	 * Parse an HTTP-version string within a response
	 */
	private function http_version_response()
	{
		if ($this->http_version() && $this->data[$this->position] == "\x20")
		{
			$this->state = 'status_code';
			$this->position++;
		}
		else
		{
			$this->state = false;
		}
	}

	/**
	 * Parse a status code
	 */
	private function status_code()
	{
		if (strspn($this->data, '1234567890', $this->position, 3) == 3)
		{
			$this->status_code = substr($this->data, $this->position, 3);
			$this->state = 'reason_phrase';
			$this->position += 3;
		}
		else
		{
			$this->state = false;
		}
	}

	/**
	 * Skip over the reason phrase (it has no normative value, and you can send absolutely anything here)
	 */
	private function reason_phrase()
	{
		$len = strcspn($this->data, "\r\n", $this->position);
		$this->reason = substr($this->data, $this->position, $len);
		$this->position += $len;
		if ($this->has_data())
		{
			if (substr($this->data, $this->position, 2) == "\r\n")
			{
				$this->position += 2;
			}
			elseif (strspn($this->data, "\r\n", $this->position, 1))
			{
				$this->position++;
			}
			$this->state = 'name';
		}
	}

	/**
	 * Parse a header name
	 */
	private function name()
	{
		$len = strcspn($this->data, ':', $this->position);
		$this->name = substr($this->data, $this->position, $len);
		$this->position += $len;

		if ($this->has_data() && $this->data[$this->position] == ':')
		{
			$this->state = 'value_next';
			$this->position++;
		}
		else
		{
			$this->state = false;
		}
	}

	/**
	 * See what state to move the state machine to while within non-quoted header values
	 */
	private function value_next()
	{
		if ($this->is_linear_whitespace())
		{
			$this->state = 'value_linear_whitespace';
		}
		elseif ($this->data[$this->position] == '"')
		{
			$this->state = 'value_quote_next';
			$this->position++;
		}
		elseif (substr($this->data, $this->position, 2) == "\r\n")
		{
			$this->state = 'end_crlf';
			$this->position += 2;
		}
		elseif (strspn($this->data, "\r\n", $this->position, 1))
		{
			$this->state = 'end_crlf';
			$this->position++;
		}
		else
		{
			$this->state = 'value_no_quote';
		}
	}

	/**
	 * Parse a header value while outside quotes
	 */
	private function value_no_quote()
	{
		$len = strcspn($this->data, "\x09\x20\r\n\"", $this->position);
		$this->value .= substr($this->data, $this->position, $len);
		$this->state = 'value_next';
		$this->position += $len;
	}

	/**
	 * Parse LWS outside quotes
	 */
	private function value_linear_whitespace()
	{
		$this->linear_whitespace();
		$this->state = 'value_next';
	}

	/**
	 * See what state to move the state machine to while within quoted header values
	 */
	private function value_quote_next()
	{
		if ($this->is_linear_whitespace())
		{
			$this->state = 'value_linear_whitespace_quote';
		}
		else
		{
			switch ($this->data[$this->position])
			{
				case '"':
					$this->state = 'value_next';
					$this->position++;
					break;

				case '\\':
					$this->state = 'value_quote_char';
					$this->position++;
					break;

				default:
					$this->state = 'value_quote';
					break;
			}
		}
	}

	/**
	 * Parse a header value while within quotes
	 */
	private function value_quote()
	{
		$len = strcspn($this->data, "\x09\x20\r\n\"\\", $this->position);
		$this->value .= substr($this->data, $this->position, $len);
		$this->position += $len;
		$this->state = 'value_quote_next';
	}

	/**
	 * Parse an escaped character within quotes
	 */
	private function value_quote_char()
	{
		$this->value .= $this->data[$this->position];
		$this->state = 'value_quote_next';
		$this->position++;
	}

	/**
	 * Parse LWS within quotes
	 */
	private function value_linear_whitespace_quote()
	{
		$this->linear_whitespace();
		$this->state = 'value_quote_next';
	}

	/**
	 * Parse a CRLF, and see whether we have a further header, or whether we are followed by the body
	 */
	private function end_crlf()
	{
		$this->name = strtolower($this->name);
		$this->value = trim($this->value, "\x20");
		if (isset($this->headers[$this->name]))
		{
			$this->headers[$this->name] .= ', ' . $this->value;
		}
		else
		{
			$this->headers[$this->name] = $this->value;
		}

		if (substr($this->data, $this->position, 2) == "\r\n")
		{
			$this->body = substr($this->data, $this->position + 2);
			$this->state = 'emit';
		}
		elseif (strspn($this->data, "\r\n", $this->position, 1))
		{
			$this->body = substr($this->data, $this->position + 1);
			$this->state = 'emit';
		}
		else
		{
			$this->name = '';
			$this->value = '';
			$this->state = 'name';
		}
	}
}

?>