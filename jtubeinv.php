<?php
$s = isset($_GET['s']);

$ins = 'http://s60tube.io.vn/';
if ($s) {
	echo $ins;
} else {
	echo json_encode(['result' => 'success', 'url' => $ins, 'cached' => false], JSON_UNESCAPED_SLASHES);
}
die;

set_time_limit(20);
$j = json_decode(file_get_contents('./invapi.json'), true);
$check = "api/v1/videos/";
$cur = $_GET['current'] ?? null;

if (file_exists('./invapicache') && time() - filemtime('./invapicache') < 24 * 60 * 60 && !isset($_GET['f'])) {
	$ins = file_get_contents('./invapicache');
	if ($ins != $cur) {
		if ($s) {
			echo $ins;
		} else {
			echo json_encode(['result' => 'success', 'url' => $ins, 'cached' => true], JSON_UNESCAPED_SLASHES);
		}
		die;
	}
}
foreach($j['instances'] as $ins) {
	try {
		if ($ins == $cur) continue;
		$r = file_get_contents($ins.'api/v1/trending?fields=videoId,error');
		if (!$r) continue;
		$r = json_decode($r, true);
		if (!$r || isset($r['error'])) continue;
		$video = $r[rand(0,5)]['videoId'];
		$r = file_get_contents($ins.'api/v1/videos/'.$video);
		if (!$r) continue;
		$r = json_decode($r, true);
		if (!$r || isset($r['error']) || !isset($r['formatStreams'])) continue;
		if ($s) {
			echo $ins;
		} else {
			echo json_encode(['result' => 'success', 'url' => $ins, 'cached' => false], JSON_UNESCAPED_SLASHES);
		}
		file_put_contents('./invapicache', $ins);
		die;
	} catch (Exception $e) {}
}
if ($s) {
	echo 'FAILED';
	die;
}
echo '{"result":"error"}';
