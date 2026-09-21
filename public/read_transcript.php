<?php
$lines = file('C:/Users/TUONG NGOC VINH/.gemini/antigravity/brain/b7f090d0-269f-483f-b4ab-f6fadc8a6e09/.system_generated/logs/transcript_full.jsonl');
foreach($lines as $l) {
    $j = json_decode($l);
    if ($j && isset($j->content)) {
        $c = mb_strtolower($j->content);
        if (strpos($c, 'tính năng mới') !== false || strpos($c, 'nâng cấp') !== false || strpos($c, 'chức năng mới') !== false || strpos($c, 'ý tưởng') !== false) {
            echo "Step " . $j->step_index . ": " . mb_substr($j->content, 0, 500) . "\n\n";
        }
    }
}
