<?php

/**
 *
 */
use App\Concert;
use App\Billing\FakePaymentGateway;
use App\Billing\PaymentGateway;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class PurchaseTicketsTest extends TestCase
{

    use DatabaseMigrations;

    protected function setUp()
    {
        parent::setUp();
        $this->paymentGateway = new FakePaymentGateway;
        $this->app->instance(PaymentGateway::class, $this->paymentGateway);
    }

    private function orderTickets($concert, $params)
    {
        $savedRequest = $this->app['request'];
        $this->json('POST', "/concerts/{$concert->id}/orders", $params);
        $this->app['request'] = $savedRequest;
    }

    private function assertValidationError($field)
    {
        $this->assertResponseStatus(422);
        $this->assertArrayHasKey($field, $this->decodeResponseJson());
    }

    // Start with one test that named similar to the test class itself
    /** @test */
    function customer_can_purchase_tickets_to_a_published_concert()
    {
        // Arrange step
        // Create Concert
        $concert = Factory(Concert::class)->states('published')->create([
            'ticket_price' => 3250,
        ])->addTickets(3);

        // Act step
        // Purchase Concert Ticket
        $this->orderTickets($concert,  [
            'email' => 'john@example.com',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        // Assert step
        $this->assertResponseStatus(201);

        // what do we want to get back:
        $this->seeJsonSubset([
            'email' => 'john@example.com',
            'ticket_quantity' => 3,
            'amount' => 9750,
        ]);

        // Make sure the customer was charged the correct amount
        $this->assertEquals(9750, $this->paymentGateway->totalCharges());

        // Make sure an order exists for this customer
        $this->assertTrue($concert->hasOrderFor('john@example.com'));
        $this->assertEquals(3, $concert->ordersFor('john@example.com')->first()->ticketQuantity());
    }

    /** @test */
    function cannot_purchase_tickets_to_an_unpublished_concert()
    {
        $concert = Factory(Concert::class)->states('unpublished')->create();
        $concert->addTickets(3);

        // Act step
        // Purchase Concert Ticket
        $this->orderTickets($concert,  [
            'email' => 'john@example.com',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertResponseStatus(404);
        $this->assertFalse($concert->hasOrderFor('john@example.com'));
        // Make sure the customer was not charged
        $this->assertEquals(0, $this->paymentGateway->totalCharges());
    }


    /** @test */
    function an_order_is_not_created_if_payment_fails()
    {
        $concert = Factory(Concert::class)->states('published')->create()->addTickets(3);

        $this->orderTickets($concert,  [
            'email' => 'john@example.com',
            'ticket_quantity' => 3,
            'payment_token' => 'invalid-token',
        ]);

        $this->assertResponseStatus(422);

        $this->assertFalse($concert->hasOrderFor('john@example.com'));

        $this->assertEquals(3, $concert->ticketsRemaining());
    }

    /** @test */
    function cannot_purchase_more_tickets_than_remain()
    {
        // arrange
        $concert = Factory(Concert::class)->states('published')->create()->addTickets(50);

        // act
        $this->orderTickets($concert,  [
            'email' => 'john@example.com',
            'ticket_quantity' => 51,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        // assert
        $this->assertResponseStatus(422);

        // check that an order was not created
        $this->assertFalse($concert->hasOrderFor('john@example.com'));

        // check that the customer was not charged for 51 tickets
        $this->assertEquals(0, $this->paymentGateway->totalCharges());

        // Check that 50 tickets still remain
        $this->assertEquals(50, $concert->ticketsRemaining());
    }

    /** @test */
    function email_is_required_to_purchase_tickets()
    {
        // Arrange
        $concert = Factory(Concert::class)->states('published')->create();

        // Act
        $this->orderTickets($concert, [
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        // Assert
        $this->assertValidationError('email');
    }

    /** @test */
    function email_must_be_valid_to_purchase_tickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'not-an-email-address',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertValidationError('email');
    }

    /** @test */
    function ticket_quantity_is_required_to_purchase_tickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'john@example.com',
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertValidationError('ticket_quantity');
    }

    /** @test */
    function ticket_quantity_must_be_at_least_1_to_purchase_tickets()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'john@example.com',
            'ticket_quantity' => 0,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        $this->assertValidationError('ticket_quantity');
    }

    /** @test */
    function payment_token_is_required()
    {
        $concert = factory(Concert::class)->states('published')->create();

        $this->orderTickets($concert, [
            'email' => 'john@example.com',
            'ticket_quantity' => 3,
        ]);

        $this->assertValidationError('payment_token');
    }

    /** @test */
    function cannot_purchase_another_customer_is_already_trying_to_purchase()
    {
        // Laravel Sub-requests

        // Initiate Request A
            // Initiate Request B
            // Finish Request B
        // Finish Request A

        // This is near impossible:
        // Find tickets for person A
                                    // Find tickets for person B
        // Try to charge person A
                                    // Try to charge person B
        // Create order for person A
                                    // Create order for person B

        // What actually needs to happen for this test to work using Sub-requests:
        // Find tickets for person A
                                    // Find tickets for person B
                                    // Try to charge person B
                                    // Create order for person B
        // Try to charge person A
        // Create order for person A


        // $this->disableExceptionHandling();

        // Arrange
        $concert = Factory(Concert::class)->states('published')->create([
            'ticket_price' => 1200
        ])->addTickets(3);

        $this->paymentGateway->beforeFirstCharge(function ($paymentGateway) use ($concert) {
            $this->orderTickets($concert, [
                'email' => 'personB@example.com',
                'ticket_quantity' => 1,
                'payment_token' => $this->paymentGateway->getValidTestToken(),
            ]);

            // Assert
            $this->assertResponseStatus(422);

            // check that an order was not created
            $this->assertFalse($concert->hasOrderFor('personB@example.com'));

            // check that the customer was not charged for 1 ticket
            $this->assertEquals(0, $this->paymentGateway->totalCharges());
        });

        // Act
        $this->orderTickets($concert, [
            'email' => 'personA@example.com',
            'ticket_quantity' => 3,
            'payment_token' => $this->paymentGateway->getValidTestToken(),
        ]);

        // dd($concert->orders()->first()->toArray());

        // Assert
        // Make sure the customer was charged the correct amount
        $this->assertEquals(3600, $this->paymentGateway->totalCharges());

        // Make sure an order exists for this customer
        $this->assertTrue($concert->hasOrderFor('personA@example.com'));
        $this->assertEquals(3, $concert->ordersFor('personA@example.com')->first()->ticketQuantity());

    }
}
