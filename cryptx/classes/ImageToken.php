<?php

namespace CryptX;

/**
 * The stand-in for an address in an image URL.
 *
 * When CryptX renders an address as a picture, the picture has to be fetched
 * from somewhere, and until 4.2.0 that somewhere was
 * "https://example.org/<hash>/info@example.com". The address sat in the page
 * source -- entity-encoded, which stops nothing that decodes entities -- and,
 * once the browser had resolved those entities, in the request line of every
 * single image load. From there it reached the access log, and every proxy,
 * CDN and log aggregator on the way. A visitor's browser handed the address to
 * more machines than a plainly written address on the page would have.
 *
 * A token replaces it: opaque in the page, opaque in the log, and the server
 * can still work out which address to draw.
 *
 * Two properties decide the construction, and both are unusual enough to be
 * worth stating:
 *
 * The key is NOT the encryption password. That one is published -- it travels
 * to the browser in every link, because the browser is what does the
 * decrypting. A token keyed with it could be read by exactly the audience this
 * is hiding from. Config::getImageTokenSecret() is a separate secret that never
 * leaves the server.
 *
 * The token is deterministic: the same address always yields the same token.
 * A random nonce would give a fresh URL on every page view, so no browser and
 * no CDN could ever reuse a cached image, and the server would draw a new PNG
 * for every visitor on every page. The IV is therefore derived from the
 * address itself with a keyed hash. That is a deliberate weakening, and it has
 * two halves worth naming rather than discovering:
 *
 * Passively, an observer can tell that two pictures show the same address --
 * which costs nothing, because two identical addresses on a page look identical
 * anyway. Actively, somebody who can get an address of their choosing rendered
 * on the site -- a comment, on a site that draws addresses as pictures -- can
 * compare the token they get back with one on another page and so confirm a
 * guessed address without ever fetching the picture. That is cheaper than
 * guessing, and no cheaper than simply fetching the picture and looking at it,
 * which anyone can do. The token hides the address from whoever reads the log,
 * not from whoever asks the server.
 *
 * @package CryptX
 * @since   4.2.0
 */
final class ImageToken
{
    /** AES-GCM, same cipher as the links, different key and different purpose. */
    private const CIPHER = 'aes-256-gcm';

    /** Bytes of IV, as AES-GCM wants them. */
    private const IV_LENGTH = 12;

    /** Bytes of authentication tag. */
    private const TAG_LENGTH = 16;

    /**
     * Separates this key from anything else derived from the same secret.
     *
     * Even though the secret has no second use today, deriving through HKDF
     * with a label means a future one cannot accidentally share a key.
     */
    private const KEY_INFO = 'cryptx-image-token';

    /**
     * A second, separate key for deriving the IV.
     *
     * The same key would work and no attack on it is known -- HMAC and AES are
     * different primitives. Real deterministic-AEAD constructions separate them
     * anyway, because "no known attack" is a statement about today and a second
     * HKDF label costs nothing.
     */
    private const IV_KEY_INFO = 'cryptx-image-token-iv';

    /**
     * Addresses are padded up to a multiple of this before encryption.
     *
     * Without it the token's length gives the address's length away exactly --
     * base64 of a fixed overhead plus the plaintext, reversible with one
     * division. A harvester reading nothing but the page markup could shorten
     * its guessing list accordingly. Padding turns that into a bucket: every
     * address up to 32 characters looks alike, then every one up to 64.
     *
     * NUL is the padding byte, and unambiguous here because an address can
     * never contain one -- Exposure::isAddress() would reject it.
     */
    private const PAD_TO = 32;

