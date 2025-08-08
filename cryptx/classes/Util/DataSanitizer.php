<?php

namespace CryptX\Util;

class DataSanitizer
{
    /**
     * Sanitize given options
     *
     * @param array $data Data to sanitize
     * @return array Sanitized data
     */
    public static function sanitize(array $data): array
    {
        $textFields = [
            'version',
            'at',
            'dot',
            'css_id',
            'css_class',
            'alt_linktext',
            'http_linkimage_title',
            'alt_linkimage_title',
            'excludedIDs',
            'alt_uploadedimage',
            'c2i_font',
            'c2i_fontRGB',
            'whiteList'
        ];

        $intFields = [
            'the_content',
            'the_meta_key',
            'the_excerpt',
            'comment_text',
            'java',
            'load_java',
            'opt_linktext',
            'autolink',
            'c2i_fontSize',
            'echo',
            'iterations'
        ];

        $boolFields = ['metaBox'];

        foreach ($textFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = sanitize_text_field($data[$field]);
            }
        }

        foreach ($intFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = (int)$data[$field];
            }
        }

        foreach ($boolFields as $field) {
            if (isset($data[$field])) {
                $data[$field] = (bool)$data[$field];
            }
        }

        return $data;
    }
}