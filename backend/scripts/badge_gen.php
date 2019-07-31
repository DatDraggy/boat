<?php
$texts = array(
  [
    1,
    'Hunter'
  ],
  [
    2,
    'Kieran'
  ],
  [
    4,
    'Trust'
  ]
);
$texts = array(
  [
    182,
    'Longname Goes Here'
  ]
);

foreach ($texts as $text) {
  $im = imagecreatefrompng("source/crew_front.png");
  $white = ImageColorAllocate($im, 255, 255, 255);
  $black = ImageColorAllocate($im, 0, 0, 0);

  ImageTTFText($im, 80, 0, 165, 2010, $white, '../../webfonts/TideSans-700Mondo.ttf', $text[1]);
  ImageTTFText($im, 100, 0, 150, 2222, $white, '../../webfonts/TideSans-700Mondo.ttf', '#' . $text[0]);

  ImagePng($im, "images/{$text[0]}.png");
}