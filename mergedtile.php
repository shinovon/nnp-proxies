<?php
$lang = $_GET["lang"];
$x = $_GET["x"];
$y = $_GET["y"];
$z = $_GET["z"];
$sat = imagecreatefromstring(file_get_contents("https://core-sat.maps.yandex.net/tiles?l=sat&lang=${lang}&x=${x}&y=${y}&z=${z}"));
$skl = imagecreatefromstring(file_get_contents("https://core-renderer-tiles.maps.yandex.net/tiles?l=skl&lang=${lang}&x=${x}&y=${y}&z=${z}"));
imagecopy($sat, $skl, 0, 0, 0, 0, 256, 256);
header('Content-Type: image/jpeg');
imagejpeg($sat);