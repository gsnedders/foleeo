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
	
	private function text_or_binary()
	{
		if (substr($this->file->body, 0, 2) === "\xFE\xFF"
			|| substr($this->file->body, 0, 2) === "\xFF\xFE"
			|| substr($this->file->body, 0, 4) === "\x00\x00\xFE\xFF"
			|| substr($this->file->body, 0, 3) === "\xEF\xBB\xBF")
		{
			return 'text/plain';
		}
		elseif (preg_match('/[\x00-\x08\x0E-\x1A\x1C-\x1F]/', $this->file->body))
		{
			return 'application/octect-stream';
		}
		else
		{
			return 'text/plain';
		}
	}
	
	private function unknown()
	{
		if (strtolower(substr($this->file->body, 0, 14)) === '<!doctype html'
			|| strtolower(substr($this->file->body, 0, 5)) === '<html'
			|| strtolower(substr($this->file->body, 0, 7)) === '<script')
		{
			return 'text/html';
		}
		elseif (substr($this->file->body, 0, 5) === '%PDF-')
		{
			return 'application/pdf';
		}
		elseif (substr($this->file->body, 0, 11) === '%!PS-Adobe-')
		{
			return 'application/postscript';
		}
		elseif (substr($this->file->body, 0, 6) === 'GIF87a'
			|| substr($this->file->body, 0, 6) === 'GIF89a')
		{
			return 'image/gif';
		}
		elseif (substr($this->file->body, 0, 8) === "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A")
		{
			return 'image/png';
		}
		elseif (substr($this->file->body, 0, 3) === "\xFF\xD8\xFF")
		{
			return 'image/jpeg';
		}
		elseif (substr($this->file->body, 0, 2) === "\x42\x4D")
		{
			return 'image/bmp';
		}
		else
		{
			return $this->text_or_binary();
		}
	}
	
	private function image()
	{
		if (substr($this->file->body, 0, 6) === 'GIF87a'
			|| substr($this->file->body, 0, 6) === 'GIF89a')
		{
			return 'image/gif';
		}
		elseif (substr($this->file->body, 0, 8) === "\x89\x50\x4E\x47\x0D\x0A\x1A\x0A")
		{
			return 'image/png';
		}
		elseif (substr($this->file->body, 0, 3) === "\xFF\xD8\xFF")
		{
			return 'image/jpeg';
		}
		elseif (substr($this->file->body, 0, 2) === "\x42\x4D")
		{
			return 'image/bmp';
		}
		else
		{
			return false;
		}
	}
	
	private function feed_or_html()
	{
		$len = strlen($this->file->body);
		$pos = strspn($this->file->body, "\x09\x0A\x0D\x20");
		
		while ($pos < $len)
		{
			switch ($this->file->body[$pos])
			{
				case "\x09":
				case "\x0A":
				case "\x0D":
				case "\x20":
					$pos += strspn($this->file->body, "\x09\x0A\x0D\x20", $pos);
					continue 2;
				
				case '<':
					$pos++;
					break;
				
				default:
					return 'text/html';
			}
			
			if (substr($this->file->body, 0, 3) === '!--')
			{
				$pos += 3;
				if ($pos < $len && ($pos = strpos($this->file->body, '-->', $pos)) !== false)
				{
					$pos += 3;
				}
				else
				{
					return 'text/html';
				}
			}
			elseif (isset(
}