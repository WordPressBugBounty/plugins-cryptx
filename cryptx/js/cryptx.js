const UPPER_LIMIT = 8364;
const DEFAULT_VALUE = 128;

/**
 * Decrypts an encrypted string using a specific encryption algorithm.
 *
 * @param {string} encryptedString - The encrypted string to be decrypted.
 * @returns {string} The decrypted string.
 */
function DeCryptString(encryptedString) {
	let charCode = 0;
	let decryptedString = "mailto:";
	let encryptionKey = 0;

	for (let i = 0; i < encryptedString.length; i += 2) {
		encryptionKey = encryptedString.substr(i, 1);
		charCode = encryptedString.charCodeAt(i + 1);

		if (charCode >= UPPER_LIMIT) {
			charCode = DEFAULT_VALUE;
		}

		decryptedString += String.fromCharCode(charCode - encryptionKey);
	}

	return decryptedString;
}

/**
 * Redirects the current page to the decrypted URL.
 *
 * @param {string} encryptedUrl - The encrypted URL to be decrypted and redirected to.
 * @return {void}
 */
function DeCryptX( encryptedUrl )
{
	location.href=DeCryptString( encryptedUrl );
}