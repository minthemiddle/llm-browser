<?php
require 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize markdown parser
$markdownConverter = new League\CommonMark\CommonMarkConverter();

// Database connection (read-only)
// $db = new SQLite3('file:' . $_ENV['DATABASE_PATH'] . '?mode=ro', SQLITE3_OPEN_READONLY);
$db = new SQlite3('/Users/martinbetz/Library/Application Support/io.datasette.llm/logs.db');

// Fetch data
$results = $db->query('SELECT prompt, response, model, datetime_utc FROM responses ORDER BY datetime_utc DESC LIMIT 60');

// UI Components
function formatDateTime($utc) {
    $date = new DateTime($utc);
    return $date->format('M j, Y g:i A');
}

function renderResponse($prompt, $response, $model, $datetime) {
    global $markdownConverter;
    $formattedPrompt = $markdownConverter->convertToHtml($prompt);
    $formattedResponse = $markdownConverter->convertToHtml($response);
    
    return <<<HTML
    <div class="response-card">
        <div class="response-header">
            <span class="model">{$model}</span>
            <span class="datetime">{$datetime}</span>
        </div>
        <details>
            <summary>{$formattedPrompt}</summary>
            <div class="response-content">{$formattedResponse}</div>
        </details>
    </div>
    HTML;
}

// Page Layout
echo <<<HTML
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Responses Viewer</title>
    <style>
        body { font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif; max-width: 800px; margin: 2rem auto; }
        .response-card { border: 1px solid #e5e7eb; border-radius: 0.5rem; padding: 1rem; margin-bottom: 1rem; }
        .response-header { display: flex; justify-content: space-between; margin-bottom: 0.5rem; }
        .model { font-weight: 500; color: #3b82f6; }
        .datetime { color: #6b7280; }
        details summary { cursor: pointer; font-weight: 500; }
        .response-content { margin-top: 1rem; padding: 0.5rem; background: #f9fafb; border-radius: 0.25rem; }
    </style>
</head>
<body>
    <h1>Responses</h1>
HTML;

// Display responses
while ($row = $results->fetchArray(SQLITE3_ASSOC)) {
    echo renderResponse(
        $row['prompt'],
        $row['response'],
        $row['model'],
        formatDateTime($row['datetime_utc'])
    );
}

echo <<<HTML
</body>
</html>
HTML;
