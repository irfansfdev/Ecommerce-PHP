<?php
use PHPMailer\PHPMailer\PHPMailer;

require_once __DIR__ . '/Env.php';
$autoloadFile = dirname(__DIR__) . '/vendor/autoload.php';
if (is_file($autoloadFile)) {
    require_once $autoloadFile;
}

class EmailService
{
    public static function sendOrderConfirmation(Database $db, $orderId)
    {
        return self::safely('order confirmation #' . (int) $orderId, function () use ($db, $orderId) {
            $order = $db->selectOne(
                "SELECT o.*, u.name AS customer_name, u.email AS customer_email
                 FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?",
                [(int) $orderId]
            );

            if (!$order) {
                throw new RuntimeException('Order or customer record was not found.');
            }

            $items = $db->select(
                "SELECT oi.quantity, oi.unit_price, oi.subtotal, p.name
                 FROM order_items oi JOIN products p ON p.id = oi.product_id
                 WHERE oi.order_id = ? ORDER BY oi.id ASC",
                [(int) $orderId]
            );
            $summary = self::formatItems($items);
            $paymentMethod = self::paymentMethodLabel($order['payment_method']);
            $orderDate = self::formatDate($order['created_at']);
            $brand = self::storeName();
            $statusBadge = self::statusBadge($order['order_status']);
            $stripePaymentNoteHtml = '';
            $stripePaymentNoteText = '';
            if ($order['payment_method'] === 'stripe' && $order['payment_status'] === 'completed') {
                $stripePaymentNoteHtml = '<p>Your card payment was successfully completed.</p>';
                $stripePaymentNoteText = "Your card payment was successfully completed.\n\n";
            }

            $html = '<p>Hi ' . self::escape($order['customer_name']) . ',</p>'
                . '<p>Thanks for choosing ' . self::escape($brand) . '. We have received your order and our team is getting it ready.</p>'
                . $stripePaymentNoteHtml
                . self::detailsTable([
                    ['Order ID', '#' . (int) $order['id']],
                    ['Order number', self::escape($order['order_number'])],
                    ['Order date', self::escape($orderDate)],
                    ['Payment method', self::escape($paymentMethod)],
                    ['Order status', $statusBadge],
                ])
                . self::orderSummaryHtml($summary, $order['total_amount'])
                . '<h2 style="font-size:16px;margin:28px 0 8px">Delivery address</h2>'
                . '<p style="margin:0;color:#49515a;line-height:1.6">' . nl2br(self::escape($order['shipping_address'])) . '</p>';

            $text = 'Hi ' . $order['customer_name'] . ",\n\n"
                . 'Thanks for choosing ' . $brand . '. We have received your order and our team is getting it ready.' . "\n\n"
                . $stripePaymentNoteText
                . 'Order ID: #' . (int) $order['id'] . "\n"
                . 'Order number: ' . $order['order_number'] . "\n"
                . 'Order date: ' . $orderDate . "\n"
                . 'Payment method: ' . $paymentMethod . "\n"
                . 'Order status: ' . ucfirst($order['order_status']) . "\n\n"
                . self::orderSummaryText($summary, $order['total_amount']) . "\n"
                . "Delivery address:\n" . $order['shipping_address'];

            return self::send(
                $order['customer_email'],
                $order['customer_name'],
                'Order Confirmed! Your Order #' . self::subjectValue($order['order_number']) . ' Has Been Received',
                self::emailLayout('We have received your order', $html, 'View Your Orders'),
                self::plainTextFooter($text),
                'order confirmation #' . $order['order_number']
            );
        });
    }

