<?php

namespace App\Http\Controllers;

use App\Concert;
use App\Order;
use App\Reservation;

use Illuminate\Http\Request;
use App\Billing\PaymentGateway;
use App\Billing\PaymentFailedException;
use App\Exceptions\NotEnoughTicketsException;

class ConcertOrdersController extends Controller
{

    private $paymentGateway;

    function __construct(PaymentGateway $paymentGateway)
    {
        $this->paymentGateway = $paymentGateway;
    }

    public function store($concertId)
    {
        $concert = Concert::published()->findOrFail($concertId);

        $this->validate(request(), [
            'email' => ['required', 'email'],
            'ticket_quantity' => ['required', 'integer', 'min:1'],
            'payment_token' => ['required'],
        ]);

        try {
            // Find the tickets for the customer
            $reservation = $concert->reserveTickets(request('ticket_quantity'), request('email'));

            // Charge the customer
            // $this->paymentGateway->charge($tickets->sum('price'), request('payment_token'));
            // $this->paymentGateway->charge($reservation->totalCost(), request('payment_token'));

            // Create order in reservation
            $order = $reservation->complete($this->paymentGateway, request('payment_token'));

            // Create the order (Long Parameter Smell)
            // $order = Order::forTickets($reservation->tickets(), $reservation->email(), $reservation->totalCost());

            // Create the order from a reservation (Feature Envy Smell)
            // $order = Order::fromReservation($reservation);

            return response()->json($order, 201);

        } catch (PaymentFailedException $e) {
            $reservation->cancel();
            return response()->json([], 422);
        } catch (NotEnoughTicketsException $e) {
            return response()->json([], 422);
        }
    }
}
