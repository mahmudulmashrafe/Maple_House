<?php
/**
 * Email Notification System for Maple House
 * Handles sending renewal reminders and payment notifications
 */

require_once '../config/database.php';

class EmailNotifications {
    private $db;
    private $from_email = 'admin@maplehouse.com';
    private $from_name = 'Maple House Administration';
    
    public function __construct() {
        $database = new Database();
        $this->db = $database->getConnection();
    }
    
    /**
     * Send renewal reminder emails to residents
     */
    public function sendRenewalReminders() {
        try {
            // Find residents with renewals due in next 3 days
            $reminder_query = "SELECT r.id, u.first_name, u.last_name, u.email, 
                                     pp.plan_name, pp.monthly_fee,
                                     rh.renewal_due_date, rh.subscription_end_date
                              FROM residents r
                              JOIN users u ON r.user_id = u.id
                              JOIN payment_plans pp ON r.plan_id = pp.id
                              JOIN resident_revenue_history rh ON r.id = rh.resident_id
                              WHERE rh.is_active = 1 
                              AND rh.renewal_due_date BETWEEN CURDATE() AND DATE_ADD(CURDATE(), INTERVAL 3 DAY)
                              AND pp.monthly_fee > 0
                              AND u.email IS NOT NULL
                              ORDER BY rh.renewal_due_date ASC";
            
            $reminder_stmt = $this->db->prepare($reminder_query);
            $reminder_stmt->execute();
            $residents = $reminder_stmt->fetchAll(PDO::FETCH_ASSOC);
            
            $sent_count = 0;
            $error_count = 0;
            
            foreach ($residents as $resident) {
                if ($this->sendRenewalEmail($resident)) {
                    $sent_count++;
                } else {
                    $error_count++;
                }
            }
            
            return [
                'success' => true,
                'sent_count' => $sent_count,
                'error_count' => $error_count,
                'total_residents' => count($residents)
            ];
            
        } catch (Exception $e) {
            return [
                'success' => false,
                'message' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Send individual renewal reminder email
     */
    private function sendRenewalEmail($resident) {
        $to = $resident['email'];
        $subject = "Subscription Renewal Reminder - Maple House";
        
        $days_until_due = (strtotime($resident['renewal_due_date']) - time()) / (60 * 60 * 24);
        $days_until_due = max(0, ceil($days_until_due));
        
        $message = $this->getRenewalEmailTemplate($resident, $days_until_due);
        
        $headers = [
            'From: ' . $this->from_name . ' <' . $this->from_email . '>',
            'Reply-To: ' . $this->from_email,
            'X-Mailer: PHP/' . phpversion(),
            'MIME-Version: 1.0',
            'Content-Type: text/html; charset=UTF-8'
        ];
        
        return mail($to, $subject, $message, implode("\r\n", $headers));
    }
    
    /**
     * Get HTML email template for renewal reminder
     */
    private function getRenewalEmailTemplate($resident, $days_until_due) {
        $urgency_class = $days_until_due <= 1 ? 'urgent' : 'normal';
        $urgency_text = $days_until_due <= 1 ? 'URGENT: ' : '';
        
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #2c5aa0; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .urgent { background: #e74c3c; }
                .normal { background: #2c5aa0; }
                .button { display: inline-block; background: #27ae60; color: white; padding: 12px 24px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
                .details { background: white; padding: 15px; margin: 15px 0; border-left: 4px solid #3498db; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header {$urgency_class}'>
                    <h1>{$urgency_text}Subscription Renewal Reminder</h1>
                    <p>Maple House - Your Home Away From Home</p>
                </div>
                
                <div class='content'>
                    <h2>Dear {$resident['first_name']} {$resident['last_name']},</h2>
                    
                    <p>This is a friendly reminder that your subscription renewal is due soon.</p>
                    
                    <div class='details'>
                        <h3>Subscription Details:</h3>
                        <p><strong>Plan:</strong> {$resident['plan_name']}</p>
                        <p><strong>Monthly Fee:</strong> ৳" . number_format($resident['monthly_fee']) . "</p>
                        <p><strong>Renewal Due Date:</strong> " . date('F j, Y', strtotime($resident['renewal_due_date'])) . "</p>
                        <p><strong>Days Remaining:</strong> {$days_until_due} day(s)</p>
                    </div>
                    
                    <p>To ensure uninterrupted service, please make your payment before the due date. 
                    After the grace period (2 days), your account will be automatically moved to the basic plan.</p>
                    
                    <p><strong>Payment Options:</strong></p>
                    <ul>
                        <li>Visit the front desk</li>
                        <li>Bank transfer</li>
                        <li>Cash payment</li>
                    </ul>
                    
                    <p>If you have any questions or need assistance, please contact our administration team.</p>
                    
                    <p>Thank you for choosing Maple House!</p>
                    
                    <p>Best regards,<br>
                    Maple House Administration Team</p>
                </div>
                
                <div class='footer'>
                    <p>This is an automated message. Please do not reply to this email.</p>
                    <p>Maple House | Contact: admin@maplehouse.com | Phone: +880-XXX-XXXX</p>
                </div>
            </div>
        </body>
        </html>";
    }
    
    /**
     * Send payment confirmation email
     */
    public function sendPaymentConfirmation($resident_id, $transaction_id) {
        try {
            // Get resident and payment details
            $payment_query = "SELECT r.id, u.first_name, u.last_name, u.email,
                                    pp.plan_name, rh.amount, rh.payment_date, rh.transaction_id,
                                    rh.subscription_start_date, rh.subscription_end_date
                             FROM residents r
                             JOIN users u ON r.user_id = u.id
                             JOIN payment_plans pp ON r.plan_id = pp.id
                             JOIN resident_revenue_history rh ON r.id = rh.resident_id
                             WHERE r.id = :resident_id AND rh.transaction_id = :transaction_id";
            
            $payment_stmt = $this->db->prepare($payment_query);
            $payment_stmt->bindParam(':resident_id', $resident_id);
            $payment_stmt->bindParam(':transaction_id', $transaction_id);
            $payment_stmt->execute();
            $payment_data = $payment_stmt->fetch(PDO::FETCH_ASSOC);
            
            if (!$payment_data || !$payment_data['email']) {
                return false;
            }
            
            $to = $payment_data['email'];
            $subject = "Payment Confirmation - Maple House";
            $message = $this->getPaymentConfirmationTemplate($payment_data);
            
            $headers = [
                'From: ' . $this->from_name . ' <' . $this->from_email . '>',
                'Reply-To: ' . $this->from_email,
                'X-Mailer: PHP/' . phpversion(),
                'MIME-Version: 1.0',
                'Content-Type: text/html; charset=UTF-8'
            ];
            
            return mail($to, $subject, $message, implode("\r\n", $headers));
            
        } catch (Exception $e) {
            error_log("Payment confirmation email error: " . $e->getMessage());
            return false;
        }
    }
    
    /**
     * Get HTML email template for payment confirmation
     */
    private function getPaymentConfirmationTemplate($payment_data) {
        return "
        <!DOCTYPE html>
        <html>
        <head>
            <meta charset='UTF-8'>
            <style>
                body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; }
                .container { max-width: 600px; margin: 0 auto; padding: 20px; }
                .header { background: #27ae60; color: white; padding: 20px; text-align: center; }
                .content { background: #f9f9f9; padding: 20px; }
                .receipt { background: white; padding: 20px; margin: 15px 0; border: 2px solid #27ae60; }
                .amount { font-size: 24px; font-weight: bold; color: #27ae60; text-align: center; margin: 15px 0; }
                .footer { text-align: center; padding: 20px; color: #666; font-size: 12px; }
            </style>
        </head>
        <body>
            <div class='container'>
                <div class='header'>
                    <h1>✓ Payment Confirmed</h1>
                    <p>Thank you for your payment!</p>
                </div>
                
                <div class='content'>
                    <h2>Dear {$payment_data['first_name']} {$payment_data['last_name']},</h2>
                    
                    <p>We have successfully received your subscription payment. Your account has been updated.</p>
                    
                    <div class='receipt'>
                        <h3>Payment Receipt</h3>
                        <p><strong>Transaction ID:</strong> {$payment_data['transaction_id']}</p>
                        <p><strong>Plan:</strong> {$payment_data['plan_name']}</p>
                        <div class='amount'>Amount Paid: ৳" . number_format($payment_data['amount']) . "</div>
                        <p><strong>Payment Date:</strong> " . date('F j, Y', strtotime($payment_data['payment_date'])) . "</p>
                        <p><strong>Subscription Period:</strong> " . 
                            date('F j, Y', strtotime($payment_data['subscription_start_date'])) . " to " . 
                            date('F j, Y', strtotime($payment_data['subscription_end_date'])) . "</p>
                    </div>
                    
                    <p>Your subscription is now active and will continue until the end date shown above.</p>
                    
                    <p>If you have any questions about your payment or subscription, please contact our administration team.</p>
                    
                    <p>Thank you for choosing Maple House!</p>
                    
                    <p>Best regards,<br>
                    Maple House Administration Team</p>
                </div>
                
                <div class='footer'>
                    <p>Keep this email as your payment receipt.</p>
                    <p>Maple House | Contact: admin@maplehouse.com | Phone: +880-XXX-XXXX</p>
                </div>
            </div>
        </body>
        </html>";
    }
}

// If called directly (for cron job)
if (php_sapi_name() === 'cli') {
    $emailSystem = new EmailNotifications();
    $result = $emailSystem->sendRenewalReminders();
    
    if ($result['success']) {
        echo "Renewal reminders sent: {$result['sent_count']} successful, {$result['error_count']} errors\n";
    } else {
        echo "Error sending reminders: {$result['message']}\n";
    }
}
?>
