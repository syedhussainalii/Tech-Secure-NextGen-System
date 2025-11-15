<?php
// Import PHPMailer classes
use PHPMailer\PHPMailer\PHPMailer;
use PHPMailer\PHPMailer\SMTP;
use PHPMailer\PHPMailer\Exception;

// Load Composer's autoloader
// ! Make sure this path is correct for your file structure
require '../../vendor/autoload.php';
// ! Make sure this path is correct
require './secrets.php';


$mailProvider = 'smtp';

/**
 * Toggle for sending thank you email to the user
 * true = Send thank you email to user
 * false = Do not send thank you email to user
 */
$sendThxEmail = true;

$recipientEmail = 'tahapasha2008@gmail.com';
$recipientName = 'Information TSN';

/**
 * GMAIL SENDER SETTINGS
 * (Used only if $mailProvider = 'gmail')
 */
$gmailSettings = [
    'Host' => 'smtp.gmail.com',
    'Username' => GMAIL_USER,
    'Password' => GMAIL_PASS,
    'SMTPSecure' => PHPMailer::ENCRYPTION_STARTTLS,
    'Port' => 587,
    'setFrom' => GMAIL_USER,
    'setFromName' => 'TSN Website (via Gmail)'
];

// CUSTOM SMTP SETTINGS (now reads from config.php)
$smtpSettings = [
    'Host' => SMTP_HOST,
    'Username' => SMTP_USER,
    'Password' => SMTP_PASS,
    'SMTPSecure' => PHPMailer::ENCRYPTION_STARTTLS, // Updated for standard SMTP
    'Port' => 587, // Updated for STARTTLS
    'setFrom' => SMTP_USER,
    'setFromName' => 'TSN Application Form' // Changed name
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
    echo "Server Error: Invalid mail provider configured.";
    exit;
}
// Check if the form was submitted using POST
if ($_SERVER["REQUEST_METHOD"] == "POST") {

    // 1. GET AND SANITIZE FORM DATA
    $name = trim(htmlspecialchars($_POST['full_name']));
    $email = trim(htmlspecialchars($_POST['email']));
    $state = trim(htmlspecialchars($_POST['state']));

    // 2. VALIDATE DATA
    if (empty($name) || empty($email) || empty($state) || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
        http_response_code(400);
        echo "Please fill out all required fields and use a valid email address.";
        exit;
    }

    // 3. VALIDATE FILE UPLOAD
    if (!isset($_FILES['resume']) || $_FILES['resume']['error'] != UPLOAD_ERR_OK) {
        http_response_code(400);
        echo "Error uploading resume. Please try again.";
        // More detailed error checking could go here
        // e.g., check $_FILES['resume']['error'] for specific codes
        exit;
    }

    // Create an instance of PHPMailer
    $mail = new PHPMailer(true);

    try {
        // 3. APPLY SERVER SETTINGS (from the $settings array)
        $mail->isSMTP();
        $mail->Host = $settings['Host'];
        $mail->SMTPAuth = true;
        $mail->Username = $settings['Username'];
        $mail->Password = $settings['Password'];
        $mail->SMTPSecure = $settings['SMTPSecure'];
        $mail->Port = $settings['Port'];

        // Set who the email is FROM
        $mail->setFrom($settings['setFrom'], $settings['setFromName']);

        // Add the RECIPIENT (where the email is going)
        $mail->addAddress($recipientEmail, $recipientName);

        // Add the user's email as the "Reply-To"
        $mail->addReplyTo($email, $name);

        // 4. ADD ATTACHMENT
        $mail->addAttachment($_FILES['resume']['tmp_name'], $_FILES['resume']['name']);

        // 5. CONTENT
        $mail->isHTML(false); // Plain text
        $mail->Subject = "New Job Application from $name";

        $body = "You have received a new job application.\n\n";
        $body .= "Full Name: $name\n";
        $body .= "Email: $email\n";
        $body .= "State: $state\n\n";
        $body .= "The applicant's resume is attached to this email.\n";

        $mail->Body = $body;

        // 6. SEND THE EMAIL
        $mail->send();

        // Send thank you email to the user if the setting is enabled
        if ($sendThxEmail) {
            $thankYouMail = new PHPMailer(true);

            try {
                // Apply the same server settings for sending thank you email
                $thankYouMail->isSMTP();
                $thankYouMail->Host = $settings['Host'];
                $thankYouMail->SMTPAuth = true;
                $thankYouMail->Username = $settings['Username'];
                $thankYouMail->Password = $settings['Password'];
                $thankYouMail->SMTPSecure = $settings['SMTPSecure'];
                $thankYouMail->Port = $settings['Port'];

                // Set who the thank you email is FROM
                $thankYouMail->setFrom($settings['setFrom'], $settings['setFromName']);

                // Add the USER as the recipient for the thank you email
                $thankYouMail->addAddress($email, $name);

                // Set the subject and content for the thank you email
                $thankYouMail->isHTML(false); // Plain text
                $thankYouMail->Subject = "Thank You for Your Application";

                $thankYouBody = "Dear $name,\n\n";
                $thankYouBody .= "Thank you for applying. We have received your application and will review it shortly.\n\n";
                $thankYouBody .= "Here is a copy of the information you submitted:\n\n";
                $thankYouBody .= "Full Name: $name\n";
                $thankYouBody .= "Email: $email\n";
                $thankYouBody .= "State: $state\n\n";
                $thankYouBody .= "Your resume was also successfully received.\n\n";
                $thankYouBody .= "Best regards,\n";
                $thankYouBody .= "The Hiring Team";

                $thankYouMail->Body = $thankYouBody;

                // Send the thank you email
                $thankYouMail->send();
            } catch (Exception $e) {
                // If sending thank you email fails, log the error but don't show to user
                error_log("Thank you email failed to send: " . $thankYouMail->ErrorInfo);
            }
        }

        http_response_code(200);
        // You would typically redirect to a "thank you" page,
        // but for a simple example, we just echo a message.
        echo 'Thank you! Your application has been sent successfully.';

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