<?php

namespace App\Http\Controllers\PC\Payment;

use App\Helpers\StripeHelper;
use App\Http\Controllers\PC\Controller;
use App\Models\Payment\StripeCard;
use App\Models\Payment\StripePaymentVerifyCard;
use App\Models\PaymentHistory;
use App\Repositories\Payment\StripeCardRepository;
use App\Services\Payment\StripeService;
use Carbon\Carbon;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

class PaymentController extends Controller
{
    protected $stripeService;
    protected $stripeHelper;
    private $stripeCardRepo;

    public function __construct(StripeService $stripeService, StripeHelper $stripeHelper, StripeCardRepository $stripeCardRepo)
    {
        $this->stripeService = $stripeService;
        $this->stripeHelper = $stripeHelper;
        $this->stripeCardRepo = $stripeCardRepo;
    }

    public function managePaymentMethodIndex()
    {
        if (!(Gate::allows('accountSupper') || Gate::allows('accountContractor'))) {
            abort(403);
        }
        $user = Auth::user();
        $stripeUser = $user->stripe_user;
        $stripeUsers = $user->stripe_users;

        $numberLicence = $user->company->licence_managements()->sum('licence_num');
        $licenceLast = $user->licence_last;
        $paymentHistories = PaymentHistory::where('company_id', $user->company_id)->get();

        $cards = $user->cards;

        // get next pay date
        $nextPayDate = '';
        // next_payment_attempt: 1653621690
        //https://dashboard.stripe.com/v1/invoices/upcoming
        //customer=cus_LaAqt3Qinw3832&subscription=sub_1Kt0VSING9npPdWilfRWtAbK
        $userSub = $user->stripe_subs()->first();
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
        return view(
            'payments.stripe.manage_payment_method',
            compact(
                'user',
                'stripeUser',
                'numberLicence',
                'licenceLast',
                'paymentHistories',
                'stripeUsers',
                'cards',
                'nextPayDate'
            )
        );
    }

    public function paymentIntentSaveCard()
    {
        $user = Auth::user();

        $card = request()->get('card') ?? [];
        $paymentMethodId = request()->get('payment_method_id');
        $stripeToken = request()->get('stripe_token');
        $billingDetails = request()->get('billing_details');

        $dataCustomer = [
            'name' => !empty($billingDetails['name']) ? $billingDetails['name'] : $user->disp_name,
            'email' => !empty($billingDetails['email']) ? $billingDetails['email'] : $user->mail_address,
            "source" => $stripeToken,
        ];
        $dataCard = [
            'pm_last_four' => $card['last4'] ?? 'xxxx',
            'pm_type' => $card['brand'] ?? 'xxx',
        ];

        // save customer
        $this->stripeService->saveCustomer($user, $dataCustomer, $dataCard);

        $stripeUser = $user->stripe_user()->first();
        if (!$stripeUser) {
            return $this->respondWithError('Please Add Method Payment', []);
        }
        // payment init

        try {
            $charge = $stripeUser->charge(config('apps.stripe.amount_verify'), $paymentMethodId);

            // save info to refund after charge
            StripePaymentVerifyCard::create([
                'user_id' => $user->user_id,
                'payment_intent_id' => $charge->id
            ]);
        } catch (\Throwable $th) {
            return $this->respondWithError($th->getMessage(), []);
        }

        if (!$charge) {
            return $this->respondWithError('charge Error', []);
        }

        return $this->respondSuccess('Add payment success', [
            'data' => [
                'url_return' => route('payment.add-payment-method'),
            ],
        ]);
    }

    /**
     * setup init and save card
     *
     * @return void
     */
    public function setupIntentSaveCard()
    {
        $user = Auth::user();

        $card = request()->get('card') ?? [];
        $paymentMethodId = request()->get('payment_method_id');
        $stripeToken = request()->get('stripe_token');
        $billingDetails = request()->get('billing_details');

        $dataCustomer = [
            'name' => !empty($billingDetails['name']) ? $billingDetails['name'] : $user->disp_name,
            'email' => !empty($billingDetails['email']) ? $billingDetails['email'] : $user->mail_address,
            'source' => $stripeToken,
        ];
        $dataCard = [
            'pm_last_four' => $card['last4'] ?? 'xxxx',
            'pm_type' => $card['brand'] ?? 'xxx',
            'primary_flg' => 1
        ];

        // save customer
        $this->stripeService->saveCustomer($user, $dataCustomer, $dataCard);

        $stripeUser = $user->stripe_user()->first();
        if (!$stripeUser) {
            return $this->respondWithError('Please Add Method Payment', []);
        }
        // setup init

        try {
            $setupItent = $this->stripeHelper->createSetupIntent([
                'customer' => $stripeUser->stripe_id
            ]);
        } catch (\Throwable $th) {
            return $this->respondWithError($th->getMessage(), []);
        }

        if (!$setupItent) {
            return $this->respondWithError('setupItent Error', []);
        }

        return $this->respondSuccess('Add payment success', [
            'data' => [
                'url_return' => route('payment.add-payment-method'),
            ],
        ]);
    }

    public function deleteCard(Request $request)
    {
        try {
            $result = [];
            $cardId = $request->input('card_id');
            $nextCardDefault = $request->input('next_card_default_id');
            $card = $this->stripeCardRepo->getByCol('id', $cardId);
            if (empty($card)) {
                $result['status'] = config('apps.general.error');
                $result['message'] = trans('message.INF_COM_0003');
                return $result;
            }
            $user = Auth::user();
            if (empty($user->company)) {
                $result['status'] = config('apps.general.error');
                $result['message'] = trans('company is not exists');
                return $result;
            }

            $cards = $user->cards()->get();
            if (empty($cards) || count($cards) == 0) {
                $result['status'] = config('apps.general.error');
                $result['message'] = trans('user have not any card');
                return $result;
            }
            if ($cards->count() > 1) {
                $allCardId = StripeCard::query()->orderBy('id', 'desc')->pluck('id')->toArray();
                if (($key = array_search($cardId, $allCardId, true)) !== false) {
                    unset($allCardId[$key]);
                }
                $allCardId = array_values($allCardId);
                $nextCardDefault = $allCardId[count($allCardId) - 1];

                $nextCard = $this->stripeCardRepo->getByCol('id', $nextCardDefault);
                if (empty($nextCard)) {
                    $result['status'] = config('apps.general.error');
                    $result['message'] = trans('the next card is invalid');
                    return $result;
                }
                $cardDelete = $this->stripeService->deleteCard($cardId, $nextCardDefault);
            } else {
                $licenses = $user->company->licence_managements();
                if (empty($licenses) || $licenses->sum('licence_num') === 0) {
                    $cardDelete = $this->stripeService->deleteCard($cardId);
                } else {
                    $result['status'] = config('apps.general.error');
                    $result['message'] = trans('You can note delete card, because, you need to pay your license in the future');
                    return $result;
                }
            }


            if ($cardDelete) {
                $result['status'] = config('apps.general.success');
                $result['message'] = trans('message.INF_COM_0054');
                return $result;
            }
            $result['status'] = config('apps.general.error');
            $result['message'] = trans('message.ERR_COM_0054');
            return $result;
        } catch (\Exception $exception) {
            $result['status'] = config('apps.general.error');
            $result['message'] = trans('message.ERR_COM_0054');
            set_log_error('PaymentController-deleteCard', $exception->getMessage());
            return $result;
        }
    }
}
