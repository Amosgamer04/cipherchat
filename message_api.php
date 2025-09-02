<?php
header('Content-Type: application/json');
require_once 'config.php'; // Contains database connection details

class MessageHandler {
    private $conn;
    
    public function __construct() {
        $this->conn = getDBConnection();
    }
    
    // Save a new message
    public function saveMessage($data) {
        try {
            // Validate input
            if (empty($data['chat_id']) || empty($data['sender_id']) || empty($data['content'])) {
                throw new Exception('Missing required fields');
            }
            
            // Encrypt message content
            $encryptedContent = encryptData($data['content']);
            
            // Prepare metadata
            $metadata = [
                'type' => $data['message_type'] ?? 'text',
                'encrypted' => true
            ];
            
            if (isset($data['file_info'])) {
                $metadata['file_info'] = $data['file_info'];
            }
            
            // Insert message
            $stmt = $this->conn->prepare("
                INSERT INTO messages (
                    chat_id, 
                    sender_id, 
                    message_type, 
                    content, 
                    metadata, 
                    created_at
                ) VALUES (?, ?, ?, ?, ?, NOW())
            ");
            
            $messageType = $data['message_type'] ?? 'text';
            $metadataJson = json_encode($metadata);
            
            $stmt->bind_param(
                "sssss",
                $data['chat_id'],
                $data['sender_id'],
                $messageType,
                $encryptedContent,
                $metadataJson
            );
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to save message: " . $stmt->error);
            }
            
            $messageId = $stmt->insert_id;
            
            // Handle file upload if present
            if (!empty($data['file_info'])) {
                $this->saveFile($messageId, $data['file_info']);
            }
            
            return [
                'status' => 'success',
                'message_id' => $messageId
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Save file metadata
    private function saveFile($messageId, $fileInfo) {
        $stmt = $this->conn->prepare("
            INSERT INTO files (
                message_id,
                file_name,
                file_path,
                file_type,
                file_size,
                uploaded_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $stmt->bind_param(
            "isssi",
            $messageId,
            $fileInfo['name'],
            $fileInfo['path'],
            $fileInfo['type'],
            $fileInfo['size']
        );
        
        if (!$stmt->execute()) {
            throw new Exception("Failed to save file: " . $stmt->error);
        }
    }
    
    // Get messages for a chat
    public function getMessages($chatId, $lastMessageId = 0) {
        try {
            $stmt = $this->conn->prepare("
                SELECT m.*, p.user_name as sender_name 
                FROM messages m
                LEFT JOIN participants p ON m.sender_id = p.user_id
                WHERE m.chat_id = ? AND m.id > ?
                ORDER BY m.created_at ASC
            ");
            
            $stmt->bind_param("si", $chatId, $lastMessageId);
            $stmt->execute();
            $result = $stmt->get_result();
            
            $messages = [];
            while ($row = $result->fetch_assoc()) {
                // Decrypt message content
                $row['content'] = decryptData($row['content']);
                $row['metadata'] = json_decode($row['metadata'], true);
                
                // Get file info if exists
                if ($row['message_type'] !== 'text') {
                    $row['file_info'] = $this->getFileInfo($row['id']);
                }
                
                $messages[] = $row;
            }
            
            return [
                'status' => 'success',
                'messages' => $messages
            ];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
    
    // Get file information
    private function getFileInfo($messageId) {
        $stmt = $this->conn->prepare("
            SELECT * FROM files WHERE message_id = ?
        ");
        
        $stmt->bind_param("i", $messageId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        return $result->fetch_assoc();
    }
    
    // Mark message as read
    public function markAsRead($messageId) {
        try {
            $stmt = $this->conn->prepare("
                UPDATE messages SET is_read = 1 WHERE id = ?
            ");
            
            $stmt->bind_param("i", $messageId);
            
            if (!$stmt->execute()) {
                throw new Exception("Failed to mark message as read");
            }
            
            return ['status' => 'success'];
            
        } catch (Exception $e) {
            return [
                'status' => 'error',
                'message' => $e->getMessage()
            ];
        }
    }
}

// Handle incoming requests
$messageHandler = new MessageHandler();
$action = $_POST['action'] ?? '';

try {
    switch ($action) {
        case 'send_message':
            $response = $messageHandler->saveMessage([
                'chat_id' => $_POST['chat_id'],
                'sender_id' => $_POST['sender_id'],
                'content' => $_POST['content'],
                'message_type' => $_POST['message_type'] ?? 'text',
                'file_info' => $_POST['file_info'] ?? null
            ]);
            break;
            
        case 'get_messages':
            $response = $messageHandler->getMessages(
                $_POST['chat_id'],
                $_POST['last_message_id'] ?? 0
            );
            break;
            
        case 'mark_read':
            $response = $messageHandler->markAsRead($_POST['message_id']);
            break;
            
        default:
            throw new Exception('Invalid action');
    }
} catch (Exception $e) {
    $response = [
        'status' => 'error',
        'message' => $e->getMessage()
    ];
}

echo json_encode($response);
?>