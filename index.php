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
    
    // Create plain text summary from first 50 chars of prompt
    $summary = substr($prompt, 0, 50);
    if (strlen($prompt) > 50) {
        $summary .= '...';
    }
    // Escape HTML in summary to show plain text
    $formattedSummary = htmlspecialchars($summary);
    
    return <<<HTML
    <div class="response-card">
        <div class="response-header">
            <span class="datetime">{$datetime}</span>
        </div>
        <details>
            <summary>{$formattedSummary}</summary>
            <div class="response-content">
                <div class="metadata">
                    <span class="model">Model: {$model}</span>
                </div>
                <div class="prompt-section">
                    <h3>Prompt</h3>
                    {$formattedPrompt}
                </div>
                <div class="response-section">
                    <h3>Response</h3>
                    {$formattedResponse}
                </div>
            </div>
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
        .datetime { color: #6b7280; }
        details summary { cursor: pointer; font-weight: 500; }
        .metadata { margin-bottom: 1rem; }
        .metadata .model { 
            font-size: 0.9rem;
            color: #6b7280;
            font-weight: 500;
        }
        .response-content { margin-top: 1rem; padding: 1rem; background: #f9fafb; border-radius: 0.25rem; }
        .prompt-section, .response-section { margin-bottom: 1.5rem; }
        .prompt-section h3, .response-section h3 {
            margin: 0 0 0.5rem 0;
            color: #374151;
            font-size: 0.9rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
        }
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
