<?php
function resize($image, $w, $h) {
	$w = (int) $w;
	$h = (int) $h;
	$oldw = imagesx($image);
	$oldh = imagesy($image);
	$temp = imagecreatetruecolor($w, $h);
	imagecopyresampled($temp, $image, 0, 0, 0, 0, $w, $h, $oldw, $oldh);
	return $temp;
}
function reqHeaders($arr, $url = null, $ua = null) {
	$res = array();
	if ($ua != null) {
		array_push($res, 'User-Agent: ' . $ua);
	} else $ua = false;
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
			if ($ua) continue;
			if(strpos($v, 'CLDC-1.1 Mozilla/5.0') !== false) {
				$v = substr($v, strpos($v, 'Mozilla/5.0'));
			}
			if(strpos($v, ' UNTRUSTED/1.0') !== false) {
				$v = str_replace(' UNTRUSTED/1.0', '', $v);
			}
			array_push($res, $k . ': ' . $v);
			$ua = true;
		} else if($lk != 'connection' && $lk != 'accept-encoding' && stripos($url, 'cf-') !== 0 && $lk != 'x-forwarded-for') {
			if($v == '' && ($lk == 'content-length' || $lk == 'content-type')) continue;
			array_push($res, $k . ': ' . $v);
		}
	}
	if(!$ua) array_push($res, 'User-Agent: Mozilla/5.0 (Windows NT 10.0; Win64; x64; rv:109.0) Gecko/20100101 Firefox/115.0');
	array_push($res, 'X-Forwarded-For: ' . ($_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR']));
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
				if($lk != 'connection' && $lk != 'transfer-encoding' && $lk != 'location' && $lk != 'content-length') {
					header($s, true);
				}
			}
		}
	}
}
$url = urldecode($_SERVER['QUERY_STRING']);
if(stripos($url, ':') !== false && stripos($url, 'http') !== 0) die;
if (strpos($url, '//') == 0) $url = 'https:' . $url;
$method = $_SERVER['REQUEST_METHOD'];
$post = $method == 'POST';
$in = $post ? $in = file_get_contents('php://input') : null;
$tw = 0; $th = 0; $png = false; $jpg = false; $rotate = false;
$i = strpos($url, ';');
$ua = null;
if ($i !== false) {
	$s = explode(';', substr($url, $i+1));
	foreach($s as $a) {
		$b = explode('=', $a);
		switch ($b[0]) {
		case 'tw': $tw = (int) $b[1];
			break;
		case 'th': $th = (int) $b[1];
			break;
		case 'method': $method = $b[1];
			break;
		case 'png': $png = true;
			break;
		case 'jpg': $jpg = true;
			break;
		case 'r': $rotate = true;
			break;
		case 'cache':
			header('Cache-Control: private, max-age=2592000');
			break;
		case 'ua': $ua = $b[1];
			break;
		}
	}
	$url = substr($url, 0, $i);
}

$reqheaders = reqHeaders(getallheaders(), $url, $ua);
$ch = curl_init();
curl_setopt($ch, CURLOPT_URL, $url);
curl_setopt($ch, CURLOPT_HTTPHEADER, $reqheaders);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, 1);
curl_setopt($ch, CURLOPT_HEADER, true);
curl_setopt($ch, CURLOPT_PROTOCOLS, CURLPROTO_HTTP | CURLPROTO_HTTPS);

if ($method)
	curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);

if($post) {
	curl_setopt($ch, CURLOPT_POST, 1);
	curl_setopt($ch, CURLOPT_POSTFIELDS, $in);
}
$res = curl_exec($ch);
http_response_code(curl_getinfo($ch, CURLINFO_HTTP_CODE));
$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
$header = substr($res, 0, $headerSize);
$body = substr($res, $headerSize);
handleHeaders($header);
curl_close($ch);
$i = strlen($url)-4;
if($png || strrpos($url, '.png') == $i) {
	if ($tw > 0 || $th > 0) {
		$img = imagecreatefromstring($body);
		if (!$img) {
			http_response_code(500);
			die;
		}
		if ($rotate) {
			$img = imagerotate($img, 270, 0);
		}
		$ow = imagesx($img); $oh = imagesy($img);
		$h = $th;
		$w = ($ow / $oh) * $h;
		if($h == 0 || ($w > $tw && $tw > 0)) {
			$w = $tw;
			$h = ($oh / $ow) * $w;
		}
		$t = resize($img, $w, $h);
		imagedestroy($img);
		imagepng($t);
		imagedestroy($t);
		die;
	} else if($png) {
		$img = imagecreatefromstring($body);
		imagepng($img);
		imagedestroy($img);
		die;
	}
} else if ($jpg || strrpos($url, '.jpg') == $i || strrpos($url, 'webm') == $i || strrpos($url, 'webp') == $i) {
	if ($tw > 0 || $th > 0) {
		$img = imagecreatefromstring($body); 
		if (!$img) {
			http_response_code(500);
			die;
		}
		if ($rotate) {
			$img = imagerotate($img, 270, 0);
		}
		$ow = imagesx($img); $oh = imagesy($img);
		$h = $th;
		$w = ($ow / $oh) * $h;
		if ($h == 0 || ($w > $tw && $tw > 0)) {
			$w = $tw;
			$h = ($oh / $ow) * $w;
		}
		$t = resize($img, $w, $h);
		imagedestroy($img);
		imagejpeg($t, null, 90);
		imagedestroy($t);
		die;
	} else if($jpg || strrpos($url, 'webm') == $i || strrpos($url, 'webp') == $i) {
		$img = imagecreatefromstring($body);
		if (!$img) {
			http_response_code(500);
			die;
		}
		imagejpeg($img, null, 90);
		imagedestroy($img);
		die;
	}
}
echo $body;
?>
