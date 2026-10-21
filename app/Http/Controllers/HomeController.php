<?php

namespace App\Http\Controllers;

use App\Models\DeliveryMan;
use App\Models\OrderTransaction;
use App\Models\Order;
use App\Models\Contact;
use App\Models\DataSetting;
use Illuminate\Http\Request;
use App\CentralLogics\Helpers;
use App\Models\BusinessSetting;
use Gregwar\Captcha\CaptchaBuilder;
use Brian2694\Toastr\Facades\Toastr;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\View;
use App\Models\SubscriptionTransaction;
use Illuminate\Support\Facades\Session;

class HomeController extends Controller
{

    /**
     * Public Waddy marketing site (static bilingual landing page).
     */
    public function index()
    {
        return view('site.home');
    }

    public function terms_and_conditions()
    {
        return view('site.terms');
    }

    public function about_us()
    {
        return view('site.about');
    }

    public function contact_us()
    {
        return view('site.contact');
    }

    /**
     * JSON endpoint for the website contact form; messages land in the admin contacts inbox.
     */
    public function contact_message(Request $request)
    {
        $topics = [
            'order' => 'Help with an order',
            'refund' => 'Refund or payment',
            'account' => 'My account',
            'partner' => 'Partner with Waddy',
            'rider' => 'Deliver with Waddy',
            'other' => 'Something else',
        ];

        // Honeypot field: bots fill it, people never see it. Pretend it worked.
        if ($request->filled('_gotcha')) {
            return response()->json(['ok' => true]);
        }

        $data = $request->validate([
            'name' => 'required|string|min:2|max:80',
            'email' => 'required|email|max:100',
            'phone' => 'nullable|string|max:20',
            'topic' => 'required|in:' . implode(',', array_keys($topics)),
            'order' => 'nullable|string|max:20',
            'message' => 'required|string|min:10|max:1000',
            'consent' => 'accepted',
        ]);

        $subject = $topics[$data['topic']];
        if (!empty($data['order'])) {
            $subject .= ' (order #' . $data['order'] . ')';
        }

        $message = $data['message'];
        if (!empty($data['phone'])) {
            $message .= "\n\nPhone: " . $data['phone'];
        }

        $contact = new Contact;
        $contact->name = $data['name'];
        $contact->email = $data['email'];
        $contact->subject = $subject;
        $contact->message = $message;
        $contact->save();

        return response()->json(['ok' => true]);
    }

    public function send_message(Request $request)
    {
        $request->validate([
            'name' => 'required',
            'email' => 'required',
            'subject' => 'required',
            'message' => 'required',
        ]);

        $recaptcha = Helpers::get_business_settings('recaptcha');
        if (isset($recaptcha) && $recaptcha['status'] == 1) {
            $request->validate([
                'g-recaptcha-response' => [
                    function ($attribute, $value, $fail) {
                        $secret_key = Helpers::get_business_settings('recaptcha')['secret_key'];
                        $gResponse = Http::asForm()->post('https://www.google.com/recaptcha/api/siteverify', [
                            'secret' => $secret_key,
                            'response' => $value,
                            'remoteip' => \request()->ip(),
                        ]);

                        if (!$gResponse->successful()) {
                            $fail(translate('ReCaptcha Failed'));
                        }
                    },
                ],
            ]);
        } else if (strtolower(session('six_captcha')) != strtolower($request->custome_recaptcha)) {
            Toastr::error(translate('messages.ReCAPTCHA Failed'));
            return back();
        }

        $contact = new Contact;
        $contact->name = $request->name;
        $contact->email = $request->email;
        $contact->subject = $request->subject;
        $contact->message = $request->message;
        $contact->save();

        Toastr::success('Message sent successfully!');
        return back();
    }

    public function privacy_policy()
    {
        return view('site.privacy');
    }

    public function refund_policy()
    {
        return view('site.refund');
    }

    public function shipping_policy(Request $request)
    {
        $data = self::get_settings('shipping_policy');
        $status = self::get_settings_status('shipping_policy_status');

        abort_if($status == 0, 404);
        $config = Helpers::get_business_settings('landing_page');
        $landing_integration_type = Helpers::get_business_data('landing_integration_type');
        $redirect_url = Helpers::get_business_data('landing_page_custom_url');

        if (isset($config) && $config) {
            return view('shipping-policy', compact('data'));
        } elseif ($landing_integration_type == 'file_upload' && File::exists('resources/views/layouts/landing/custom/index.blade.php')) {
            return view('layouts.landing.custom.index');
        } elseif ($landing_integration_type == 'url') {
            return redirect($redirect_url);
        } else {
            abort(404);
        }
    }

