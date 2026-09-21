<?php
$lines = file('C:/Users/TUONG NGOC VINH/.gemini/antigravity/brain/b7f090d0-269f-483f-b4ab-f6fadc8a6e09/.system_generated/logs/transcript_full.jsonl');
foreach($lines as $l) {
    $j = json_decode($l);
    if ($j && $j->step_index == 399) {
        echo $j->content . "\n";
    }
}
