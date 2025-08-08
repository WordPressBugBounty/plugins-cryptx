/**
 * Secure CryptX Library - Fixed for backward compatibility
 */

// Configuration constants
const ITERATIONS = window.cryptxConfig?.iterations || 100000; // fallback to old value
const KEY_LENGTH = window.cryptxConfig?.keyLength || 32;
const IV_LENGTH = window.cryptxConfig?.ivLength || 16;
const SALT_LENGTH = window.cryptxConfig?.saltLength || 16;
const CONFIG = {
	ALLOWED_PROTOCOLS: ['http:', 'https:', 'mailto:'],
	MAX_URL_LENGTH: 2048,
	ENCRYPTION_KEY_SIZE: 32,
	IV_SIZE: 16
};

/**
 * Utility functions for secure operations
 */
class SecureUtils {
	static getSecureRandomBytes(length) {
		if (typeof crypto === 'undefined' || !crypto.getRandomValues) {
			throw new Error('Secure random number generation not available');
		}
		return crypto.getRandomValues(new Uint8Array(length));
	}

	static arrayBufferToBase64(buffer) {
		const bytes = new Uint8Array(buffer);
		let binary = '';
		for (let i = 0; i < bytes.byteLength; i++) {
			binary += String.fromCharCode(bytes[i]);
		}
		return btoa(binary);
	}

	static base64ToArrayBuffer(base64) {
		const binary = atob(base64);
		const buffer = new ArrayBuffer(binary.length);
		const bytes = new Uint8Array(buffer);
		for (let i = 0; i < binary.length; i++) {
			bytes[i] = binary.charCodeAt(i);
		}
		return buffer;
	}

	static validateUrl(url) {
		if (typeof url !== 'string' || url.length === 0) {
			return null;
		}

		if (url.length > CONFIG.MAX_URL_LENGTH) {
			console.error('URL exceeds maximum length');
			return null;
		}

		try {
			const urlObj = new URL(url);

			if (!CONFIG.ALLOWED_PROTOCOLS.includes(urlObj.protocol)) {
				console.error('Protocol not allowed:', urlObj.protocol);
				return null;
			}

			if (urlObj.protocol === 'mailto:') {
				const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
				if (!emailRegex.test(urlObj.pathname)) {
					console.error('Invalid email format in mailto URL');
					return null;
				}
			}

			return url;
		} catch (error) {
			console.error('Invalid URL format:', error.message);
			return null;
		}
	}

