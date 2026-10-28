<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** One page that leads to every setup screen, so the side menu can stay short. */
class ManageController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $owner = $user->role === 'owner';

        $groups = [
            'Your business' => array_filter([
                $owner ? ['Business and team', 'settings', 'Name, time zone, colour, team members and their roles.'] : null,
                $owner ? ['Plan and billing', 'billing', 'Your plan, what you have used, payments.'] : null,
                $owner ? ['Getting started', 'setup', 'Draft your services from a price list, connect, test, go live.'] : null,
            ]),
            'Chat and bookings' => array_filter([
                $owner ? ['Menu builder', 'menu', 'What the bot says and the buttons customers see.'] : null,
                $owner ? ['Booking setup', 'booking.setup', 'Services, prices, practitioners, opening hours, booking rules.'] : null,
                $owner ? ['Channels', 'channels', 'Connect WhatsApp, Messenger and Instagram.'] : null,
                ['AI assistant', 'ai', 'Let the AI help, teach it extra answers, see its usage.'],
                ['Message templates', 'templates', 'Approved WhatsApp messages for after 24 hours.'],
            ]),
            'Organise' => [
                ['Saved replies', 'library', 'Ready-made answers your team can insert in a chat.'],
                ['Tags', 'tags', 'Labels for customers and chats.'],
            ],
        ];

        return view('admin.manage', ['business' => $user->business, 'active' => 'manage', 'groups' => array_filter($groups)]);
    }
}