    public function cancelation(Request $request)
    {
        $data = self::get_settings('cancellation_policy');
        $status = self::get_settings_status('cancellation_policy_status');
        abort_if($status == 0, 404);
        $config = Helpers::get_business_settings('landing_page');
        $landing_integration_type = Helpers::get_business_data('landing_integration_type');
        $redirect_url = Helpers::get_business_data('landing_page_custom_url');

        if (isset($config) && $config) {
            return view('cancelation', compact('data'));
        } elseif ($landing_integration_type == 'file_upload' && File::exists('resources/views/layouts/landing/custom/index.blade.php')) {
            return view('layouts.landing.custom.index');
        } elseif ($landing_integration_type == 'url') {
            return redirect($redirect_url);
        } else {
            abort(404);
        }
    }

    public static function get_settings($name)
    {
        $config = null;
        $data = DataSetting::where(['key' => $name])->first();
        return $data ? $data->value : '';
    }

    public static function get_settings_localization($name, $lang)
    {
        $data = DataSetting::withoutGlobalScope('translate')->with(['translations' => function ($query) use ($lang) {
            return $query->where('locale', $lang);
        }])->where(['key' => $name])->first();
        if ($data && count($data->translations) > 0) {
            $data = $data->translations[0]['value'];
        } else {
            $data = $data ? $data->value : '';
        }
        return $data;
    }

    public static function get_settings_status($name)
    {
        $data = DataSetting::where(['key' => $name])->first()?->value;
        return $data;
    }

    public function lang($local)
    {
        $direction = BusinessSetting::where('key', 'site_direction')->first();
        $direction = $direction->value ?? 'ltr';
        $language = BusinessSetting::where('key', 'system_language')->first();
        foreach (json_decode($language['value'], true) as $key => $data) {
            if ($data['code'] == $local) {
                $direction = isset($data['direction']) ? $data['direction'] : 'ltr';
            }
        }
        session()->forget('landing_language_settings');
        Helpers::landing_language_load();
        session()->put('landing_site_direction', $direction);
        session()->put('landing_local', $local);
        return redirect()->back();
    }


    public function subscription_invoice($id)
    {
        $id = base64_decode($id);
        $BusinessData = ['admin_commission', 'business_name', 'address', 'phone', 'logo', 'email_address'];
        $transaction = SubscriptionTransaction::with(['store.vendor', 'package:id,package_name,price'])->findOrFail($id);
        $BusinessData = BusinessSetting::whereIn('key', $BusinessData)->pluck('value', 'key');
        $logo = BusinessSetting::where('key', "logo")->first();
        $mpdf_view = View::make('subscription-invoice', compact('transaction', 'BusinessData', 'logo'));
        Helpers::gen_mpdf(view: $mpdf_view, file_prefix: 'Subscription', file_postfix: $id);
        return back();
    }
    public function order_invoice($id)
    {
        $id = base64_decode($id);
        $BusinessData = ['footer_text', 'email_address'];
        $order = Order::findOrFail($id);
        $BusinessData = BusinessSetting::whereIn('key', $BusinessData)->pluck('value', 'key');
        $logo = BusinessSetting::where('key', "logo")->first();
        $mpdf_view = View::make('order-invoice', compact('order', 'BusinessData', 'logo'));
        Helpers::gen_mpdf(view: $mpdf_view, file_prefix: 'OrderInvoice', file_postfix: $id);
        return back();
    }

    public function earningReportInvoice(Request $request, $id)
    {
        $type       = $request->input('type', 'all');
        $startDate  = $request->start_date;
        $endDate    = $request->end_date;

        $logo = BusinessSetting::where('key', "logo")->first();
        $dm   = DeliveryMan::findOrFail($id);

        $businessDataKeys = ['footer_text', 'email_address', 'phone', 'app_url'];
        $businessData     = BusinessSetting::whereIn('key', $businessDataKeys)->pluck('value', 'key');

        $query = OrderTransaction::where('delivery_man_id', $dm->id)
            ->select(['id','order_id', 'delivery_man_id', 'dm_tips', 'original_delivery_charge', 'delivery_fee_comission', 'created_at']);

        if ($startDate && $endDate) {
            $query->whereBetween('created_at', [$startDate, $endDate]);
        } elseif ($startDate) {
            $query->whereDate('created_at', '>=', $startDate);
        } elseif ($endDate) {
            $query->whereDate('created_at', '<=', $endDate);
        }

        if ($type === 'delivery_fee') {
            $query->where('original_delivery_charge', '>', 0);
        } elseif ($type === 'delivery_tips') {
            $query->where('dm_tips', '>', 0);
        }

        $earnings = $query->get();

        $mpdf_view = View::make('deliveryman-report-invoice', compact(
            'earnings', 'dm', 'logo', 'businessData', 'startDate', 'endDate'
        ));

        Helpers::gen_mpdf(view: $mpdf_view, file_prefix: 'Earning Statement', file_postfix: $id);

        return back();
    }

}