    public static function sendOrderStatusUpdate(Database $db, $orderId, $previousStatus)
    {
        if ($previousStatus === '') {
            return false;
        }

        return self::safely('order status update #' . (int) $orderId, function () use ($db, $orderId, $previousStatus) {
            $order = $db->selectOne(
                "SELECT o.*, u.name AS customer_name, u.email AS customer_email
                 FROM orders o JOIN users u ON u.id = o.user_id WHERE o.id = ?",
                [(int) $orderId]
            );

            if (!$order) {
                throw new RuntimeException('Order or customer record was not found.');
            }

            $newStatus = $order['order_status'];
            if ($previousStatus === $newStatus) {
                return false;
            }

            $statusContent = [
                'shipped' => [
                    'subject' => 'Your Order #ORDER# Is On Its Way',
                    'heading' => 'Your order is on its way',
                    'message' => 'Good news! Your order has left our store and is now on its way to you.',
                ],
                'delivered' => [
                    'subject' => 'Your Order #ORDER# Has Been Delivered',
                    'heading' => 'Your order has been delivered',
                    'message' => 'Your order has been successfully delivered. We hope you enjoy your purchase!',
                ],
                'cancelled' => [
                    'subject' => 'Your Order #ORDER# Has Been Cancelled',
                    'heading' => 'Your order has been cancelled',
                    'message' => 'Your order has been cancelled. If this was unexpected, please contact our support team and we will be happy to help.',
                ],
            ];

            if (!isset($statusContent[$newStatus])) {
                return false;
            }

            $copy = $statusContent[$newStatus];
            $orderNumber = self::subjectValue($order['order_number']);
            $updatedAt = date('F j, Y g:i A');
            $items = $db->select(
                "SELECT oi.quantity, oi.unit_price, oi.subtotal, p.name
                 FROM order_items oi JOIN products p ON p.id = oi.product_id
                 WHERE oi.order_id = ? ORDER BY oi.id ASC",
                [(int) $orderId]
            );
            $summary = self::formatItems($items);

            $html = '<p>Hi ' . self::escape($order['customer_name']) . ',</p>'
                . '<p>' . self::escape($copy['message']) . '</p>'
                . self::detailsTable([
                    ['Order ID', '#' . (int) $order['id']],
                    ['Order number', self::escape($order['order_number'])],
                    ['Previous status', self::escape(ucfirst($previousStatus))],
                    ['Current status', self::statusBadge($newStatus)],
                    ['Order total', self::escape(self::formatMoney($order['total_amount']))],
                    ['Payment method', self::escape(self::paymentMethodLabel($order['payment_method']))],
                    ['Payment status', self::escape(self::paymentStatusLabel($order['payment_status']))],
                    ['Updated', self::escape($updatedAt)],
                ])
                . self::orderSummaryHtml($summary, $order['total_amount']);
            $text = 'Hi ' . $order['customer_name'] . ",\n\n" . $copy['message'] . "\n\n"
                . 'Order ID: #' . (int) $order['id'] . "\n"
                . 'Order number: ' . $order['order_number'] . "\n"
                . 'Previous status: ' . ucfirst($previousStatus) . "\n"
                . 'Current status: ' . ucfirst($newStatus) . "\n"
                . 'Order total: ' . self::formatMoney($order['total_amount']) . "\n"
                . 'Payment method: ' . self::paymentMethodLabel($order['payment_method']) . "\n"
                . 'Payment status: ' . self::paymentStatusLabel($order['payment_status']) . "\n"
                . 'Updated: ' . $updatedAt . "\n\n"
                . self::orderSummaryText($summary, $order['total_amount']);

            if ($newStatus === 'delivered') {
                $html .= '<p style="margin:20px 0 0">You can review your order details from your account. We hope you enjoy your purchase.</p>';
                $text .= "\n\nYou can review your order details from your account. We hope you enjoy your purchase.";
            }

            if ($newStatus === 'cancelled') {
                $html .= '<p style="margin:20px 0 0">Need help? Contact us at <a href="mailto:support@shopwave.test" style="color:#2364aa">support@shopwave.test</a>.</p>';
                $text .= "\n\nNeed help? Contact us at support@shopwave.test.";
            }

            return self::send(
                $order['customer_email'],
                $order['customer_name'],
                str_replace('#ORDER#', '#' . $orderNumber, $copy['subject']),
                self::emailLayout($copy['heading'], $html, 'View Your Orders'),
                self::plainTextFooter($text),
                'order status update #' . $order['order_number']
            );
        });
    }

