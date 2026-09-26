<?php
$file = 'g:/xampp/htdocs/tntt/ylcqukhi_glyphutrung_updated.sql';
$content = file_get_contents($file);

// Fix Roles
$content = str_replace(
    "('du_bi','Dß╗▒ Bß╗ï',1,'','Hß╗ù trß╗ú tß║íi lß╗øp ─æã░ß╗úc ph├ón c├┤ng')",
    "('du_bi','Dự Bị',1,'','Hỗ trợ tại lớp được phân công')",
    $content
);

// Fix Titles
$content = str_replace(
    ",(62,'du_bi','Dß╗▒ Bß╗ï',1)",
    "",
    $content
);

file_put_contents($file, $content);
echo "Fixed Mojibake in SQL file.";
