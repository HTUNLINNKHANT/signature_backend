<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\Validator;

class ContactController extends Controller
{
    /**
     * Submit a new contact message and send it via email.
     */
    public function store(Request $request): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255',
            'phone' => 'nullable|string|max:20',
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:5000',
        ]);

        if ($validator->fails()) {
            return response()->json([
                'success' => false,
                'message' => 'Validation failed',
                'errors' => $validator->errors()
            ], 422);
        }

        try {
            $to = 'htunlynnkhant@gmail.com';
            $subject = 'New Contact Form Submission: ' . $request->subject;
            
            $messageBody = "You have received a new message from your website contact form.\n\n";
            $messageBody .= "Here are the details:\n";
            $messageBody .= "--------------------------\n";
            $messageBody .= "Name: " . $request->name . "\n";
            $messageBody .= "Email: " . $request->email . "\n";
            $messageBody .= "Phone: " . ($request->phone ?? 'Not provided') . "\n";
            $messageBody .= "Subject: " . $request->subject . "\n";
            $messageBody .= "Message:\n" . $request->message . "\n";
            $messageBody .= "--------------------------\n";
            $messageBody .= "IP Address: " . $request->ip() . "\n";
            $messageBody .= "User Agent: " . $request->userAgent() . "\n";

            // Using the user's email as the From address can sometimes cause deliverability issues (spam filters).
            // The 'Reply-To' header is a safer way to ensure replies go to the user.
            $headers = 'From: ' . $request->email . "\r\n" .
                       'Reply-To: ' . $request->email . "\r\n" .
                       'X-Mailer: PHP/' . phpversion();

            // Use PHP's mail() function
            $mailSent = mail($to, $subject, $messageBody, $headers);

            if (!$mailSent) {
                 throw new \Exception('The mail() function failed to send the email.');
            }

            return response()->json([
                'success' => true,
                'message' => 'Thank you for your message! It has been sent successfully.'
            ], 200);

        } catch (\Exception $e) {
            // Log the error for debugging purposes
            error_log('Contact form email sending failed: ' . $e->getMessage());

            return response()->json([
                'success' => false,
                'message' => 'Failed to send message. Please try again later.',
                // Optionally return error in debug mode
                'error' => config('app.debug') ? $e->getMessage() : 'A server error occurred.'
            ], 500);
        }
    }
}