    private static function send($recipient, $recipientName, $subject, $html, $text, $context)
    {
        $username = Env::get('MAIL_USERNAME', '');
        $password = Env::get('MAIL_PASSWORD', '');
        if ($username === '' || $password === '') {
            error_log('Email not sent (' . $context . '): configure MAIL_USERNAME and MAIL_PASSWORD in .env.');
            return false;
        }

        $mailer = new PHPMailer(true);
        $mailer->isSMTP();
        $mailer->Host = Env::get('MAIL_HOST', 'smtp.gmail.com');
        $mailer->SMTPAuth = true;
        $mailer->Username = $username;
        $mailer->Password = $password;
        $mailer->SMTPSecure = PHPMailer::ENCRYPTION_STARTTLS;
        $mailer->Port = (int) Env::get('MAIL_PORT', '587');
        $mailer->Timeout = 15;
        $mailer->CharSet = 'UTF-8';
        $mailer->setFrom(
            Env::get('MAIL_FROM_ADDRESS', $username),
            Env::get('MAIL_FROM_NAME', 'XolvaShop')
        );
        $mailer->addAddress($recipient, $recipientName);
        $mailer->Subject = $subject;
        $mailer->isHTML(true);
        $mailer->Body = $html;
        $mailer->AltBody = $text;
        if (is_file(self::logoPath())) {
            $mailer->addEmbeddedImage(self::logoPath(), 'store-logo', 'store-logo.png', 'base64', 'image/png');
        }
        $mailer->send();

        return true;
    }

    private static function safely($context, callable $operation)
    {
        try {
            return (bool) $operation();
        } catch (Throwable $e) {
            $message = $e->getMessage();
            foreach ([Env::get('MAIL_USERNAME', ''), Env::get('MAIL_PASSWORD', '')] as $secret) {
                if ($secret !== '') {
                    $message = str_replace($secret, '[redacted]', $message);
                }
            }
            error_log('Email processing failed (' . $context . '): ' . $message);
            return false;
        }
    }

    private static function formatItems(array $items)
    {
        $html = '';
        $text = '';
        $subtotal = 0.0;

        foreach ($items as $item) {
            $quantity = (int) $item['quantity'];
            $unitPrice = (float) $item['unit_price'];
            $lineSubtotal = (float) $item['subtotal'];
            $subtotal += $lineSubtotal;
            $html .= '<tr>'
                . '<td class="item-name" style="padding:12px 10px;border-bottom:1px solid #e8edf2;color:#263442;word-break:break-word">' . self::escape($item['name']) . '</td>'
                . '<td align="center" style="padding:12px 6px;border-bottom:1px solid #e8edf2;color:#495766">' . $quantity . '</td>'
                . '<td align="right" style="padding:12px 8px;border-bottom:1px solid #e8edf2;color:#495766;white-space:nowrap">' . self::escape(self::formatMoney($unitPrice)) . '</td>'
                . '<td align="right" style="padding:12px 10px;border-bottom:1px solid #e8edf2;color:#263442;font-weight:600;white-space:nowrap">' . self::escape(self::formatMoney($lineSubtotal)) . '</td>'
                . '</tr>';
            $text .= '- ' . $item['name'] . ' | Qty: ' . $quantity
                . ' | Unit price: ' . self::formatMoney($unitPrice)
                . ' | Subtotal: ' . self::formatMoney($lineSubtotal) . "\n";
        }

        return ['html' => $html, 'text' => $text, 'subtotal' => $subtotal];
    }

