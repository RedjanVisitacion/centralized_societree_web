<?php
/**
 * SITE Chat Engine Machine
 * Provides intelligent chat features including:
 * - Auto-responses
 * - Message analysis
 * - Keyword detection
 * - Automated notifications
 * - Message filtering
 * - Chat statistics
 */

require_once(__DIR__ . '/../db_connection.php');

class ChatEngine {
    private $conn;
    private $pdo;
    
    // Auto-response patterns
    private $autoResponses = [
        'greeting' => [
            'patterns' => ['hello', 'hi', 'hey', 'good morning', 'good afternoon', 'good evening'],
            'responses' => [
                'Hello! Welcome to SITE chat. How can I help you today?',
                'Hi there! I\'m the SITE chat assistant. What can I do for you?',
                'Hey! Great to see you in the SITE community chat!'
            ]
        ],
        'help' => [
            'patterns' => ['help', 'assist', 'support', 'how to', 'what is', 'explain'],
            'responses' => [
                'I\'m here to help! You can ask me about SITE events, announcements, or general information.',
                'Need assistance? I can help with information about our organization and activities.',
                'How can I assist you today? Feel free to ask about SITE services or events!'
            ]
        ],
        'events' => [
            'patterns' => ['event', 'activity', 'meeting', 'workshop', 'seminar', 'conference'],
            'responses' => [
                'Check out our Events page for upcoming SITE activities and workshops!',
                'We have exciting events planned! Visit the Events section for more details.',
                'Stay updated with our latest events and activities in the Events module.'
            ]
        ],
        'services' => [
            'patterns' => ['service', 'offer', 'provide', 'available', 'facility'],
            'responses' => [
                'SITE offers various services to students. Check our Services page for details!',
                'We provide multiple services for IT students. Visit the Services section to learn more.',
                'Our organization offers great services for technology enthusiasts!'
            ]
        ],
        'thanks' => [
            'patterns' => ['thank', 'thanks', 'appreciate', 'grateful'],
            'responses' => [
                'You\'re welcome! Happy to help!',
                'Glad I could assist you!',
                'Anytime! Feel free to reach out if you need more help.'
            ]
        ]
    ];
    
    // Inappropriate content filters
    private $contentFilters = [
        'spam' => ['spam', 'advertisement', 'buy now', 'click here', 'free money'],
        'inappropriate' => ['offensive', 'harassment', 'bullying'],
        'academic_misconduct' => ['cheat', 'copy homework', 'exam answers', 'plagiarize']
    ];
    
    public function __construct() {
        global $conn, $pdo;
        $this->conn = $conn;
        $this->pdo = $pdo;
        $this->initializeTables();
    }
    
    private function initializeTables() {
        // Create chat engine tables
        $tables = [
            "CREATE TABLE IF NOT EXISTS chat_auto_responses (
                id INT AUTO_INCREMENT PRIMARY KEY,
                trigger_pattern VARCHAR(255) NOT NULL,
                response_text TEXT NOT NULL,
                category VARCHAR(100) NOT NULL,
                is_active TINYINT(1) DEFAULT 1,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            
            "CREATE TABLE IF NOT EXISTS chat_message_analysis (
                id INT AUTO_INCREMENT PRIMARY KEY,
                message_id INT NOT NULL,
                sentiment_score DECIMAL(3,2) DEFAULT 0.00,
                keywords JSON,
                category VARCHAR(100),
                flagged TINYINT(1) DEFAULT 0,
                flag_reason VARCHAR(255),
                analyzed_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (message_id) REFERENCES site_chat(id) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            
            "CREATE TABLE IF NOT EXISTS chat_statistics (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                messages_sent INT DEFAULT 0,
                messages_received INT DEFAULT 0,
                last_active TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                total_words INT DEFAULT 0,
                avg_response_time INT DEFAULT 0,
                UNIQUE KEY unique_student (student_id),
                FOREIGN KEY (student_id) REFERENCES student(id_number) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci",
            
            "CREATE TABLE IF NOT EXISTS chat_notifications (
                id INT AUTO_INCREMENT PRIMARY KEY,
                student_id INT NOT NULL,
                message TEXT NOT NULL,
                type VARCHAR(50) DEFAULT 'info',
                is_read TINYINT(1) DEFAULT 0,
                created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
                FOREIGN KEY (student_id) REFERENCES student(id_number) ON DELETE CASCADE
            ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_general_ci"
        ];
        
        foreach ($tables as $sql) {
            $this->conn->query($sql);
        }
        
        $this->seedAutoResponses();
    }
    
