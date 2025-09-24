# Laravel Mail Configuration for Production

This document provides comprehensive instructions for configuring Laravel mail settings for production deployment of the e-commerce checkout system.

## Overview

The e-commerce system sends automated emails for:
- **Order Confirmation** - Sent immediately after order placement
- **Payment Approval** - Sent when admin approves payment
- **Payment Rejection** - Sent when admin rejects payment with re-payment instructions

## Production Mail Configuration

### 1. Environment Variables

Copy the following configuration to your `.env` file and customize with your mail service credentials:

```env
# Mail Configuration
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
MAIL_FROM_ADDRESS="noreply@yourstore.com"
MAIL_FROM_NAME="Your Store Name"
```

### 2. Popular SMTP Providers

#### Gmail SMTP
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.gmail.com
MAIL_PORT=587
MAIL_USERNAME=your-email@gmail.com
MAIL_PASSWORD=your-app-password
MAIL_ENCRYPTION=tls
```

**Setup Instructions:**
1. Enable 2-factor authentication on your Gmail account
2. Generate an App Password: Google Account → Security → App passwords
3. Use the App Password (not your regular password) in `MAIL_PASSWORD`

#### SendGrid
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.sendgrid.net
MAIL_PORT=587
MAIL_USERNAME=apikey
MAIL_PASSWORD=your-sendgrid-api-key
MAIL_ENCRYPTION=tls
```

**Setup Instructions:**
1. Create a SendGrid account
2. Generate an API key in SendGrid dashboard
3. Use "apikey" as username and your API key as password

#### Mailgun
```env
MAIL_MAILER=smtp
MAIL_HOST=smtp.mailgun.org
MAIL_PORT=587
MAIL_USERNAME=your-mailgun-username
MAIL_PASSWORD=your-mailgun-password
MAIL_ENCRYPTION=tls
```

#### Amazon SES
```env
MAIL_MAILER=ses
AWS_ACCESS_KEY_ID=your-access-key
AWS_SECRET_ACCESS_KEY=your-secret-key
AWS_DEFAULT_REGION=us-east-1
MAIL_FROM_ADDRESS="noreply@yourstore.com"
```

### 3. Development/Testing Configuration

For development and testing, use the log driver to write emails to log files:

```env
MAIL_MAILER=log
```

Emails will be written to `storage/logs/laravel.log` instead of being sent.

## Email Templates

The system includes three professional HTML email templates:

1. **Order Confirmation** (`resources/views/emails/order-confirmation.blade.php`)
   - Sent immediately after order placement
   - Includes order details, items, shipping address, and payment status

2. **Payment Approved** (`resources/views/emails/order-approved.blade.php`)
   - Sent when admin approves payment
   - Confirms payment verification and order processing

3. **Payment Rejected** (`resources/views/emails/order-rejected.blade.php`)
   - Sent when admin rejects payment
   - Includes rejection reason and re-payment instructions

## Mail Classes

Laravel Mail classes handle email sending:

- `App\Mail\OrderConfirmation` - Order confirmation emails
- `App\Mail\OrderApproved` - Payment approval emails
- `App\Mail\OrderRejected` - Payment rejection emails

## Error Handling

All email sending is wrapped in try-catch blocks with logging:

```php
try {
    Mail::to($user->email)->send(new OrderConfirmation($order));
} catch (\Exception $e) {
    Log::error('Failed to send order confirmation email: ' . $e->getMessage());
}
```

Failed emails are logged to `storage/logs/laravel.log` for debugging.

## Testing Email Configuration

### 1. Test SMTP Connection

Use Laravel Tinker to test your mail configuration:

```bash
php artisan tinker
```

```php
Mail::raw('Test email', function ($message) {
    $message->to('test@example.com')->subject('Test Email');
});
```

### 2. Queue Configuration

For high-volume production environments, consider using queues for email sending:

```env
QUEUE_CONNECTION=database
```

Then dispatch emails to queue:

```php
Mail::to($user->email)->queue(new OrderConfirmation($order));
```

Run the queue worker:

```bash
php artisan queue:work
```

## Security Considerations

1. **Use App Passwords** - Never use your main email password
2. **Environment Variables** - Keep credentials in `.env`, never commit to version control
3. **From Address** - Use a professional "noreply" or "orders" email address
4. **Rate Limiting** - Most providers have sending limits, monitor usage
5. **SPF/DKIM** - Configure DNS records for better deliverability

## Troubleshooting

### Common Issues

1. **Authentication Failed**
   - Verify username/password are correct
   - For Gmail, ensure App Password is used
   - Check if 2FA is enabled

2. **Connection Timeout**
   - Verify SMTP host and port
   - Check firewall settings
   - Try different ports (25, 465, 587)

3. **Emails Not Delivered**
   - Check spam folders
   - Verify DNS records (SPF, DKIM)
   - Monitor provider logs

### Debug Mode

Enable mail debugging in `.env`:

```env
LOG_LEVEL=debug
```

Check logs in `storage/logs/laravel.log` for detailed error messages.

## Production Checklist

- [ ] Configure SMTP credentials in `.env`
- [ ] Test email sending with sample order
- [ ] Verify all three email types (confirmation, approval, rejection)
- [ ] Set up proper "from" address and name
- [ ] Configure DNS records for deliverability
- [ ] Monitor email logs for errors
- [ ] Set up queue workers if using queues
- [ ] Test email delivery to different providers (Gmail, Outlook, etc.)

## Support

For additional help with mail configuration:
- Laravel Mail Documentation: https://laravel.com/docs/mail
- Provider-specific setup guides in their documentation
- Check `storage/logs/laravel.log` for error details
