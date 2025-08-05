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

<div class="cryptx-tab-content cryptx-howto-settings">
    <table class="form-table">
        <!-- Shortcode Usage Section -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php esc_html_e("How to use CryptX Shortcode", 'cryptx'); ?>
                </h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e("Basic Usage", 'cryptx'); ?></th>
            <td>
                <p><?php esc_html_e("The [cryptx] shortcode allows you to protect email addresses from spam bots in your WordPress posts and pages, even when CryptX is disabled globally for that content.", 'cryptx'); ?></p>
                <pre><code>[cryptx]user@example.com[/cryptx]</code></pre>
                <p class="description"><?php esc_html_e("Displays: A protected version of user@example.com", 'cryptx'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e("Advanced Usage", 'cryptx'); ?></th>
            <td>
                <pre><code>[cryptx linktext="Contact Us" subject="Website Inquiry"]user@example.com[/cryptx]</code></pre>
                <p class="description"><?php esc_html_e("Creates a link with custom text and pre-filled subject line.", 'cryptx'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e("Available Attributes", 'cryptx'); ?></th>
            <td>
                <table class="widefat striped">
                    <thead>
                    <tr>
                        <th><?php esc_html_e("Attribute", 'cryptx'); ?></th>
                        <th><?php esc_html_e("Description", 'cryptx'); ?></th>
                        <th><?php esc_html_e("Default", 'cryptx'); ?></th>
                        <th><?php esc_html_e("Example", 'cryptx'); ?></th>
                    </tr>
                    </thead>
                    <tbody>
                    <tr>
                        <td><code>linktext</code></td>
                        <td><?php esc_html_e("Custom text to display instead of the email address", 'cryptx'); ?></td>
                        <td><?php esc_html_e("Email address", 'cryptx'); ?></td>
                        <td><code>linktext="Contact Us"</code></td>
                    </tr>
                    <tr>
                        <td><code>subject</code></td>
                        <td><?php esc_html_e("Pre-defined subject line for the email", 'cryptx'); ?></td>
                        <td><?php esc_html_e("None", 'cryptx'); ?></td>
                        <td><code>subject="Website Inquiry"</code></td>
                    </tr>
                    </tbody>
                </table>
            </td>
        </tr>

        <!-- Examples Section -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php esc_html_e("Examples", 'cryptx'); ?>
                </h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e("1. Basic Email Protection", 'cryptx'); ?></th>
            <td>
                <pre><code>[cryptx]user@example.com[/cryptx]</code></pre>
                <p class="description"><?php esc_html_e("Displays: A protected version of user@example.com", 'cryptx'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e("2. Custom Link Text", 'cryptx'); ?></th>
            <td>
                <pre><code>[cryptx linktext="Send us an email"]user@example.com[/cryptx]</code></pre>
                <p class="description"><?php esc_html_e("Displays: \"Send us an email\" as a protected link", 'cryptx'); ?></p>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e("3. With Subject Line", 'cryptx'); ?></th>
            <td>
                <pre><code>[cryptx subject="Product Inquiry"]sales@example.com[/cryptx]</code></pre>
                <p class="description"><?php esc_html_e("Creates a link that opens the email client with a pre-filled subject line", 'cryptx'); ?></p>
            </td>
        </tr>

        <!-- Best Practices Section -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php esc_html_e("Best Practices", 'cryptx'); ?>
                </h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e("Recommendations", 'cryptx'); ?></th>
            <td>
                <ul>
                    <li><?php esc_html_e("Use the shortcode when you need to protect individual email addresses in content where CryptX is disabled globally", 'cryptx'); ?></li>
                    <li><?php esc_html_e("Consider using custom link text for better user experience", 'cryptx'); ?></li>
                    <li><?php esc_html_e("Use meaningful subject lines when applicable", 'cryptx'); ?></li>
                    <li><?php esc_html_e("Don't nest shortcodes within the email address", 'cryptx'); ?></li>
                </ul>
            </td>
        </tr>

        <!-- JavaScript Functions Section -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php esc_html_e("JavaScript Functions", 'cryptx'); ?>
                </h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e("generateDeCryptXHandler()", 'cryptx'); ?></th>
            <td>
                <p><?php esc_html_e("A JavaScript function that generates an encrypted handler for email address protection. This function creates a special URL format that encrypts email addresses to protect them from spam bots while keeping them clickable for real users.", 'cryptx'); ?></p>

                <h4><?php esc_html_e("Parameters", 'cryptx'); ?></h4>
                <ul>
                    <li><code>emailAddress</code> (string) - <?php esc_html_e("The email address to encrypt (e.g., \"user@example.com\")", 'cryptx'); ?></li>
                </ul>

                <h4><?php esc_html_e("Returns", 'cryptx'); ?></h4>
                <ul>
                    <li><?php esc_html_e("(string) A JavaScript handler string in the format \"javascript:DeCryptX('encrypted_string')\"", 'cryptx'); ?></li>
                </ul>

                <h4><?php esc_html_e("Basic Usage", 'cryptx'); ?></h4>
                <pre><code class="language-javascript">const handler = generateDeCryptXHandler("user@example.com");
// Returns: javascript:DeCryptX('1A2B3C...')</code></pre>

                <h4><?php esc_html_e("Creating a Protected Link", 'cryptx'); ?></h4>
                <pre><code class="language-javascript">const link = document.createElement('a');
link.href = generateDeCryptXHandler('user@example.com');
link.textContent = "Contact Us";
document.body.appendChild(link);</code></pre>

                <h4><?php esc_html_e("Implementation with Error Handling", 'cryptx'); ?></h4>
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
            </td>
        </tr>

        <!-- Important Notes Section -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php esc_html_e("Important Notes", 'cryptx'); ?>
                </h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e("Requirements", 'cryptx'); ?></th>
            <td>
                <ul>
                    <li><?php esc_html_e("The shortcode works independently of global CryptX settings", 'cryptx'); ?></li>
                    <li><?php esc_html_e("JavaScript must be enabled in the visitor's browser", 'cryptx'); ?></li>
                    <li><?php esc_html_e("Email addresses are protected using JavaScript encryption", 'cryptx'); ?></li>
                </ul>
            </td>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e("Browser Support", 'cryptx'); ?></th>
            <td>
                <ul>
                    <li><?php esc_html_e("Works in all modern browsers", 'cryptx'); ?></li>
                    <li><?php esc_html_e("Provide fallback for users with JavaScript disabled", 'cryptx'); ?></li>
                </ul>
            </td>
        </tr>

        <!-- Troubleshooting Section -->
        <tr>
            <th colspan="2">
                <h3 style="margin: 20px 0 10px 0; padding: 10px 0; border-bottom: 1px solid #ddd;">
                    <?php esc_html_e("Troubleshooting", 'cryptx'); ?>
                </h3>
            </th>
        </tr>
        <tr>
            <th scope="row"><?php esc_html_e("Common Issues", 'cryptx'); ?></th>
            <td>
                <ul>
                    <li><strong><?php esc_html_e("Email Not Protected:", 'cryptx'); ?></strong> <?php esc_html_e("Ensure the shortcode syntax is correct with both opening and closing tags", 'cryptx'); ?></li>
                    <li><strong><?php esc_html_e("Link Not Working:", 'cryptx'); ?></strong> <?php esc_html_e("Verify that JavaScript is enabled in the browser", 'cryptx'); ?></li>
                    <li><strong><?php esc_html_e("Strange Characters:", 'cryptx'); ?></strong> <?php esc_html_e("Make sure the email address format is valid", 'cryptx'); ?></li>
                    <li><strong><?php esc_html_e("Different encryptions:", 'cryptx'); ?></strong> <?php esc_html_e("Normal behavior: same email generates different encrypted strings for security", 'cryptx'); ?></li>
                </ul>
            </td>
        </tr>

        <!-- Version Info -->
        <tr>
            <th scope="row"><?php esc_html_e("Version Information", 'cryptx'); ?></th>
            <td>
                <p><strong><?php esc_html_e("Shortcode available since:", 'cryptx'); ?></strong> <?php esc_html_e("Version 2.7", 'cryptx'); ?></p>
                <p><strong><?php esc_html_e("JavaScript functions since:", 'cryptx'); ?></strong> <?php esc_html_e("Version 3.5.0", 'cryptx'); ?></p>
            </td>
        </tr>
    </table>
</div>

<style>
    .cryptx-howto-settings pre {
        background: #f4f4f4;
        padding: 15px;
        border-radius: 4px;
        overflow-x: auto;
        margin: 10px 0;
    }

    .cryptx-howto-settings code {
        background: #f4f4f4;
        padding: 2px 6px;
        border-radius: 3px;
        font-family: Consolas, Monaco, monospace;
    }

    .cryptx-howto-settings pre code {
        background: none;
        padding: 0;
    }

    .cryptx-howto-settings ul {
        margin-left: 20px;
    }

    .cryptx-howto-settings li {
        margin-bottom: 8px;
    }

    .cryptx-howto-settings h4 {
        margin-top: 20px;
        margin-bottom: 10px;
        color: #23282d;
    }

    .cryptx-howto-settings .widefat {
        margin-top: 10px;
    }

    .cryptx-howto-settings .widefat th,
    .cryptx-howto-settings .widefat td {
        padding: 8px 10px;
    }
</style>