	static escapeJavaScript(str) {
		if (typeof str !== 'string') {
			return '';
		}
		return str.replace(/\\/g, '\\\\')
			.replace(/'/g, "\\'")
			.replace(/"/g, '\\"')
			.replace(/\n/g, '\\n')
			.replace(/\r/g, '\\r')
			.replace(/\t/g, '\\t');
	}
}

/**
 * Legacy encryption class - Fixed to match original PHP algorithm
 */
class LegacyEncryption {
	/**
	 * Decrypts using the original CryptX algorithm (matches PHP version)
	 * @param {string} encryptedString
	 * @returns {string}
	 */
	static originalDecrypt(encryptedString) {
		if (typeof encryptedString !== 'string' || encryptedString.length === 0) {
			throw new Error('Invalid encrypted string');
		}

		// Constants from original algorithm
		const UPPER_LIMIT = 8364;
		const DEFAULT_VALUE = 128;

		let charCode = 0;
		let decryptedString = "mailto:";
		let encryptionKey = 0;

		try {
			for (let i = 0; i < encryptedString.length; i += 2) {
				if (i + 1 >= encryptedString.length) {
					break;
				}

				// Get the salt (encryption key) from current position
				encryptionKey = parseInt(encryptedString.charAt(i), 10);

				// Handle invalid salt values
				if (isNaN(encryptionKey)) {
					encryptionKey = 0;
				}

				// Get the character code from next position
				charCode = encryptedString.charCodeAt(i + 1);

				// Apply the same logic as original
				if (charCode >= UPPER_LIMIT) {
					charCode = DEFAULT_VALUE;
				}

				// Decrypt by subtracting the salt
				const decryptedCharCode = charCode - encryptionKey;

				// Validate the result
				if (decryptedCharCode < 0 || decryptedCharCode > 1114111) {
					throw new Error('Invalid character code during decryption');
				}

				decryptedString += String.fromCharCode(decryptedCharCode);
			}

			return decryptedString;
		} catch (error) {
			throw new Error('Original decryption failed: ' + error.message);
		}
	}

	/**
	 * Encrypts using the original CryptX algorithm (matches PHP version)
	 * @param {string} inputString
	 * @returns {string}
	 */
	static originalEncrypt(inputString) {
		if (typeof inputString !== 'string' || inputString.length === 0) {
			throw new Error('Invalid input string');
		}

		// Remove "mailto:" prefix if present for encryption
		const cleanInput = inputString.replace(/^mailto:/, '');
		let crypt = '';

		// ASCII values blacklist (from PHP constant)
		const ASCII_VALUES_BLACKLIST = ['32', '34', '39', '60', '62', '63', '92', '94', '96', '127'];

		try {
			for (let i = 0; i < cleanInput.length; i++) {
				let salt, asciiValue;
				let attempts = 0;
				const maxAttempts = 20; // Prevent infinite loops

				do {
					if (attempts >= maxAttempts) {
						// Fallback to a safe salt if we can't find a valid one
						salt = 1;
						asciiValue = cleanInput.charCodeAt(i) + salt;
						break;
					}

					// Generate random number between 0 and 3 (matching PHP rand(0,3))
					const randomValues = SecureUtils.getSecureRandomBytes(1);
					salt = randomValues[0] % 4;

					// Get ASCII value and add salt
					asciiValue = cleanInput.charCodeAt(i) + salt;

					// Check if value exceeds limit (matching PHP logic)
					if (asciiValue >= 8364) {
						asciiValue = 128;
					}

					attempts++;
				} while (ASCII_VALUES_BLACKLIST.includes(asciiValue.toString()) && attempts < maxAttempts);

				// Append salt and character to result
				crypt += salt.toString() + String.fromCharCode(asciiValue);
			}

			return crypt;
		} catch (error) {
			throw new Error('Original encryption failed: ' + error.message);
		}
	}
}

/**
 * Modern encryption class using Web Crypto API - PHP Compatible
 */
class SecureEncryption {
	static async deriveKey(password, salt) {
		const encoder = new TextEncoder();
		const keyMaterial = await crypto.subtle.importKey(
			'raw',
			encoder.encode(password),
			{ name: 'PBKDF2' },
			false,
			['deriveKey']
		);

		return crypto.subtle.deriveKey(
			{
				name: 'PBKDF2',
				salt: salt,
				iterations: ITERATIONS,
				hash: 'SHA-256'
			},
			keyMaterial,
			{ name: 'AES-GCM', length: KEY_LENGTH * 8},
			false,
			['encrypt', 'decrypt']
		);
	}

	static async encrypt(plaintext, password) {
		if (typeof plaintext !== 'string' || typeof password !== 'string') {
			throw new Error('Both plaintext and password must be strings');
		}

		const encoder = new TextEncoder();
		const salt = SecureUtils.getSecureRandomBytes(16);
		const iv = SecureUtils.getSecureRandomBytes(CONFIG.IV_SIZE);

		const key = await this.deriveKey(password, salt);

		const encrypted = await crypto.subtle.encrypt(
			{ name: 'AES-GCM', iv: iv },
			key,
			encoder.encode(plaintext)
		);

		// Match PHP format: salt(16) + iv(16) + encrypted_data + tag(16)
		const encryptedArray = new Uint8Array(encrypted);
		const encryptedData = encryptedArray.slice(0, -16); // Remove tag from encrypted data
		const tag = encryptedArray.slice(-16); // Get the tag

		const combined = new Uint8Array(salt.length + iv.length + encryptedData.length + tag.length);
		combined.set(salt, 0);
		combined.set(iv, salt.length);
		combined.set(encryptedData, salt.length + iv.length);
		combined.set(tag, salt.length + iv.length + encryptedData.length);

		return SecureUtils.arrayBufferToBase64(combined.buffer);
	}

	static async decrypt(encryptedData, password) {
		if (typeof encryptedData !== 'string' || typeof password !== 'string') {
			throw new Error('Both encryptedData and password must be strings');
		}

		try {
			const combined = SecureUtils.base64ToArrayBuffer(encryptedData);
			const totalLength = combined.byteLength;

			// PHP format: salt(16) + iv(16) + encrypted_data + tag(16)
			const saltLength = 16;
			const ivLength = 16;
			const tagLength = 16;
			const encryptedDataLength = totalLength - saltLength - ivLength - tagLength;

			if (totalLength < saltLength + ivLength + tagLength) {
				throw new Error('Encrypted data too short');
			}

			const salt = combined.slice(0, saltLength);
			const iv = combined.slice(saltLength, saltLength + ivLength);
			const encryptedDataOnly = combined.slice(saltLength + ivLength, saltLength + ivLength + encryptedDataLength);
			const tag = combined.slice(-tagLength); // Last 16 bytes

			const key = await this.deriveKey(password, new Uint8Array(salt));

			// Reconstruct the encrypted data with tag for Web Crypto API
			const encryptedWithTag = new Uint8Array(encryptedDataOnly.byteLength + tag.byteLength);
			encryptedWithTag.set(new Uint8Array(encryptedDataOnly), 0);
			encryptedWithTag.set(new Uint8Array(tag), encryptedDataOnly.byteLength);

			const decrypted = await crypto.subtle.decrypt(
				{ name: 'AES-GCM', iv: new Uint8Array(iv) },
				key,
				encryptedWithTag
			);

			const decoder = new TextDecoder();
			return decoder.decode(decrypted);
		} catch (error) {
			throw new Error('Decryption failed: ' + error.message);
		}
	}
}

/**
 * Main CryptX functions with backward compatibility
 */

/**
 * Securely decrypts and validates a URL before navigation
 * @param {string} encryptedUrl
 * @param {string} password
 */
async function secureDecryptAndNavigate(encryptedUrl, password = 'default_key') {
	if (typeof encryptedUrl !== 'string' || encryptedUrl.length === 0) {
		console.error('Invalid encrypted URL provided');
		return;
	}

	try {
		let decryptedUrl;

		// Try modern decryption first, then fall back to original algorithm
		try {
			decryptedUrl = await SecureEncryption.decrypt(encryptedUrl, password);
		} catch (modernError) {
			console.warn('Modern decryption failed, trying original algorithm');
			decryptedUrl = LegacyEncryption.originalDecrypt(encryptedUrl);
		}

		const validatedUrl = SecureUtils.validateUrl(decryptedUrl);
		if (!validatedUrl) {
			console.error('Invalid or unsafe URL detected');
			return;
		}

		window.location.href = validatedUrl;

	} catch (error) {
		console.error('Error during URL decryption and navigation:', error.message);
	}
}

/**
 * Legacy function for backward compatibility - using original algorithm
 * @param {string} encryptedString
 * @returns {string|null}
 */
function DeCryptString(encryptedString) {
	try {
		return LegacyEncryption.originalDecrypt(encryptedString);
	} catch (error) {
		console.error('Legacy decryption failed:', error.message);
		return null;
	}
}

/**
 * Legacy function for backward compatibility - secured
 * @param {string} encryptedUrl
 */
function DeCryptX(encryptedUrl) {
	const decryptedUrl = DeCryptString(encryptedUrl);
	if (!decryptedUrl) {
		console.error('Failed to decrypt URL');
		return;
	}

	const validatedUrl = SecureUtils.validateUrl(decryptedUrl);
	if (!validatedUrl) {
		console.error('Invalid or unsafe URL detected');
		return;
	}

	window.location.href = validatedUrl;
}

/**
 * Generates encrypted email link with proper security
 * @param {string} emailAddress
 * @param {string} password
 * @returns {Promise<string>}
 */
async function generateSecureEmailLink(emailAddress, password = 'default_key') {
	if (typeof emailAddress !== 'string' || emailAddress.length === 0) {
		throw new Error('Valid email address required');
	}

	const emailRegex = /^[^\s@]+@[^\s@]+\.[^\s@]+$/;
	if (!emailRegex.test(emailAddress)) {
		throw new Error('Invalid email format');
	}

	const mailtoUrl = `mailto:${emailAddress}`;
	const encryptedData = await SecureEncryption.encrypt(mailtoUrl, password);
	const escapedData = SecureUtils.escapeJavaScript(encryptedData);

	return `javascript:secureDecryptAndNavigate('${escapedData}', '${SecureUtils.escapeJavaScript(password)}')`;
}

/**
 * Legacy function for backward compatibility - using original algorithm
 * @param {string} emailAddress
 * @returns {string}
 */
function generateDeCryptXHandler(emailAddress) {
	if (typeof emailAddress !== 'string' || emailAddress.length === 0) {
		console.error('Valid email address required');
		return 'javascript:void(0)';
	}

	try {
		const encrypted = LegacyEncryption.originalEncrypt(emailAddress);
		const escaped = SecureUtils.escapeJavaScript(encrypted);
		return `javascript:DeCryptX('${escaped}')`;
	} catch (error) {
		console.error('Error generating handler:', error.message);
		return 'javascript:void(0)';
	}
}

/**
 * Legacy function - matches original PHP generateHashFromString
 * @param {string} inputString
 * @returns {string}
 */
function generateHashFromString(inputString) {
	try {
		return LegacyEncryption.originalEncrypt(inputString);
	} catch (error) {
		console.error('Error generating hash:', error.message);
		return '';
	}
}

// Export functions for module usage
if (typeof module !== 'undefined' && module.exports) {
	module.exports = {
		secureDecryptAndNavigate,
		generateSecureEmailLink,
		DeCryptX,
		DeCryptString,
		generateDeCryptXHandler,
		generateHashFromString,
		SecureEncryption,
		LegacyEncryption,
		SecureUtils
	};
}