    private static function orderSummaryHtml(array $summary, $total)
    {
        return '<h2 style="font-size:16px;margin:28px 0 10px;color:#263442">Order summary</h2>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;border-collapse:collapse;font-size:13px">'
            . '<thead><tr style="background:#f4f7fa;color:#526170">'
            . '<th align="left" style="padding:10px">Item</th><th align="center" style="padding:10px 6px">Qty</th>'
            . '<th align="right" style="padding:10px 8px">Unit price</th><th align="right" style="padding:10px">Subtotal</th>'
            . '</tr></thead><tbody>' . $summary['html'] . '</tbody></table>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;margin-top:10px">'
            . '<tr><td style="padding:5px 10px;color:#586674">Items subtotal</td><td align="right" style="padding:5px 10px;color:#263442">'
            . self::escape(self::formatMoney($summary['subtotal'])) . '</td></tr>'
            . '<tr><td style="padding:10px;border-top:1px solid #dce3e9;color:#263442;font-weight:700">Order total</td>'
            . '<td align="right" style="padding:10px;border-top:1px solid #dce3e9;color:#263442;font-size:16px;font-weight:700">'
            . self::escape(self::formatMoney($total)) . '</td></tr></table>';
    }

    private static function orderSummaryText(array $summary, $total)
    {
        return "Order summary:\n" . $summary['text']
            . 'Items subtotal: ' . self::formatMoney($summary['subtotal']) . "\n"
            . 'Order total: ' . self::formatMoney($total);
    }

    private static function detailsTable(array $rows)
    {
        $html = '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;margin:20px 0;background:#f5f7f9;border:1px solid #e8edf2;border-radius:4px">';
        foreach ($rows as [$label, $value]) {
            $html .= '<tr><td style="padding:9px 12px;color:#667482;font-size:13px">' . self::escape($label) . '</td>'
                . '<td align="right" style="padding:9px 12px;color:#263442;font-size:13px;font-weight:600">' . $value . '</td></tr>';
        }
        return $html . '</table>';
    }

    private static function statusBadge($status)
    {
        $colors = [
            'processing' => ['#fff5df', '#805b12'],
            'shipped' => ['#eaf3ff', '#245a91'],
            'delivered' => ['#eaf6ef', '#28633d'],
            'cancelled' => ['#fbeeee', '#8b3c3c'],
        ];
        [$background, $color] = $colors[$status] ?? ['#eef1f4', '#45515c'];
        return '<span style="display:inline-block;padding:4px 9px;border-radius:12px;background:' . $background . ';color:' . $color . ';font-size:12px">'
            . self::escape(ucfirst($status)) . '</span>';
    }

    private static function emailLayout($heading, $content, $buttonLabel = null)
    {
        $brand = self::escape(self::storeName());
        $logo = is_file(self::logoPath())
            ? '<img src="cid:store-logo" width="150" alt="' . $brand . '" style="display:block;width:150px;max-width:100%;height:auto;border:0">'
            : '<span style="font-size:21px;font-weight:700;color:#18334b">' . $brand . '</span>';
        $button = '';
        $buttonUrl = self::customerOrdersUrl();
        if ($buttonLabel !== null && $buttonUrl !== '') {
            $button = '<table role="presentation" cellpadding="0" cellspacing="0" style="margin:24px 0 8px"><tr><td bgcolor="#245b83" style="border-radius:4px">'
                . '<a href="' . self::escape($buttonUrl) . '" style="display:inline-block;padding:13px 22px;color:#fff;text-decoration:none;font-size:14px;font-weight:700">'
                . self::escape($buttonLabel) . '</a></td></tr></table>';
        }
        $preheader = self::escape($heading);

        return '<!doctype html><html lang="en"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1">'
            . '<style>body{margin:0!important;padding:0!important;background:#f1f4f6}table{border-spacing:0}a{color:#245b83}'
            . '@media only screen and (max-width:620px){.email-shell{width:100%!important}.email-pad{padding:22px 16px!important}.item-name{max-width:120px!important}'
            . 'th,td{font-size:12px!important}.mobile-hide{display:none!important}}</style></head>'
            . '<body style="margin:0;padding:0;background:#f1f4f6;font-family:Arial,Helvetica,sans-serif;color:#263442">'
            . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;color:transparent">' . $preheader . '</div>'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="width:100%;background:#f1f4f6"><tr><td align="center" style="padding:28px 12px">'
            . '<table class="email-shell" role="presentation" width="600" cellpadding="0" cellspacing="0" style="width:100%;max-width:600px;background:#fff;border:1px solid #e4e9ed">'
            . '<tr><td class="email-pad" style="padding:28px 34px 12px;border-bottom:1px solid #edf0f2">' . $logo
            . '<p style="margin:8px 0 0;color:#75818c;font-size:11px;letter-spacing:1px;text-transform:uppercase">' . $brand . ' customer care</p></td></tr>'
            . '<tr><td class="email-pad" style="padding:28px 34px 34px;font-size:14px;line-height:1.65">'
            . '<h1 style="margin:0 0 18px;color:#18334b;font-size:23px;line-height:1.3">' . self::escape($heading) . '</h1>'
            . $content . $button . '</td></tr>'
            . '<tr><td class="email-pad" style="padding:20px 34px;background:#f7f9fa;border-top:1px solid #edf0f2;color:#6d7984;font-size:12px;line-height:1.6">'
            . '<p style="margin:0 0 5px">Questions about your order? Email <a href="mailto:support@shopwave.test" style="color:#245b83">support@shopwave.test</a> or call +0123 456 789.</p>'
            . '<p style="margin:0">Thank you for shopping with ' . $brand . '.</p></td></tr>'
            . '</table></td></tr></table></body></html>';
    }

