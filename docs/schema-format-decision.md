# Database Schema Format Decision for AI Consumption

**Date:** March 12, 2026
**Decision:** We use **YAML (`.yml`)** for defining database schemas.

## Context
When deciding on a file format for database schema definitions (like `domain.yml`) that needs to be consumed, understood, and edited by AI models (such as Gemini 3.1 Pro and Claude 3.5 Sonnet) as well as parsed by the application framework, several formats were evaluated: YAML, JSON, XML, SQL, and Markdown.

## Why YAML? (The Decision)
YAML is the chosen format because it offers the best balance of token efficiency, high readability, and easy programmatic parsing.

### 1. YAML vs. JSON
*   **Token Efficiency:** YAML uses significantly fewer tokens than JSON because it lacks braces, quotes, and closing tags. This leaves more context window available for the actual task and improves response speed.
*   **Readability:** It has the highest signal-to-noise ratio. The AI can instantly grasp the semantic structure.
*   **The Trailing Comma Problem:** The #1 reason JSON partial edits fail with AIs is trailing commas. Asking an AI to delete the last column or add a new one in a JSON file often results in invalid JSON due to comma mismanagement. YAML completely avoids this.

### 2. The Whitespace Risk in YAML
YAML's strict reliance on significant whitespace is its biggest vulnerability, but it is manageable.
*   **Full Generation:** When an AI generates a new YAML file or rewrites a large block from scratch, it almost never makes an indentation error. AIs are heavily trained on massive amounts of YAML data (Kubernetes manifests, GitHub Actions, OpenAPI specs, etc.).
*   **Partial Edits:** Where automated coding agents occasionally fail is during partial file updates (find-and-replace). If the AI miscalculates the exact indentation relative to the surrounding context, it can break the parser. However, top-tier modern AIs handle spaces accurately 99% of the time, especially when modifying complete YAML blocks.

### 3. Why Not XML?
If the risk of whitespace errors in YAML seems high, XML is still not a recommended fallback.
*   **Worst Token Economy:** Opening and closing tags (`<expiration_date>...</expiration_date>`) force the AI to read and write the exact same token string twice for every single key.
*   **Decreased Attention:** Large schemas in XML eat up the context window faster, dilute the AI's attention span, and increase costs.
*   **Hallucinations/Errors:** When an AI gets confused in XML during a partial edit, it tends to leave unclosed tags or mismatch the closing tag name—which is just as fatal to a parser as a bad YAML space.

### 4. Why Not SQL (`CREATE TABLE`) or Markdown?
*   **SQL:** While semantically native to LLMs (they understand `VARCHAR(255)` perfectly), parsing raw SQL into an array-like structure for a PHP framework's ORM is highly complex and counterproductive.
*   **Markdown:** AIs think and output natively in Markdown, making it great for general text. However, it is terrible for strict, nested data. Writing a custom parser to extract hierarchical structures (`columns`, `relations`) from Markdown tables or code-blocks is highly impractical.

## Conclusion
YAML provides the highest signal-to-noise ratio. Maximizing the AI's understanding of the schema and keeping context usage low outweighs the minor risk of whitespace issues during automated partial edits. JSON serves as the best fallback only if robust, whitespace-insensitive parsing becomes absolutely mandatory at the cost of token density. XML should be avoided due to severe token inefficiency.
