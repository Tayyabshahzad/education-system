<?php

namespace App\Services;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class MemberService
{
    public function __construct(private PaymentService $payments) {}

    /**
     * Create a member account.
     *
     * $funding: null = member pays later, 'wallet' = $creator pays from their wallet now,
     * 'received' = staff records the money as already received.
     */
    public function create(array $data, ?User $sponsor, ?User $creator = null, ?string $funding = null, float $fundAmount = 0): User
    {
        return DB::transaction(function () use ($data, $sponsor, $creator, $funding, $fundAmount) {
            $user = User::create([
                'name' => $data['name'],
                'username' => $data['username'],
                'email' => $data['email'],
                'phone' => $data['phone'] ?? null,
                'cnic' => $data['cnic'] ?? null,
                'country' => $data['country'] ?? null,
                'password' => $data['password'],
                'joining_amount' => $data['joining_amount'] ?? 0,
                'sponsor_id' => $sponsor?->id,
                'created_by' => $creator?->id,
            ]);
            $user->assignRole(User::ROLE_MEMBER);

            if ($funding === 'wallet') {
                $this->payments->payFromWallet($creator, $user, $fundAmount);
            } elseif ($funding === 'received') {
                $this->payments->recordReceived($user, $fundAmount, $creator, 'Recorded when account was created');
            }

            return $user->refresh();
        });
    }

    /** Move a member under a new sponsor. Earlier commissions are not recalculated. */
    public function changeSponsor(User $member, ?User $newSponsor): void
    {
        if ($newSponsor && ($newSponsor->is($member) || $member->hasInDownline($newSponsor))) {
            throw ValidationException::withMessages(['sponsor' => 'A member cannot be placed under themselves or under their own downline.']);
        }

        $member->sponsor_id = $newSponsor?->id;
        $member->save();
    }

    /** Soft-delete a member; their direct referrals move up to the deleted member's sponsor. */
    public function delete(User $member): void
    {
        DB::transaction(function () use ($member) {
            User::where('sponsor_id', $member->id)->update(['sponsor_id' => $member->sponsor_id]);

            // Free unique fields so the email/username can be registered again.
            $suffix = '#deleted-'.$member->id;
            $member->forceFill([
                'email' => $member->email.$suffix,
                'username' => $member->username.$suffix,
                'cnic' => $member->cnic ? $member->cnic.$suffix : null,
                'phone' => $member->phone ? $member->phone.$suffix : null,
            ])->save();

            $member->delete();
        });
    }
}
