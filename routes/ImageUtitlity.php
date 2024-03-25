<?php


class ImageUtility {

    public function getUserCredits() {
        return get_option('_altly_license_key_user_credits', 0);  // Default to 0 if not set
    }

    public function analyzeImage($apiBaseUrl, $image_url) {
        $apiUrl = $apiBaseUrl . '/analyze/image';
        $headers = $this->prepareHeaders();
        $body = json_encode(['images' => $image_url]);

        $api_response = wp_remote_post($apiUrl, ['headers' => $headers, 'body' => $body]);
        if (is_wp_error($api_response)) {
            return new WP_Error('api_error', $api_response->get_error_message());
        }

        $api_status = wp_remote_retrieve_response_code($api_response);
        $api_data = json_decode(wp_remote_retrieve_body($api_response), true);
        if ($api_status != 200) {
            return new WP_Error('api_failure', 'API call failed', ['status' => $api_status]);
        }

        return $api_data;
    }

    private function prepareHeaders() {
        $license_key = get_option('_altly_license_key');
        return ['Content-Type' => 'application/json', 'license-key' => $license_key];
    }

    public function updateImageAltText($attachment_id, $imageData) {
        update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($imageData['caption']));
        update_post_meta($attachment_id, 'confidence_score', sanitize_text_field($imageData['confidence']));
    }

    public function updateUserCredits($apiBaseUrl) {
        $apiUrl = $apiBaseUrl . '/validate/license-key';
        $headers = $this->prepareHeaders();
        $body = json_encode(['license-key' => get_option('_altly_license_key')]);

        $api_response = wp_remote_post($apiUrl, ['headers' => $headers, 'body' => $body]);
        if (!is_wp_error($api_response) && wp_remote_retrieve_response_code($api_response) == 200) {
            $api_data = json_decode(wp_remote_retrieve_body($api_response), true);
            if (isset($api_data['data']['id']) && !empty($api_data['data']['id'])) {
                update_option('_altly_license_key_user_credits', $api_data['data']['credits']);
            }
        }
    }
}
