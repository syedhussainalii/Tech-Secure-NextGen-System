<?php
// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require '../vendor/autoload.php';
require './secrets.php'; // Load sensitive data from a separate file
// ##################################################################
// ### 1. CONFIGURATION SETTINGS (EDIT THIS SECTION)              ###
// ##################################################################

/**
 * Set the mail provider to use.
 * 'gmail' = Use the Gmail settings below.
 * 'smtp'  = Use the Custom SMTP settings below.
 */
$mailProvider = 'gmail';

/**
 * The email address that will RECEIVE the form submissions.
 * (e.g., your personal 'tahapasha2008@gmail.com')
 */
$recipientEmail = 'tahapasha2008@gmail.com';
$recipientName = 'Taha Pasha (Admin)';

/**
 * GMAIL SENDER SETTINGS
 * (Used only if $mailProvider = 'gmail')
 */
// GMAIL SETTINGS (now reads from config.php)
$gmailSettings = [
    'Host'       => 'smtp.gmail.com',
    'Username'   => GMAIL_USER, // 👈 CHANGED
    'Password'   => GMAIL_PASS, // 👈 CHANGED
    'SMTPSecure' => PHPMailer::ENCRYPTION_SMTPS,
    'Port'       => 465,
    'setFrom'    => GMAIL_USER, // 👈 CHANGED
    'setFromName'=> 'TSN Website (via Gmail)'
];

// CUSTOM SMTP SETTINGS (now reads from config.php)
$smtpSettings = [
    'Host'       => SMTP_HOST, // 👈 CHANGED
    'Username'   => SMTP_USER, // 👈 CHANGED
    'Password'   => SMTP_PASS, // 👈 CHANGED
    'SMTPSecure' => PHPMailer::ENCRYPTION_SMTPS,
    'Port'       => 465,
    'setFrom'    => SMTP_USER, // 👈 CHANGED
    'setFromName'=> 'TSN Website Form'
];

// ##################################################################
// ### END OF CONFIGURATION                                       ###
// ##################################################################


// --- No need to edit below this line ---

// Select the correct settings based on the $mailProvider switch
if ($mailProvider == 'gmail') {
    $settings = $gmailSettings;
} elseif ($mailProvider == 'smtp') {
    $settings = $smtpSettings;
} else {
    http_response_code(500);
    echo "Server Error: Invalid mail provider configured in mail.php.";
    exit;
}

// Check if the form was submitted using POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. GET AND SANITIZE FORM DATA
    $name = trim(htmlspecialchars($_POST['name']));
    $email = trim(htmlspecialchars($_POST['email']));
    $phone = trim(htmlspecialchars($_POST['phone']));
    $message = trim(htmlspecialchars($_POST['message']));

    // 2. VALIDATE DATA
    if (empty($name) || empty($email) || empty($message) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Please fill out all required fields and use a valid email address.";
        exit;
    }

    // Create an instance of PHPMailer
    $mail = new PHPMailer(true);

    try {
        // 3. APPLY SERVER SETTINGS (from the $settings array)
        $mail->isSMTP();
        $mail->Host       = $settings['Host'];
        $mail->SMTPAuth   = true;
        $mail->Username   = $settings['Username'];
        $mail->Password   = $settings['Password'];
        $mail->SMTPSecure = $settings['SMTPSecure'];
        $mail->Port       = $settings['Port'];

        // 4. RECIPIENTS
        
        // Set who the email is FROM
        $mail->setFrom($settings['setFrom'], $settings['setFromName']);
        
        // Add the RECIPIENT (where the email is going)
        $mail->addAddress($recipientEmail, $recipientName);

        // Add the user's email as the "Reply-To"
        $mail->addReplyTo($email, $name);

        // 5. CONTENT
        $mail->isHTML(false); // Plain text
        $mail->Subject = "New Contact Form Submission from $name";
        
        $body = "You have received a new message from your website contact form.\n\n";
        $body .= "Here are the details:\n\n";
        $body .= "Name: $name\n";
        $body .= "Email: $email\n";
        $body .= "Phone: $phone\n\n";
        $body .= "Message:\n$message\n";
        
        $mail->Body = $body;

        // 6. SEND THE EMAIL
        $mail->send();
        http_response_code(200);
        echo 'Thank you! Your message has been sent successfully.';

    } catch (Exception $e) {
        http_response_code(500);
        echo "Oops! Something went wrong. Mailer Error: {$mail->ErrorInfo}";
    }

} else {
    // If someone tries to access the file directly
    http_response_code(405);
    echo "This file cannot be accessed directly.";
}
?>