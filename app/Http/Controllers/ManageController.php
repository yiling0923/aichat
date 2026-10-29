<?php

namespace App\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;

/** The few things you set once and rarely change. Day to day setup pages live in the side menu. */
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
            'Organise' => [
                ['Tags', 'tags', 'Labels for customers and chats.'],
            ],
            'You' => [
                ['My account', 'account', 'Your name and password.'],
                ['Phone alerts', 'account', 'Get a pop-up on this device for new chats and bookings.', '#phone-alerts'],
            ],
        ];

        return view('admin.manage', ['business' => $user->business, 'active' => 'manage', 'groups' => array_filter($groups)]);
    }
}
