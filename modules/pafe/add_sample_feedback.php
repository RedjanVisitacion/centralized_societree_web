<?php
// Sample script to add test feedback data
require_once '../../db_connection.php';

try {
    // Create feedback table if it doesn't exist
    $pdo->exec("
        CREATE TABLE IF NOT EXISTS pafe_feedback (
            id INT AUTO_INCREMENT PRIMARY KEY,
            student_id INT,
            name VARCHAR(255) NOT NULL,
            email VARCHAR(255) NOT NULL,
            subject VARCHAR(255) NOT NULL,
            message TEXT NOT NULL,
            rating INT DEFAULT NULL,
            status ENUM('unread', 'read', 'replied') DEFAULT 'unread',
            admin_reply TEXT NULL,
            replied_at TIMESTAMP NULL,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            FOREIGN KEY (student_id) REFERENCES student(id_number) ON DELETE SET NULL
        )
    ");

    // Sample feedback data
    $sample_feedback = [
        [
            'student_id' => 2022309359,
            'name' => 'Alexander Pepito',
            'email' => 'alexanderpepitojr6@gmail.com',
            'subject' => 'Great Event Organization',
            'message' => 'I really enjoyed the recent PAFE event. The organization was excellent and the speakers were very informative. Thank you for the great experience!',
            'rating' => 5,
            'status' => 'unread'
        ],
        [
            'student_id' => 2023304604,
            'name' => 'Mae Rodriguez',
            'email' => 'maerodriguez491@gmail.com',
            'subject' => 'Suggestion for Future Events',
            'message' => 'Could we have more interactive workshops in future events? I think hands-on activities would be more engaging for students.',
            'rating' => 4,
            'status' => 'unread'
        ],
        [
            'student_id' => 2023304652,
            'name' => 'Vince Rey Claveria',
            'email' => 'vincereyclaveria@gmail.com',
            'subject' => 'Technical Issues During Event',
            'message' => 'There were some audio problems during the last presentation. It was hard to hear the speaker clearly. Please check the sound system for future events.',
            'rating' => 3,
            'status' => 'read'
        ],
        [
            'student_id' => null,
            'name' => 'Anonymous Student',
            'email' => 'student@example.com',
            'subject' => 'Appreciation Message',
            'message' => 'Thank you PAFE for all the opportunities and learning experiences. The organization has been very helpful in my academic journey.',
            'rating' => 5,
            'status' => 'replied',
            'admin_reply' => 'Thank you for your kind words! We are glad to be part of your academic journey and will continue to provide quality events and opportunities.',
            'replied_at' => date('Y-m-d H:i:s', strtotime('-2 days'))
        ]
    ];

    // Insert sample data
    $stmt = $pdo->prepare("
        INSERT INTO pafe_feedback (student_id, name, email, subject, message, rating, status, admin_reply, replied_at, created_at) 
        VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?)
    ");

    foreach ($sample_feedback as $feedback) {
        $created_at = date('Y-m-d H:i:s', strtotime('-' . rand(1, 7) . ' days'));
        $replied_at = isset($feedback['replied_at']) ? $feedback['replied_at'] : null;
        $admin_reply = isset($feedback['admin_reply']) ? $feedback['admin_reply'] : null;
        
        $stmt->execute([
            $feedback['student_id'],
            $feedback['name'],
            $feedback['email'],
            $feedback['subject'],
            $feedback['message'],
            $feedback['rating'],
            $feedback['status'],
            $admin_reply,
            $replied_at,
            $created_at
        ]);
    }

    echo "<h2>✅ Sample Feedback Data Added Successfully!</h2>";
    echo "<p>Added " . count($sample_feedback) . " sample feedback entries.</p>";
    echo "<p><a href='pafe_feedback.php'>Go to Feedback Management</a></p>";

} catch (PDOException $e) {
    echo "<h2>❌ Error Adding Sample Data</h2>";
    echo "<p>Error: " . $e->getMessage() . "</p>";
}
?>