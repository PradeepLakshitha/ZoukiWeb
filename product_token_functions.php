<?php
/**
 * Functions for generating product safety tokens and QR codes
 */

/**
 * Generate a secure token for a product
 *
 * @param int $product_id The product ID
 * @return string The generated token
 */
function generateProductToken($product_id) {
    // Simple token generation - in production you'd want stronger encryption
    return hash('sha256', $product_id . 'ZoukiSafety2025');
}

/**
 * Generate a product safety URL with secure token
 *
 * @param int $product_id The product ID
 * @param string $base_url The base URL of the site
 * @return string The full URL for the product safety page
 */
function generateProductSafetyUrl($product_id, $base_url) {
    $token = generateProductToken($product_id);
    return $base_url . 'product_safety.php?id=' . $product_id . '&token=' . $token;
}

/**
 * Generate QR code image tag for a product using external API
 *
 * @param int $product_id The product ID
 * @param string $base_url The base URL of the site
 * @return string The QR code image HTML
 */
function generateProductQrCode($product_id, $base_url) {
    // Generate the safety URL
    $url = generateProductSafetyUrl($product_id, $base_url);

    // URL encode for use in API
    $encoded_url = urlencode($url);

    // Use Google Charts API (no installation required)
    $qr_src = "https://chart.googleapis.com/chart?chs=250x250&cht=qr&chl={$encoded_url}&choe=UTF-8";

    // Return image HTML
    return '<img src="' . $qr_src . '" alt="Product Safety QR Code" class="img-fluid" style="max-width: 100%;">';
}

/**
 * Get base URL of the current site
 *
 * @return string The base URL
 */
function getBaseUrl() {
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off' || $_SERVER['SERVER_PORT'] == 443) ? "https://" : "http://";
    $domain = $_SERVER['HTTP_HOST'];
    $path = dirname($_SERVER['PHP_SELF']);

    // Ensure path ends with a slash
    if ($path !== '/' && substr($path, -1) !== '/') {
        $path .= '/';
    }

    return $protocol . $domain . $path;
}