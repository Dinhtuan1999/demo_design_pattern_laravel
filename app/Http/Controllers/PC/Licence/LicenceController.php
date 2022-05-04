<?php

namespace App\Http\Controllers\PC\Licence;

use App\Events\AfterChangeLicenceEvent;
use App\Helpers\StripeHelper;
use App\Http\Controllers\PC\Controller;
use App\Services\LicenceManagementService;
use App\Services\Payment\StripeService;
use Carbon\Carbon;
use GuzzleHttp\Promise\Create;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class LicenceController extends Controller
{
    protected $licenceManagementService;
    protected $stripeHelper;
    private $stripeService;

    public function __construct(
        LicenceManagementService $licenceManagementService,
        StripeHelper             $stripeHelper,
        StripeService            $stripeService
    ) {
        $this->licenceManagementService = $licenceManagementService;
        $this->stripeHelper = $stripeHelper;
        $this->stripeService = $stripeService;
    }

    public function addLicenceIndex()
    {
        $user = Auth::user();

        $stripeUser = $user->stripe_user;

        if (!$stripeUser) {
            $this->setSessionFlashError(__("Please Add Method Payment"));
            return redirect()->route('payment.add-payment-method');
        }

        $licence = $user->liscence;
        $numberLicence = $licence ? $licence->licence_num : 0;
        return view('licences.add_licence_index', compact('user', 'numberLicence'));
    }

    public function deleteLicenceIndex()
    {
        $user = Auth::user();

        $licenseData = $this->licenceManagementService->getNumberLicences($user->company_id);
        $licenseData = $licenseData['data'] ?? [];

        return view('licences.delete_licence_index', compact('user', 'licenseData'));
    }

    public function confirmAddLicenceIndex()
    {
        $numberLicenceBuy = request()->get('number_licence_buy');
        $dateBuyLicenceEmulator = request()->get('date_buy_licence_emulator');

        $user = Auth::user();

        $stripeUser = $user->stripe_user;
        if (!$stripeUser) {
            $this->setSessionFlashError(__("Please Add Method Payment"));
            return redirect()->route('payment.add-payment-method');
        }

        $licence = $user->liscence;
        $numberLicence = $licence ? $licence->licence_num : 0;
        $now = Carbon::now();

        $total = $numberLicenceBuy * valueOnePriceLicence();
        return view('licences.confirm_add_licence_index', compact('user', 'numberLicence', 'now', 'numberLicenceBuy', 'stripeUser', 'total', 'dateBuyLicenceEmulator'));
    }

    public function buyLicenceAjax(Request $request)
    {
        $user = Auth::user();
        $stripeUser = $user->stripe_user;
        if (!$stripeUser) {
            return $this->respondWithError(__("Please Add Method Payment"));
        }

        $numberLicenceBuy = $request->input('number_licence_buy');
        $dateBuyLicenceEmulator = $request->input('date_buy_licence_emulator');
        $priceId = 'basic-monthly';
        $nameSubscription = $numberLicenceBuy . " licenses per month plan";

        $stripeSubscriptions = $stripeUser->subscriptions()->orderBy('created_at', 'desc')->get();

        //billing_cycle_anchor -> set time paid -> timestamp
        if ($stripeSubscriptions->isEmpty()) {

            // create subscriptions first
            $addSubscription = $stripeUser->newSubscription($nameSubscription, $priceId)
                ->quantity($numberLicenceBuy)
                ->create(null, ['customer' => $stripeUser->stripe_id]);
            //            $addSubscription = $stripeUser->newSubscription($nameSubscription, $priceId)
            //                ->quantity($numberLicenceBuy)
            //                ->create(null, ['customer' => $stripeUser->stripe_id], ['billing_cycle_anchor' => 1871626]);
            if (!$addSubscription) {
                return $this->respondWithError(__("By Licence error. Please again"));
            }

            event(new AfterChangeLicenceEvent($user, $numberLicenceBuy));

            return $this->respondSuccess(__("Buy Licence Success"));
        } else {
            // if exist subscriptions
            $stripeSubscription = $stripeSubscriptions->first();
            // get subcription
            $subcriptionCurrent = $this->stripeHelper->getSubscriptionById($stripeSubscription->stripe_id);

            if (!$subcriptionCurrent) {
                return $this->respondWithError(__("Charge error. Please again"));
            }
            $userSub = $user->stripe_subs()->first();
            $periodEnd = $subcriptionCurrent->current_period_end;
            if (!empty($userSub->stripe_id) && !empty($stripeUser->stripe_id)) {
                $invoice = $this->stripeService->detailInvoice([
                    'subscription' => $userSub->stripe_id,
                    'customer' => $stripeUser->stripe_id
                ]);
                $nextPay = $invoice->next_payment_attempt;

                if (!empty($nextPay)) {
                    $nextPayDate = Carbon::parse(date('Y-m-d H:i', $nextPay))->timezone('Asia/Tokyo')->format('Y/m/d H:i');
                }
            }


            $periodEnd = $subcriptionCurrent->current_period_end;
            $periodEnd = Carbon::createFromTimestamp($periodEnd);
            $dateBuyLicence = Carbon::now()->format('Y-m-d');
            if ($dateBuyLicenceEmulator) {
                $dateBuyLicence = Carbon::createFromDate($dateBuyLicenceEmulator);
            }

            $numberDateLicenceToEnd = $dateBuyLicence->diffInDays($periodEnd);
            $amountCharge = $this->getAmountLicenceCharge($numberLicenceBuy, $numberDateLicenceToEnd);

            // payment init license
//            $charge = $this->stripeHelper->chargePaymentIntentByCustomerId($amountCharge, $stripeUser->stripe_id);


//            if (!$charge) {
//                return $this->respondWithError(__("Charge error. Please again"));
//            }
            // change subscription
            $numberLicenceCurrent = 0;
            if ($stripeSubscriptionFirst = $stripeSubscriptions->first()) {
                $numberLicenceCurrent = $stripeSubscriptionFirst->items()->sum('quantity');
            }

            $numberLicenceNew = intval($numberLicenceCurrent) + $numberLicenceBuy;
            $updateSubscription = $stripeUser->subscription($priceId)->noProrate()->updateQuantity($numberLicenceNew);

            $now = date('Y-m-d H:i');


            $oneLicense = valueOnePriceLicence() / 30 * 27;


            $invoice = $stripeUser->invoiceFor(
                'Buy license',
                $oneLicense,
                ['quantity' => $numberLicenceBuy],
                ['subscription' => $subcriptionCurrent->id]
            );
//            dd($subcriptionCurrent->id, $invoice);


            if (!$updateSubscription) {
                return $this->respondWithError(__("Update Subscription Failf. Please again"));
            }
            // update item
            if ($stripeSubscriptionFirst = $stripeSubscriptions->first()) {
                if ($itemFirst = $stripeSubscriptionFirst->items->first()) {
                    $itemFirst->quantity = $numberLicenceNew;
                    $itemFirst->save();
                }
            }

            event(new AfterChangeLicenceEvent($user, $numberLicenceBuy, $amountCharge));

            return $this->respondSuccess(__("Buy Licence Success"));
        }
    }

    private function getAmountLicenceCharge($numberLicenceBuy, $dateBuyLicenceEmulator)
    {
        return $numberLicenceBuy * valueOnePriceLicence() / 30 * 27;
    }

    public function deleteLicenceAjax()
    {
        $user = Auth::user();
        $stripeUser = $user->stripe_user;
        if (!$stripeUser) {
            return $this->respondWithError(__("Please Add Method Payment"));
        }

        $numberLicenceDelete = request()->get('number_licence_delete');
        $priceId = 'basic-monthly';

        $numberLicenceDelete = -1 * intval($numberLicenceDelete);

        $stripeSubscriptions = $stripeUser->subscriptions()->orderBy('created_at', 'desc')->get();

        if ($stripeSubscriptions->isEmpty()) {
            return $this->respondWithError(__("You have not registered any subscription yet"));
        } else {
            // if exist subscriptions

            // change subscription
            $numberLicenceCurrent = 0;
            if ($stripeSubscriptionFirst = $stripeSubscriptions->first()) {
                $numberLicenceCurrent = $stripeSubscriptionFirst->items()->sum('quantity');
            }

            $numberLicenceNew = intval($numberLicenceCurrent) + $numberLicenceDelete;

            if ($numberLicenceNew < 0) {
                return $this->respondWithError(__("The imported license is larger than the current one"));
            }

            $updateSubscription = $stripeUser->subscription($priceId)->noProrate()->updateQuantity($numberLicenceNew);

            if (!$updateSubscription) {
                return $this->respondWithError(__("Update Subscription Failf. Please again"));
            }
            // update item
            if ($stripeSubscriptionFirst = $stripeSubscriptions->first()) {
                if ($itemFirst = $stripeSubscriptionFirst->items->first()) {
                    $itemFirst->quantity = $numberLicenceNew;
                    $itemFirst->save();
                }
            }

            event(new AfterChangeLicenceEvent($user, $numberLicenceDelete));

            return $this->respondSuccess(__("Delete Licence Success"));
        }
    }
}
