<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;

/** Plain-language starter text for the terms and the privacy notice. A lawyer should review it before a public launch. */
class LegalController extends Controller
{
    public function terms(): View
    {
        return view('legal.terms', $this->data([
            ['Using the service', [
                'By creating an account or using the service you agree to these terms. If you use it for a business, you confirm you can accept them for that business.',
                'You are responsible for what you and your team send, for keeping your passwords safe, and for the accuracy of the prices, hours and answers you give the assistant.',
            ]],
            ['Messaging rules', [
                'Message only customers who have contacted you or agreed to hear from you. Respect STOP: the service stops promotional messages to anyone who sends it.',
                'Follow the rules of WhatsApp, Instagram, Messenger, Google and any other channel you connect. Do not send spam, scams, illegal or harmful content.',
            ]],
            ['The AI assistant', [
                'The AI writes replies from the information you give it. It can make mistakes, so check what it is allowed to say in your settings and review important chats.',
                'You can pause the AI at any time. Replies are limited by your plan.',
            ]],
            ['Plans and payment', [
                'A free trial runs for a set number of days with no card needed. Paid plans renew monthly until cancelled.',
                'A cancelled plan keeps working until the paid month ends. Prices are shown in US dollars. We may change prices for future months with notice.',
            ]],
            ['Your data', [
                'You own your business data and your customers\' data. We use it only to run the service. You can export your customer list and download or erase a customer\'s data at any time.',
                'See the privacy notice for details.',
            ]],
            ['Pausing and ending accounts', [
                'We may pause an account that breaks these terms or abuses the service, and will tell you why. You can stop using the service at any time and ask us to delete your account.',
            ]],
            ['Limits of the service', [
                'We work to keep the service running, but we cannot promise it will always be available or error free. To the extent the law allows, we are not responsible for losses caused by downtime, messages not delivered by third-party channels, or AI mistakes.',
            ]],
            ['Changes', [
                'We may update these terms. When we make an important change we will tell you. Using the service after a change means you accept it.',
            ]],
        ]));
    }

    public function privacy(): View
    {
        return view('legal.privacy', $this->data([
            ['What we store', [
                ['Your business details, team members and settings.', 'Your customers\' names, phone numbers, emails, chat messages and bookings.', 'Orders, tasks and notes your team enters.', 'Billing records such as plan and payment status. Card details are handled by the payment provider, not stored by us.'],
            ]],
            ['Why we use it', [
                'Only to run the service for you: showing chats, making bookings, sending reminders and reports, and answering customers.',
                'We do not sell your data or your customers\' data.',
            ]],
            ['Who can see it', [
                'Each business sees only its own data. Your team members see what their role allows. Platform staff can see account and usage information to run and support the service.',
            ]],
            ['AI and other services', [
                'If AI mode is on, the text of a chat is sent to our AI provider to write a reply. Chat tools such as Meta (WhatsApp, Instagram, Messenger) and Google (Calendar) process messages and bookings under their own terms.',
            ]],
            ['Customer choices', [
                'Customers can opt out of promotions by typing STOP. A business can download or erase a customer\'s data from the customer page.',
                'We send promotional messages only to people who agreed to receive them.',
            ]],
            ['Cookies and local storage', [
                'Staff sign-in uses a session cookie. The customer chat remembers a private chat code in the browser so a conversation can continue. We do not use advertising trackers.',
            ]],
            ['Keeping and deleting data', [
                'We keep data while your account is active. When you ask us to delete your account we remove your business data, apart from records we must keep by law, such as payments. Backups are overwritten on a regular schedule.',
            ]],
            ['Security', [
                'Passwords are stored hashed. Connections use HTTPS in production, and access is limited by role. No system is perfectly secure, so tell us at once if you suspect a problem.',
            ]],
        ]));
    }

    private function data(array $sections): array
    {
        return ['sections' => $sections, 'updated' => \App\Support\Lang::fmt(\Illuminate\Support\Carbon::create(2026, 10, 28), 'j F Y', 'Y年n月j日'), 'contact' => config('mail.from.address') ?: 'hello@example.com'];
    }
}
