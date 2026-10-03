<?php
namespace App\Services;
/** Expected concurrency or availability refusal: no payment should be reissued. */
final class CheckoutConflict extends \RuntimeException {}
