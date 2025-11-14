<?php
/**
 * Career Form Email Handler - V2
 * Handles job application submissions from career.html
 */

// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load dependencies
require '../../vendor/autoload.php';
require './secrets.php';

// Security: Prevent direct access
if ($_SERVER["REQUEST_METHOD"] !== "POST") {
    http_response_code(405);
    die("Method Not Allowed");
}

// Configuration
$CONFIG = [
    'mail_provider' => 'smtp', // 'smtp' or 'gmail'
    'send_thank_you' => true,
    'recipient_email' => 'tahapasha2008@gmail.com',
    'recipient_name' => 'TSN Admin',
    'max_file_size' => 5 * 1024 * 1024, // 5MB
    'allowed_mime_types' => [
        'application/pdf',
        'application/msword',
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document'
    ],
    'allowed_extensions' => ['pdf', 'doc', 'docx']
];

// Mail provider settings
$MAIL_SETTINGS = [
    'gmail' => [
        'Host' => 'smtp.gmail.com',
        'Username' => GMAIL_USER,
        'Password' => GMAIL_PASS,
        'SMTPSecure' => PHPMailer::ENCRYPTION_STARTTLS,
        'Port' => 587,
        'setFrom' => GMAIL_USER,
        'setFromName' => 'TSN Website (via Gmail)'
    ],
    'smtp' => [
        'Host' => SMTP_HOST,
        'Username' => SMTP_USER,
        'Password' => SMTP_PASS,
        'SMTPSecure' => PHPMailer::ENCRYPTION_STARTTLS,
        'Port' => 587,
        'setFrom' => SMTP_USER,
        'setFromName' => 'TSN Contact Form'
    ]
];

// Select mail settings
if (!isset($MAIL_SETTINGS[$CONFIG['mail_provider']])) {
    http_response_code(500);
    die("Invalid mail provider configuration");
}
$settings = $MAIL_SETTINGS[$CONFIG['mail_provider']];

// ============================================================
// STEP 1: COLLECT AND SANITIZE INPUT
// ============================================================

$name = isset($_POST['fullname']) ? trim(strip_tags($_POST['fullname'])) : '';
$email = isset($_POST['email']) ? trim(strip_tags($_POST['email'])) : '';
$state = isset($_POST['state']) ? trim(strip_tags($_POST['state'])) : '';
$resume = isset($_FILES['resume']) ? $_FILES['resume'] : null;

// ============================================================
// STEP 2: VALIDATE INPUT
// ============================================================

// Validate name
if (empty($name)) {
    http_response_code(400);
    die("Please enter your full name.");
}

// Validate email
if (empty($email)) {
    http_response_code(400);
    die("Please enter your email address.");
}

if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    http_response_code(400);
    die("Please enter a valid email address.");
}

// Validate state
if (empty($state)) {
    http_response_code(400);
    die("Please select your state.");
}

// Validate resume file
if (!$resume || !isset($resume['tmp_name']) || empty($resume['tmp_name'])) {
    http_response_code(400);
    die("Please attach your resume.");
}

// Check for upload errors
if ($resume['error'] !== UPLOAD_ERR_OK) {
    $errorMessages = [
        UPLOAD_ERR_INI_SIZE => 'The file is too large (server limit).',
        UPLOAD_ERR_FORM_SIZE => 'The file is too large.',
        UPLOAD_ERR_PARTIAL => 'The file was only partially uploaded.',
        UPLOAD_ERR_NO_FILE => 'No file was uploaded.',
        UPLOAD_ERR_NO_TMP_DIR => 'Server error: Missing temporary folder.',
        UPLOAD_ERR_CANT_WRITE => 'Server error: Failed to write file to disk.',
        UPLOAD_ERR_EXTENSION => 'File upload stopped by extension.'
    ];

    $errorMsg = isset($errorMessages[$resume['error']])
        ? $errorMessages[$resume['error']]
        : 'An unknown error occurred during upload.';

    http_response_code(400);
    die($errorMsg);
}

// Validate file size
if ($resume['size'] > $CONFIG['max_file_size']) {
    http_response_code(400);
    die("File size must be less than 5MB.");
}

// Validate file extension
$fileName = strtolower($resume['name']);
$fileExtension = pathinfo($fileName, PATHINFO_EXTENSION);

if (!in_array($fileExtension, $CONFIG['allowed_extensions'])) {
    http_response_code(400);
    die("Invalid file type. Please upload a PDF, DOC, or DOCX file.");
}

// Validate MIME type (more secure than just checking extension)
if (function_exists('finfo_open')) {
    $finfo = finfo_open(FILEINFO_MIME_TYPE);
    $mimeType = finfo_file($finfo, $resume['tmp_name']);
    finfo_close($finfo);

    if (!in_array($mimeType, $CONFIG['allowed_mime_types'])) {
        http_response_code(400);
        die("Invalid file type detected. Please upload a valid PDF, DOC, or DOCX file.");
    }
}

// ============================================================
// STEP 3: SEND MAIN EMAIL TO ADMIN
// ============================================================

