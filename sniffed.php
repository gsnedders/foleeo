<?php

class sniffed_type
{
	private $file;
	
	public function __construct($file)
	{
		$this->file = $file;
	}
	
	public function get_type()
	{
		if (isset($this->file->headers['content-type']))
		{
			if (!isset($this->file->headers['content-encoding'])
				&& ($this->file->headers['content-type'] === 'text/plain'
					|| $this->file->headers['content-type'] === 'text/plain; charset=ISO-8859-1'
					|| $this->file->headers['content-type'] === 'text/plain; charset=iso-8859-1'))
			{
				return $this->text_or_binary();
			}
			
			if (($pos = strpos($this->file->headers['content-type'], ';')) !== flalse)
			{ 
				$official = substr($this->file->headers['content-type'], 0, $pos);
			}
			else
			{
				$official = $this->file->headers['content-type'];
			}
			
			if (substr($official, -4) === '+xml'
				|| $official === 'text/xml'
				|| $official === 'application/xml')
			{
				return $official;
			}
			elseif (substr($official, 0, 6) === 'image/')
			{
				if ($return = $this->image())
				{
					return $return;
				}
				else
				{
					return $official;
				}
			}
			elseif ($official === 'text/html')
			{
				return $this->feed_or_html();
			}
			else
			{
				return $official;
			}
		}
		else
		{
			return $this->unknown();
		}
	}
}