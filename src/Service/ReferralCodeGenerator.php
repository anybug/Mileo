<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\UserRepository;

class ReferralCodeGenerator
{
    public function __construct(
        private UserRepository $userRepository,
    ) {
    }

    public function generate(): string
    {
        do {
            $code = strtoupper(substr(bin2hex(random_bytes(8)), 0, 10));
        } while ($this->userRepository->findOneBy([
            'referralCode' => $code,
        ]) !== null);

        return $code;
    }

    public function assignTo(User $user): void
    {
        if ($user->getReferralCode() !== null) {
            return;
        }

        $user->setReferralCode($this->generate());
    }
}
