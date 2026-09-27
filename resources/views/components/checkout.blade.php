@props(['session', 'mode' => 'payment'])

{{--
    Hosted checkout for an Areeba::checkout() session.
      mode="payment"  (default) redirects to the Areeba payment page
      mode="embedded" renders the payment form inside this element
    The payer returns to the session's returnUrl / cancelUrl. Client-side errors
    are dispatched as an `areeba:error` DOM event.
--}}
@if (isset($mode) && $mode === 'embedded')
    <div id="areeba-checkout" {{ $attributes }}></div>
@endif

<script>
    function areebaCheckoutError(error) {
        document.dispatchEvent(new CustomEvent('areeba:error', { detail: error }));
    }
</script>
<script src="{{ rtrim(config('areeba.gateway_url'), '/') }}/static/checkout/checkout.min.js" data-error="areebaCheckoutError"></script>
<script>
    Checkout.configure({ session: { id: @json($session->sessionId) } });
    @if (isset($mode) && $mode === 'embedded')
    Checkout.showEmbeddedPage('#areeba-checkout');
    @else
    Checkout.showPaymentPage();
    @endif
</script>
