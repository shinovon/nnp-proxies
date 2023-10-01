<?php
ini_set('error_reporting', E_ALL);
ini_set('display_errors', 1);
ini_set('display_startup_errors', 1);
$url = $_GET['u'];
if(substr($url, 0, 4) !== 'http') die;
if(strpos($url, 'https://iteroni.com') === 0) {
	$url = 'http://iteroni.com'.substr($url, strlen('https://iteroni.com'));
}
$in = null;
$post = false;
$arr = getallheaders();
if(isset($_GET['post'])) {
	$post = true;
	$in = file_get_contents('php://input');
}
$h = array();
array_push($h, 'X-Forwarded-For: ' . $_SERVER['REMOTE_ADDR']);
foreach($arr as $k=>$v) {
	$lk = strtolower($k);
	if($lk == 'host' || $lk == 'connection' || $lk == 'accept-encoding' || $lk == 'accept-encoding')
		continue;
	array_push($h, $k . ': ' . $v);
}
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $h);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, true);
if($post) {
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $in);
}
$res = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$h2 = substr($res, 0, $headerSize);
$body = substr($res, $headerSize);
$headersTmpArray = explode("\r\n", $h2);
for ($i = 0; $i < count($headersTmpArray); ++$i) {
	$s = $headersTmpArray[$i];
	if(strlen($s) > 0) {
		if(strpos($s, ':')) {
			$k = substr($s, 0, strpos($s, ':'));
			$v = substr($s, strpos($s, ':') + 1);
			$lk = strtolower($k);
			if($lk == 'server' || $lk == 'connection' || $lk == 'transfer-encoding')
				continue;
			header($s);
		}
	}
}
curl_close($ch);
echo $body;
?>
