<?php

namespace App\Http\Controllers;

use App\Billing\Stripe;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class StripeWebhookController extends Controller
{
    public function __invoke(Request $request, Stripe $stripe): Response
    {
        $payload = $request->getContent();

        if (! $stripe->validSignature($payload, $request->header('Stripe-Signature'))) {
            return response('Bad signature', 400);
        }

        $stripe->handle((array) json_decode($payload, true));

        return response('ok');
    }
}
