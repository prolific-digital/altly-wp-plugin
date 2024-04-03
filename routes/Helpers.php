<?php

namespace Altly\AltTextGenerator;

use Ramsey\Uuid\Uuid;

class Helpers {
  
    public function defineMediaArgs() {
      // Set up query arguments to retrieve media attachments of type 'image/jpeg' and 'image/png'.
      $args = array(
        'post_type' => 'attachment', // Target media attachments.
        'post_status' => 'inherit', // Include all attachments regardless of status.
        'post_mime_type' => array('image/jpeg', 'image/png'), // Filter by JPEG and PNG image formats.
        'posts_per_page' => -1, // Retrieve all matching media items.
      );
  
      return $args;
    }

    public function prepareHeaders() {
        $license_key = get_option('_altly_license_key');
        return ['Content-Type' => 'application/json', 'Authorization' => 'Bearer '. $license_key];
    }

    public function getUserCredits() {
        return get_option('_altly_license_key_user_credits', 0);  // Default to 0 if not set
    }

    public function analyzeImage($apiBaseUrl, $image_url) {
        $apiUrl = $apiBaseUrl . '/analyze/image';

        error_log('apiUrl: ' . print_r($apiUrl, true));

        $headers = $this->prepareHeaders();

        $attachment_id = attachment_url_to_postid($image_url);
        
        $images = [
          [
              "name" => "Bourbon Bottle",
              "url" => $image_url,
              "cms_id" => "cms_id_1",
              "cms_platform" => "cms_platform_1"
          ]
      ];

      $jsonBody = json_encode(['images' => $images]);
        

        // $body = json_encode(['images' => [$image_url]]);

        error_log('Body: ' . print_r($jsonBody, true));

        $api_response = wp_remote_post($apiUrl, ['headers' => $headers, 'body' => $jsonBody]);
        if (is_wp_error($api_response)) {
            return new WP_Error('api_error', $api_response->get_error_message());
        }

        error_log('API Response: ' . print_r($api_response, true));

        // $api_status = wp_remote_retrieve_response_code($api_response);
        // $api_data = json_decode(wp_remote_retrieve_body($api_response), true);
        // if ($api_status != 200) {
        //     return new WP_Error('api_failure', 'API call failed', ['status' => $api_status]);
        // }

        // return $api_data;
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

    public function addAltTextToImage($data, $images_missing_alt_text_arr) {
      if (count($images_missing_alt_text_arr) > 0) {
        // loop over the data and get matching image IDs
        // Extract IDs from $images_missing_alt_text_arr
        $missingAltTextAttachmentIDs = array_column($images_missing_alt_text_arr, 'id');
  
        foreach ($data['images'] as $item) {
          $attachment_id = $item['cms_id'];
          if (in_array($attachment_id, $missingAltTextAttachmentIDs)) {
              $altText = $item['alt_text'];
              // $confidenceScore = $item['confidence'];
  
              // update alt text for all images missing alts
              update_post_meta($attachment_id, '_wp_attachment_image_alt', sanitize_text_field($altText));
              // update_post_meta($attachment_id, 'confidence_score', sanitize_text_field($confidenceScore));
          }
        }
      }
    }
  
    public function checkLicenseKey($license_key) {
      if ($license_key === get_option('_altly_license_key')) {
        // error_log('Valid license key: ' . print_r(get_option('_altly_license_key'), true));
        return true;
      }
  
      return false;
    }
  
    public function getImagesMissingAltText() {
  
      // Set up query arguments to retrieve media attachments of type 'image/jpeg' and 'image/png'.
      $args = $this->defineMediaArgs();
  
      $media_query = new \WP_Query($args);
      $images_missing_alt_text_arr = array();
  
      // Check if the query returned any posts (media attachments).
      if ($media_query->have_posts()) {
        // Loop through the media attachments.
        while ($media_query->have_posts()) {
          $media_query->the_post(); // Set up the global post data.
          $attachment_id = get_the_ID(); // Retrieve the ID of the current attachment.
          $alt_text = get_post_meta($attachment_id, '_wp_attachment_image_alt', true);
          $image_url = wp_get_attachment_url($attachment_id); // Get the URL of the attachment.
          $file_path = get_attached_file($attachment_id); // Retrieve the file path of the attachment.
          $attachment_metadata = wp_get_attachment_metadata($attachment_id);
  
          // Compile the details for the current attachment.
          $current_media_details = array(
            'id' => $attachment_id,
            'alt_text' => esc_attr($alt_text),
            'url' => esc_url($image_url),
            'file_path' => esc_url($file_path), // Note: Consider a different method for sanitizing file paths.
            'metadata' => $attachment_metadata,
          );
  
          // Check if the alt text is missing and add to the missing alt text array.
          if (empty($alt_text)) {
            $images_missing_alt_text_arr[] = $current_media_details;
          }
        }
        wp_reset_postdata(); // Reset the global post data to avoid conflicts with other queries.
      } else {
        // Return a response indicating that no media was found if the query returned no posts.
        return rest_ensure_response(array('message' => 'No media found.'));
      }
      wp_reset_postdata(); // Reset the global post data again for good measure.
  
      return $images_missing_alt_text_arr;
    }

    public function queueImages($attachment_id) {
        $apiUrl = 'https://api.altly.io/v1/batch/queue';

        $headers = $this->prepareHeaders();
        $processing_id = '';

        $altly_processing_id = get_post_meta($attachment_id, 'altly_processing_id', true);

        // check if attachment already has altly_processing_id
        if ($altly_processing_id !== '' && $altly_processing_id !== null) {
          $processing_id = $altly_processing_id;
        } else {
          // generate uuid and update image metadata
          $processing_id = Uuid::uuid4()->toString();
          update_post_meta($attachment_id, 'altly_processing_id', sanitize_text_field($processing_id));
          update_post_meta($attachment_id, 'altly_processing_status', 'pending');
        }

        $image_url = wp_get_attachment_url($attachment_id);

        $api_url = home_url() . '/wp-json/altly/v1/process-response';
  
        $images = [
          [
            "url" => $image_url,
            "api_endpoint" => $api_url, // this might change
            "asset_id" => $attachment_id,
            'transaction_id' => $processing_id,
            "platform_name" => "WordPress"
          ]
        ];

        error_log('Image: ' . print_r($images, true));
      
        $jsonBody = json_encode(['images' => $images]);

        $api_response = wp_remote_post($apiUrl, [
            'headers' => $headers,
            'body' => $jsonBody,
        ]);

        return $this->processApiResponse($api_response);
    }

    public function processApiResponse($api_response) {
        if (is_wp_error($api_response)) {
            $error_message = $api_response->get_error_message();
            error_log('API Request Error: ' . $error_message);
            return new \WP_REST_Response(['error' => $error_message], 500);
        }

        $api_status = wp_remote_retrieve_response_code($api_response);
        $api_data = json_decode(wp_remote_retrieve_body($api_response), true);

        error_log('API Response: ' . print_r($api_data, true));

        if ($api_status == 200) {
            return new \WP_REST_Response(['message' => 'Images queued successfully'] + $api_data, 200);
        }

        return new \WP_REST_Response($api_data ?: ['error' => 'Invalid API response'], $api_status ?: 500);
    }

    public function compileMediaDetails($media_query) {
        $media_details = [];
        if ($media_query->have_posts()) {
            while ($media_query->have_posts()) {
                $media_query->the_post();
                $attachment_id = get_the_ID();
        
                $media_details[] = [
                    'id' => $attachment_id,
                    'alt_text' => esc_attr(get_post_meta($attachment_id, '_wp_attachment_image_alt', true)),
                    'confidence_score' => esc_attr(get_post_meta($attachment_id, 'confidence_score', true)),
                    'url' => esc_url(wp_get_attachment_url($attachment_id)),
                    'file_path' => esc_url(get_attached_file($attachment_id)),
                    'metadata' => wp_get_attachment_metadata($attachment_id),
                ];
            }
        }
    
        return $media_details;
    }
}


// error_log('Altly Processing ID: ' . print_r($processing_id, true));