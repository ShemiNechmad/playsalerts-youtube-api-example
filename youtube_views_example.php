<?php
/*********************************
 * PlaysAlerts.com — Example Script
 * Hourly YouTube Views Checker
 *
 * This is a PUBLIC example demonstrating how
 * playsalerts.com retrieves YouTube view statistics
 * and sends notification emails to users.
 *
 * ⚠️ This file contains NO real credentials.
 * Replace placeholders with your own environment variables.
 *********************************/

use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\Exception;

// Example dependency includes (not bundled in this repo)
require __DIR__ . '/vendor/autoload.php';

ini_set('display_errors', 1);
error_reporting(E_ALL);
date_default_timezone_set('UTC');

header('Content-Type: application/json');

// -------------------------------
// CONFIG (PUBLIC SAFE PLACEHOLDERS)
// -------------------------------

// Secret token used to trigger scheduled jobs
const JOB_SECRET_TOKEN = 'SECRET_KEY';

// YouTube API configuration
const YOUTUBE_API_KEY = 'SECRET_KEY';
const YOUTUBE_API_URL = 'https://www.googleapis.com/youtube/v3/videos';
const YOUTUBE_BATCH_SIZE = 50;

// Email sender (example domain from playsalerts.com)
const EMAIL_FROM = 'assistant@playsalerts.com';

// -------------------------------
// AUTH
// -------------------------------
if (!isset($_GET['token']) || $_GET['token'] !== JOB_SECRET_TOKEN) {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit;
}

// -------------------------------
// DATABASE CONNECTION
// -------------------------------

/*
 * In production, playsalerts.com connects to a secure database.
 * This example assumes a PDO instance is created in db.php.
 *
 * IMPORTANT:
 * Never commit real database credentials to public repositories.
 */
require 'db.php'; // Should return a configured PDO instance

// -------------------------------
// HELPERS
// -------------------------------

function extractVideoId(string $value): ?string
{
    $value = trim($value);

    // YouTube video IDs are exactly 11 characters
    if (preg_match('/^[a-zA-Z0-9_-]{11}$/', $value)) {
        return $value;
    }

    return null;
}

function fetchYoutubeViews(array $videoIds): array
{
    if (empty($videoIds)) {
        return [];
    }

    $results = [];

    $chunks = array_chunk($videoIds, YOUTUBE_BATCH_SIZE);

    foreach ($chunks as $chunk) {
        $url = YOUTUBE_API_URL . '?' . http_build_query([
            'part' => 'statistics',
            'id'   => implode(',', $chunk),
            'key'  => YOUTUBE_API_KEY,
        ]);

        $response = @file_get_contents($url);

        if ($response === false) {
            continue;
        }

        $data = json_decode($response, true);

        if (!isset($data['items'])) {
            continue;
        }

        foreach ($data['items'] as $item) {
            if (isset($item['id'], $item['statistics']['viewCount'])) {
                $results[$item['id']] = (int)$item['statistics']['viewCount'];
            }
        }
    }

    return $results;
}

// -------------------------------
// MAIN LOGIC (SIMPLIFIED EXAMPLE)
// -------------------------------

$stats = [
    'usersProcessed' => 0,
    'videosChecked'  => 0,
    'emailsSent'     => 0,
];

try {

    /*
     * In the real playsalerts.com system:
     * 1. Active subscribers are loaded from the database
     * 2. Their tracked YouTube videos are checked
     * 3. New views trigger email notifications
     */

    // Example placeholder query
    $stmt = $pdo->query("SELECT email FROM users LIMIT 5");
    $users = $stmt->fetchAll(PDO::FETCH_ASSOC);

    foreach ($users as $user) {

        $stats['usersProcessed']++;

        // -------------------------------
        // EMAIL EXAMPLE (SANITIZED)
        // -------------------------------

        $mail = new PHPMailer(true);

        try {
            $mail->isSMTP();

            /*
             * SMTP credentials are stored securely in environment variables
             * in the real playsalerts.com infrastructure.
             */
            $mail->Host       = 'SMTP_HOST';
            $mail->SMTPAuth   = true;
            $mail->Username   = 'SMTP_USERNAME';
            $mail->Password   = 'SECRET_KEY';
            $mail->SMTPSecure = 'tls';
            $mail->Port       = 587;

            $mail->setFrom(EMAIL_FROM, 'PlaysAlerts');
            $mail->addAddress($user['email']);

            $mail->isHTML(true);
            $mail->Subject = 'Example Notification from PlaysAlerts.com';
            $mail->Body    = '<p>This is an example notification.</p>';
            $mail->AltBody = 'Example notification from PlaysAlerts.com';

            $mail->send();
            $stats['emailsSent']++;

        } catch (Exception $e) {
            error_log('Mail error: ' . $mail->ErrorInfo);
        }
    }

    echo json_encode([
        'status' => 'ok',
        'stats'  => $stats,
    ]);

} catch (Throwable $e) {

    http_response_code(500);

    echo json_encode([
        'status'  => 'error',
        'message' => 'Example error handler triggered',
    ]);
}
