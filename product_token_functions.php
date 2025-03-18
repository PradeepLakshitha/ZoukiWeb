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
 * Generate QR code SVG for a product
 *
 * @param int $product_id The product ID
 * @param string $base_url The base URL of the site
 * @return string The QR code as SVG
 */
function generateProductQrCode($product_id, $base_url) {
    // Include the PHP QR Code library
    require_once 'phpqrcode/qrlib.php';

    // Generate the URL
    $url = generateProductSafetyUrl($product_id, $base_url);

    // Create a temporary file to store the QR code
    $tempfile = tempnam(sys_get_temp_dir(), 'qrcode');

    // Generate the QR code as an SVG
    QRcode::svg($url, $tempfile, 'M', 4, 2);

    // Read the SVG content
    $svg_content = file_get_contents($tempfile);

    // Clean up the temporary file
    unlink($tempfile);

    return $svg_content;
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