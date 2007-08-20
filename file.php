<pre>
<?php

function callable_htmlspecialchars($string)
{
	return htmlspecialchars($string);
}
ob_start('callable_htmlspecialchars');

function show_CRLF($string)
{
	return str_replace("\\r\r\\n\n", "\\r\\n\r\n", str_replace(array("\r", "\n"), array("\\r\r", "\\n\n"), $string));
}
//ob_start('show_CRLF');

$url = 'http://diveintomark.org:80/tests/client/autodiscovery/';

$url_parts = parse_url($url);
$fp = fsockopen($url_parts['host'], $url_parts['port'], $errno, $errstr);
if (!$fp)
{
	// bleh
}
else
{
	$get = (isset($url_parts['query'])) ? "$url_parts[path]?$url_parts[query]" : $url_parts['path'];
	$out = "GET $get HTTP/1.1\r\n";
	$out .= "Host: $url_parts[host]\r\n";
	$out .= "Content-Length: 0\r\n";
	$out .= "Connection: Keep-Alive\r\n";
	print_r($out);
	fwrite($fp, $out . "\r\n");
	
	$data = '';
	$info = stream_get_meta_data($fp);
	while (!$info['eof'] && !$info['timed_out'])
	{
		$data .= fread($fp, 1160);
		$info = stream_get_meta_data($fp);
	}
	print_r(split_headers($data));
	die;
	
	$out = "GET /tests/client/autodiscovery/html4-001.html HTTP/1.1\r\n";
	$out .= "Host: $url_parts[host]\r\n";
	$out .= "Connection: close\r\n\r\n";
	fwrite($fp, $out);
	
	$data = fread($fp, 1160);
	$info = stream_get_meta_data($fp);
	while (!$info['eof'] && !$info['timed_out'])
	{
		$data .= fgets($fp, 1160);
		$info = stream_get_meta_data($fp);
	}
	print_r(split_headers($data));
	
	fclose($fp);
}

function split_headers(&$data)
{
	$headers = explode("\r\n\r\n", $data, 2);
	$headers = $headers;
	return $headers;
}