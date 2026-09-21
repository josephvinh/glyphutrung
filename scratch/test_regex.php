<?php
$str = '<span x-show="!showPw" class="contents"><i data-lucide="eye" class="w-4 h-4"></i></span>';
$newStr = preg_replace('/<span([^>]*x-show="[^"]*"[^>]*)class="contents"([^>]*)>/', '<span$1class="inline-flex items-center justify-center"$2>', $str);
echo $newStr;