    private function seedAutoResponses() {
        // Check if auto responses already exist
        $result = $this->conn->query("SELECT COUNT(*) as count FROM chat_auto_responses");
        $row = $result->fetch_assoc();
        
        if ($row['count'] == 0) {
            // Insert default auto responses
            foreach ($this->autoResponses as $category => $data) {
                foreach ($data['patterns'] as $pattern) {
                    $response = $data['responses'][array_rand($data['responses'])];
                    $stmt = $this->conn->prepare("INSERT INTO chat_auto_responses (trigger_pattern, response_text, category) VALUES (?, ?, ?)");
                    $stmt->bind_param('sss', $pattern, $response, $category);
                    $stmt->execute();
                    $stmt->close();
                }
            }
        }
    }
    
    public function processMessage($messageId, $studentId, $message, $isAdmin = false) {
        // Analyze message
        $analysis = $this->analyzeMessage($message);
        
        // Store analysis
        $this->storeMessageAnalysis($messageId, $analysis);
        
        // Update statistics
        $this->updateChatStatistics($studentId, 'sent');
        
        // Check for auto-response triggers (only for non-admin messages)
        if (!$isAdmin) {
            $autoResponse = $this->checkAutoResponse($message);
            if ($autoResponse) {
                $this->sendAutoResponse($studentId, $autoResponse);
            }
        }
        
        // Check for content violations
        $violation = $this->checkContentViolation($message);
        if ($violation) {
            $this->handleContentViolation($messageId, $studentId, $violation);
        }
        
        return $analysis;
    }
    
    private function analyzeMessage($message) {
        $analysis = [
            'sentiment_score' => $this->calculateSentiment($message),
            'keywords' => $this->extractKeywords($message),
            'category' => $this->categorizeMessage($message),
            'word_count' => str_word_count($message),
            'flagged' => false,
            'flag_reason' => null
        ];
        
        return $analysis;
    }
    
    private function calculateSentiment($message) {
        // Simple sentiment analysis
        $positiveWords = ['good', 'great', 'excellent', 'awesome', 'love', 'like', 'happy', 'thanks', 'helpful'];
        $negativeWords = ['bad', 'terrible', 'hate', 'dislike', 'angry', 'frustrated', 'problem', 'issue', 'wrong'];
        
        $message = strtolower($message);
        $positiveCount = 0;
        $negativeCount = 0;
        
        foreach ($positiveWords as $word) {
            $positiveCount += substr_count($message, $word);
        }
        
        foreach ($negativeWords as $word) {
            $negativeCount += substr_count($message, $word);
        }
        
        $totalWords = str_word_count($message);
        if ($totalWords == 0) return 0.0;
        
        $score = ($positiveCount - $negativeCount) / $totalWords;
        return max(-1.0, min(1.0, $score)); // Normalize between -1 and 1
    }
    
    private function extractKeywords($message) {
        // Extract important keywords
        $commonWords = ['the', 'a', 'an', 'and', 'or', 'but', 'in', 'on', 'at', 'to', 'for', 'of', 'with', 'by', 'is', 'are', 'was', 'were', 'be', 'been', 'have', 'has', 'had', 'do', 'does', 'did', 'will', 'would', 'could', 'should', 'may', 'might', 'can', 'this', 'that', 'these', 'those'];
        
        $words = str_word_count(strtolower($message), 1);
        $keywords = array_filter($words, function($word) use ($commonWords) {
            return strlen($word) > 3 && !in_array($word, $commonWords);
        });
        
        return array_values(array_unique($keywords));
    }
    
