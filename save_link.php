<?php
header('Content-Type: application/json');
require_once 'config.php';

try {
    $data = json_decode(file_get_contents('php://input'), true);
    
    // Hash password if provided
    $password = !empty($data['password']) ? password_hash($data['password'], PASSWORD_DEFAULT) : null;
    
    $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);
    
    $stmt = $conn->prepare("
        INSERT INTO chat_links (
            link_id, 
            expiration, 
            max_participants, 
            has_password, 
            password, 
            self_destruct, 
            file_sharing,
            created_at
        ) VALUES (?, ?, ?, ?, ?, ?, ?, UTC_TIMESTAMP())");
    
    $stmt->bind_param(
        "sssisii",
        $data['link_id'],
        $data['expiration'],
        $data['max_participants'],
        $data['has_password'],
        $password,
        $data['self_destruct'],
        $data['file_sharing']
    );
    
    if (!$stmt->execute()) {
        throw new Exception("Failed to save link: " . $stmt->error);
    }
    
    $response = [
        'status' => 'success',
        'message' => 'Link saved successfully'
    ];
    
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
?>