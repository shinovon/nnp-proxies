<?php
$url = trim(urldecode($_SERVER['QUERY_STRING']));
{
	if (substr($url, 0, 4) !== 'http')
		die;
	$parsed = parse_url($url);
	if (!$parsed || !isset($parsed['host']))
		die;
	$ip = gethostbyname($parsed['host']);
	if (!filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE))
		die;
}
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
if(isset($_SERVER['HTTP_USER_AGENT']))
	curl_setopt($ch, CURLOPT_USERAGENT, $_SERVER['HTTP_USER_AGENT']);
curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);
curl_exec($ch);
curl_close($ch);
?>