    private function categorizeMessage($message) {
        $message = strtolower($message);
        
        foreach ($this->autoResponses as $category => $data) {
            foreach ($data['patterns'] as $pattern) {
                if (strpos($message, $pattern) !== false) {
                    return $category;
                }
            }
        }
        
        return 'general';
    }
    
    private function storeMessageAnalysis($messageId, $analysis) {
        $stmt = $this->conn->prepare("INSERT INTO chat_message_analysis (message_id, sentiment_score, keywords, category, flagged, flag_reason) VALUES (?, ?, ?, ?, ?, ?)");
        $keywordsJson = json_encode($analysis['keywords']);
        $stmt->bind_param('idssss', $messageId, $analysis['sentiment_score'], $keywordsJson, $analysis['category'], $analysis['flagged'], $analysis['flag_reason']);
        $stmt->execute();
        $stmt->close();
    }
    
    private function updateChatStatistics($studentId, $type) {
        // Insert or update statistics
        $stmt = $this->conn->prepare("INSERT INTO chat_statistics (student_id, messages_sent, messages_received, last_active) VALUES (?, ?, ?, NOW()) ON DUPLICATE KEY UPDATE messages_sent = messages_sent + ?, messages_received = messages_received + ?, last_active = NOW()");
        
        $sent = ($type === 'sent') ? 1 : 0;
        $received = ($type === 'received') ? 1 : 0;
        
        $stmt->bind_param('iiiii', $studentId, $sent, $received, $sent, $received);
        $stmt->execute();
        $stmt->close();
    }
    
    private function checkAutoResponse($message) {
        $message = strtolower($message);
        
        // Check database for custom auto responses
        $stmt = $this->conn->prepare("SELECT response_text FROM chat_auto_responses WHERE is_active = 1 AND ? LIKE CONCAT('%', trigger_pattern, '%') ORDER BY RAND() LIMIT 1");
        $stmt->bind_param('s', $message);
        $stmt->execute();
        $result = $stmt->get_result();
        
        if ($row = $result->fetch_assoc()) {
            $stmt->close();
            return $row['response_text'];
        }
        
        $stmt->close();
        return null;
    }
    
    private function sendAutoResponse($studentId, $responseText) {
        // Send auto response as admin message
        $stmt = $this->conn->prepare("INSERT INTO site_chat (student_id, message, is_admin) VALUES (?, ?, 1)");
        $stmt->bind_param('is', $studentId, $responseText);
        $stmt->execute();
        $stmt->close();
    }
    
    private function checkContentViolation($message) {
        $message = strtolower($message);
        
        foreach ($this->contentFilters as $category => $filters) {
            foreach ($filters as $filter) {
                if (strpos($message, $filter) !== false) {
                    return $category;
                }
            }
        }
        
        return null;
    }
    
    private function handleContentViolation($messageId, $studentId, $violationType) {
        // Flag the message
        $stmt = $this->conn->prepare("UPDATE chat_message_analysis SET flagged = 1, flag_reason = ? WHERE message_id = ?");
        $stmt->bind_param('si', $violationType, $messageId);
        $stmt->execute();
        $stmt->close();
        
        // Send notification to admin
        $this->createNotification($studentId, "Your message has been flagged for review: " . $violationType, 'warning');
    }
    
    public function createNotification($studentId, $message, $type = 'info') {
        $stmt = $this->conn->prepare("INSERT INTO chat_notifications (student_id, message, type) VALUES (?, ?, ?)");
        $stmt->bind_param('iss', $studentId, $message, $type);
        $stmt->execute();
        $stmt->close();
    }
    