$mail = new PHPMailer(true);

try {
    // Server settings
    $mail->isSMTP();
    $mail->Host = $settings['Host'];
    $mail->SMTPAuth = true;
    $mail->Username = $settings['Username'];
    $mail->Password = $settings['Password'];
    $mail->SMTPSecure = $settings['SMTPSecure'];
    $mail->Port = $settings['Port'];

    // Recipients
    $mail->setFrom($settings['setFrom'], $settings['setFromName']);
    $mail->addAddress($CONFIG['recipient_email'], $CONFIG['recipient_name']);
    $mail->addReplyTo($email, $name);

    // Attachment
    $mail->addAttachment($resume['tmp_name'], $resume['name']);

    // Content
    $mail->isHTML(false);
    $mail->Subject = "New Job Application from $name";

    $emailBody = "You have received a new job application from the TSN Systems career form.\n\n";
    $emailBody .= "APPLICANT DETAILS\n";
    $emailBody .= "==================\n";
    $emailBody .= "Name: $name\n";
    $emailBody .= "Email: $email\n";
    $emailBody .= "State: $state\n";
    $emailBody .= "Resume File: {$resume['name']}\n";
    $emailBody .= "File Size: " . round($resume['size'] / 1024, 2) . " KB\n\n";
    $emailBody .= "Submitted: " . date('F j, Y, g:i a T') . "\n";
    $emailBody .= "==================\n\n";
    $emailBody .= "Please review the attached resume and contact the applicant if suitable.\n";

    $mail->Body = $emailBody;

    // Send the email
    if (!$mail->send()) {
        throw new Exception("Failed to send email: " . $mail->ErrorInfo);
    }

} catch (Exception $e) {
    http_response_code(500);
    die("Failed to send your application. Please try again or contact us directly at info@tsnsys.us");
}

// ============================================================
// STEP 4: SEND THANK YOU EMAIL TO APPLICANT
// ============================================================

if ($CONFIG['send_thank_you']) {
    $thankYouMail = new PHPMailer(true);

    try {
        // Server settings
        $thankYouMail->isSMTP();
        $thankYouMail->Host = $settings['Host'];
        $thankYouMail->SMTPAuth = true;
        $thankYouMail->Username = $settings['Username'];
        $thankYouMail->Password = $settings['Password'];
        $thankYouMail->SMTPSecure = $settings['SMTPSecure'];
        $thankYouMail->Port = $settings['Port'];

        // Recipients
        $thankYouMail->setFrom($settings['setFrom'], $settings['setFromName']);
        $thankYouMail->addAddress($email, $name);

        // Content
        $thankYouMail->isHTML(false);
        $thankYouMail->Subject = "Thank You for Your Application - TSN Systems";

        $thankYouBody = "Dear $name,\n\n";
        $thankYouBody .= "Thank you for your interest in joining TSN Systems!\n\n";
        $thankYouBody .= "We have successfully received your job application and resume. Our recruitment team will carefully review your qualifications and experience.\n\n";
        $thankYouBody .= "APPLICATION SUMMARY\n";
        $thankYouBody .= "-------------------\n";
        $thankYouBody .= "Name: $name\n";
        $thankYouBody .= "Email: $email\n";
        $thankYouBody .= "State: $state\n";
        $thankYouBody .= "Resume: {$resume['name']}\n";
        $thankYouBody .= "Submitted: " . date('F j, Y') . "\n\n";
        $thankYouBody .= "WHAT'S NEXT?\n";
        $thankYouBody .= "-------------------\n";
        $thankYouBody .= "If your qualifications match our current openings, a member of our team will contact you within 1-2 weeks to discuss next steps.\n\n";
        $thankYouBody .= "Please note that due to the high volume of applications we receive, we may not be able to respond to every applicant individually. If you don't hear from us within 2 weeks, we encourage you to check our careers page for other opportunities.\n\n";
        $thankYouBody .= "We appreciate your interest in TSN Systems and wish you all the best in your career journey.\n\n";
        $thankYouBody .= "Best regards,\n";
        $thankYouBody .= "The TSN Systems Recruitment Team\n\n";
        $thankYouBody .= "-------------------\n";
        $thankYouBody .= "TSN Systems\n";
        $thankYouBody .= "2914 Pine Ave #1039\n";
        $thankYouBody .= "Niagara Falls, NY 14301\n";
        $thankYouBody .= "Phone: +1 (716) 368-7617\n";
        $thankYouBody .= "Email: info@tsnsys.us\n";

        $thankYouMail->Body = $thankYouBody;

        // Send thank you email (don't fail if this doesn't work)
        $thankYouMail->send();

    } catch (Exception $e) {
        // Log error but don't show to user
        error_log("Thank you email failed: " . $e->getMessage());
    }
}

// ============================================================
// STEP 5: SEND SUCCESS RESPONSE
// ============================================================

http_response_code(200);
echo "Thank you for your application! We have received your resume and will review it carefully. You should receive a confirmation email shortly.";
exit;
?>