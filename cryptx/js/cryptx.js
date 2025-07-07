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

/**
 * Generates a hashed string from the input string using a custom algorithm.
 * The method applies a randomized salt to the ASCII values of the characters
 * in the input string while avoiding certain blacklisted ASCII values.
 *
 * @param {string} inputString - The input string to be hashed.
 * @return {string} The generated hash string.
 */
function generateHashFromString(inputString) {
	// Replace & with itself (this line seems redundant in the original PHP code)
	inputString = inputString.replace("&", "&");
	let crypt = '';

	// ASCII values blacklist (taken from the PHP constant)
	const ASCII_VALUES_BLACKLIST = ['32', '34', '39', '60', '62', '63', '92', '94', '96', '127'];

	for (let i = 0; i < inputString.length; i++) {
		let salt, asciiValue;
		do {
			// Generate random number between 0 and 3
			salt = Math.floor(Math.random() * 4);
			// Get ASCII value and add salt
			asciiValue = inputString.charCodeAt(i) + salt;

			// Check if value exceeds limit
			if (8364 <= asciiValue) {
				asciiValue = 128;
			}
		} while (ASCII_VALUES_BLACKLIST.includes(asciiValue.toString()));

		// Append salt and character to result
		crypt += salt + String.fromCharCode(asciiValue);
	}

	return crypt;
}

/**
 * Generates a DeCryptX handler URL with a hashed email address.
 *
 * @param {string} emailAddress - The email address to be hashed and included in the handler URL.
 * @return {string} A string representing the JavaScript DeCryptX handler with the hashed email address.
 */
function generateDeCryptXHandler(emailAddress) {
	return `javascript:DeCryptX('${generateHashFromString(emailAddress)}')`;
}