    public function getChatStatistics($studentId = null) {
        if ($studentId) {
            $stmt = $this->conn->prepare("SELECT * FROM chat_statistics WHERE student_id = ?");
            $stmt->bind_param('i', $studentId);
        } else {
            $stmt = $this->conn->prepare("SELECT cs.*, s.first_name, s.last_name FROM chat_statistics cs JOIN student s ON cs.student_id = s.id_number ORDER BY cs.messages_sent DESC LIMIT 10");
        }
        
        $stmt->execute();
        $result = $stmt->get_result();
        $stats = [];
        
        while ($row = $result->fetch_assoc()) {
            $stats[] = $row;
        }
        
        $stmt->close();
        return $stats;
    }
    
    public function getNotifications($studentId, $unreadOnly = false) {
        $sql = "SELECT * FROM chat_notifications WHERE student_id = ?";
        if ($unreadOnly) {
            $sql .= " AND is_read = 0";
        }
        $sql .= " ORDER BY created_at DESC LIMIT 20";
        
        $stmt = $this->conn->prepare($sql);
        $stmt->bind_param('i', $studentId);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $notifications = [];
        while ($row = $result->fetch_assoc()) {
            $notifications[] = $row;
        }
        
        $stmt->close();
        return $notifications;
    }
    
    public function markNotificationRead($notificationId) {
        $stmt = $this->conn->prepare("UPDATE chat_notifications SET is_read = 1 WHERE id = ?");
        $stmt->bind_param('i', $notificationId);
        $stmt->execute();
        $stmt->close();
    }
    
    public function getMessageAnalytics($days = 7) {
        $stmt = $this->conn->prepare("
            SELECT 
                DATE(c.timestamp) as date,
                COUNT(*) as message_count,
                AVG(ca.sentiment_score) as avg_sentiment,
                COUNT(CASE WHEN ca.flagged = 1 THEN 1 END) as flagged_count
            FROM site_chat c 
            LEFT JOIN chat_message_analysis ca ON c.id = ca.message_id 
            WHERE c.timestamp >= DATE_SUB(NOW(), INTERVAL ? DAY)
            GROUP BY DATE(c.timestamp)
            ORDER BY date DESC
        ");
        
        $stmt->bind_param('i', $days);
        $stmt->execute();
        $result = $stmt->get_result();
        
        $analytics = [];
        while ($row = $result->fetch_assoc()) {
            $analytics[] = $row;
        }
        
        $stmt->close();
        return $analytics;
    }
}

// API endpoints for chat engine
if (isset($_GET['engine_action'])) {
    header('Content-Type: application/json');
    
    $engine = new ChatEngine();
    $action = $_GET['engine_action'];
    
    try {
        switch ($action) {
            case 'get_notifications':
                $studentId = $_GET['student_id'] ?? 0;
                $unreadOnly = isset($_GET['unread_only']) && $_GET['unread_only'] === 'true';
                $notifications = $engine->getNotifications($studentId, $unreadOnly);
                echo json_encode(['success' => true, 'notifications' => $notifications]);
                break;
                
            case 'mark_notification_read':
                $notificationId = $_POST['notification_id'] ?? 0;
                $engine->markNotificationRead($notificationId);
                echo json_encode(['success' => true]);
                break;
                
            case 'get_statistics':
                $studentId = $_GET['student_id'] ?? null;
                $stats = $engine->getChatStatistics($studentId);
                echo json_encode(['success' => true, 'statistics' => $stats]);
                break;
                
            case 'get_analytics':
                $days = $_GET['days'] ?? 7;
                $analytics = $engine->getMessageAnalytics($days);
                echo json_encode(['success' => true, 'analytics' => $analytics]);
                break;
                
            case 'create_notification':
                $studentId = $_POST['student_id'] ?? 0;
                $message = $_POST['message'] ?? '';
                $type = $_POST['type'] ?? 'info';
                $engine->createNotification($studentId, $message, $type);
                echo json_encode(['success' => true]);
                break;
                
            default:
                echo json_encode(['success' => false, 'error' => 'Unknown action']);
        }
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'error' => $e->getMessage()]);
    }
    exit;
}
?>