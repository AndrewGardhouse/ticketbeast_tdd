<?php

use App\Ticket;
use App\Concert;
use App\Reservation;

use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Billing\FakePaymentGateway;

class ReservationTest extends TestCase
{
    use DatabaseMigrations;

    /** @test */
    function calculating_the_total_cost()
    {
        // Arrange
        // $concert = Factory(Concert::class)->create(['ticket_price' => 1200])->addTickets(3);
        $tickets = collect([
            // This won't work because the ticket price is accessed through the Concert relationship
            // new Ticket(['price' => 1200]),
            // new Ticket(['price' => 1200]),
            // new Ticket(['price' => 1200]),

            // Mockery::mock(Ticket::class, ['price' => 1200]),
            // Mockery::mock(Ticket::class, ['price' => 1200]),
            // Mockery::mock(Ticket::class, ['price' => 1200]),

            (object) ['price' => 1200],
            (object) ['price' => 1200],
            (object) ['price' => 1200],
        ]);

        // Act
        $reservation = new Reservation($tickets, 'john@example.com');

        // Assert
        $this->assertEquals(3600, $reservation->totalCost());

    }

    /** @test */
    function retrieving_the_reservations_tickets()
    {
        // Arrange
        $tickets = collect([
            (object) ['price' => 1200],
            (object) ['price' => 1200],
            (object) ['price' => 1200],
        ]);

        // Act
        $reservation = new Reservation($tickets, 'john@example.com');

        // Assert
        $this->assertEquals($tickets, $reservation->tickets());

    }

    /** @test */
    function retrieving_the_customers_email()
    {
        // Arrange
        $tickets = collect();

        // Act
        $reservation = new Reservation($tickets, 'john@example.com');

        // Assert
        $this->assertEquals('john@example.com', $reservation->email());

    }

    /** @test */
    function reserved_tickets_are_released_when_a_reservation_is_cancelled() {
        $tickets = collect([
            Mockery::spy(Ticket::class),
            Mockery::spy(Ticket::class),
            Mockery::spy(Ticket::class),
        ]);
        $reservation = new Reservation($tickets, 'john@example.com');

        $reservation->cancel();

        foreach ($tickets as $ticket) {
            $ticket->shouldHaveReceived('release');
        }
    }

    /** @test */
    function completing_a_reservation() {
        // Arrange
        $concert = Factory(Concert::class)->create(['ticket_price' => 1200]);
        $tickets = Factory(Ticket::class, 3)->create(['concert_id' => $concert->id]);
        $reservation = new Reservation($tickets, 'jane@example.com');
        $paymentGateway = new FakePaymentGateway;

        // Act
        $order = $reservation->complete($paymentGateway, $paymentGateway->getValidTestToken());

        // Assert
        $this->assertEquals('jane@example.com', $order->email);
        $this->assertEquals(3, $order->ticketQuantity());
        $this->assertEquals(3600, $order->amount);
        $this->assertEquals(3600, $paymentGateway->totalCharges());
    }
}
