<?php

$fp = fsockopen('geoffers.uni.cc', 80);
stream_set_timeout($fp, 0, 250000);
fwrite($fp, "HEAD / HTTP/1.1\r\nHost: geoffers.uni.cc\r\n\r\n");
$info = stream_get_meta_data($fp);
while (!feof($fp) && !$info['timed_out'])
{
	$info = stream_get_meta_data($fp);
	echo fread($fp, 1160);
}
fwrite($fp, "HEAD /habari HTTP/1.1\r\nHost: geoffers.uni.cc\r\nConnection: close\r\n\r\n");
while (!feof($fp))
{
	echo fgets($fp, 1160);
}
fclose($fp);
