<?php
try {
    $pdo = new PDO('mysql:host=localhost', 'root', '');
    echo "MySQL Connected!\n";
    echo "Databases: " . implode(', ', $pdo->query('SHOW DATABASES')->fetchAll(PDO::FETCH_COLUMN));
} catch (Exception $e) {
    echo "MySQL Error: " . $e->getMessage();
}
