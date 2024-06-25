<?php

function reqHeaders($arr, $url = null) {
	$res = array();
	$ua = false;
	foreach($arr as $k=>$v) {
		$lk = strtolower($k);
		if($lk == 'host' && isset($url)) {
			$dom = '';
			if(strpos($url, 'http://') === 0) {
				$dom = substr($url, 7);
			} else if(strpos($url, 'https://') === 0) {
				$dom = substr($url, 8);
			} else {
				$dom = $url;
			}
			$pos = strpos($dom, '/');
			if($pos) {
				$dom = substr($dom, 0, $pos);
			}
			array_push($res, 'Host: '. $dom);
		} else if($lk == 'user-agent') {
			if(strpos($v, 'CLDC-1.1 Mozilla/5.0') !== false) {
				$v = substr($v, strpos($v, 'Mozilla/5.0'));
			}
			if(strpos($v, ' UNTRUSTED/1.0') !== false) {
				$v = str_replace(' UNTRUSTED/1.0', '', $v);
			}
			array_push($res, $k . ': ' . $v);
			$ua = true;
		} else if($lk != 'connection' && $lk != 'accept-encoding' && $lk != 'user-agent' && stripos($url, 'cf-') !== 0) {
			if($v == '' && ($lk == 'content-length' || $lk == 'content-type')) continue;
			array_push($res, $k . ': ' . $v);
		}
	}
	if(!$ua) array_push($res, 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0');
	return $res;
}

function handleHeaders($str) {
	$headersTmpArray = explode("\r\n", $str);
	for ($i = 0; $i < count($headersTmpArray); ++$i) {
		$s = $headersTmpArray[$i];
		if(strlen($s) > 0) {
			if(strpos($s, ":")) {
				$k = substr($s, 0 , strpos($s, ":"));
				$v = substr($s, strpos($s, ":" )+1);
				$lk = strtolower($k);
				if(/*$lk != 'server' && */$lk != 'connection' && $lk != 'transfer-encoding' && $lk != 'location' && $lk != 'content-length') {
					header($s, true);
				}
			}
		}
	}
}
$url = urldecode($_SERVER['QUERY_STRING']);
if(stripos($url, 'file') === 0 || stripos($url, 'ftp') === 0) die;
$reqheaders = reqHeaders(getallheaders(), $url);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $reqheaders);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, true);
$res = curl_exec($ch);
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($res, 0, $headerSize);
$body = substr($res, $headerSize);
handleHeaders($header);
curl_close($ch);
echo $body;
?>