    private static function plainTextFooter($text)
    {
        return $text . "\n\nQuestions about your order? Email support@shopwave.test or call +0123 456 789.\nThank you for shopping with " . self::storeName() . '.';
    }

    private static function customerOrdersUrl()
    {
        $baseUrl = trim((string) Env::get('APP_URL', ''));
        if ($baseUrl !== '') {
            if (!filter_var($baseUrl, FILTER_VALIDATE_URL)) {
                return '';
            }
            $baseUrl = rtrim($baseUrl, '/');
            if (!preg_match('~/public$~i', $baseUrl)) {
                $baseUrl .= '/public';
            }
            return $baseUrl . '/account.php#tab-orders';
        }

        $host = $_SERVER['HTTP_HOST'] ?? '';
        if (!preg_match('/^[a-z0-9.-]+(?::[0-9]{1,5})?$/i', $host)) {
            return '';
        }

        $scriptDirectory = str_replace('\\', '/', dirname($_SERVER['SCRIPT_NAME'] ?? ''));
        $projectPath = preg_replace('~/(?:admin(?:/.*)?|public)$~i', '', rtrim($scriptDirectory, '/'));
        $scheme = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off') ? 'https' : 'http';
        return $scheme . '://' . $host . rtrim($projectPath, '/') . '/public/account.php#tab-orders';
    }

    private static function logoPath()
    {
        return dirname(__DIR__) . '/public/assets/images/demos/demo-4/logo.png';
    }

    private static function storeName()
    {
        return Env::get('MAIL_FROM_NAME', 'ShopWave');
    }

    private static function paymentMethodLabel($paymentMethod)
    {
        $labels = ['cod' => 'Cash on Delivery', 'stripe' => 'Card payment'];
        return $labels[$paymentMethod] ?? ucfirst((string) $paymentMethod);
    }

    private static function paymentStatusLabel($paymentStatus)
    {
        return $paymentStatus === 'completed' ? 'Paid (Completed)' : ucfirst((string) $paymentStatus);
    }

    private static function formatDate($date)
    {
        $timestamp = strtotime($date);
        return $timestamp === false ? (string) $date : date('F j, Y g:i A', $timestamp);
    }

    private static function formatMoney($amount)
    {
        $currency = strtolower(Env::get('STRIPE_CURRENCY', 'usd'));
        $symbols = ['usd' => '$', 'pkr' => 'Rs. ', 'gbp' => '£', 'eur' => '€'];
        $prefix = $symbols[$currency] ?? strtoupper($currency) . ' ';
        return $prefix . number_format((float) $amount, 2);
    }

    private static function subjectValue($value)
    {
        return trim(preg_replace('/[\r\n]+/', ' ', (string) $value));
    }

    private static function escape($value)
    {
        return htmlspecialchars((string) $value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
    }
}