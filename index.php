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
    
    // Get the original markdown for copying
    $markdownContent = htmlspecialchars($response, ENT_QUOTES);
    
    return <<<HTML
    <div class="bg-white rounded-lg shadow-sm p-6 mb-6 transition-shadow hover:shadow-md prose prose-sm max-w-none">
        <div class="flex justify-between mb-4">
            <span class="text-sm text-gray-500">{$datetime}</span>
        </div>
        <details>
            <summary class="cursor-pointer font-medium text-lg text-gray-900 hover:text-blue-600 outline-none">{$formattedSummary}</summary>
            <div class="mt-6 p-6 bg-gray-50 rounded-lg">
                <div class="mb-6">
                    <span class="text-sm text-gray-500 font-medium px-2 py-1 bg-gray-100 rounded">Model: {$model}</span>
                </div>
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm text-gray-700 uppercase tracking-wider font-semibold">Prompt</h3>
                        <button 
                            onclick="toggleCollapse(this, 'prompt-{$datetime}')" 
                            class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                        >
                            Collapse
                        </button>
                    </div>
                    <div id="prompt-{$datetime}" class="collapsible">
                        <div class="full-content prose prose-sm max-w-none">{$formattedPrompt}</div>
                        <div class="collapsed-preview"></div>
                    </div>
                </div>
                <div class="mb-8">
                    <div class="flex justify-between items-center mb-4">
                        <h3 class="text-sm text-gray-700 uppercase tracking-wider font-semibold">Response</h3>
                        <button 
                            onclick="toggleCollapse(this, 'response-{$datetime}')" 
                            class="text-sm text-blue-600 hover:text-blue-700 font-medium"
                        >
                            Collapse
                        </button>
                    </div>
                    <div id="response-{$datetime}" class="collapsible">
                        <div class="full-content prose prose-sm max-w-none">{$formattedResponse}</div>
                        <div class="collapsed-preview"></div>
                    </div>
                </div>
            </div>
        </details>
        <button 
            onclick="copyToClipboard(this)"
            data-markdown="{$markdownContent}"
            class="mt-4 px-4 py-2 text-sm text-gray-600 hover:text-gray-800 border border-gray-300 rounded-lg hover:bg-gray-50 transition-colors"
        >
            Copy to clipboard
        </button>
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
    <title>LLM Browser</title>
    <script src="https://cdn.tailwindcss.com?plugins=typography"></script>
    <script>
        async function copyToClipboard(button) {
            try {
                const markdown = button.getAttribute('data-markdown');
                if (!markdown) {
                    throw new Error('No markdown content found');
                }
                await navigator.clipboard.writeText(markdown);
                const originalText = button.textContent;
                button.textContent = 'Copied!';
                setTimeout(() => {
                    button.textContent = originalText;
                }, 2000);
            } catch (err) {
                console.error('Failed to copy:', err);
                button.textContent = 'Failed to copy';
                setTimeout(() => {
                    button.textContent = 'Copy to clipboard';
                }, 2000);
            }
        }

        function toggleCollapse(element, containerId) {
            const container = document.getElementById(containerId);
            const content = container.querySelector('.full-content');
            const preview = container.querySelector('.collapsed-preview');
            
            if (container.classList.contains('collapsed')) {
                // Expand
                content.style.display = 'block';
                preview.style.display = 'none';
                element.textContent = 'Collapse';
                container.classList.remove('collapsed');
            } else {
                // Collapse
                const lines = content.textContent.split('\\n');
                const firstLines = lines.slice(0, 3).join('\\n');
                const lastLines = lines.slice(-3).join('\\n');
                preview.innerHTML = firstLines + '<br>...<br>' + lastLines;
                
                content.style.display = 'none';
                preview.style.display = 'block';
                element.textContent = 'Expand';
                container.classList.add('collapsed');
            }
        }
    </script>
    <style>
        .collapsible {
            position: relative;
        }
        .collapsible .full-content {
            display: block;
        }
        .collapsible .collapsed-preview {
            display: none;
            background: white;
            padding: 8px;
            border-radius: 4px;
            border: 1px solid #e5e7eb;
        }
        .collapsible.collapsed .full-content {
            display: none;
        }
        .collapsible.collapsed .collapsed-preview {
            display: block;
        }
    </style>
</head>
<body class="font-sans max-w-4xl mx-auto my-8 px-4 text-gray-900 leading-relaxed bg-gray-50">
    <form class="bg-white rounded-lg shadow-sm p-6 mb-12" method="get">
        <input type="text" name="q" class="w-full px-3 py-2 mb-4 border border-gray-200 rounded-lg focus:outline-none focus:border-blue-500 focus:ring-1 focus:ring-blue-500 transition-colors" placeholder="Search...">
        <button type="submit" class="bg-blue-600 text-white px-6 py-2 rounded-lg font-medium hover:bg-blue-700 transition-colors">Search</button>
    </form>
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
