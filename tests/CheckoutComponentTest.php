<?php

declare(strict_types=1);

namespace AhmadChebbo\AreebaPayment\Tests;

use AhmadChebbo\AreebaPayment\Data\CheckoutSession;

class CheckoutComponentTest extends TestCase
{
    private function session(): CheckoutSession
    {
        return new CheckoutSession(sessionId: 'SESSION0002', successIndicator: 'indicator-abc', orderId: '1001');
    }

    public function test_it_loads_checkout_js_and_opens_the_payment_page(): void
    {
        $view = $this->blade('<x-areeba::checkout :session="$session" />', ['session' => $this->session()]);

        $view->assertSee('https://areeba.test/static/checkout/checkout.min.js', false);
        $view->assertSee('"SESSION0002"', false);
        $view->assertSee('Checkout.showPaymentPage()', false);
    }

    public function test_it_can_embed_the_payment_form_in_the_page(): void
    {
        $view = $this->blade('<x-areeba::checkout :session="$session" mode="embedded" />', ['session' => $this->session()]);

        $view->assertSee('id="areeba-checkout"', false);
        $view->assertSee("Checkout.showEmbeddedPage('#areeba-checkout')", false);
        $view->assertDontSee('Checkout.showPaymentPage()', false);
    }

    public function test_the_session_id_is_safely_encoded_for_javascript(): void
    {
        $session = new CheckoutSession(sessionId: '</script><script>alert(1)', successIndicator: 'x', orderId: '1');

        $view = $this->blade('<x-areeba::checkout :session="$session" />', ['session' => $session]);

        $view->assertDontSee('</script><script>alert(1)', false);
    }
}
