<?php
/**
 * Small vCard generator endpoint
 * Receives POSTed form fields and an optional photo upload and emits a
 * vCard 3.0 file as an attachment.
 *
 * Security: This script uses basic sanitization and server-side checks for
 * uploaded file type and size. Before using in production, add stricter
 * input validation, rate limiting and, if needed, authentication.
 */

// Set download headers early. Note: filename may be replaced by client-side JS.
header('Content-Type: text/vcard; charset=UTF-8');
header('Content-Disposition: attachment; filename="contact.vcf"');

/**
 * Sanitize string input from POST.
 * Trims, strips tags and escapes HTML-special characters to avoid injection
 * when echoing the vCard content in other contexts.
 *
 * @param string $input
 * @return string
 */
function sanitize(string $input): string {
    return htmlspecialchars(strip_tags(trim($input)), ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

/**
 * Send a JSON error response and terminate the script.
 * Uses a 400 Bad Request status by default but allows other codes.
 *
 * @param string $message
 * @param int $httpCode
 * @return void
 */
function jsonError(string $message, int $httpCode = 400): void {
    http_response_code($httpCode);
    // Remove any content-disposition header so browsers do not try to download
    // a file when the server is returning an error JSON payload.
    if (function_exists('header_remove')) {
        header_remove('Content-Disposition');
    } else {
        // Fallback: reset Content-Disposition to inline
        header('Content-Disposition: inline');
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['error' => $message], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Send structured validation errors as JSON
 *
 * @param array $errors  associative array of field => message
 * @param int $httpCode
 * @return void
 */
function jsonValidationErrors(array $errors, int $httpCode = 422): void {
    http_response_code($httpCode);
    if (function_exists('header_remove')) {
        header_remove('Content-Disposition');
    } else {
        header('Content-Disposition: inline');
    }
    header('Content-Type: application/json; charset=UTF-8');
    echo json_encode(['errors' => $errors], JSON_UNESCAPED_UNICODE);
    exit;
}

/**
 * Normalize an email address for storage and comparisons.
 * - Converts domain to ASCII using IDN (punycode) if available
 * - Lowercases the domain part
 */
function normalizeEmail(string $email): string {
    $email = trim($email);
    $parts = explode('@', $email);
    if (count($parts) !== 2) return $email;
    [$local, $domain] = $parts;
    // Try to convert internationalized domain names to ASCII (punycode)
    if (function_exists('idn_to_ascii')) {
        $domainAscii = idn_to_ascii($domain, IDNA_DEFAULT, INTL_IDNA_VARIANT_UTS46);
        if ($domainAscii !== false) {
            $domain = $domainAscii;
        }
    }
    return $local . '@' . strtolower($domain);
}

/**
 * Normalize a URL: force https when possible and strip common tracking params.
 */
function normalizeUrl(string $url): string {
    $url = trim($url);
    $parts = parse_url($url);
    if ($parts === false) return $url;

    // Default to https if scheme is missing and host exists
    if (empty($parts['scheme']) && !empty($parts['host'])) {
        $parts['scheme'] = 'https';
    }

    // Prefer https if provided as http
    if (isset($parts['scheme']) && $parts['scheme'] === 'http') {
        $parts['scheme'] = 'https';
    }

    // Strip common tracking parameters from query string
    $trackingParams = ['utm_source','utm_medium','utm_campaign','utm_term','utm_content','fbclid','gclid'];
    $query = [];
    if (!empty($parts['query'])) {
        parse_str($parts['query'], $query);
        foreach ($trackingParams as $p) {
            if (isset($query[$p])) unset($query[$p]);
        }
    }

    // Rebuild URL
    $new = '';
    if (!empty($parts['scheme'])) $new .= $parts['scheme'] . '://';
    if (!empty($parts['user'])) {
        $new .= $parts['user'];
        if (!empty($parts['pass'])) $new .= ':' . $parts['pass'];
        $new .= '@';
    }
    if (!empty($parts['host'])) $new .= $parts['host'];
    if (!empty($parts['port'])) $new .= ':' . $parts['port'];
    if (!empty($parts['path'])) $new .= $parts['path'];
    if (!empty($query)) $new .= '?' . http_build_query($query);
    if (!empty($parts['fragment'])) $new .= '#' . $parts['fragment'];

    return $new;
}

/**
 * Normalize phone numbers: remove everything except digits and leading +
 * This makes phone numbers more consistent across vCard consumers.
 *
 * @param string $phone
 * @return string
 */
function formatPhoneNumber(string $phone): string {
    return preg_replace('/[^0-9+]/', '', $phone);
}

/**
 * Escape vCard text values as required by the vCard 3.0 spec.
 * Commas, semicolons and backslashes must be escaped. Newlines become \n.
 *
 * @param string $value
 * @return string
 */
function escapeVCardValue(string $value): string {
    return str_replace(["\\", "\n", "\r", ",", ";"], ["\\\\", "\\n", "", "\\,", "\\;"], $value);
}

/**
 * Fold long vCard lines according to RFC rules: keep lines <= $limit and
 * continue with CRLF + single space. This increases compatibility with
 * readers that expect folded lines (e.g., Outlook, Apple Contacts).
 *
 * @param string $line
 * @param int $limit
 * @return string
 */
function foldVCardLine(string $line, int $limit = 75): string {
    $result = '';
    while (strlen($line) > $limit) {
        $result .= substr($line, 0, $limit) . "\r\n ";
        $line = substr($line, $limit);
    }
    $result .= $line;
    return $result;
}

// Build vCard body
$vcard = "BEGIN:VCARD\r\n";
$vcard .= "VERSION:3.0\r\n";
$vcard .= "PRODID:-//vCard Generator//EN\r\n";
$vcard .= "CHARSET:UTF-8\r\n";

// Name (FN, N)
if (!empty($_POST['name'])) {
    $name = sanitize((string)$_POST['name']);
    $vcard .= foldVCardLine("FN:" . escapeVCardValue($name) . "\r\n");

    // Try to split into last and first for N: field. Works for many Western names
    // but isn't universal — keep it simple for a small utility.
    $nameParts = preg_split('/\s+/', $name);
    $lastName = array_pop($nameParts) ?: '';
    $firstName = implode(' ', $nameParts);
    $vcard .= foldVCardLine("N:" . escapeVCardValue($lastName) . ";" . escapeVCardValue($firstName) . ";;;\r\n");
}

// Emails
// Collect structured validation errors here
$errors = [];

if (!empty($_POST['email'])) {
    $emailRaw = (string)$_POST['email'];
    if (!filter_var($emailRaw, FILTER_VALIDATE_EMAIL)) {
        $errors['email'] = 'Invalid primary email address';
    } else {
        // Normalize and sanitize before using
        $email = sanitize(normalizeEmail($emailRaw));
        $vcard .= foldVCardLine("EMAIL;TYPE=INTERNET:" . escapeVCardValue($email) . "\r\n");
    }
}

if (!empty($_POST['additionalEmail'])) {
    $additionalEmailRaw = (string)$_POST['additionalEmail'];
    if (!filter_var($additionalEmailRaw, FILTER_VALIDATE_EMAIL)) {
        $errors['additionalEmail'] = 'Invalid additional email address';
    } else {
        $additionalEmail = sanitize(normalizeEmail($additionalEmailRaw));
        $vcard .= foldVCardLine("EMAIL;TYPE=INTERNET:" . escapeVCardValue($additionalEmail) . "\r\n");
    }
}

// If there are validation errors so far, return them as structured JSON
if (!empty($errors)) {
    jsonValidationErrors($errors);
}

// Phone numbers
if (!empty($_POST['mobile'])) {
    $mobile = formatPhoneNumber(sanitize((string)$_POST['mobile']));
    $vcard .= foldVCardLine("TEL;type=CELL;type=VOICE;type=pref:" . escapeVCardValue($mobile) . "\r\n");
}
if (!empty($_POST['additionalMobile'])) {
    $additionalMobile = formatPhoneNumber(sanitize((string)$_POST['additionalMobile']));
    $vcard .= foldVCardLine("TEL;TYPE=WORK,VOICE:" . escapeVCardValue($additionalMobile) . "\r\n");
}

// WhatsApp: avoid duplicate TEL entries if number already present
if (!empty($_POST['whatsapp'])) {
    $whatsapp = formatPhoneNumber(sanitize((string)$_POST['whatsapp']));
    $mobileClean = $mobile ?? null;
    $additionalMobileClean = $additionalMobile ?? null;
    if ($whatsapp !== $mobileClean && $whatsapp !== $additionalMobileClean) {
        $vcard .= foldVCardLine("TEL;type=CELL;type=VOICE;type=WHATSAPP:" . escapeVCardValue($whatsapp) . "\r\n");
    }
    // A social profile entry can help some importers map the contact.
    $vcard .= foldVCardLine("X-SOCIALPROFILE;type=whatsapp;x-user=" . escapeVCardValue($whatsapp) . ":https://wa.me/" . ltrim($whatsapp, '+') . "\r\n");
}

// Organization and title
if (!empty($_POST['organization'])) {
    $org = sanitize((string)$_POST['organization']);
    $vcard .= foldVCardLine("ORG:" . escapeVCardValue($org) . "\r\n");
    $vcard .= foldVCardLine("X-ORGANIZATION:" . escapeVCardValue($org) . "\r\n");
}
if (!empty($_POST['title'])) {
    $title = sanitize((string)$_POST['title']);
    $vcard .= foldVCardLine("TITLE:" . escapeVCardValue($title) . "\r\n");
    $vcard .= foldVCardLine("X-TITLE:" . escapeVCardValue($title) . "\r\n");
}

// Address
if (!empty($_POST['address'])) {
    $address = sanitize((string)$_POST['address']);
    $vcard .= foldVCardLine("ADR;TYPE=WORK:;;" . escapeVCardValue($address) . "\r\n");
}

// Website
if (!empty($_POST['website'])) {
    $websiteRaw = (string)$_POST['website'];
    if (!filter_var($websiteRaw, FILTER_VALIDATE_URL)) {
        $errors['website'] = 'Invalid website URL';
    } else {
        $website = sanitize(normalizeUrl($websiteRaw));
        $vcard .= foldVCardLine("URL;TYPE=WORK:" . escapeVCardValue($website) . "\r\n");
    }
}

// LinkedIn / social URL
if (!empty($_POST['linkedin'])) {
    $linkedinRaw = (string)$_POST['linkedin'];
    if (!filter_var($linkedinRaw, FILTER_VALIDATE_URL)) {
        $errors['linkedin'] = 'Invalid LinkedIn URL';
    } else {
        $linkedin = sanitize(normalizeUrl($linkedinRaw));
        $vcard .= foldVCardLine("URL;TYPE=SOCIAL:" . escapeVCardValue($linkedin) . "\r\n");
        $vcard .= foldVCardLine("X-SOCIALPROFILE;TYPE=linkedin:" . escapeVCardValue($linkedin) . "\r\n");
    }
}

// If there are validation errors now, return them as structured JSON
if (!empty($errors)) {
    jsonValidationErrors($errors);
}

// Photo handling: validate MIME type and size, then embed as BASE64
if (!empty($_FILES['photo']) && isset($_FILES['photo']['error']) && $_FILES['photo']['error'] === UPLOAD_ERR_OK) {
    $allowedTypes = ['image/jpeg' => 'JPEG', 'image/png' => 'PNG', 'image/gif' => 'GIF'];
    $maxSize = 5 * 1024 * 1024; // 5MB

    $file = $_FILES['photo'];
    if (array_key_exists($file['type'], $allowedTypes) && $file['size'] <= $maxSize) {
        $photoRaw = file_get_contents($file['tmp_name']);
        if ($photoRaw !== false) {
            // Encode in base64 and chunk the output to 76 characters per line
            // Prepend a space to each continuation line as many vCard readers expect.
            $base64 = base64_encode($photoRaw);
            $chunks = chunk_split($base64, 76, "\r\n");
            $chunks = trim($chunks);
            $chunks = preg_replace('/\r\n/', "\r\n ", $chunks);
            $typeLabel = $allowedTypes[$file['type']];
            $photoLine = "PHOTO;ENCODING=BASE64;TYPE=" . $typeLabel . ":\r\n " . $chunks . "\r\n";
            $vcard .= $photoLine;
        }
    }
}

$vcard .= "END:VCARD\r\n";

// Emit the vCard to the client
echo $vcard;

// End of script
?>