<?php

use App\Models\CartItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Str;

it('never flashes card numbers or CVCs back into the session', function () {
    Route::post('/_test/card-form', fn (Request $request) => $request->validate([
        'card_number' => ['required'],
        'cvc' => ['required'],
        'cardholder_name' => ['required'],
        'missing' => ['required'],
    ]))->middleware('web');

    $this->post('/_test/card-form', ['card_number' => '4242424242424242', 'cvc' => '123', 'cardholder_name' => 'Demo Buyer'])
        ->assertRedirect()
        ->assertSessionHasInput('cardholder_name', 'Demo Buyer');

    expect(session()->getOldInput('card_number'))->toBeNull()
        ->and(session()->getOldInput('cvc'))->toBeNull();
});

it('does not echo card details in a payment validation error', function () {
    $user = signedIn();
    CartItem::factory()->for($user)->for(stockedOffer(stock: 1))->create(['quantity' => 1]);
    $reference = $this->postJson('/api/v1/checkout', [], ['Idempotency-Key' => (string) Str::uuid()])->assertCreated()->json('data.reference');

    $response = $this->postJson("/api/v1/orders/{$reference}/pay", [
        'card_number' => '4242424242424241',
        'expiry_month' => 12,
        'expiry_year' => now()->addYears(2)->year,
        'cvc' => '98',
        'cardholder_name' => 'Demo Buyer',
    ])->assertUnprocessable()->assertJsonValidationErrors(['card_number', 'cvc']);

    expect($response->getContent())->not->toContain('4242424242424241')->not->toContain('"98"');
});
