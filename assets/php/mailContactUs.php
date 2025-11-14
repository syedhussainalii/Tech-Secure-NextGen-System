<?php
// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
require '../../vendor/autoload.php';
require './secrets.php';


$mailProvider = 'smtp';

/**
 * Toggle for sending thank you email to the user
 * true = Send thank you email to user
 * false = Do not send thank you email to user
 */
$sendThxEmail = true;

$recipientEmail = 'tahapasha2008@gmail.com';
$recipientName = 'Taha Pasha (Admin)';

/**
 * GMAIL SENDER SETTINGS
 * (Used only if $mailProvider = 'gmail')
 */
$gmailSettings = [
    'Host'       => 'smtp.gmail.com',
    'Username'   => GMAIL_USER,
    'Password'   => GMAIL_PASS,
    'SMTPSecure' => PHPMailer::ENCRYPTION_STARTTLS,
    'Port'       => 587,
    'setFrom'    => GMAIL_USER,
    'setFromName'=> 'TSN Website (via Gmail)'
];

// CUSTOM SMTP SETTINGS (now reads from config.php)
$smtpSettings = [
    'Host'       => SMTP_HOST,
    'Username'   => SMTP_USER,
    'Password'   => SMTP_PASS,
    'SMTPSecure' => PHPMailer::ENCRYPTION_STARTTLS, // Updated for standard SMTP
    'Port'       => 587, // Updated for STARTTLS
    'setFrom'    => SMTP_USER,
    'setFromName'=> 'TSN Contact Form'
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

    // Determine if form is from homepage (index.html) or contact page
    $isFromHomepage = isset($_POST['homepage_form']) && $_POST['homepage_form'] === 'true';

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
        
        // Set who the email is FROM
        $mail->setFrom($settings['setFrom'], $settings['setFromName']);
        
        // Add the RECIPIENT (where the email is going)
        $mail->addAddress($recipientEmail, $recipientName);

        // Add the user's email as the "Reply-To"
        $mail->addReplyTo($email, $name);

        // 5. CONTENT
        $mail->isHTML(false); // Plain text

        if ($isFromHomepage) {
            $mail->Subject = "Message Sent from Homepage form";
            $body = "You have received a new message from your website homepage form.\n\n";
        } else {
            $mail->Subject = "New Contact Form Submission from $name";
            $body = "You have received a new message from your website contact form.\n\n";
        }

        $body .= "Name: $name\n";
        $body .= "Email: $email\n";
        $body .= "Phone: $phone\n\n";
        $body .= "Message:\n$message\n";
        
        $mail->Body = $body;

        // 6. SEND THE EMAIL
        $mail->send();

        // Send thank you email to the user if the setting is enabled
        if ($sendThxEmail) {
            $thankYouMail = new PHPMailer(true);

            try {
                // Apply the same server settings for sending thank you email
                $thankYouMail->isSMTP();
                $thankYouMail->Host       = $settings['Host'];
                $thankYouMail->SMTPAuth   = true;
                $thankYouMail->Username   = $settings['Username'];
                $thankYouMail->Password   = $settings['Password'];
                $thankYouMail->SMTPSecure = $settings['SMTPSecure'];
                $thankYouMail->Port       = $settings['Port'];

                // Set who the thank you email is FROM
                $thankYouMail->setFrom($settings['setFrom'], $settings['setFromName']);

                // Add the USER as the recipient for the thank you email
                $thankYouMail->addAddress($email, $name);

                // Set the subject and content for the thank you email
                $thankYouMail->isHTML(false); // Plain text
                $thankYouMail->Subject = "Thank You for Contacting Us";

                $thankYouBody = "Dear $name,\n\n";
                $thankYouBody .= "Thank you for reaching out to us. We have received your message and will reply shortly.\n\n";
                $thankYouBody .= "Here is a copy of your message:\n\n";
                $thankYouBody .= "Name: $name\n";
                $thankYouBody .= "Email: $email\n";
                $thankYouBody .= "Phone: $phone\n\n";
                $thankYouBody .= "Message:\n$message\n\n";
                $thankYouBody .= "Best regards,\n";
                $thankYouBody .= "The TSN Team";

                $thankYouMail->Body = $thankYouBody;

                // Send the thank you email
                $thankYouMail->send();
            } catch (Exception $e) {
                // If sending thank you email fails, log the error but don't show to user
                error_log("Thank you email failed to send: " . $thankYouMail->ErrorInfo);
            }
        }

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