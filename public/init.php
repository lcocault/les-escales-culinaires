<?php
// public/init.php – bootstraps the application; included at top of every public page

define('ROOT_DIR', dirname(__DIR__));

require_once ROOT_DIR . '/config/config.php';
require_once ROOT_DIR . '/vendor/autoload.php';
require_once ROOT_DIR . '/src/Database.php';
require_once ROOT_DIR . '/src/Auth.php';
require_once ROOT_DIR . '/src/UserModel.php';
require_once ROOT_DIR . '/src/SessionModel.php';
require_once ROOT_DIR . '/src/BookingModel.php';
require_once ROOT_DIR . '/src/BasketModel.php';
require_once ROOT_DIR . '/src/GeneralMessageModel.php';
require_once ROOT_DIR . '/src/Mailer.php';
require_once ROOT_DIR . '/src/PaymentService.php';
require_once ROOT_DIR . '/src/RatingModel.php';
require_once ROOT_DIR . '/src/PackModel.php';
require_once ROOT_DIR . '/src/PromoCodeModel.php';
require_once ROOT_DIR . '/src/ShopProductModel.php';
require_once ROOT_DIR . '/src/ShopOrderModel.php';
require_once ROOT_DIR . '/src/GroupBookingModel.php';
require_once ROOT_DIR . '/src/GroupSessionSlotModel.php';

// Helper: escape output (accepts null, returns empty string for null)
function e(?string $value): string
{
    if ($value === null) {
        return '';
    }
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

// Helper: set a flash message
function flash(string $type, string $message): void
{
    Auth::start();
    $_SESSION['flash'] = ['type' => $type, 'message' => $message];
}

// Helper: format a price in cents to a human-readable euro string
function formatPrice(int $cents): string
{
    return number_format($cents / 100, 2, ',', ' ') . ' €';
}

// Helper: format a date string (Y-m-d) to French long format
function formatDate(string $date): string
{
    $ts = strtotime($date);
    $days   = ['dimanche', 'lundi', 'mardi', 'mercredi', 'jeudi', 'vendredi', 'samedi'];
    $months = [
        1 => 'janvier', 'février', 'mars', 'avril', 'mai', 'juin',
        'juillet', 'août', 'septembre', 'octobre', 'novembre', 'décembre',
    ];
    return $days[(int) date('w', $ts)] . ' '
         . (int) date('j', $ts) . ' '
         . $months[(int) date('n', $ts)] . ' '
         . date('Y', $ts);
}

// Helper: format a date string (Y-m-d) and time string (H:i:s) to French long format with hour
function formatSessionDateTime(string $date, string $time): string
{
    $ts = strtotime($date . ' ' . $time);
    if ($ts === false) {
        return e($date);
    }
    $days   = ['Dimanche', 'Lundi', 'Mardi', 'Mercredi', 'Jeudi', 'Vendredi', 'Samedi'];
    $months = [
        1 => 'Janvier', 'Février', 'Mars', 'Avril', 'Mai', 'Juin',
        'Juillet', 'Août', 'Septembre', 'Octobre', 'Novembre', 'Décembre',
    ];
    return $days[(int) date('w', $ts)] . ' '
         . (int) date('j', $ts) . ' '
         . $months[(int) date('n', $ts)] . ' '
         . date('Y', $ts) . ' '
         . (int) date('G', $ts) . 'h' . date('i', $ts);
}

// Helper: check whether a session row is in the past (after end_time)
function sessionIsPast(array $session): bool
{
    return strtotime($session['session_date'] . ' ' . $session['end_time']) < time();
}

// Helper: returns the number of items in the current user's basket.
// Uses a static variable so the DB is queried at most once per request.
function currentBasketCount(): int
{
    static $count = null;
    if ($count === null && Auth::isLoggedIn()) {
        $count = (new BasketModel())->countByUser(Auth::currentUserId());
    }
    return $count ?? 0;
}

// Helper: convert an age_category value to a French label
function ageCategoryLabel(string $category): string
{
    $labels = [
        '3-5'  => '3 à 5 ans',
        '3-10' => '3 à 10 ans',
        '3-12' => '3 à 12 ans',
        '6-12' => '6 à 12 ans',
        '13+'  => '13 ans et +',
    ];
    return $labels[$category] ?? $category;
}

// Helper: returns the number of items in the current shop cart (session-based).
function shopCartCount(): int
{
    Auth::start();
    $cart = $_SESSION['shop_cart'] ?? [];
    return array_sum($cart);
}

// Helper: label for a shop delivery method
function shopDeliveryLabel(string $method): string
{
    $labels = [
        'home'             => 'Livraison à domicile',
        'market_wednesday' => 'Retrait marché Croix-de-Pierre (mercredi)',
        'market_friday'    => 'Retrait marché Croix-de-Pierre (vendredi)',
        'shop'             => 'Retrait en boutique',
    ];
    return $labels[$method] ?? $method;
}

// Helper: label for a shop order status
function shopOrderStatusLabel(string $status): string
{
    $labels = [
        'pending'   => '⏳ En attente de paiement',
        'paid'      => '💳 Payée',
        'prepared'  => '🍱 Préparée',
        'delivered' => '✅ Livrée / Remise',
        'cancelled' => '❌ Annulée',
    ];
    return $labels[$status] ?? $status;
}

// Helper: resolves a shop product image source (external URL or uploaded file path).
function shopProductImageSrc(array $product): ?string
{
    if (!empty($product['external_photo_url'])) {
        return (string) $product['external_photo_url'];
    }
    if (!empty($product['photo_filename'])) {
        return APP_BASE_URL . '/uploads/shop/' . $product['photo_filename'];
    }
    return null;
}
