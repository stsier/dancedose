<?php

namespace Drupal\uc_paypal\Controller;

use Drupal\Core\Controller\ControllerBase;
use Drupal\uc_order\Entity\Order;

/**
 * Returns responses for PayPal routes.
 */
class EcController extends ControllerBase {

  /**
   * Completes the transaction for Express Checkout Mark Flow.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse
   *   A redirect to the order complete page (on success) or cart (on failure).
   */
  public function ecComplete() {
    $session = \Drupal::service('session');
    if (!$session->has('TOKEN') || !($order = Order::load($session->get('cart_order')))) {
      $session->remove('cart_order');
      $session->remove('TOKEN');
      $session->remove('PAYERID');
      $this->messenger()->addMessage($this->t('An error has occurred in your PayPal payment. Please review your cart and try again.'));
      return $this->redirect('uc_cart.cart');
    }

    // Get the payer ID from PayPal.
    $plugin = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);
    $response = $plugin->sendNvpRequest([
      'METHOD' => 'GetExpressCheckoutDetails',
      'TOKEN' => $session->get('TOKEN'),
    ]);
    $session->set('PAYERID', $response['PAYERID']);

    // Immediately complete the order.
    $plugin->orderSubmit($order);

    // Redirect to the order completion page.
    $session->remove('uc_checkout_review_' . $order->id());
    $session->set('uc_checkout_complete_' . $order->id(), TRUE);
    return $this->redirect('uc_cart.checkout_complete');
  }

  /**
   * Handles the review page for Express Checkout Shortcut Flow.
   *
   * @return \Symfony\Component\HttpFoundation\RedirectResponse|array
   *   A redirect to the cart or a build array.
   */
  public function ecReview() {
    $session = \Drupal::service('session');
    if (!$session->has('TOKEN') || !($order = Order::load($session->get('cart_order')))) {
      $session->remove('cart_order');
      $session->remove('TOKEN');
      $session->remove('PAYERID');
      $this->messenger()->addMessage($this->t('An error has occurred in your PayPal payment. Please review your cart and try again.'));
      return $this->redirect('uc_cart.cart');
    }

    // Get the payer ID from PayPal.
    $plugin = \Drupal::service('plugin.manager.uc_payment.method')->createFromOrder($order);
    $response = $plugin->sendNvpRequest([
      'METHOD' => 'GetExpressCheckoutDetails',
      'TOKEN' => $session->get('TOKEN'),
    ]);
    $session->set('PAYERID', $response['PAYERID']);

    // Store delivery address.
    $address = $order->getAddress('delivery');
    $shipname = $response['SHIPTONAME'];
    if (strpos($shipname, ' ') > 0) {
      $address->setFirstName(substr($shipname, 0, strrpos(trim($shipname), ' ')));
      $address->setLastName(substr($shipname, strrpos(trim($shipname), ' ') + 1));
    }
    else {
      $address->setFirstName($shipname);
      $address->setLastName('');
    }
    $address->setStreet1($response['SHIPTOSTREET']);
    $address->setStreet2(isset($response['SHIPTOSTREET2']) ? $response['SHIPTOSTREET2'] : '');
    $address->setCity($response['SHIPTOCITY']);
    $address->setZone($response['SHIPTOSTATE']);
    $address->setPostalCode($response['SHIPTOZIP']);
    $address->setCountry($response['SHIPTOCOUNTRYCODE']);
    $order->setAddress('delivery', $address);

    // Store billing details.
    $address = $order->getAddress('billing');
    $address->setFirstName($response['FIRSTNAME']);
    $address->setLastName($response['LASTNAME']);
    $address->setCountry($response['COUNTRYCODE']);
    $order->setAddress('billing', $address);
    $order->setEmail($response['EMAIL']);

    $order->save();

    $build['instructions'] = [
      '#markup' => $this->t("Your order is almost complete! Please fill in the following details and click 'Continue checkout' to finalize the purchase."),
    ];

    $build['form'] = $this->formBuilder()->getForm('\Drupal\uc_paypal\Form\EcReviewForm', $order);

    return $build;
  }

}