    /**
     * The token for an address.
     *
     * @param string $address The address to hide.
     *
     * @return string A URL-safe token, or an empty string if it cannot be made.
     */
    public static function mint(string $address): string
    {
        if ($address === '' || !self::isAvailable()) {
            return '';
        }

        $key = self::key(self::KEY_INFO);
        $ivKey = self::key(self::IV_KEY_INFO);

        if ($key === '' || $ivKey === '') {
            return '';
        }

        // Derived from the address, so the same address gives the same URL and
        // the image stays cacheable. Keyed, so the derivation cannot be
        // reproduced without the secret.
        $iv = substr(hash_hmac('sha256', $address, $ivKey, true), 0, self::IV_LENGTH);

        $tag = '';
        $encrypted = openssl_encrypt(
            self::pad($address),
            self::CIPHER,
            $key,
            OPENSSL_RAW_DATA,
            $iv,
            $tag
        );

        if ($encrypted === false) {
            return '';
        }

        // Base64url: the token travels in a path segment, so "+" and "/" would
        // have to be percent-encoded and "=" is noise.
        return rtrim(strtr(base64_encode($iv . $encrypted . $tag), '+/', '-_'), '=');
    }

    /**
     * The address behind a token.
     *
     * @param string $token The token from the URL.
     *
     * @return string The address, or an empty string if the token is not ours.
     */
    public static function read(string $token): string
    {
        if ($token === '' || !self::isAvailable()) {
            return '';
        }

        // Anything outside the base64url alphabet is not a token of ours, and
        // rejecting it here keeps malformed input away from the decoder.
        if (preg_match('/^[A-Za-z0-9_-]+$/', $token) !== 1) {
            return '';
        }

        $raw = base64_decode(strtr($token, '-_', '+/'), true);

        if ($raw === false || strlen($raw) <= self::IV_LENGTH + self::TAG_LENGTH) {
            return '';
        }

        $iv = substr($raw, 0, self::IV_LENGTH);
        $tag = substr($raw, -self::TAG_LENGTH);
        $encrypted = substr($raw, self::IV_LENGTH, -self::TAG_LENGTH);

        // The current secret first, then the one it replaced -- for as long as
        // that one is inside its grace period. Without the second try, changing
        // the secret would leave a broken picture in every page still sitting
        // in a cache, and the site owner would have no way to tell why: the
        // token is opaque, so nothing in the markup says which secret made it.
        //
        // Without minting, either way: if there is no secret at all, no token
        // was ever made and there is nothing here to decode. Minting on this
        // path would let a stranger calling the image endpoint decide when the
        // secret comes into being.
        $config = CryptX::get_instance()->getConfig();

        foreach ([$config->peekImageTokenSecret(), $config->previousImageTokenSecret()] as $secret) {
            if ($secret === '') {
                continue;
            }

            $address = openssl_decrypt(
                $encrypted,
                self::CIPHER,
                hash_hkdf('sha256', $secret, 32, self::KEY_INFO),
                OPENSSL_RAW_DATA,
                $iv,
                $tag
            );

            // The tag covers the IV as well, so a token from another site or a
            // tampered one fails here rather than drawing somebody else's
            // address.
            if ($address === false) {
                continue;
            }

            $address = rtrim($address, "\0");

            // Belt and braces, and cheap: only addresses were ever minted, so
            // anything else means the secret leaked or the construction changed.
            if (Exposure::isAddress($address)) {
                return $address;
            }
        }

        return '';
    }

    /**
     * Pads an address up to the next multiple of PAD_TO.
     *
     * @param string $address The address.
     *
     * @return string The padded plaintext.
     */
    private static function pad(string $address): string
    {
        $length = strlen($address);
        $target = (int) (ceil(max(1, $length) / self::PAD_TO) * self::PAD_TO);

        return str_pad($address, $target, "\0");
    }

    /**
     * Whether the platform can do this at all.
     *
     * @return bool True when the cipher is there.
     */
    private static function isAvailable(): bool
    {
        return function_exists('openssl_encrypt')
            && in_array(self::CIPHER, openssl_get_cipher_methods(), true);
    }

    /**
     * A key, derived from the site's own image secret.
     *
     * @param string $info Which key -- the label keeps the two apart.
     * @param bool $mint Whether a missing secret may be created here.
     *
     * @return string 32 raw bytes, or an empty string if there is no secret.
     */
    private static function key(string $info, bool $mint = true): string
    {
        $config = CryptX::get_instance()->getConfig();

        $secret = $mint
            ? $config->getImageTokenSecret()
            : $config->peekImageTokenSecret();

        if ($secret === '') {
            return '';
        }

        return hash_hkdf('sha256', $secret, 32, $info);
    }
}
