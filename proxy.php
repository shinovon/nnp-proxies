<?php
$url = urldecode($_SERVER['QUERY_STRING']);
if(substr($url, 0, 4) !== 'http') die;
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
if(isset($_SERVER['HTTP_USER_AGENT']))
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_exec($ch);
curl_close($ch);
?>
