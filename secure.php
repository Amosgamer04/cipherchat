<?php
require_once 'config.php';

class SecureChat {
    private $conn;
    
    public function __construct() {
        $this->conn = new PDO(
            "mysql:host=".DB_HOST.";dbname=".DB_NAME, 
            DB_USER, 
            DB_PASS
        );
        $this->conn->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    }

    public function saveMessage($data) {
        $this->validateInput($data, ['chat_id', 'sender_id', 'sender_name', 'content']);
        
        $stmt = $this->conn->prepare("
            INSERT INTO messages (
                chat_id, 
                sender_id,
                sender_name,
                content, 
                message_type,
                created_at
            ) VALUES (?, ?, ?, ?, ?, NOW())
        ");
        
        $messageType = $data['message_type'] ?? 'text';
        
        $stmt->execute([
            $data['chat_id'],
            $data['sender_id'],
            $data['sender_name'],
            $data['content'],
            $messageType
        ]);
        
        return [
            'status' => 'success',
            'message_id' => $this->conn->lastInsertId()
        ];
    }

    public function getMessages($data) {
        $this->validateInput($data, ['chat_id']);
        $lastId = $data['last_message_id'] ?? 0;
        
        $stmt = $this->conn->prepare("
            SELECT * FROM messages 
            WHERE chat_id = ? AND id > ?
            ORDER BY created_at ASC
            LIMIT 100
        ");
        
        $stmt->execute([$data['chat_id'], $lastId]);
        $messages = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        return [
            'status' => 'success',
            'messages' => $messages
        ];
    }

    public function joinChat($data) {
        $this->validateInput($data, ['chat_id', 'user_id', 'user_name']);
        
        // Check if chat exists
        $stmt = $this->conn->prepare("SELECT 1 FROM chat_links WHERE link_id = ?");
        $stmt->execute([$data['chat_id']]);
        if (!$stmt->fetch()) throw new Exception('Chat not found');
        
        // Add/update participant
        $stmt = $this->conn->prepare("
            INSERT INTO participants (
                chat_id,
                user_id,
                user_name,
                joined_at
            ) VALUES (?, ?, ?, NOW())
            ON DUPLICATE KEY UPDATE
                last_seen = NOW(),
                user_name = VALUES(user_name)
        ");
        
        $stmt->execute([
            $data['chat_id'],
            $data['user_id'],
            $data['user_name']
        ]);
        
        // Get participant count
        $stmt = $this->conn->prepare("
            SELECT COUNT(*) as count FROM participants WHERE chat_id = ?
        ");
        $stmt->execute([$data['chat_id']]);
        $result = $stmt->fetch();
        
        return [
            'status' => 'success',
            'participants_count' => $result['count']
        ];
    }

    public function handleFileUpload($files, $postData) {
        $this->validateInput($postData, ['chat_id', 'sender_id', 'sender_name']);
        
        if (empty($files['file'])) {
            throw new Exception('No file uploaded');
        }
        
        $file = $files['file'];
        $this->validateFile($file);
        
        $uploadDir = UPLOAD_DIR . $postData['chat_id'] . '/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = bin2hex(random_bytes(16)) . '.' . pathinfo($file['name'], PATHINFO_EXTENSION);
        $filePath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($file['tmp_name'], $filePath)) {
            throw new Exception('File upload failed');
        }
        
        // Save to messages table
        $messageData = [
            'chat_id' => $postData['chat_id'],
            'sender_id' => $postData['sender_id'],
            'sender_name' => $postData['sender_name'],
            'content' => 'File: ' . $file['name'],
            'message_type' => $this->getFileType($file['type'])
        ];
        
        $result = $this->saveMessage($messageData);
        
        // Save to files table
        $stmt = $this->conn->prepare("
            INSERT INTO files (
                message_id,
                file_name,
                file_path,
                file_type,
                file_size
            ) VALUES (?, ?, ?, ?, ?)
        ");
        
        $stmt->execute([
            $result['message_id'],
            $file['name'],
            $filePath,
            $file['type'],
            $file['size']
        ]);
        
        return [
            'status' => 'success',
            'message_id' => $result['message_id'],
            'file_url' => $filePath
        ];
    }

    public function handleVoiceUpload($files, $postData) {
        $this->validateInput($postData, ['chat_id', 'sender_id', 'sender_name']);
        
        if (empty($files['voice'])) {
            throw new Exception('No voice file uploaded');
        }
        
        $voiceFile = $files['voice'];
        $uploadDir = UPLOAD_DIR . $postData['chat_id'] . '/voices/';
        if (!file_exists($uploadDir)) {
            mkdir($uploadDir, 0755, true);
        }
        
        $fileName = bin2hex(random_bytes(16)) . '.webm';
        $filePath = $uploadDir . $fileName;
        
        if (!move_uploaded_file($voiceFile['tmp_name'], $filePath)) {
            throw new Exception('Voice upload failed');
        }
        
        // Get duration (requires ffmpeg or similar)
        $duration = $this->getAudioDuration($filePath);
        
        // Save to messages table
        $messageData = [
            'chat_id' => $postData['chat_id'],
            'sender_id' => $postData['sender_id'],
            'sender_name' => $postData['sender_name'],
            'content' => 'Voice message',
            'message_type' => 'voice'
        ];
        
        $result = $this->saveMessage($messageData);
        
        // Save to voice_messages table
        $stmt = $this->conn->prepare("
            INSERT INTO voice_messages (
                message_id,
                file_path,
                duration
            ) VALUES (?, ?, ?)
        ");
        
        $stmt->execute([
            $result['message_id'],
            $filePath,
            $duration
        ]);
        
        return [
            'status' => 'success',
            'message_id' => $result['message_id'],
            'voice_url' => $filePath,
            'duration' => $duration
        ];
    }

    private function getAudioDuration($filePath) {
        // Simple implementation - you may need ffmpeg for accurate duration
        return round(filesize($filePath) / 10000); // Rough estimate
    }

    private function validateInput($data, $requiredFields) {
        foreach ($requiredFields as $field) {
            if (empty($data[$field])) {
                throw new Exception("Missing required field: $field");
            }
        }
    }

    private function validateFile($file) {
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('File upload error: ' . $file['error']);
        }
        
        if ($file['size'] > MAX_FILE_SIZE) {
            throw new Exception('File too large (max ' . (MAX_FILE_SIZE/1024/1024) . 'MB)');
        }
        
        if (!in_array($file['type'], ALLOWED_TYPES)) {
            throw new Exception('File type not allowed');
        }
    }

    private function getFileType($mimeType) {
        if (strpos($mimeType, 'image/') === 0) return 'image';
        if (strpos($mimeType, 'audio/') === 0) return 'voice';
        return 'file';
    }
}