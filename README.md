# LLM Browser

A web interface for browsing and searching prompts and responses logged by the [LLM](https://llm.datasette.io/) command-line tool.

## Features

- Browse historical LLM prompts and responses
- Search across both prompts and responses
- View markdown-formatted content with syntax highlighting
- Collapsible sections for long content
- Responsive design with Tailwind CSS

## Setup

1. Install LLM with SQLite logging enabled:

   ```bash
   pip install llm
   ```

2. Configure LLM to log to SQLite (see [LLM Logging Documentation](https://llm.datasette.io/en/stable/logging.html)):

   ```bash
   llm logs path /path/to/llm.db
   ```

3. Clone this repository:

   ```bash
   git clone https://github.com/yourusername/llm-browser.git
   cd llm-browser
   ```

4. Install dependencies:

   ```bash
   composer install
   ```

5. Create a `.env` file with your SQLite database path:

   ```env
   DATABASE_PATH=/path/to/llm.db
   ```

6. Start the PHP built-in server:

   ```bash
   php -S localhost:8000
   ```

7. Open your browser to `http://localhost:8000`

## Usage

- The main page shows the most recent prompts and responses
- Use the search bar to find specific content
- Click "Collapse" to hide long content and show a preview
- Click "Expand" to show the full content again

## Technology Stack

- PHP
- SQLite
- Tailwind CSS
- CommonMark (for markdown rendering)

## License

MIT License

## Contributing

Pull requests are welcome. For major changes, please open an issue first to discuss what you would like to change.
