<?php

use App\Concert;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;
use App\Exceptions\NotEnoughTicketsException;

class ConcertTest extends TestCase
{

  use DatabaseMigrations;

  /** @test */
  function can_get_formatted_date()
  {
    // Create a concert with a known date

    $concert = Factory(Concert::class)->make([
      'date' => Carbon::parse('2016-12-01 8:00pm'),
    ]);

    // Verify the date is formatted as expected
    $this->assertEquals('December 1, 2016', $concert->formatted_date);
  }

  /** @test */
  function can_get_formatted_start_time()
  {
    $concert = Factory(Concert::class)->make([
      'date' => Carbon::parse('2016-12-01 17:00:00'),
    ]);

    $this->assertEquals('5:00pm', $concert->formatted_start_time);
  }

  /** @test */
  function can_get_ticket_price_in_dollars()
  {
    $concert = Factory(Concert::class)->make([
      'ticket_price' => 6750,
    ]);

    $this->assertEquals('67.50', $concert->ticket_price_in_dollars);
  }

  /** @test */
  function concerts_with_a_published_at_date_are_published()
  {
      // Arrange
      $publishedConcertA = Factory(Concert::class)->states('published')->create();
      $publishedConcertB = Factory(Concert::class)->states('published')->create();
      $unpublishedConcert = Factory(Concert::class)->states('unpublished')->create();

      // Act
      $publishedConcerts = Concert::published()->get();

      // Assert
      $this->assertTrue($publishedConcerts->contains($publishedConcertA));
      $this->assertTrue($publishedConcerts->contains($publishedConcertB));
      $this->assertFalse($publishedConcerts->contains($unpublishedConcert));
  }

  /** @test */
  function can_order_concert_tickets()
  {
      // Arrange
      $concert = Factory(Concert::class)->create()->addTickets(3);

      // Act
      $order = $concert->orderTickets('jane@example.com', 3);

      // Assert
      $this->assertEquals('jane@example.com', $order->email);
      $this->assertEquals(3, $order->ticketQuantity());
  }

  /** @test */
  function can_add_tickets()
  {
      // Arrange
      $concert = Factory(Concert::class)->create();

      // Act
      $concert->addTickets(50);

      // Assert
      $this->assertEquals(50, $concert->ticketsRemaining());
  }

  /** @test */
  function tickets_remaining_does_not_include_tickets_associated_with_an_order()
  {
      // Arrange
      $concert = Factory(Concert::class)->create()->addTickets(50);

      // Act
      $concert->orderTickets('jane@example.com', 30);

      // Assert
      $this->assertEquals(20, $concert->ticketsRemaining());
  }

  /** @test */
  function trying_to_purchase_more_tickets_than_remain_throws_an_exception()
  {
      // Arrange
      $concert = Factory(Concert::class)->create()->addTickets(10);

      // Act
      try {
          $concert->orderTickets('jane@example.com', 11);
      } catch (NotEnoughTicketsException $e) {
          $this->assertFalse($concert->hasOrderFor('jane@example.com'));
          $this->assertEquals(10, $concert->ticketsRemaining());
          return;
      }

      // Assert
      $this->fail("Order succeeded even though there were not enough tickets remaining.");
  }

  /** @test */
  function cannot_order_tickets_that_have_already_been_purchased()
  {
      // Arrange
      $concert = Factory(Concert::class)->create()->addTickets(10);
      $concert->orderTickets('jane@example.com', 8);

      // Act
      try {
          $concert->orderTickets('john@example.com', 3);
      } catch (NotEnoughTicketsException $e) {
          $this->assertFalse($concert->hasOrderFor('john@example.com'));
          $this->assertEquals(2, $concert->ticketsRemaining());
          return;
      }

      // Assert
      $this->fail("Order succeeded even though there were not enough tickets remaining.");
  }

  /** @test */
  function can_reserve_available_tickets()
  {
      // Arrange
      $concert = Factory(Concert::class)->create()->addTickets(3);
      $this->assertEquals(3, $concert->ticketsRemaining());

      // Act
      $reservation = $concert->reserveTickets(2, 'john@example.com');

      // Assert
      $this->assertCount(2, $reservation->tickets());
      $this->assertEquals(1, $concert->ticketsRemaining());
      $this->assertEquals('john@example.com', $reservation->email());
  }

  /** @test */
  function cannot_reserve_tickets_that_have_already_been_purchased()
  {
      // Arrange
      $concert = Factory(Concert::class)->create()->addTickets(3);
      $concert->orderTickets('jane@example.com', 2);

      // Act
      try {
          $reservedTickets = $concert->reserveTickets(2, 'john@example.com');
      } catch (NotEnoughTicketsException $e) {
          $this->assertEquals(1, $concert->ticketsRemaining());
          return;
      }

      // Assert
      $this->fail("reserving tickets succeeded even though the tickets were already sold");
  }

  /** @test */
  function cannot_reserve_tickets_that_have_already_been_reserved()
  {
      // Arrange
      $concert = Factory(Concert::class)->create()->addTickets(3);
      $email = 'jane@example.com';
      $concert->reserveTickets(2, $email = 'jane@example.com');

      // Act
      try {
          $concert->reserveTickets(2, 'john@example.com');
      } catch (NotEnoughTicketsException $e) {
          $this->assertEquals(1, $concert->ticketsRemaining());
          return;
      }

      // Assert
      $this->fail("reserving tickets succeeded even though the tickets were already reserved");
  }

}
