<?php
// Invidious videoplayback proxy rewritten on php
define("HTTP_CHUNK_SIZE", 10485760);
set_time_limit(0);

function error($code, $s=null) {
	if ($s) {
		echo $s;
	}
	http_response_code($code);
	die();
}
function array_to_url_params($array) {
	$s = "";
	foreach ($array as $k => $v) {
		$s .= $k . "=" . urlencode($v) . '&';
	}
	$s = substr($s, 0, strlen($s)-1);
	return $s;
}
function make_client() {
	$ch = curl_init();
	return $ch;
}
function client_headers($ch, $headers) {
	$curlheaders = array();
	foreach($headers as $header => $value) {
		array_push($curlheaders, "${header}: ${value}");
	}
	$ip = $_SERVER['HTTP_X_FORWARDED_FOR'] ?? $_SERVER['REMOTE_ADDR'] ?? $_SERVER['HTTP_CLIENT_IP'] ?? false;
	if($ip) {
		array_push($curlheaders, "X-Forwarded-For: ${ip}");
	}
	curl_setopt($ch, CURLOPT_HTTPHEADER, $curlheaders);
}
function client_response($ch) {
	$response = [];
	curl_setopt($ch, CURLOPT_HEADER, true);
	curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
	$exec = curl_exec($ch);
	$err = curl_errno($ch);
	if ($err && $err != 21) {
		return [ "error" => curl_error($ch) ];
	}
	$headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
	$response["status_code"] = curl_getinfo($ch, CURLINFO_RESPONSE_CODE);
	$response["body"] = substr($exec, $headerSize);
	$headers = explode("\r\n", substr($exec, 0, $headerSize));
	for ($i = 0; $i < count($headers); ++$i) {
		$s = $headers[$i];
		if(strlen($s) > 0 && strpos($s, ":") !== false) {
			$header = substr($s, 0, strpos($s, ":"));
			$value = substr($s, strpos($s, ":") + 2);
			$response["headers"][$header] = $value;
		}
	}
	return $response;
}
function client_get($ch, $host, $url, $headers) {
	curl_setopt($ch, CURLOPT_URL, "${host}${url}");
	curl_setopt($ch, CURLOPT_NOBODY, false);
	client_headers($ch, $headers);
	return client_response($ch);
}
function client_head($ch, $host, $url, $headers) {
	curl_setopt($ch, CURLOPT_URL, "${host}${url}");
	curl_setopt($ch, CURLOPT_NOBODY, true);
	client_headers($ch, $headers);
	return client_response($ch);
}
function client_close($ch) {
	curl_close($ch);
}
function parse_range($range) {
	if(!$range) {
		return [0, null];
	}
	if(strpos($range, "bytes=") === 0) {
		$range = substr($range, 6);
	}
	foreach (explode(",", $range) as $r) {
		$r = explode("-", $r);
		
		$start_range = empty($r[0]) ? 0 : intval($r[0]);
		$end_range = empty($r[1]) ? null : intval($r[1]);
		
		return [$start_range, $end_range];
	}
	
	return [0, null];
}
function proxy_file($response) {
	$enc = $response["headers"]["Content-Encoding"] ?? "";
	if (strpos($enc, "gzip") !== false) {
		echo gzencode($response["body"]);
	} else if (strpos($enc, "deflate") !== false) {
		echo gzdeflate($response["body"]);
	} else {
		echo $response["body"];
	}
}

$query = $_GET;

$fvip = $query["fvip"] ?? "3";
$mns = explode(",", $query["mn"] ?? "");

if (isset($query["region"])) {
	$region = $query["region"];
	unset($query["region"]);
}

if (isset($query["host"]) && !empty($query["host"])) {
	$host = $query["host"];
	unset($query["host"]);
} else {
	$host = array_pop($mns);
	$host = "r${fvip}---${host}.googlevideo.com";
}

# Sanity check, to avoid being used as an open proxy
if (strpos($host, ".googlevideo.com") === false) {
	error(400, "Invalid \"host\" parameter");
}

$host = "https://${host}";
$query_params = array_to_url_params($query);
$url = "/videoplayback?${query_params}";

$headers = [];
$origheaders = [];
foreach (getallheaders() as $header => $value) {
	$l = strtolower($header);
	if ($l == "accept" || $l == "accept-encoding" || $l == "cache-control" || $l == "content-length" || $l == "if-none-match" || $l == "range") {
		$headers[$header] = $value;
	}
}

# See: https://github.com/iv-org/invidious/issues/3302
$range_header = $headers["Range"] ?? null;
if ($range_header === null) {
	$range_for_head = $query["range"] ?? "0-640";
	$headers["Range"] = "bytes=${range_for_head}";
}

