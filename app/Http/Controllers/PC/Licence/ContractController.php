<?php

namespace App\Http\Controllers\PC\Licence;

use App\Helpers\StripeHelper;
use App\Http\Controllers\PC\Controller;
use App\Models\Contract;
use Illuminate\Support\Facades\Auth;

class ContractController extends Controller
{
    private $stripeHelper;

    public function __construct(StripeHelper $stripeHelper)
    {
        $this->stripeHelper = $stripeHelper;
    }

    public function cancelContract()
    {
        $user = Auth::user();
        $stripeUser = $user->stripe_user;
        if (!$stripeUser) {
            return $this->respondWithError(__("Please Add Method Payment"));
        }
        $stripeSubscriptions = $stripeUser->subscriptions()->orderBy('created_at', 'desc')->get();
        $stripeSubscription = $stripeSubscriptions->first();
        $result = $this->stripeHelper->cancelSubscription($stripeSubscription->stripe_id);
        if (!$result) {
            return $this->respondWithError(__("Cancel Contract Fail"));
        }
        $stripeSubscription->delete();
        Contract::query()->where('company_id', $user->company_id)->update(['delete_flg' => config('apps.general.is_deleted')]);
        return $this->respondSuccess(__("Cancel Contract Success"));
    }
}
