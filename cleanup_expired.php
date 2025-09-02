<?php
require 'config.php';

$db = new PDO("mysql:host=".DB_HOST.";dbname=".DB_NAME, DB_USER, DB_PASS);

// Delete expired chats using UTC (server time)
$db->query("
    DELETE FROM chat_links WHERE 
    (expiration = '1h' AND created_at < UTC_TIMESTAMP() - INTERVAL 1 HOUR) OR
    (expiration = '24h' AND created_at < UTC_TIMESTAMP() - INTERVAL 1 DAY) OR
    (expiration = '7d' AND created_at < UTC_TIMESTAMP() - INTERVAL 7 DAY) OR
    (expiration = '30d' AND created_at < UTC_TIMESTAMP() - INTERVAL 30 DAY)
");

// Clean orphaned files
$stmt = $db->query("SELECT file_path FROM files WHERE message_id NOT IN (SELECT id FROM messages)");
$orphaned = $stmt->fetchAll(PDO::FETCH_COLUMN);

foreach($orphaned as $file) {
    if(file_exists($file)) {
        unlink($file);
    }
}

echo "Expired chats cleaned successfully";
?>