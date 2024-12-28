<?php
require 'vendor/autoload.php';

// Load environment variables
$dotenv = Dotenv\Dotenv::createImmutable(__DIR__);
$dotenv->load();

// Initialize markdown parser
$markdownConverter = new League\CommonMark\CommonMarkConverter();

// Database connection
$db = new SQLite3($_ENV['DATABASE_PATH'], SQLITE3_OPEN_READWRITE);

// Get search query
$searchQuery = isset($_GET['q']) ? $_GET['q'] : '';

// Fetch data
if (!empty($searchQuery)) {
    $searchTerm = $db->escapeString($searchQuery);
    $query = "
        SELECT r.prompt, r.response, r.model, r.datetime_utc
        FROM responses r
        JOIN responses_fts fts ON r.rowid = fts.rowid
        WHERE responses_fts MATCH '$searchTerm'
        ORDER BY r.datetime_utc DESC
        LIMIT 60
    ";
} else {
    $query = 'SELECT prompt, response, model, datetime_utc FROM responses ORDER BY datetime_utc DESC LIMIT 60';
}

$results = $db->query($query);
if ($results === false) {
    die("Error in query: " . $db->lastErrorMsg());
}

// UI Components
function formatDateTime($utc) {
    $date = new DateTime($utc);
    return $date->format('M j, Y g:i A');
}

function renderResponse($prompt, $response, $model, $datetime) {
    global $markdownConverter;
    // Highlight search terms in prompt and response
    $highlightedPrompt = $prompt;
    $highlightedResponse = $response;
    if (!empty($searchQuery)) {
        $highlightedPrompt = preg_replace('/(' . preg_quote($searchQuery, '/') . ')/i', '<mark>$1</mark>', $prompt);
        $highlightedResponse = preg_replace('/(' . preg_quote($searchQuery, '/') . ')/i', '<mark>$1</mark>', $response);
    }

    $formattedPrompt = $markdownConverter->convertToHtml($highlightedPrompt);
    $formattedResponse = $markdownConverter->convertToHtml($highlightedResponse);

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
        body { 
            font-family: "Inter", -apple-system, BlinkMacSystemFont, sans-serif; 
            max-width: 800px; 
            margin: 2rem auto; 
            padding: 0 1rem;
            line-height: 1.5;
            color: #1a1a1a;
        }
        
        .response-card { 
            border: 1px solid #e5e7eb; 
            border-radius: 0.5rem; 
            padding: 1.5rem; 
            margin-bottom: 1.5rem;
            background: white;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
            transition: box-shadow 0.2s ease;
        }
        
        .response-card:hover {
            box-shadow: 0 2px 6px rgba(0,0,0,0.1);
        }
        
        .response-header { 
            display: flex; 
            justify-content: space-between; 
            margin-bottom: 1rem; 
        }
        
        .datetime { 
            color: #6b7280; 
            font-size: 0.875rem;
        }
        
        details summary { 
            cursor: pointer; 
            font-weight: 500;
            font-size: 1.125rem;
            color: #111827;
            outline: none;
        }
        
        details summary:hover {
            color: #2563eb;
        }
        
        .metadata { 
            margin-bottom: 1.5rem; 
        }
        
        .metadata .model { 
            font-size: 0.875rem;
            color: #6b7280;
            font-weight: 500;
            padding: 0.25rem 0.5rem;
            background: #f3f4f6;
            border-radius: 0.25rem;
            display: inline-block;
        }
        
        .response-content { 
            margin-top: 1.5rem; 
            padding: 1.5rem; 
            background: #f9fafb; 
            border-radius: 0.5rem; 
        }
        
        .prompt-section, .response-section { 
            margin-bottom: 2rem; 
        }
        
        .prompt-section h3, .response-section h3 {
            margin: 0 0 1rem 0;
            color: #374151;
            font-size: 0.875rem;
            text-transform: uppercase;
            letter-spacing: 0.05em;
            font-weight: 600;
        }
        
        .search-form {
            margin-bottom: 3rem;
            background: white;
            padding: 1.5rem;
            border-radius: 0.5rem;
            box-shadow: 0 1px 3px rgba(0,0,0,0.05);
        }
        
        .search-input {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid #e5e7eb;
            border-radius: 0.5rem;
            margin-bottom: 1rem;
            font-size: 1rem;
            transition: border-color 0.2s ease;
        }
        
        .search-input:focus {
            outline: none;
            border-color: #2563eb;
            box-shadow: 0 0 0 3px rgba(37,99,235,0.1);
        }
        
        .search-button {
            background-color: #2563eb;
            color: #fff;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 0.5rem;
            cursor: pointer;
            font-size: 1rem;
            font-weight: 500;
            transition: background-color 0.2s ease;
        }
        
        .search-button:hover {
            background-color: #1d4ed8;
        }
        
        h1 {
            font-size: 1.875rem;
            font-weight: 600;
            margin-bottom: 2rem;
            color: #111827;
        }
    </style>
</head>
<body>

    <form class="search-form" method="get">
        <input type="text" name="q" class="search-input" placeholder="Search...">
        <button type="submit" class="search-button">Search</button>
    </form>

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
