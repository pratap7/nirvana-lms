<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Domain\Billing\Actions\ProcessWebhookAction;
use App\Domain\Billing\Exceptions\TamperedAmountException;
use App\Domain\Billing\Exceptions\WebhookVerificationException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;

class BillingWebhookController extends Controller
{
    /**
     * Handle incoming Stripe webhook notifications.
     */
    public function handleStripe(Request $request, ProcessWebhookAction $processWebhook): JsonResponse
    {
        $rawPayload = (string) $request->getContent();
        $signature = (string) $request->header('Stripe-Signature', '');

        if (empty($signature)) {
            return response()->json(['error' => 'Missing Stripe-Signature header'], 400);
        }

        try {
            $result = $processWebhook->execute(
                rawPayload: $rawPayload,
                signature: $signature,
                gatewayName: 'stripe'
            );

            return response()->json([
                'received' => true,
                'status' => $result['status'],
                'event_id' => $result['event']->gateway_event_id,
            ], 200);
        } catch (WebhookVerificationException $e) {
            Log::warning('Stripe webhook verification rejected', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'Invalid signature'], 400);
        } catch (TamperedAmountException $e) {
            Log::error('Stripe webhook payment amount tampering detected', ['message' => $e->getMessage()]);

            return response()->json(['error' => 'Amount verification failed'], 422);
        } catch (\Throwable $e) {
            Log::error('Stripe webhook processing error', ['exception' => $e]);

            return response()->json(['error' => 'Webhook processing failed'], 500);
        }
    }
}