$client = make_client();
$error = "";
$count = 0;
while ($count < 5) {
	$count++;
	try {
		$response = client_head($client, $host, $url, $headers);
		if (isset($response["error"])) {
			if (!empty($mns)) {
				$mn = array_pop($mns);
			}
			$fvip = "3";

			$host = "https://r${fvip}---${mn}.googlevideo.com";
			//$client = make_client();
			continue;
		}
		if ($response["headers"]["Location"] ?? false) {
			$location = $response["headers"]["Location"];
			$loc = parse_url($location);
			header("Access-Control-Allow-Origin: *", true);
			$new_host = "${loc["scheme"]}://${loc["host"]}";
			if ($new_host != $host) {
				$host = $new_host;
				//client_close($client);
				//$client = make_client();
			}
			
			$url = "${loc["path"]}?${loc["query"]}&host=${loc["host"]}";
		} else {
			break;
		}
	} catch (Exception $ex) {
		error(500, $ex->getTraceAsString());
	}
}

# Remove the Range header added previously.
if ($range_header === null) {
	unset($headers["Range"]);
}

if ($response["status_code"] >= 400) {
	header("Content-Type: text/plain", true);
	error($response["status_code"]);
}

if (strpos($url, "&file=seg.ts") !== false) {
	try {
		$resp = client_get($client, $host, $url, $headers);
		foreach ($resp["headers"] as $key => $value) {
			$l = strtolower($key);
			if ($l != "access-control-allow-origin" && $l != "alt-svc" && $l != "server") {
				header($key . ": " . $value, true);
			}
		}
		
		header("Access-Control-Allow-Origin: *", true);
		
		if ($location = $resp["headers"]["Location"] ?? null) {
			header("Location: {$_SERVER["PHP_SELF"]}?" . substr($location, strpos($location, 'videoplayback?') + 14));
			http_response_code(302);
			client_close($client);
			die();
		}
		echo $resp["body"];
	} catch (Exception $ex) {
	}
} else {
	$content_length = null;
	$first_chunk = true;
	$a = parse_range($range_header);
	$range_start = $a[0];
	$range_end = $a[1];
	$chunk_start = $range_start;
	$chunk_end = $range_end;

	if (!$chunk_end || $chunk_end - $chunk_start > HTTP_CHUNK_SIZE) {
		$chunk_end = $chunk_start + HTTP_CHUNK_SIZE - 1;
	}
	# TODO: Record bytes written so we can restart after a chunk fails
	while (true) {
		if (!$range_end && $content_length) {
			$range_end = $content_length;
		}

		if ($range_end && $chunk_start > $range_end) {
			break;
		}

		if ($range_end && $chunk_end > $range_end) {
			$chunk_end = $range_end;
		}

		$headers["Range"] = "bytes=${chunk_start}-${chunk_end}";
		try {
			$resp = client_get($client, $host, $url, $headers);
			if (isset($resp["error"])) {
				client_close($client);
				$client = make_client();
			} else {
				if ($first_chunk) {
					if (!$range_header && $resp["status_code"] == 206) {
						http_response_code(200);
					} else {
						http_response_code($resp["status_code"]);
					}
					
					foreach ($resp["headers"] as $key => $value) {
						$l = strtolower($key);
						if ($l != "access-control-allow-origin" && $l != "alt-svc" && $l != "server" && $l != "content-range") {
							header("${key}: ${value}", true);
						}
					}
					
					header("Access-Control-Allow-Origin: *", true);
					
					if ($location = $resp["headers"]["Location"] ?? null) {
						$location = parse_url($location);
						$location = "{$_SERVER["PHP_SELF"]}?${location["query"]}";
						if(isset($location["host"]) && !empty($location["host"])) {
							$location .= "&host=${location["host"]}";
						}
						header("Location: ${location}");
						http_response_code(302);
						break;
					}
					
					if ($title = $query_params["title"] ?? null) {
						 # https://blog.fastmail.com/2011/06/24/download-non-english-filenames/
						$filename = urlencode($title);
						$header = "attachment; filename=\"${filename}\"; filename*=UTF-8''${filename}";
						header("Content-Disposition: ${header}", true);
					}
					
					if (strpos($resp["headers"]["Transfer-Encoding"] ?? "", "chunked") === false) {
						$a = explode("/", $resp["headers"]["Content-Range"]);
						$content_length = intval(end($a));
						if ($range_header) {
							$end = !$range_end ? $content_length - 1 : $range_end;
							header("Content-Range: bytes ${range_start}-${end}/${content_length}", true);
							$cl = $end + 1 - $range_start;
							header("Content-Length: ${cl}", true);
						} else {
							header("Content-Length: ${content_length}", true);
						}
					}
				}
				
			}
			
			proxy_file($resp);
		} catch (Exception $ex) {
		}
		$chunk_start = $chunk_end + 1;
		$chunk_end += HTTP_CHUNK_SIZE;
		$first_chunk = false;
	}
}
client_close($client);

