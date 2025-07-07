<?php
/**
 * Template for the How To tab in CryptX settings
 *
 * @var string $example_code The example code to display
 */

if (!defined('ABSPATH')) {
    exit;
}
?>

<h4><?php esc_html_e("How to use CryptX in your Template", 'cryptx'); ?></h4>
<div class="cryptx-documentation">
    <p>The <code>[cryptx]</code> shortcode allows you to protect email addresses from spam bots in your WordPress posts
        and pages, even when CryptX is disabled globally for that content.</p>

    <h3>Basic Usage</h3>
    <pre><code>[cryptx]user@example.com[/cryptx]</code></pre>

    <h3>Advanced Usage</h3>
    <pre><code>[cryptx linktext="Contact Us" subject="Website Inquiry"]user@example.com[/cryptx]</code></pre>

    <h4>Available Attributes</h4>
    <table class="cryptx-attributes">
        <thead>
        <tr>
            <th>Attribute</th>
            <th>Description</th>
            <th>Default</th>
            <th>Example</th>
        </tr>
        </thead>
        <tbody>
        <tr>
            <td><code>linktext</code></td>
            <td>Custom text to display instead of the email address</td>
            <td>Email address</td>
            <td><code>linktext="Contact Us"</code></td>
        </tr>
        <tr>
            <td><code>subject</code></td>
            <td>Pre-defined subject line for the email</td>
            <td>None</td>
            <td><code>subject="Website Inquiry"</code></td>
        </tr>
        </tbody>
    </table>

    <h3>Examples</h3>

    <h4>1. Basic Email Protection</h4>
    <pre><code>[cryptx]user@example.com[/cryptx]</code></pre>
    <p>Displays: A protected version of user@example.com</p>

    <h4>2. Custom Link Text</h4>
    <pre><code>[cryptx linktext="Send us an email"]user@example.com[/cryptx]</code></pre>
    <p>Displays: "Send us an email" as a protected link</p>

    <h4>3. With Subject Line</h4>
    <pre><code>[cryptx subject="Product Inquiry"]sales@example.com[/cryptx]</code></pre>
    <p>Creates a link that opens the email client with a pre-filled subject line</p>

    <h3>Best Practices</h3>
    <ul>
        <li>Use the shortcode when you need to protect individual email addresses in content where CryptX is disabled
            globally
        </li>
        <li>Consider using custom link text for better user experience</li>
        <li>Use meaningful subject lines when applicable</li>
        <li>Don't nest shortcodes within the email address</li>
    </ul>

    <h3>Troubleshooting</h3>
    <ul>
        <li><strong>Email Not Protected:</strong> Ensure the shortcode syntax is correct with both opening and closing
            tags
        </li>
        <li><strong>Link Not Working:</strong> Verify that JavaScript is enabled in the browser</li>
        <li><strong>Strange Characters:</strong> Make sure the email address format is valid</li>
    </ul>

    <div class="cryptx-notes">
        <h4>Important Notes</h4>
        <ul>
            <li>The shortcode works independently of global CryptX settings</li>
            <li>JavaScript must be enabled in the visitor's browser</li>
            <li>Email addresses are protected using JavaScript encryption</li>
        </ul>
    </div>

    <div class="cryptx-version">
        <p><strong>Available since:</strong> Version 2.7</p>
    </div>
</div>

<h4><?php esc_html_e("How to use CryptX javascript function", 'cryptx'); ?></h4>
<div class="cryptx-documentation">
    <h2>JavaScript Email Protection Functions</h2>

    <p>This section describes the JavaScript functions available for email protection in CryptX.</p>

    <h3>generateDeCryptXHandler()</h3>

    <p>A JavaScript function that generates an encrypted handler for email address protection.
        This function creates a special URL format that encrypts email addresses to protect them
        from spam bots while keeping them clickable for real users.</p>

    <h4>Parameters</h4>
    <ul>
        <li><code>emailAddress</code> (string) - The email address to encrypt (e.g., "user@example.com")</li>
    </ul>

    <h4>Returns</h4>
    <ul>
        <li>(string) A JavaScript handler string in the format "javascript:DeCryptX('encrypted_string')"</li>
    </ul>

    <h4>Examples</h4>

    <p>Basic usage in JavaScript:</p>
    <pre><code class="language-javascript">const handler = generateDeCryptXHandler("user@example.com");
