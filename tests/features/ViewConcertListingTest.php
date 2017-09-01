<?php

use App\Concert;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\WithoutMiddleware;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Foundation\Testing\DatabaseTransactions;

class ViewConcertListingTest extends TestCase
{
  use DatabaseMigrations;
  /** @test */
  function user_can_view_a_published_concert_listing()
  {
    // Arrange
    // Create a concert

    $concert = Factory(Concert::class)->states('published')->create([
        'title' => 'The Red Chord',
        'subtitle' => 'with Animosity and Lethargy',
        'date' => Carbon::parse('December 13, 2016 8:00pm'),
        'ticket_price' => 3250,
        'venue' => 'The Mosh Pit',
        'venue_address' => '123 Example St.',
        'city' => 'Laraville',
        'state' => 'ON',
        'zip' => '17916',
        'additional_information' => 'For tickets, call (555) 555-5555',
    ]);

    // Act
    // View the concert listing
    $this->visit('/concert/'.$concert->id);

    // Assert
    // See the concert details
    $this->see('The Red Chord');
    $this->see('with Animosity and Lethargy');
    $this->see('December 13, 2016');
    $this->see('8:00pm');
    $this->see('32.50');
    $this->see('The Mosh Pit');
    $this->see('123 Example St.');
    $this->see('Laraville, ON 17916');
    // $this->see('ON');
    // $this->see('17916');
    $this->see('For tickets, call (555) 555-5555');
  }

  /** @test */
  function user_cannot_view_unpublished_concert_listings()
  {
      // Arrange Step
      $concert = Factory(Concert::class)->states('unpublished')->create();

      // Act Step

      // use visit() for cases where you want test to succeed and need to make assertions about a page
      // $this->visit('/concert/'.$concert->id);

      // use http verb helpers (get(), post(), put(), patch()) when making assertions about failures or assertions about the http response
      $this->get('/concert/'.$concert->id);

      // Assert Step
      $this->assertResponseStatus(404);
  }
}
