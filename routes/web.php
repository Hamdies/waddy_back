<?php

use Illuminate\Support\Facades\Route;
use App\Http\Controllers\PaymobController;
use App\Http\Controllers\FirebaseController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/


Route::post('/subscribeToTopic', [FirebaseController::class, 'subscribeToTopic']);
// Optional own domain for Waddy Vets (e.g. vets.waddy.app): its home page is the
// vets landing. Registered before `home` because routes match in order.
if ($vetDomain = config('app.vet_site_domain')) {
    Route::domain($vetDomain)->get('/', 'VetClinicApplicationController@landing')->name('vet-clinic.home');
}
Route::get('/', 'HomeController@index')->name('home');
Route::get('lang/{locale}', 'HomeController@lang')->name('lang');
Route::get('terms-and-conditions', 'HomeController@terms_and_conditions')->name('terms-and-conditions');
Route::get('about-us', 'HomeController@about_us')->name('about-us');
Route::get('contact-us', 'HomeController@contact_us')->name('contact-us');
Route::post('send-message', 'HomeController@send_message')->name('send-message');
Route::post('contact-us', 'HomeController@contact_message')->name('contact-us.send')->middleware('throttle:5,10');
Route::get('privacy-policy', 'HomeController@privacy_policy')->name('privacy-policy');
Route::get('cancelation', 'HomeController@cancelation')->name('cancelation');
Route::get('refund-policy', 'HomeController@refund_policy')->name('refund');
Route::redirect('refund', 'refund-policy', 301);
Route::get('shipping-policy', 'HomeController@shipping_policy')->name('shipping-policy');
Route::post('newsletter/subscribe', 'NewsletterController@newsLetterSubscribe')->name('newsletter.subscribe');
Route::get('subscription-invoice/{id}', 'HomeController@subscription_invoice')->name('subscription_invoice');
Route::get('order-invoice/{id}', 'HomeController@order_invoice')->name('order_invoice');
Route::get('deliveryman-earning-report-invoice/{id}', 'HomeController@earningReportInvoice')->name('delivery_earning_invoice');

Route::get('login/{tab}', 'LoginController@login')->name('login');
Route::post('login_submit', 'LoginController@submit')->name('login_post');
Route::get('logout', 'LoginController@logout')->name('logout');
Route::get('/reset-password', 'LoginController@reset_password_request')->name('reset-password');
Route::post('/vendor-reset-password', 'LoginController@vendor_reset_password_request')->name('vendor-reset-password');
Route::get('/password-reset', 'LoginController@reset_password')->name('change-password');
Route::post('verify-otp', 'LoginController@verify_token')->name('verify-otp');
Route::post('reset-password-submit', 'LoginController@reset_password_submit')->name('reset-password-submit');
Route::get('otp-resent', 'LoginController@otp_resent')->name('otp_resent');

Route::get('authentication-failed', function () {
    $errors = [];
    array_push($errors, ['code' => 'auth-001', 'message' => 'Unauthenticated.']);
    return response()->json([
        'errors' => $errors,
    ], 401);
})->name('authentication-failed');

Route::group(['prefix' => 'payment-mobile'], function () {
    Route::get('/', 'PaymentController@payment')->name('payment-mobile');
    Route::get('set-payment-method/{name}', 'PaymentController@set_payment_method')->name('set-payment-method');
});

Route::get('payment-success', 'PaymentController@success')->name('payment-success');
Route::get('payment-fail', 'PaymentController@fail')->name('payment-fail');
Route::get('payment-cancel', 'PaymentController@cancel')->name('payment-cancel');

// Paymob is the only gateway in service (Egypt-only market). This used to sit
// behind a check on whether a Modules/Gateways addon was published; that addon
// does not exist, so the condition only ever evaluated one way.
Route::group(['prefix' => 'payment'], function () {
    Route::group(['prefix' => 'paymob', 'as' => 'paymob.'], function () {
        Route::any('pay', [PaymobController::class, 'credit'])->name('pay');
        Route::any('callback', [PaymobController::class, 'callback'])->name('callback');
    });
});


//Restaurant Registration
Route::group(['prefix' => 'vendor', 'as' => 'restaurant.'], function () {
    Route::get('apply', 'VendorController@create')->name('create');
    Route::post('apply', 'VendorController@store')->name('store');
    Route::get('get-all-modules', 'VendorController@get_all_modules')->name('get-all-modules');
    Route::get('get-module-type', 'VendorController@get_modules_type')->name('get-module-type');

    Route::get('back', 'VendorController@back')->name('back');
    Route::post('business-plan', 'VendorController@business_plan')->name('business_plan');
    Route::post('payment', 'VendorController@payment')->name('payment');
    Route::get('final-step', 'VendorController@final_step')->name('final_step');
});

//Deliveryman Registration
Route::group(['prefix' => 'deliveryman', 'as' => 'deliveryman.'], function () {
    Route::get('apply', 'DeliveryManController@create')->name('create');
    Route::post('apply', 'DeliveryManController@store')->name('store');

});

//Waddy Vets: standalone site where vets list their clinic (Pets module).
//Applications land in Admin › Vet clinics › Applications.
Route::group(['prefix' => 'vets', 'as' => 'vet-clinic.'], function () {
    Route::get('/', 'VetClinicApplicationController@landing')->name('landing');
    Route::get('apply', 'VetClinicApplicationController@create')->name('create');
    Route::post('apply', 'VetClinicApplicationController@store')->name('store')->middleware('throttle:10,60');
    Route::get('apply/thanks', 'VetClinicApplicationController@thanks')->name('thanks');
});
// The form's first address; printed QR codes and shared links may still use it.
Route::permanentRedirect('vet-clinic/apply', '/vets/apply');

// Tracked QR codes: count the scan, then redirect (Admin › QR code generator).
Route::get('q/{code}', \App\Http\Controllers\QrRedirectController::class)
    ->where('code', '[A-Za-z0-9]{4,16}')
    ->middleware('throttle:120,1')
    ->name('qr.redirect');

Route::get('/image-proxy', \App\Http\Controllers\ImageProxyController::class)
    ->middleware('throttle:60,1')
    ->name('image-proxy');

/*
|--------------------------------------------------------------------------
| Order Tracking API Routes
|--------------------------------------------------------------------------
*/
Route::group(['prefix' => 'api/v1/orders'], function () {
    // Lightweight tracking snapshot endpoint (kept for compatibility)
    Route::get('{id}/stream', [\App\Http\Controllers\Api\V1\OrderTrackingStreamController::class, 'stream'])
        ->middleware(['throttle:60,1'])
        ->name('api.orders.stream');
    
    // Tracking history endpoint
    Route::get('{id}/tracking-history', [\App\Http\Controllers\Api\V1\OrderTrackingHistoryController::class, 'index'])
        ->middleware(['throttle:60,1'])
        ->name('api.orders.tracking-history');
});