// Returns: javascript:DeCryptX('1A2B3C...')</code></pre>

    <p>Creating a protected link:</p>
    <pre><code class="language-javascript">const link = document.createElement('a');
link.href = generateDeCryptXHandler('user@example.com');
link.textContent = "Contact Us";
document.body.appendChild(link);</code></pre>

    <p>Direct HTML usage:</p>
    <pre><code class="language-html">&lt;a href="javascript:generateDeCryptXHandler('user@example.com')"&gt;Contact Us&lt;/a&gt;</code></pre>

    <h4>Implementation with Error Handling</h4>
    <pre><code class="language-javascript">function createSafeEmailLink(email, linkText) {
    try {
        // Input validation
        if (!email || typeof email !== 'string') {
            throw new Error('Valid email address required');
        }

        // Create link with encrypted handler
        const link = document.createElement('a');
        link.href = generateDeCryptXHandler(email);
        link.textContent = linkText || 'Contact Us';

        // Add accessibility attributes
        link.setAttribute('title', 'Send email (address is encrypted)');
        link.setAttribute('aria-label', 'Send email');

        return link;
    } catch (error) {
        console.error('Error creating encrypted email link:', error);
        return null;
    }
}</code></pre>

    <h4>Important Notes</h4>

    <h5>1. Dependencies</h5>
    <ul>
        <li>Requires generateHashFromString function</li>
        <li>Requires DeCryptX function in the global scope</li>
    </ul>

    <h5>2. Browser Requirements</h5>
    <ul>
        <li>Works in all modern browsers</li>
        <li>JavaScript must be enabled</li>
    </ul>

    <h5>3. Best Practices</h5>
    <ul>
        <li>Provide fallback for users with JavaScript disabled</li>
        <li>Use meaningful link text instead of showing the email address</li>
        <li>Add title or aria-label for accessibility</li>
    </ul>

    <h5>4. Security Considerations</h5>
    <ul>
        <li>Encryption is for spam prevention only</li>
        <li>Not suitable for sensitive data transmission</li>
        <li>Email address will be visible in browser's JavaScript console when decrypted</li>
    </ul>

    <h4>Troubleshooting</h4>

    <h5>1. If links are not working:</h5>
    <ul>
        <li>Verify DeCryptX function is included</li>
        <li>Check if JavaScript is enabled</li>
        <li>Ensure email address format is valid</li>
    </ul>

    <h5>2. Different encryptions:</h5>
    <ul>
        <li>Normal behavior: same email generates different encrypted strings</li>
        <li>Each encryption uses random values for security</li>
    </ul>

    <h5>3. Performance:</h5>
    <ul>
        <li>Lightweight function suitable for multiple uses</li>
        <li>Safe for use in loops or event handlers</li>
    </ul>

    <div class="cryptx-related">
        <h4>Related Functions</h4>
        <ul>
            <li><a href="#DeCryptX">DeCryptX()</a> - For the decryption function</li>
            <li><a href="#generateHashFromString">generateHashFromString()</a> - For the internal encryption function
            </li>
        </ul>
    </div>

    <div class="cryptx-version">
        <p><strong>Since:</strong> Version 3.5.0</p>
    </div>
</div>

<style>
    .cryptx-documentation {
        max-width: 900px;
        margin: 20px auto;
        font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, Oxygen-Sans, Ubuntu, Cantarell, "Helvetica Neue", sans-serif;
        line-height: 1.6;
    }

    .cryptx-documentation h2 {
        color: #23282d;
        border-bottom: 1px solid #eee;
        padding-bottom: 10px;
    }

    .cryptx-documentation h3,
    .cryptx-documentation h4,
    .cryptx-documentation h5 {
        color: #23282d;
        margin-top: 1.5em;
    }

    .cryptx-documentation code {
        background: #f4f4f4;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: Consolas, Monaco, monospace;
    }

    .cryptx-documentation pre {
        background: #f4f4f4;
        padding: 15px;
        border-radius: 4px;
        overflow-x: auto;
    }

    .cryptx-documentation pre code {
        background: none;
        padding: 0;
    }

    .cryptx-documentation ul {
        margin-left: 20px;
    }

    .cryptx-documentation li {
        margin-bottom: 8px;
    }

    .cryptx-related {
        margin-top: 30px;
        padding: 15px;
        background: #f8f9fa;
        border-radius: 4px;
    }

    .cryptx-version {
        margin-top: 20px;
        color: #666;
        font-style: italic;
    }
</style>