<?php

namespace App\Services;

class PromoCodeService
{
    private const CODE_LENGTH = 10;
    private const CODE_CHARACTERS = '0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ';

    /**
     * Generate a promo code for a product
     * Note: Uniqueness is not enforced as per business requirements
     *
     * @return string 10-character promo code
     */
    public function generateCode(): string
    {
        return $this->generateRandomCode();
    }

    /**
     * Generate a random 10-character alphanumeric code
     *
     * @return string Random code
     */
    private function generateRandomCode(): string
    {
        $code = '';
        $maxIndex = strlen(self::CODE_CHARACTERS) - 1;

        for ($i = 0; $i < self::CODE_LENGTH; $i++) {
            $code .= self::CODE_CHARACTERS[random_int(0, $maxIndex)];
        }

        return $code;
    }
}
